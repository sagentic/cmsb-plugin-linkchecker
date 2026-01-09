<?php
/*
Plugin Name: Link Checker
Description: Scans database content for broken links, missing images, and malformed contact links
Version: 1.00
CMS Version Required: 3.50
Author: Sagentic Web Design
Author URI: https://www.sagentic.com
*/

// Don't run from command-line
if (inCLI()) {
	return;
}

// Plugin constants
$GLOBALS['LINKCHECKER_PLUGIN'] = true;
$GLOBALS['LINKCHECKER_VERSION'] = '1.00';

// Load helper functions
require_once __DIR__ . '/linkChecker_functions.php';

// Initialize plugin on admin login
addAction('admin_postlogin', 'linkChecker_pluginInit', null, -999);

// Admin UI - only load when in admin area
if (defined('IS_CMS_ADMIN')) {
	require_once __DIR__ . '/linkChecker_admin.php';

	// Register plugin menu pages
	pluginAction_addHandlerAndLink(t('Dashboard'), 'linkChecker_adminDashboard', 'admins');
	pluginAction_addHandlerAndLink(t('Scan Results'), 'linkChecker_adminResults', 'admins');
	pluginAction_addHandlerAndLink(t('Run Scan'), 'linkChecker_adminRunScan', 'admins');
	pluginAction_addHandlerAndLink(t('Settings'), 'linkChecker_adminSettings', 'admins');
	pluginAction_addHandlerAndLink(t('Scan History'), 'linkChecker_adminHistory', 'admins');
	pluginAction_addHandlerAndLink(t('Help'), 'linkChecker_adminHelp', 'admins');
}

/**
 * Initialize plugin - create database tables if needed
 */
function linkChecker_pluginInit(): void
{
	// Create tables if they don't exist
	linkChecker_createTablesIfNeeded();
}

/**
 * Process scheduled scan (called by daily cron)
 */
function linkChecker_scheduledScan(): void
{
	$settings = linkChecker_loadPluginSettings();

	if (!$settings['scheduledScan']) {
		return;
	}

	$lastScan = $settings['lastScanDate'];
	$frequency = $settings['scanFrequency'];

	// Check if scan is due
	$shouldScan = false;
	if (!$lastScan) {
		$shouldScan = true;
	} else {
		$lastScanTime = strtotime($lastScan);
		switch ($frequency) {
			case 'daily':
				$shouldScan = (time() - $lastScanTime) >= 86400;
				break;
			case 'weekly':
				$shouldScan = (time() - $lastScanTime) >= 604800;
				break;
			case 'monthly':
				$shouldScan = (time() - $lastScanTime) >= 2592000;
				break;
		}
	}

	if ($shouldScan) {
		$results = linkChecker_runScheduledScan();
		linkChecker_sendNotificationEmail($results);
	}
}

/**
 * Cleanup old results based on retention setting
 * Called by daily cron
 */
function linkChecker_cleanup(): void
{
	global $TABLE_PREFIX;

	$settings = linkChecker_loadPluginSettings();
	$retentionDays = $settings['logRetentionDays'];
	if ($retentionDays <= 0) {
		return; // Retention disabled
	}

	$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

	// Clean up results table
	$query = "DELETE FROM `{$TABLE_PREFIX}_linkchecker_results` WHERE `createdDate` < ?";
	mysqli()->query(mysql_escapef($query, $cutoffDate));

	// Clean up scans table
	$query = "DELETE FROM `{$TABLE_PREFIX}_linkchecker_scans` WHERE `createdDate` < ?";
	mysqli()->query(mysql_escapef($query, $cutoffDate));
}

// Register cron jobs - runs once per day at midnight
addCronJob('linkChecker_scheduledScan', 'Link Checker - Scheduled Scan', '0 0 * * *');
addCronJob('linkChecker_cleanup', 'Link Checker - Cleanup Old Results', '0 1 * * *');
