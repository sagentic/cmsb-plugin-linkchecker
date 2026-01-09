<?php

/**
 * Link Checker Plugin - Admin UI Pages
 *
 * @package LinkChecker
 */


/**
 * Generate plugin navigation bar
 *
 * @param string $currentPage Current page identifier
 * @return string HTML for navigation bar
 */
function linkChecker_getPluginNav(string $currentPage): string
{
	$pages = [
		'dashboard' => ['label' => t('Dashboard'), 'action' => 'linkChecker_adminDashboard'],
		'results' => ['label' => t('Scan Results'), 'action' => 'linkChecker_adminResults'],
		'run_scan' => ['label' => t('Run Scan'), 'action' => 'linkChecker_adminRunScan'],
		'settings' => ['label' => t('Settings'), 'action' => 'linkChecker_adminSettings'],
		'history' => ['label' => t('History'), 'action' => 'linkChecker_adminHistory'],
		'help' => ['label' => t('Help'), 'action' => 'linkChecker_adminHelp'],
	];

	$html = '<nav aria-label="' . t('Link Checker plugin navigation') . '"><div class="btn-group" role="group" style="margin-bottom:20px">';
	foreach ($pages as $key => $page) {
		$isActive = ($key === $currentPage);
		$btnClass = $isActive ? 'btn btn-primary' : 'btn btn-default';
		$ariaCurrent = $isActive ? ' aria-current="page"' : '';
		$html .= '<a href="?_pluginAction=' . urlencode($page['action']) . '" class="' . $btnClass . '"' . $ariaCurrent . '>' . $page['label'] . '</a>';
	}
	$html .= '</div></nav>';

	return $html;
}

/**
 * Get advanced actions menu array
 *
 * @return array Advanced actions array for adminUI
 */
function linkChecker_getAdvancedActions(): array
{
	// Note: These URLs will be converted to POST forms with CSRF tokens by JavaScript
	return [
		t('Quick Scan') => '?_pluginAction=linkChecker_adminDashboard&_action=quickScan',
		t('Clear Old Scans') => '?_pluginAction=linkChecker_adminDashboard&_action=clearOldScans',
		t('Clear All Results') => '?_pluginAction=linkChecker_adminDashboard&_action=clearResults',
	];
}

/**
 * Dashboard page - Main plugin overview
 */
function linkChecker_adminDashboard(): void
{
	global $SETTINGS;

	// Handle form submissions
	// Note: These GET actions rely on CMSB's referer checking for CSRF protection
	// security_dieOnInvalidCsrfToken() only works with POST requests
	if (($_REQUEST['_action'] ?? '') === 'quickScan') {
		linkChecker_runLinkCheck();
		\alert(t('Quick scan completed'));
		\redirectBrowserToURL('?_pluginAction=' . __FUNCTION__);
	}

	if (($_REQUEST['_action'] ?? '') === 'clearOldScans') {
		linkChecker_cleanupOldScans();
		\alert(t('Old scan history cleared'));
		\redirectBrowserToURL('?_pluginAction=' . __FUNCTION__);
	}

	if (($_REQUEST['_action'] ?? '') === 'clearResults') {
		if (($_REQUEST['_confirm'] ?? '') === 'yes') {
			$count = linkChecker_clearAllResults();
			\alert(t('Cleared %d link check results', $count));
			\redirectBrowserToURL('?_pluginAction=' . __FUNCTION__);
		} else{
			// Show confirmation page
			$adminUI = [];
			$adminUI['PAGE_TITLE'] = [
				t("Plugins") => '?menu=admin&action=plugins',
				t("Link Checker") => '?_pluginAction=' . __FUNCTION__,
				t("Clear Results"),
			];

			$content = '';
			$content .= linkChecker_getPluginNav('dashboard');
			$content .= '<div class="alert alert-warning" style="margin-bottom:20px">';
			$content .= '<h4 style="margin-top:0"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' . t('Confirm Clear Results') . '</h4>';
			$content .= '<p>' . t('This will permanently delete ALL link check results and scan history from the database. This provides a completely fresh start.') . '</p>';
			$content .= '<p><strong>' . t('This action cannot be undone.') . '</strong></p>';
			$content .= '</div>';

			$content .= '<p>';
			$content .= '<a href="?_pluginAction=' . __FUNCTION__ . '&_action=clearResults&_confirm=yes" class="btn btn-danger">';
			$content .= '<i class="fa fa-trash" aria-hidden="true"></i> ' . t('Yes, Clear All Results');
			$content .= '</a> ';
			$content .= '<a href="?_pluginAction=' . __FUNCTION__ . '" class="btn btn-default">' . t('Cancel') . '</a>';
			$content .= '</p>';

			$adminUI['CONTENT'] = $content;
			\adminUI($adminUI);
			return;
		}
	}

	// Get data for display
	$lastScan = linkChecker_getLastScanInfo();
	$stats = linkChecker_getScanStats();
	$recentBrokenLinks = linkChecker_getRecentBrokenLinks(10);

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Link Checker"),
	];

	$adminUI['ADVANCED_ACTIONS'] = linkChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= linkChecker_getPluginNav('dashboard');

	// Load settings
	$pluginSettings = linkChecker_loadPluginSettings();

	// Last Scan Status Section
	$content .= '<div class="separator"><div>' . t('Last Scan Status') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Monitor your site\'s link health at a glance. Results from your most recent scan are shown below.') . '</p>';

	$content .= '<div class="form-horizontal">';

	// Last Scan Date
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Last Scan') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0">';
	if ($lastScan) {
		$content .= '<strong>' . date('Y-m-d H:i:s', strtotime($lastScan['startTime'])) . '</strong>';
		$scanAge = time() - strtotime($lastScan['startTime']);
		if ($scanAge > 604800) { // 1 week
			$content .= ' <span class="badge" style="background-color:#dc3545;color:#fff;margin-left:8px">' . t('Scan Overdue') . '</span>';
		}
	} else {
		$content .= '<span style="color:#6c757d">' . t('No scans yet') . '</span>';
	}
	$content .= '</p></div></div>';

	// Links Checked
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Links Checked') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0"><strong>' . ($lastScan ? intval($lastScan['linksChecked']) : 0) . '</strong></p>';
	$content .= '</div></div>';

	// Issues Found
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Issues Found') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0">';
	$issuesFound = $lastScan ? (intval($lastScan['brokenLinks']) + intval($lastScan['warnings']) + intval($lastScan['invalidLinks']) + intval($lastScan['timeouts'])) : 0;
	if ($lastScan && $issuesFound > 0) {
		$content .= '<strong style="color:#dc3545">' . $issuesFound . '</strong>';
	} else {
		$content .= '<strong style="color:#28a745">' . $issuesFound . '</strong>';
	}
	$content .= '</p></div></div>';

	// Auto Scan Status
	$content .= '<div class="form-group" style="margin-bottom:8px">';
	$content .= '<label class="col-sm-2 control-label" style="padding-top:0">' . t('Auto Scan') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<p class="form-control-static" style="padding-top:0;padding-bottom:0;margin-bottom:0;min-height:0">';
	if ($pluginSettings['scheduledScan'] ?? false) {
		$content .= '<strong style="color:#28a745"><i class="fa-duotone fa-solid fa-check" aria-hidden="true"></i> ' . t('Enabled') . '</strong>';
		$content .= ' <span class="text-muted" style="margin-left:8px">(' . \htmlencode($pluginSettings['scanFrequency']) . ')</span>';
	} else {
		$content .= '<strong style="color:#dc3545"><i class="fa-duotone fa-solid fa-xmark" aria-hidden="true"></i> ' . t('Disabled') . '</strong>';
	}
	$content .= '</p></div></div>';

	$content .= '</div>'; // end form-horizontal

	// Statistics Section
	$content .= '<div class="separator"><div>' . t('Link Statistics') . '</div></div>';

	$content .= '<div class="row g-3 mb-4">';

	// Helper function for stat card
	$renderStatCard = function ($label, $count, $color) {
		$html = '<div class="col-6 col-lg-3">';
		$html .= '<div class="border rounded-3 p-3 h-100 text-center">';
		$html .= '<div class="text-uppercase small fw-semibold mb-3">' . $label . '</div>';
		$html .= '<div class="fs-2 fw-bold" style="color:' . $color . '">' . $count . '</div>';
		$html .= '</div></div>';
		return $html;
	};

	$content .= $renderStatCard(t('Total Links'), $stats['total'], '#6c757d');
	$content .= $renderStatCard(t('Broken Links'), $stats['broken'], '#dc3545');
	$content .= $renderStatCard(t('Warnings'), $stats['warnings'], '#ffc107');
	$content .= $renderStatCard(t('Ignored'), $stats['ignored'], '#6c757d');

	$content .= '</div>';

	// Quick Actions Section
	$content .= '<div class="separator"><div>' . t('Quick Actions') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Quick Scan only rechecks previously broken links, making it faster than a full scan. Use this to verify if reported issues have been fixed.') . '</p>';

	$content .= '<div style="margin-bottom:20px">';
	$content .= '<a href="?_pluginAction=' . __FUNCTION__ . '&_action=quickScan" class="btn btn-primary"><i class="fa-duotone fa-solid fa-magnifying-glass" aria-hidden="true"></i> ' . t('Run Quick Scan') . '</a> ';
	$content .= '<a href="?_pluginAction=linkChecker_adminResults" class="btn btn-default"><i class="fa-duotone fa-solid fa-list" aria-hidden="true"></i> ' . t('View All Results') . '</a> ';
	$content .= '<a href="?_pluginAction=linkChecker_adminSettings" class="btn btn-default"><i class="fa-duotone fa-solid fa-gear" aria-hidden="true"></i> ' . t('Settings') . '</a>';
	$content .= '</div>';

	// Recent Broken Links Section
	$content .= '<div class="separator"><div>' . t('Recent Broken Links') . '</div></div>';

	if (empty($recentBrokenLinks)) {
		$content .= '<p style="color:#28a745"><i class="fa-duotone fa-solid fa-check-circle" aria-hidden="true"></i> ' . t('No broken links found!') . '</p>';
	} else {
		// Status legend
		$content .= '<div class="alert alert-info" style="margin-bottom:15px;padding:10px 15px">';
		$content .= '<strong>' . t('Status Guide:') . '</strong> ';
		$content .= '<span class="badge" style="background-color:#dc3545;color:#fff;margin:0 5px">' . t('Broken') . '</span> ' . t('404/500 errors - fix immediately') . ' &bull; ';
		$content .= '<span class="badge" style="background-color:#ffc107;color:#212529;margin:0 5px">' . t('Warning') . '</span> ' . t('301/302 redirects - consider updating') . ' &bull; ';
		$content .= '<span class="badge" style="background-color:#6c757d;color:#fff;margin:0 5px">' . t('Invalid') . '</span> ' . t('Malformed links');
		$content .= '</div>';
		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-striped table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col">' . t('Link') . '</th>';
		$content .= '<th scope="col">' . t('Found On') . '</th>';
		$content .= '<th scope="col">' . t('Status') . '</th>';
		$content .= '<th scope="col">' . t('Last Checked') . '</th>';
		$content .= '<th scope="col" class="text-center">' . t('Actions') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($recentBrokenLinks as $link) {
			$statusBadge = match ($link['status']) {
				'broken' => '<span class="badge" style="background-color:#dc3545;color:#fff">' . t('Broken') . '</span>',
				'warning' => '<span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>',
				'redirect' => '<span class="badge" style="background-color:#17a2b8;color:#fff">' . t('Redirect') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			// Construct source description
			$sourceDesc = $link['tableName'];
			if ($link['fieldName']) {
				$sourceDesc .= ' (' . $link['fieldName'] . ')';
			}
			if ($link['recordNum']) {
				$sourceDesc .= ' #' . $link['recordNum'];
			}

			$content .= '<tr>';
			$content .= '<td class="text-truncate" style="max-width:300px" title="' . \htmlencode($link['url']) . '">' . \htmlencode($link['url']) . '</td>';
			$content .= '<td class="text-truncate" style="max-width:250px" title="' . \htmlencode($sourceDesc) . '">';
			$content .= \htmlencode($sourceDesc);
			$content .= '</td>';
			$content .= '<td>' . $statusBadge . ' <small class="text-muted">(' . intval($link['httpCode']) . ')</small></td>';
			$content .= '<td class="text-nowrap">' . date('Y-m-d H:i', strtotime($link['scanDate'])) . '</td>';
			$content .= '<td class="text-center text-nowrap">';
			if ($link['tableName'] && $link['recordNum']) {
				$content .= '<a href="?menu=' . urlencode($link['tableName']) . '&action=edit&num=' . intval($link['recordNum']) . '" class="btn btn-xs btn-primary" target="_blank" rel="noopener" title="' . t('Edit Record') . '"><i class="fa-duotone fa-solid fa-edit" aria-hidden="true"></i> <span class="sr-only">' . t('Edit Record (opens in new tab)') . '</span></a> ';
			}
			$content .= '<button type="button" class="btn btn-xs btn-default" onclick="recheckSingle(' . intval($link['num']) . ')" title="' . t('Recheck') . '"><i class="fa-duotone fa-solid fa-rotate" aria-hidden="true"></i> <span class="sr-only">' . t('Recheck') . '</span></button> ';
			$content .= '<button type="button" class="btn btn-xs btn-warning" onclick="ignoreSingle(' . intval($link['num']) . ')" title="' . t('Ignore') . '"><i class="fa-duotone fa-solid fa-eye-slash" aria-hidden="true"></i> <span class="sr-only">' . t('Ignore') . '</span></button>';
			$content .= '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table></div>';

		$content .= '<p style="margin-top:10px"><a href="?_pluginAction=linkChecker_adminResults&filterStatus=broken">' . t('View all broken links') . ' &raquo;</a></p>';
	}

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Scan Results page - View all link check results
 */
function linkChecker_adminResults(): void
{
	// Handle bulk actions
	if (($_REQUEST['bulkAction'] ?? '') && !empty($_REQUEST['selectedLinks'])) {
		\security_dieOnInvalidCsrfToken(); // CSRF protection

		if (!is_array($_REQUEST['selectedLinks'])) {
			\alert(t('Invalid input'));
			\redirectBrowserToURL('?_pluginAction=linkChecker_adminResults');
		}

		$action = $_REQUEST['bulkAction'];
		$selectedIds = array_map('intval', $_REQUEST['selectedLinks']);

		if ($action === 'markFixed') {
			linkChecker_markLinksAsFixed($selectedIds);
			\alert(sprintf(t('%d links marked as fixed'), count($selectedIds)));
		} elseif ($action === 'markIgnored') {
			linkChecker_markLinksAsIgnored($selectedIds);
			\alert(sprintf(t('%d links marked as ignored'), count($selectedIds)));
		} elseif ($action === 'recheck') {
			linkChecker_recheckLinks($selectedIds);
			\alert(sprintf(t('%d links rechecked'), count($selectedIds)));
		}

		\redirectBrowserToURL('?_pluginAction=linkChecker_adminResults');
	}

	// Pagination
	$page = max(1, intval($_REQUEST['page'] ?? 1));
	$perPage = intval($_REQUEST['perPage'] ?? 50);
	$perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;
	$offset = ($page - 1) * $perPage;

	// Filters
	$filterStatus = $_REQUEST['filterStatus'] ?? '';
	$filterType = $_REQUEST['filterType'] ?? '';
	$filterTable = $_REQUEST['filterTable'] ?? '';
	$filterDateFrom = $_REQUEST['filterDateFrom'] ?? '';
	$filterDateTo = $_REQUEST['filterDateTo'] ?? '';

	// Sorting
	$sortBy = $_REQUEST['sortBy'] ?? 'scanDate';
	$sortDir = $_REQUEST['sortDir'] ?? 'DESC';
	$sortDir = in_array(strtoupper($sortDir), ['ASC', 'DESC']) ? strtoupper($sortDir) : 'DESC';

	// Build where clause
	$where = "1=1";
	if ($filterStatus) {
		$where .= " AND `status` = '" . \mysql_escape($filterStatus) . "'";
	}
	if ($filterType) {
		$where .= " AND `link_type` = '" . \mysql_escape($filterType) . "'";
	}
	if ($filterTable) {
		$where .= " AND `tableName` = '" . \mysql_escape($filterTable) . "'";
	}
	if ($filterDateFrom) {
		$where .= " AND DATE(`scanDate`) >= '" . \mysql_escape($filterDateFrom) . "'";
	}
	if ($filterDateTo) {
		$where .= " AND DATE(`scanDate`) <= '" . \mysql_escape($filterDateTo) . "'";
	}

	// Get total count
	$totalCount = \mysql_count('_linkchecker_results', $where);
	$totalPages = ceil($totalCount / $perPage);

	// Valid sort columns
	$validSortCols = ['url', 'status', 'httpCode', 'scanDate', 'tableName', 'linkType'];
	$sortColumn = in_array($sortBy, $validSortCols) ? $sortBy : 'scanDate';

	// Get results
	$results = \mysql_select('_linkchecker_results', "{$where} ORDER BY `{$sortColumn}` {$sortDir} LIMIT {$offset}, {$perPage}");

	// Get available tables for filter
	$availableTables = linkChecker_getEnabledTables();

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Link Checker") => '?_pluginAction=linkChecker_adminDashboard',
		t("Scan Results"),
	];

	$adminUI['ADVANCED_ACTIONS'] = linkChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= linkChecker_getPluginNav('results');

	// Filters Section
	$content .= '<div class="separator"><div>' . t('Filter Results') . '</div></div>';

	$content .= '<p class="help-block" style="margin-top:0;margin-bottom:15px">' . t('Use filters to narrow down results. Multiple filters can be combined to find specific issues.') . '</p>';

	$content .= '<form method="get" id="filterForm">';
	$content .= '<input type="hidden" name="_pluginAction" value="linkChecker_adminResults">';
	$content .= '<div class="form-horizontal">';

	// Status filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterStatus" class="col-sm-2 control-label">' . t('Status') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterStatus" id="filterStatus" class="form-control" style="width:200px;display:inline-block">';
	$content .= '<option value="">' . t('All') . '</option>';
	$content .= '<option value="ok"' . ($filterStatus === 'ok' ? ' selected' : '') . '>' . t('OK') . '</option>';
	$content .= '<option value="broken"' . ($filterStatus === 'broken' ? ' selected' : '') . '>' . t('Broken') . '</option>';
	$content .= '<option value="warning"' . ($filterStatus === 'warning' ? ' selected' : '') . '>' . t('Warning') . '</option>';
	$content .= '<option value="redirect"' . ($filterStatus === 'redirect' ? ' selected' : '') . '>' . t('Redirect') . '</option>';
	$content .= '<option value="ignored"' . ($filterStatus === 'ignored' ? ' selected' : '') . '>' . t('Ignored') . '</option>';
	$content .= '</select>';
	$content .= '</div></div>';

	// Link Type filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterType" class="col-sm-2 control-label">' . t('Link Type') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterType" id="filterType" class="form-control" style="width:200px;display:inline-block">';
	$content .= '<option value="">' . t('All') . '</option>';
	$content .= '<option value="internal"' . ($filterType === 'internal' ? ' selected' : '') . '>' . t('Internal') . '</option>';
	$content .= '<option value="external"' . ($filterType === 'external' ? ' selected' : '') . '>' . t('External') . '</option>';
	$content .= '</select>';
	$content .= '</div></div>';

	// Table filter
	$content .= '<div class="form-group">';
	$content .= '<label for="filterTable" class="col-sm-2 control-label">' . t('Table') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="filterTable" id="filterTable" class="form-control" style="width:250px;display:inline-block">';
	$content .= '<option value="">' . t('All Tables') . '</option>';
	foreach ($availableTables as $table) {
		$schema = \loadSchema($table);
		$menuName = $schema['menuName'] ?? $table;
		$selected = ($filterTable === $table) ? ' selected' : '';
		$content .= '<option value="' . \htmlencode($table) . '"' . $selected . '>' . \htmlencode($menuName) . ' (' . \htmlencode($table) . ')</option>';
	}
	$content .= '</select>';
	$content .= '</div></div>';

	// Date range filters
	$content .= '<div class="form-group">';
	$content .= '<label for="filterDateFrom" class="col-sm-2 control-label">' . t('Date Range') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<input type="date" name="filterDateFrom" id="filterDateFrom" class="form-control" style="width:150px;display:inline-block;margin-right:8px" value="' . \htmlencode($filterDateFrom) . '" placeholder="' . t('From') . '"> ';
	$content .= t('to') . ' ';
	$content .= '<input type="date" name="filterDateTo" id="filterDateTo" class="form-control" style="width:150px;display:inline-block;margin-left:8px" value="' . \htmlencode($filterDateTo) . '" placeholder="' . t('To') . '">';
	$content .= '</div></div>';

	// Per page
	$content .= '<div class="form-group">';
	$content .= '<label for="perPage" class="col-sm-2 control-label">' . t('Per Page') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="perPage" id="perPage" class="form-control" style="width:100px;display:inline-block">';
	foreach ([10, 25, 50, 100, 250] as $pp) {
		$content .= '<option value="' . $pp . '"' . ($perPage === $pp ? ' selected' : '') . '>' . $pp . '</option>';
	}
	$content .= '</select>';
	$content .= '</div></div>';

	// Buttons
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label"></div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<button type="submit" class="btn btn-primary">' . t('Filter') . '</button>';
	$content .= ' <a href="?_pluginAction=linkChecker_adminResults" class="btn btn-default">' . t('Reset') . '</a>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal
	$content .= '</form>';

	// Results count and bulk actions
	$content .= '<div style="margin-top:20px;margin-bottom:15px">';
	$content .= '<div class="row">';
	$content .= '<div class="col-sm-6">';
	$content .= '<p>' . sprintf(t('Showing %d - %d of %d entries'), min($offset + 1, $totalCount), min($offset + $perPage, $totalCount), $totalCount) . '</p>';
	$content .= '</div>';
	$content .= '<div class="col-sm-6 text-right">';
	$content .= '<form method="post" id="bulkActionForm" style="display:inline-block">';
	$content .= '<input type="hidden" name="_pluginAction" value="linkChecker_adminResults">';
	$content .= '<input type="hidden" name="_CSRFToken" value="' . \htmlencode($_SESSION['_CSRFToken'] ?? '') . '">';
	$content .= '<select name="bulkAction" id="bulkAction" class="form-control" style="width:150px;display:inline-block;margin-right:5px">';
	$content .= '<option value="">' . t('Bulk Actions') . '</option>';
	$content .= '<option value="markFixed">' . t('Mark as Fixed') . '</option>';
	$content .= '<option value="markIgnored">' . t('Mark as Ignored') . '</option>';
	$content .= '<option value="recheck">' . t('Recheck Now') . '</option>';
	$content .= '</select>';
	$content .= '<button type="submit" class="btn btn-default" onclick="return confirmBulkAction()">' . t('Apply') . '</button>';
	$content .= '</form>';
	$content .= '</div>';
	$content .= '</div>';
	$content .= '</div>';

	if (empty($results)) {
		if ($filterStatus || $filterType || $filterTable || $filterDateFrom || $filterDateTo) {
			// Filters are active but no results
			$content .= '<div class="alert alert-info">';
			$content .= '<strong>' . t('No links match your current filters.') . '</strong><br>';
			$content .= t('Try adjusting your filter criteria or') . ' <a href="?_pluginAction=linkChecker_adminResults">' . t('reset filters') . '</a> ' . t('to see all results.');
			$content .= '</div>';
		} else {
			// No results at all - good news!
			$content .= '<div class="alert alert-success">';
			$content .= '<i class="fa-duotone fa-solid fa-check-circle" aria-hidden="true"></i> ';
			$content .= '<strong>' . t('Excellent! No broken links found.') . '</strong><br>';
			$content .= t('Your site\'s links are all working properly. Run a scan to check for new issues.');
			$content .= '</div>';
		}
	} else{
		// Helper function for sort link
		$sortLink = function ($column, $label) use ($filterStatus, $filterType, $filterTable, $filterDateFrom, $filterDateTo, $perPage, $sortBy, $sortDir) {
			$url = '?_pluginAction=linkChecker_adminResults';
			if ($filterStatus) $url .= '&filterStatus=' . urlencode($filterStatus);
			if ($filterType) $url .= '&filterType=' . urlencode($filterType);
			if ($filterTable) $url .= '&filterTable=' . urlencode($filterTable);
			if ($filterDateFrom) $url .= '&filterDateFrom=' . urlencode($filterDateFrom);
			if ($filterDateTo) $url .= '&filterDateTo=' . urlencode($filterDateTo);
			$url .= '&perPage=' . $perPage;
			$url .= '&sortBy=' . $column;

			$newDir = 'ASC';
			$icon = '';
			if ($sortBy === $column) {
				$newDir = ($sortDir === 'ASC') ? 'DESC' : 'ASC';
				$icon = ($sortDir === 'ASC') ? ' <i class="fa-duotone fa-solid fa-arrow-up" aria-hidden="true"></i>' : ' <i class="fa-duotone fa-solid fa-arrow-down" aria-hidden="true"></i>';
			}
			$url .= '&sortDir=' . $newDir;

			return '<a href="' . $url . '">' . $label . $icon . '</a>';
		};

		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-striped table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col" style="width:40px" class="text-center"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" aria-label="' . t('Select all links') . '"></th>';
		$content .= '<th scope="col">' . $sortLink('url', t('Link URL')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('tableName', t('Found On')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('status', t('Status')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('httpCode', t('Code')) . '</th>';
		$content .= '<th scope="col">' . $sortLink('scanDate', t('Last Checked')) . '</th>';
		$content .= '<th scope="col" class="text-center">' . t('Actions') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($results as $result) {
			$statusBadge = match ($result['status']) {
				'ok' => '<span class="badge" style="background-color:#28a745;color:#fff">' . t('OK') . '</span>',
				'broken' => '<span class="badge" style="background-color:#dc3545;color:#fff">' . t('Broken') . '</span>',
				'warning' => '<span class="badge" style="background-color:#ffc107;color:#212529">' . t('Warning') . '</span>',
				'redirect' => '<span class="badge" style="background-color:#17a2b8;color:#fff">' . t('Redirect') . '</span>',
				'ignored' => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Ignored') . '</span>',
				default => '<span class="badge" style="background-color:#6c757d;color:#fff">' . t('Unknown') . '</span>',
			};

			// Construct source description
			$sourceDesc = $result['tableName'];
			if ($result['fieldName']) {
				$sourceDesc .= ' (' . $result['fieldName'] . ')';
			}
			if ($result['recordNum']) {
				$sourceDesc .= ' #' . $result['recordNum'];
			}

			$content .= '<tr>';
			$content .= '<td class="text-center"><input type="checkbox" name="selectedLinks[]" value="' . intval($result['num']) . '" class="link-checkbox" form="bulkActionForm" aria-label="' . t('Select link') . '"></td>';
			$content .= '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . \htmlencode($result['url']) . '">';
			$content .= '<a href="' . \htmlencode($result['url']) . '" target="_blank" rel="noopener">' . \htmlencode($result['url']) . ' <span class="sr-only">' . t('(opens in new tab)') . '</span></a>';
			$content .= '</td>';
			$content .= '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . \htmlencode($sourceDesc) . '">';
			$content .= \htmlencode($sourceDesc);
			$content .= '</td>';
			$content .= '<td>' . $statusBadge . '</td>';
			$content .= '<td>' . intval($result['httpCode']) . '</td>';
			$content .= '<td class="text-nowrap">' . date('Y-m-d H:i', strtotime($result['scanDate'])) . '</td>';
			$content .= '<td class="text-center text-nowrap">';
			if ($result['tableName'] && $result['recordNum']) {
				$content .= '<a href="?menu=' . urlencode($result['tableName']) . '&action=edit&num=' . intval($result['recordNum']) . '" class="btn btn-xs btn-primary" target="_blank" rel="noopener" title="' . t('Edit Record') . '"><i class="fa-duotone fa-solid fa-edit" aria-hidden="true"></i> <span class="sr-only">' . t('Edit Record (opens in new tab)') . '</span></a> ';
			}
			$content .= '<button type="button" class="btn btn-xs btn-default" onclick="recheckSingle(' . intval($result['num']) . ')" title="' . t('Recheck') . '"><i class="fa-duotone fa-solid fa-rotate" aria-hidden="true"></i> <span class="sr-only">' . t('Recheck') . '</span></button> ';
			$content .= '<button type="button" class="btn btn-xs btn-warning" onclick="ignoreSingle(' . intval($result['num']) . ')" title="' . t('Ignore') . '"><i class="fa-duotone fa-solid fa-eye-slash" aria-hidden="true"></i> <span class="sr-only">' . t('Ignore') . '</span></button>';
			$content .= '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table></div>';

		// Pagination
		if ($totalPages > 1) {
			$baseUrl = '?_pluginAction=linkChecker_adminResults';
			if ($filterStatus) $baseUrl .= '&filterStatus=' . urlencode($filterStatus);
			if ($filterType) $baseUrl .= '&filterType=' . urlencode($filterType);
			if ($filterTable) $baseUrl .= '&filterTable=' . urlencode($filterTable);
			if ($filterDateFrom) $baseUrl .= '&filterDateFrom=' . urlencode($filterDateFrom);
			if ($filterDateTo) $baseUrl .= '&filterDateTo=' . urlencode($filterDateTo);
			$baseUrl .= '&perPage=' . $perPage;
			$baseUrl .= '&sortBy=' . urlencode($sortBy) . '&sortDir=' . urlencode($sortDir);

			$content .= '<div class="text-center" style="margin-top:15px">';

			// Previous
			if ($page > 1) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page - 1) . '" class="btn btn-default btn-sm">&laquo; ' . t('Previous') . '</a> ';
			}

			// Page numbers
			$startPage = max(1, $page - 2);
			$endPage = min($totalPages, $page + 2);

			for ($i = $startPage; $i <= $endPage; $i++) {
				if ($i === $page) {
					$content .= '<span class="btn btn-primary btn-sm">' . $i . '</span> ';
				} else {
					$content .= '<a href="' . $baseUrl . '&page=' . $i . '" class="btn btn-default btn-sm">' . $i . '</a> ';
				}
			}

			// Next
			if ($page < $totalPages) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page + 1) . '" class="btn btn-default btn-sm">' . t('Next') . ' &raquo;</a>';
			}

			$content .= '</div>';
		}
	}

	// JavaScript for bulk actions and select all
	$csrfToken = $_SESSION['_CSRFToken'] ?? '';
	$content .= '<script>
var csrfToken = ' . json_encode($csrfToken) . ';

function toggleSelectAll(checkbox) {
	var checkboxes = document.querySelectorAll(".link-checkbox");
	checkboxes.forEach(function(cb) {
		cb.checked = checkbox.checked;
	});
}

function confirmBulkAction() {
	var selected = document.querySelectorAll(".link-checkbox:checked");
	if (selected.length === 0) {
		alert("' . t('Please select at least one link') . '");
		return false;
	}
	var action = document.getElementById("bulkAction").value;
	if (!action) {
		alert("' . t('Please select an action') . '");
		return false;
	}
	return confirm("' . t('Apply this action to') . ' " + selected.length + " ' . t('selected links?') . '");
}

function recheckSingle(linkId) {
	if (confirm("' . t('Recheck this link now?') . '")) {
		submitSingleAction("recheck", linkId);
	}
}

function ignoreSingle(linkId) {
	if (confirm("' . t('Mark this link as ignored? It will be added to the ignore list and not checked in future scans.') . '")) {
		submitSingleAction("markIgnored", linkId);
	}
}

function submitSingleAction(action, linkId) {
	var form = document.createElement("form");
	form.method = "POST";
	form.action = "?_pluginAction=linkChecker_adminResults";

	var inputs = [
		{name: "_pluginAction", value: "linkChecker_adminResults"},
		{name: "bulkAction", value: action},
		{name: "selectedLinks[]", value: linkId},
		{name: "_CSRFToken", value: csrfToken}
	];

	inputs.forEach(function(input) {
		var hiddenField = document.createElement("input");
		hiddenField.type = "hidden";
		hiddenField.name = input.name;
		hiddenField.value = input.value;
		form.appendChild(hiddenField);
	});

	document.body.appendChild(form);
	form.submit();
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Run Scan page - Manual scan execution
 */
function linkChecker_adminRunScan(): void
{
	$message = '';
	$messageType = 'info';
	$scanResults = null;

	// Handle form submission
	if (($_REQUEST['runScan'] ?? '')) {
		\security_dieOnInvalidCsrfToken(); // CSRF protection

		$scanType = $_REQUEST['scanType'] ?? 'full';
		$selectedTables = $_REQUEST['selectedTables'] ?? [];

		// Validate selectedTables is array
		if (!is_array($selectedTables)) {
			$selectedTables = [];
		}

		if ($scanType === 'selected' && empty($selectedTables)) {
			$message = t('Please select at least one table to scan');
			$messageType = 'warning';
		} else {
			// Run the scan
			$scanResults = linkChecker_runLinkCheck($scanType, $selectedTables);

			// Calculate total issues
			$issuesFound = ($scanResults['broken'] ?? 0) + ($scanResults['invalid'] ?? 0) + ($scanResults['timeouts'] ?? 0);

			$message = sprintf(
				t('Scan completed. Checked %d links, found %d issues.'),
				$scanResults['linksChecked'] ?? 0,
				$issuesFound
			);
			$messageType = ($issuesFound > 0) ? 'warning' : 'success';
		}
	}

	// Get enabled tables
	$settings = linkChecker_loadPluginSettings();
	$enabledTables = $settings['enabledTables'] ?? [];

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Link Checker") => '?_pluginAction=linkChecker_adminDashboard',
		t("Run Scan"),
	];

	$adminUI['ADVANCED_ACTIONS'] = linkChecker_getAdvancedActions();

	$adminUI['FORM'] = ['name' => 'runScanForm', 'autocomplete' => 'off'];
	$adminUI['HIDDEN_FIELDS'] = [
		['name' => 'runScan', 'value' => '1'],
		['name' => '_pluginAction', 'value' => 'linkChecker_adminRunScan'],
	];
	$adminUI['BUTTONS'] = [
		['name' => '_action=runScan', 'label' => t('Run Scan')],
	];

	$content = '';

	// Plugin navigation
	$content .= linkChecker_getPluginNav('run_scan');

	// Show message if any
	if ($message) {
		$content .= '<div class="alert alert-' . $messageType . '">';
		$content .= $message;
		$content .= '</div>';
	}

	// Scan Results Summary
	if ($scanResults) {
		$content .= '<div class="separator"><div>' . t('Scan Results Summary') . '</div></div>';

		$content .= '<div class="row" style="margin-left:-10px;margin-right:-10px;margin-bottom:20px">';

		$renderStatCard = function ($label, $count, $color, $colClass = 'col-6 col-md-3') {
			$html = '<div class="' . $colClass . '" style="padding-left:10px;padding-right:10px;margin-bottom:15px">';
			$html .= '<div class="border rounded-3 p-3 h-100 text-center" style="border:1px solid #dee2e6!important">';
			$html .= '<div class="text-uppercase small fw-semibold mb-3" style="font-weight:600;font-size:11px;letter-spacing:0.5px">' . $label . '</div>';
			$html .= '<div class="fs-2 fw-bold" style="color:' . $color . ';font-size:2.5rem;font-weight:700">' . $count . '</div>';
			$html .= '</div></div>';
			return $html;
		};

		$content .= $renderStatCard(t('Links Checked'), $scanResults['linksChecked'] ?? 0, '#6c757d', 'col-12');
		$content .= $renderStatCard(t('Broken'), $scanResults['broken'] ?? 0, '#dc3545');
		$content .= $renderStatCard(t('Warnings'), $scanResults['warnings'] ?? 0, '#ffc107');
		$content .= $renderStatCard(t('Invalid'), $scanResults['invalid'] ?? 0, '#fd7e14');
		$content .= $renderStatCard(t('Timeouts'), $scanResults['timeouts'] ?? 0, '#6c757d');

		$content .= '</div>';

		$content .= '<p style="margin-bottom:20px"><a href="?_pluginAction=linkChecker_adminResults" class="btn btn-primary">' . t('View Detailed Results') . ' &raquo;</a></p>';
	}

	// Scan Options Section
	$content .= '<div class="separator"><div>' . t('Scan Options') . '</div></div>';

	$content .= '<div class="form-horizontal">';

	// Scan Type
	$content .= '<div class="form-group">';
	$content .= '<label class="col-sm-2 control-label">' . t('Scan Type') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="full" checked> ';
	$content .= '<strong>' . t('Full Scan') . '</strong> - ' . t('Check all enabled tables') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Scans all links in all enabled tables. Use this for comprehensive site-wide link checking. May take longer on large sites.') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="quick"> ';
	$content .= '<strong>' . t('Quick Scan') . '</strong> - ' . t('Check only previously broken links') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Only rechecks links that previously had issues. Much faster than a full scan. Use this to verify if you\'ve fixed reported problems.') . '</small>';
	$content .= '</label></div>';
	$content .= '<div class="radio"><label>';
	$content .= '<input type="radio" name="scanType" value="selected"> ';
	$content .= '<strong>' . t('Selected Tables') . '</strong> - ' . t('Check only selected tables below') . '<br>';
	$content .= '<small class="help-block" style="margin-left:20px;margin-top:5px">' . t('Scan only specific content sections you select below. Useful for testing after updating specific pages or sections.') . '</small>';
	$content .= '</label></div>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// Table Selection Section
	$content .= '<div class="separator"><div>' . t('Select Tables to Scan') . '</div></div>';

	$content .= '<p style="margin-bottom:15px">' . t('Select specific tables to scan. This option is used when "Selected Tables" scan type is chosen.') . '</p>';

	if (empty($enabledTables)) {
		$content .= '<div class="alert alert-warning">' . t('No tables are enabled for link checking. Please configure tables in Settings first.') . '</div>';
	} else {
		$content .= '<div style="margin-bottom:15px">';
		$content .= '<button type="button" class="btn btn-sm btn-primary" style="margin-right:5px" onclick="selectAllTables()">' . t('Select All') . '</button>';
		$content .= '<button type="button" class="btn btn-sm btn-default" onclick="selectNoTables()">' . t('Select None') . '</button>';
		$content .= '</div>';

		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col" style="width:50px" class="text-center">' . t('Scan') . '</th>';
		$content .= '<th scope="col">' . t('Section Name') . '</th>';
		$content .= '<th scope="col">' . t('Table') . '</th>';
		$content .= '<th scope="col">' . t('Record Count') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($enabledTables as $tableName) {
			$schema = \loadSchema($tableName);
			$menuName = $schema['menuName'] ?? $tableName;
			$recordCount = \mysql_count($tableName);

			$content .= '<tr>';
			$content .= '<td class="text-center">';
			$content .= '<input class="form-check-input table-checkbox" type="checkbox" name="selectedTables[]" value="' . \htmlencode($tableName) . '">';
			$content .= '</td>';
			$content .= '<td><strong>' . \htmlencode($menuName) . '</strong></td>';
			$content .= '<td><code>' . \htmlencode($tableName) . '</code></td>';
			$content .= '<td>' . $recordCount . ' ' . t('records') . '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table>';
		$content .= '</div>';
	}

	// Performance Note
	$content .= '<div class="alert alert-info" style="margin-top:20px">';
	$content .= '<strong><i class="fa-duotone fa-solid fa-info-circle" aria-hidden="true"></i> ' . t('Performance Note:') . '</strong> ';
	$content .= t('Large scans may take several minutes to complete. The scan runs in the background and will not timeout. You can navigate away from this page and check results later.');
	$content .= '</div>';

	// JavaScript for table selection
	$content .= '<script>
function selectAllTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = true;
	});
}
function selectNoTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = false;
	});
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Settings page - Configure plugin options
 */
function linkChecker_adminSettings(): void
{
	$message = '';
	$messageType = 'info';

	// Load current settings
	$settings = linkChecker_loadPluginSettings();

	// Handle form submission
	if (($_REQUEST['saveSettings'] ?? '')) {
		\security_dieOnInvalidCsrfToken(); // CSRF protection

		// Get enabled tables from form
		$enabledTables = [];
		if (!empty($_REQUEST['enabledTables']) && is_array($_REQUEST['enabledTables'])) {
			$enabledTables = array_values(array_filter($_REQUEST['enabledTables']));
		}

		// Get ignored URLs from form (manual entries)
		$manualUrls = [];
		$ignoredUrlsText = trim($_REQUEST['ignoredUrlsText'] ?? '');
		if ($ignoredUrlsText) {
			$manualUrls = array_filter(array_map('trim', preg_split('/[\r\n]+/', $ignoredUrlsText)));
		}

		// Preserve existing default patterns (array format) and merge with manual URLs (string format)
		$existingPatterns = [];
		foreach ($settings['ignoredUrls'] ?? [] as $item) {
			if (is_array($item) && isset($item['pattern'])) {
				$existingPatterns[] = $item;
			}
		}
		$mergedIgnoredUrls = array_merge($existingPatterns, $manualUrls);

		// Get ignored patterns from form (not currently used but kept for future)
		$ignoredPatterns = [];
		$ignoredPatternsText = trim($_REQUEST['ignoredPatternsText'] ?? '');
		if ($ignoredPatternsText) {
			$ignoredPatterns = array_filter(array_map('trim', preg_split('/[\r\n]+/', $ignoredPatternsText)));
		}

		// Update settings
		$settings['enabledTables'] = $enabledTables;
		$settings['checkInternalLinks'] = !empty($_REQUEST['checkInternalLinks']);
		$settings['checkExternalLinks'] = !empty($_REQUEST['checkExternalLinks']);
		$settings['checkImages'] = !empty($_REQUEST['checkImages']);
		$settings['checkEmailLinks'] = !empty($_REQUEST['checkEmailLinks']);
		$settings['checkPhoneLinks'] = !empty($_REQUEST['checkPhoneLinks']);
		$settings['scheduledScan'] = !empty($_REQUEST['scheduledScan']);
		$settings['scanFrequency'] = $_REQUEST['scanFrequency'] ?? 'daily';
		$settings['emailNotifications'] = !empty($_REQUEST['emailNotifications']);
		$settings['notificationEmail'] = trim($_REQUEST['notificationEmail'] ?? '');
		$settings['emailOnlyOnProblems'] = !empty($_REQUEST['emailOnlyOnProblems']);
		$settings['ignoredUrls'] = $mergedIgnoredUrls;
		$settings['requestTimeout'] = max(5, min(120, intval($_REQUEST['requestTimeout'] ?? 10)));
		$settings['logRetentionDays'] = max(1, min(365, intval($_REQUEST['logRetentionDays'] ?? 90)));

		if (linkChecker_savePluginSettings($settings)) {
			$message = t('Settings saved successfully');
			$messageType = 'success';
		} else {
			$message = t('Failed to save settings. Check file permissions.');
			$messageType = 'danger';
		}
	}

	// Get all content tables
	$allTables = linkChecker_getContentTables();

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Link Checker") => '?_pluginAction=linkChecker_adminDashboard',
		t("Settings"),
	];

	$adminUI['ADVANCED_ACTIONS'] = linkChecker_getAdvancedActions();

	$adminUI['FORM'] = ['name' => 'settingsForm', 'autocomplete' => 'off'];
	$adminUI['HIDDEN_FIELDS'] = [
		['name' => 'saveSettings', 'value' => '1'],
		['name' => '_pluginAction', 'value' => 'linkChecker_adminSettings'],
	];
	$adminUI['BUTTONS'] = [
		['name' => '_action=save', 'label' => t('Save Settings')],
	];

	$content = '';

	// Plugin navigation
	$content .= linkChecker_getPluginNav('settings');

	// Show message if any
	if ($message) {
		$content .= '<div class="alert alert-' . $messageType . '">';
		$content .= $message;
		$content .= '</div>';
	}

	// Scan Options Section
	$content .= '<div class="separator"><div>' . t('Scan Options') . '</div></div>';

	$content .= '<div class="form-horizontal">';

	// Check Internal Links
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Internal Links') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="checkInternalLinks" value="0">';
	$content .= '<input type="checkbox" name="checkInternalLinks" id="checkInternalLinks" value="1"' . ($settings['checkInternalLinks'] ? ' checked' : '') . '> ';
	$content .= t('Check internal links (same domain)');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Check External Links
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('External Links') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="checkExternalLinks" value="0">';
	$content .= '<input type="checkbox" name="checkExternalLinks" id="checkExternalLinks" value="1"' . ($settings['checkExternalLinks'] ? ' checked' : '') . '> ';
	$content .= t('Check external links (other domains)');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Check Images
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Images') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="checkImages" value="0">';
	$content .= '<input type="checkbox" name="checkImages" id="checkImages" value="1"' . ($settings['checkImages'] ? ' checked' : '') . '> ';
	$content .= t('Check image links (img src attributes)');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Check Email Links
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Email Links') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="checkEmailLinks" value="0">';
	$content .= '<input type="checkbox" name="checkEmailLinks" id="checkEmailLinks" value="1"' . (($settings['checkEmailLinks'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Check email links (mailto: format validation)');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Check Phone Links
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Phone Links') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="checkPhoneLinks" value="0">';
	$content .= '<input type="checkbox" name="checkPhoneLinks" id="checkPhoneLinks" value="1"' . (($settings['checkPhoneLinks'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Check phone links (tel: format validation)');
	$content .= '</label></div>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// Schedule Settings Section
	$content .= '<div class="separator"><div>' . t('Schedule Settings') . '</div></div>';

	$content .= '<div class="form-horizontal">';

	// Auto Scan Enabled
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Auto Scan') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="scheduledScan" value="0">';
	$content .= '<input type="checkbox" name="scheduledScan" id="scheduledScan" value="1"' . (($settings['scheduledScan'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Enable automatic scheduled scans');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Scan Frequency
	$content .= '<div class="form-group">';
	$content .= '<label for="scanFrequency" class="col-sm-2 control-label">' . t('Scan Frequency') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<select name="scanFrequency" id="scanFrequency" class="form-control" style="width:200px">';
	$content .= '<option value="daily"' . ($settings['scanFrequency'] === 'daily' ? ' selected' : '') . '>' . t('Daily') . '</option>';
	$content .= '<option value="weekly"' . ($settings['scanFrequency'] === 'weekly' ? ' selected' : '') . '>' . t('Weekly') . '</option>';
	$content .= '<option value="biweekly"' . ($settings['scanFrequency'] === 'biweekly' ? ' selected' : '') . '>' . t('Bi-weekly') . '</option>';
	$content .= '<option value="monthly"' . ($settings['scanFrequency'] === 'monthly' ? ' selected' : '') . '>' . t('Monthly') . '</option>';
	$content .= '</select>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// Email Notifications Section
	$content .= '<div class="separator"><div>' . t('Email Notifications') . '</div></div>';

	$content .= '<div class="form-horizontal">';

	// Email Notifications
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Enable Notifications') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="emailNotifications" value="0">';
	$content .= '<input type="checkbox" name="emailNotifications" id="emailNotifications" value="1"' . ($settings['emailNotifications'] ? ' checked' : '') . '> ';
	$content .= t('Send email notifications after scans');
	$content .= '</label></div>';
	$content .= '</div></div>';

	// Email Address
	$content .= '<div class="form-group">';
	$content .= '<label for="emailAddress" class="col-sm-2 control-label">' . t('Email Address') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<input type="email" class="form-control" style="width:300px" name="notificationEmail" id="notificationEmail" value="' . \htmlencode($settings['notificationEmail'] ?? '') . '" placeholder="admin@example.com">';
	$content .= '</div></div>';

	// Email Only If Broken
	$content .= '<div class="form-group">';
	$content .= '<div class="col-sm-2 control-label">' . t('Send Only If Issues') . '</div>';
	$content .= '<div class="col-sm-10">';
	$content .= '<div class="checkbox"><label>';
	$content .= '<input type="hidden" name="emailOnlyOnProblems" value="0">';
	$content .= '<input type="checkbox" name="emailOnlyOnProblems" id="emailOnlyOnProblems" value="1"' . (($settings['emailOnlyOnProblems'] ?? true) ? ' checked' : '') . '> ';
	$content .= t('Only send emails if broken links are found');
	$content .= '</label></div>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// Tables to Monitor Section
	$content .= '<div class="separator"><div>' . t('Tables to Monitor') . '</div></div>';

	$content .= '<p style="margin-bottom:15px">' . t('Select which content tables should be included in link checking scans.') . '</p>';

	// Select All / None buttons
	$content .= '<div style="margin-bottom:15px">';
	$content .= '<button type="button" class="btn btn-sm btn-primary" style="margin-right:5px" onclick="selectAllTables()">' . t('Select All') . '</button>';
	$content .= '<button type="button" class="btn btn-sm btn-default" onclick="selectNoTables()">' . t('Select None') . '</button>';
	$content .= '</div>';

	// Table list
	$content .= '<div class="table-responsive">';
	$content .= '<table class="table table-hover">';
	$content .= '<thead><tr>';
	$content .= '<th scope="col" style="width:50px" class="text-center">' . t('Enable') . '</th>';
	$content .= '<th scope="col">' . t('Section Name') . '</th>';
	$content .= '<th scope="col">' . t('Table') . '</th>';
	$content .= '<th scope="col">' . t('Record Count') . '</th>';
	$content .= '</tr></thead><tbody>';

	foreach ($allTables as $tableName) {
		$isEnabled = in_array($tableName, $settings['enabledTables']);
		$schema = \loadSchema($tableName);
		$menuName = $schema['menuName'] ?? $tableName;
		$recordCount = \mysql_count($tableName);

		$content .= '<tr>';
		$content .= '<td class="text-center">';
		$content .= '<input class="form-check-input table-checkbox" type="checkbox" name="enabledTables[]" value="' . \htmlencode($tableName) . '"' . ($isEnabled ? ' checked' : '') . '>';
		$content .= '</td>';
		$content .= '<td><strong>' . \htmlencode($menuName) . '</strong></td>';
		$content .= '<td><code>' . \htmlencode($tableName) . '</code></td>';
		$content .= '<td>' . $recordCount . ' ' . t('records') . '</td>';
		$content .= '</tr>';
	}

	$content .= '</tbody></table>';
	$content .= '</div>';

	// Ignore List Section
	$content .= '<div class="separator"><div>' . t('Ignore List') . '</div></div>';

	$content .= '<p style="margin-bottom:15px">' . t('Specify URLs or patterns that should be excluded from link checking.') . '</p>';

	$content .= '<div class="form-horizontal">';

	// Ignored URLs - separate strings from array patterns
	$manualUrls = [];
	$defaultPatterns = [];
	foreach ($settings['ignoredUrls'] ?? [] as $item) {
		if (is_string($item)) {
			$manualUrls[] = $item;
		} elseif (is_array($item) && isset($item['pattern'])) {
			$defaultPatterns[] = $item;
		}
	}

	$content .= '<div class="form-group">';
	$content .= '<label for="ignoredUrlsText" class="col-sm-2 control-label">' . t('Ignored URLs') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<textarea class="form-control" name="ignoredUrlsText" id="ignoredUrlsText" rows="6" placeholder="https://example.com/page-to-ignore&#10;https://example.com/another-page">';
	$content .= \htmlencode(implode("\n", $manualUrls));
	$content .= '</textarea>';
	$content .= '<p class="help-block" style="margin-top:8px">' . t('Enter complete URLs to ignore, one per line.') . '</p>';

	// Show default ignored patterns in a read-only info box
	if (!empty($defaultPatterns)) {
		$content .= '<div class="alert alert-info" style="margin-top:15px">';
		$content .= '<strong>' . t('Default Ignored Domains:') . '</strong><br>';
		$content .= '<small>';
		foreach ($defaultPatterns as $pattern) {
			$content .= '• ' . \htmlencode($pattern['pattern']) . ' - ' . \htmlencode($pattern['reason']) . '<br>';
		}
		$content .= '</small>';
		$content .= '</div>';
	}
	$content .= '</div></div>';

	// Ignored Patterns
	$content .= '<div class="form-group">';
	$content .= '<label for="ignoredPatternsText" class="col-sm-2 control-label">' . t('Ignored Patterns') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<textarea class="form-control" name="ignoredPatternsText" id="ignoredPatternsText" rows="6" placeholder="*/admin/*&#10;*/private/*&#10;*.example.com/*">';
	$content .= \htmlencode(implode("\n", $settings['ignoredPatterns'] ?? []));
	$content .= '</textarea>';
	$content .= '<p class="help-block" style="margin-top:8px">' . t('Enter URL patterns to ignore, one per line. Use * as wildcard.') . '</p>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// Performance Settings Section
	$content .= '<div class="separator"><div>' . t('Performance Settings') . '</div></div>';

	$content .= '<div class="form-horizontal">';

	// Max Execution Time
	$content .= '<div class="form-group">';
	$content .= '<label for="maxExecutionTime" class="col-sm-2 control-label">' . t('Max Execution Time') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<input type="number" class="form-control" style="width:100px; display:inline-block" name="maxExecutionTime" id="maxExecutionTime" value="' . intval($settings['maxExecutionTime'] ?? 300) . '" min="60" max="3600">';
	$content .= ' <span class="help-inline">' . t('seconds') . '</span>';
	$content .= '<p class="help-block" style="margin-top:8px">' . t('Maximum time allowed for an <strong>entire scan</strong> to run (60-3600 seconds). For large sites with hundreds of links, increase this value. This prevents scans from timing out on sites with lots of content.') . '</p>';
	$content .= '</div></div>';

	// Request Timeout
	$content .= '<div class="form-group">';
	$content .= '<label for="requestTimeout" class="col-sm-2 control-label">' . t('Request Timeout') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<input type="number" class="form-control" style="width:100px; display:inline-block" name="requestTimeout" id="requestTimeout" value="' . intval($settings['requestTimeout']) . '" min="5" max="120">';
	$content .= ' <span class="help-inline">' . t('seconds') . '</span>';
	$content .= '<p class="help-block" style="margin-top:8px">' . t('Timeout for checking <strong>each individual link</strong> (5-120 seconds). Increase this if you\'re checking links on slow-loading external sites or if your network connection is slow.') . '</p>';
	$content .= '</div></div>';

	// History Retention
	$content .= '<div class="form-group">';
	$content .= '<label for="historyRetentionDays" class="col-sm-2 control-label">' . t('History Retention') . '</label>';
	$content .= '<div class="col-sm-10">';
	$content .= '<input type="number" class="form-control" style="width:100px; display:inline-block" name="logRetentionDays" id="logRetentionDays" value="' . intval($settings['logRetentionDays'] ?? 90) . '" min="1" max="365">';
	$content .= ' <span class="help-inline">' . t('days') . '</span>';
	$content .= '<p class="help-block" style="margin-top:8px">' . t('Number of days to keep scan history (1-365 days).') . '</p>';
	$content .= '</div></div>';

	$content .= '</div>'; // end form-horizontal

	// JavaScript for table selection
	$content .= '<script>
function selectAllTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = true;
	});
}
function selectNoTables() {
	document.querySelectorAll(".table-checkbox").forEach(function(cb) {
		cb.checked = false;
	});
}
</script>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * History page - View scan history log
 */
function linkChecker_adminHistory(): void
{
	// Pagination
	$page = max(1, intval($_REQUEST['page'] ?? 1));
	$perPage = intval($_REQUEST['perPage'] ?? 25);
	$perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;
	$offset = ($page - 1) * $perPage;

	// Build where clause
	$where = "1=1";

	// Get total count
	$totalCount = \mysql_count('_linkchecker_scans', $where);
	$totalPages = ceil($totalCount / $perPage);

	// Get history entries
	$history = \mysql_select('_linkchecker_scans', "{$where} ORDER BY `createdDate` DESC LIMIT {$offset}, {$perPage}");

	// Build content
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Link Checker") => '?_pluginAction=linkChecker_adminDashboard',
		t("Scan History"),
	];

	$adminUI['ADVANCED_ACTIONS'] = linkChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= linkChecker_getPluginNav('history');

	// Results count
	$content .= '<p style="margin-top:20px;margin-bottom:15px">' . sprintf(t('Showing %d - %d of %d scans'), min($offset + 1, $totalCount), min($offset + $perPage, $totalCount), $totalCount) . '</p>';

	if (empty($history)) {
		$content .= '<p>' . t('No scan history found.') . '</p>';
	} else {
		$content .= '<div class="table-responsive">';
		$content .= '<table class="table table-striped table-hover">';
		$content .= '<thead><tr>';
		$content .= '<th scope="col">' . t('Scan Date') . '</th>';
		$content .= '<th scope="col">' . t('Links Checked') . '</th>';
		$content .= '<th scope="col">' . t('Issues Found') . '</th>';
		$content .= '<th scope="col">' . t('Broken') . '</th>';
		$content .= '<th scope="col">' . t('Warnings') . '</th>';
		$content .= '<th scope="col">' . t('Invalid') . '</th>';
		$content .= '<th scope="col">' . t('Timeouts') . '</th>';
		$content .= '<th scope="col">' . t('Duration') . '</th>';
		$content .= '<th scope="col">' . t('Scan Type') . '</th>';
		$content .= '</tr></thead><tbody>';

		foreach ($history as $scan) {
			// Calculate duration
			$duration = 0;
			if ($scan['startTime'] && $scan['endTime']) {
				$duration = strtotime($scan['endTime']) - strtotime($scan['startTime']);
			}

			// Calculate issues found
			$issuesFound = intval($scan['brokenLinks']) + intval($scan['invalidLinks']) + intval($scan['timeouts']);

			$content .= '<tr>';
			$content .= '<td class="text-nowrap">' . date('Y-m-d H:i:s', strtotime($scan['startTime'])) . '</td>';
			$content .= '<td>' . intval($scan['linksChecked']) . '</td>';
			$content .= '<td>';
			if ($issuesFound > 0) {
				$content .= '<strong style="color:#dc3545">' . $issuesFound . '</strong>';
			} else {
				$content .= '<strong style="color:#28a745">' . $issuesFound . '</strong>';
			}
			$content .= '</td>';
			$content .= '<td>' . intval($scan['brokenLinks']) . '</td>';
			$content .= '<td>' . intval($scan['warnings']) . '</td>';
			$content .= '<td>' . intval($scan['invalidLinks']) . '</td>';
			$content .= '<td>' . intval($scan['timeouts']) . '</td>';
			$content .= '<td>' . $duration . ' ' . t('sec') . '</td>';
			$content .= '<td>' . \htmlencode(ucfirst($scan['scanType'])) . '</td>';
			$content .= '</tr>';
		}

		$content .= '</tbody></table></div>';

		// Pagination
		if ($totalPages > 1) {
			$baseUrl = '?_pluginAction=linkChecker_adminHistory';
			$baseUrl .= '&perPage=' . $perPage;

			$content .= '<div class="text-center" style="margin-top:15px">';

			// Previous
			if ($page > 1) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page - 1) . '" class="btn btn-default btn-sm">&laquo; ' . t('Previous') . '</a> ';
			}

			// Page numbers
			$startPage = max(1, $page - 2);
			$endPage = min($totalPages, $page + 2);

			for ($i = $startPage; $i <= $endPage; $i++) {
				if ($i === $page) {
					$content .= '<span class="btn btn-primary btn-sm">' . $i . '</span> ';
				} else {
					$content .= '<a href="' . $baseUrl . '&page=' . $i . '" class="btn btn-default btn-sm">' . $i . '</a> ';
				}
			}

			// Next
			if ($page < $totalPages) {
				$content .= '<a href="' . $baseUrl . '&page=' . ($page + 1) . '" class="btn btn-default btn-sm">' . t('Next') . ' &raquo;</a>';
			}

			$content .= '</div>';
		}
	}

	// Per page selector
	$content .= '<div style="margin-top:20px">';
	$content .= '<form method="get" style="display:inline-block">';
	$content .= '<input type="hidden" name="_pluginAction" value="linkChecker_adminHistory">';
	$content .= '<label for="perPage">' . t('Per Page:') . ' </label>';
	$content .= '<select name="perPage" id="perPage" class="form-control" style="width:100px;display:inline-block" onchange="this.form.submit()">';
	foreach ([10, 25, 50, 100] as $pp) {
		$content .= '<option value="' . $pp . '"' . ($perPage === $pp ? ' selected' : '') . '>' . $pp . '</option>';
	}
	$content .= '</select>';
	$content .= '</form>';
	$content .= '</div>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}

/**
 * Help page - Display plugin documentation
 */
function linkChecker_adminHelp(): void
{
	$adminUI = [];

	$adminUI['PAGE_TITLE'] = [
		t("Plugins") => '?menu=admin&action=plugins',
		t("Link Checker") => '?_pluginAction=linkChecker_adminDashboard',
		t("Help"),
	];

	$adminUI['ADVANCED_ACTIONS'] = linkChecker_getAdvancedActions();

	$content = '';

	// Plugin navigation
	$content .= linkChecker_getPluginNav('help');

	// Overview Section
	$content .= '<div class="separator"><div>' . t('Overview') . '</div></div>';

	$content .= '<p>Automatically scan your CMS Builder content for broken links, missing images, and other link issues. Keep your website healthy and improve user experience by identifying and fixing broken links before they impact your visitors.</p>';

	$content .= '<p><strong>' . t('Features:') . '</strong></p>';
	$content .= '<ul>';
	$content .= '<li><strong>Comprehensive Scanning</strong> - Checks internal links, external links, images, and download files</li>';
	$content .= '<li><strong>Automated Scheduling</strong> - Run scans automatically on a daily, weekly, or monthly schedule</li>';
	$content .= '<li><strong>Email Notifications</strong> - Get notified when broken links are detected</li>';
	$content .= '<li><strong>Detailed Reports</strong> - View comprehensive scan results with filtering and sorting</li>';
	$content .= '<li><strong>Bulk Actions</strong> - Mark links as fixed, ignored, or recheck multiple links at once</li>';
	$content .= '<li><strong>Ignore List</strong> - Exclude specific URLs or patterns from checking</li>';
	$content .= '<li><strong>Edit Integration</strong> - Quick links to edit records containing broken links</li>';
	$content .= '<li><strong>Scan History</strong> - Track scan performance and trends over time</li>';
	$content .= '</ul>';

	// Installation Section
	$content .= '<div class="separator"><div>' . t('Installation') . '</div></div>';

	$content .= '<ol>';
	$content .= '<li>Copy the <code>linkChecker</code> folder to your plugins directory</li>';
	$content .= '<li>Ensure PHP files have proper permissions: <code>chmod 644 /path/to/plugins/linkChecker/*.php</code></li>';
	$content .= '<li>Log into the CMSB admin area and navigate to the Plugins menu</li>';
	$content .= '<li>The plugin will automatically create required database tables</li>';
	$content .= '<li>Verify installation by visiting <strong>Plugins &gt; Link Checker &gt; Dashboard</strong></li>';
	$content .= '<li>Go to <strong>Plugins &gt; Link Checker &gt; Settings</strong> to configure which tables to scan</li>';
	$content .= '</ol>';

	// Configuration Section
	$content .= '<div class="separator"><div>' . t('Configuration') . '</div></div>';

	$content .= '<p>All settings are configured through the admin interface at <strong>Plugins &gt; Link Checker &gt; Settings</strong>.</p>';

	$content .= '<div class="table-responsive">';
	$content .= '<table class="table table-striped">';
	$content .= '<thead><tr><th>' . t('Setting') . '</th><th>' . t('Description') . '</th><th>' . t('Default') . '</th></tr></thead>';
	$content .= '<tbody>';
	$content .= '<tr><td>Check Internal Links</td><td>Check links to pages on your own domain</td><td>Enabled</td></tr>';
	$content .= '<tr><td>Check External Links</td><td>Check links to other websites</td><td>Enabled</td></tr>';
	$content .= '<tr><td>Check Images</td><td>Verify image sources are accessible</td><td>Enabled</td></tr>';
	$content .= '<tr><td>Check Downloads</td><td>Check downloadable files (PDF, ZIP, etc.)</td><td>Enabled</td></tr>';
	$content .= '<tr><td>Auto Scan</td><td>Enable automatic scheduled scans</td><td>Disabled</td></tr>';
	$content .= '<tr><td>Scan Frequency</td><td>How often to run automatic scans</td><td>Weekly</td></tr>';
	$content .= '<tr><td>Email Notifications</td><td>Send email alerts after scans</td><td>Disabled</td></tr>';
	$content .= '<tr><td>Max Execution Time</td><td>Maximum scan duration (60-3600 seconds)</td><td>300</td></tr>';
	$content .= '<tr><td>Request Timeout</td><td>Timeout for individual link checks (5-120 seconds)</td><td>30</td></tr>';
	$content .= '<tr><td>History Retention</td><td>Days to keep scan history (1-365)</td><td>90</td></tr>';
	$content .= '</tbody></table>';
	$content .= '</div>';

	// Using the Plugin Section
	$content .= '<div class="separator"><div>' . t('Using the Plugin') . '</div></div>';

	$content .= '<p><strong>Running a Scan</strong></p>';
	$content .= '<ol>';
	$content .= '<li>Navigate to <strong>Plugins &gt; Link Checker &gt; Run Scan</strong></li>';
	$content .= '<li>Choose your scan type:</li>';
	$content .= '<ul>';
	$content .= '<li><strong>Full Scan</strong> - Checks all links in all enabled tables</li>';
	$content .= '<li><strong>Quick Scan</strong> - Only rechecks previously broken links</li>';
	$content .= '<li><strong>Selected Tables</strong> - Scans only the tables you select</li>';
	$content .= '</ul>';
	$content .= '<li>Click "Run Scan" to start the process</li>';
	$content .= '<li>Wait for the scan to complete (large sites may take several minutes)</li>';
	$content .= '<li>View results in the Scan Results page</li>';
	$content .= '</ol>';

	$content .= '<p><strong>Viewing Results</strong></p>';
	$content .= '<p>The Scan Results page shows all detected links with their status. You can:</p>';
	$content .= '<ul>';
	$content .= '<li>Filter by status (OK, Broken, Warning, Redirect, Ignored)</li>';
	$content .= '<li>Filter by link type (Internal, External)</li>';
	$content .= '<li>Filter by table or date range</li>';
	$content .= '<li>Sort by any column (URL, status, last checked, etc.)</li>';
	$content .= '<li>Click "Edit Record" to quickly fix the source content</li>';
	$content .= '<li>Use bulk actions to manage multiple links at once</li>';
	$content .= '</ul>';

	// Link Status Codes Section
	$content .= '<div class="separator"><div>' . t('Link Status Codes') . '</div></div>';

	$content .= '<div class="table-responsive" style="margin-bottom:20px">';
	$content .= '<table class="table table-striped">';
	$content .= '<thead><tr><th>' . t('Status') . '</th><th>' . t('Description') . '</th><th>' . t('Action Needed') . '</th></tr></thead>';
	$content .= '<tbody>';
	$content .= '<tr><td><span class="badge" style="background-color:#28a745;color:#fff">OK</span></td><td>Link is working properly (200 status)</td><td>No action needed</td></tr>';
	$content .= '<tr><td><span class="badge" style="background-color:#dc3545;color:#fff">Broken</span></td><td>Link not found or server error (404, 500, etc.)</td><td>Update or remove link</td></tr>';
	$content .= '<tr><td><span class="badge" style="background-color:#ffc107;color:#212529">Warning</span></td><td>Link works but has issues (slow response, etc.)</td><td>Review link</td></tr>';
	$content .= '<tr><td><span class="badge" style="background-color:#17a2b8;color:#fff">Redirect</span></td><td>Link redirects to another URL (301, 302)</td><td>Consider updating to final URL</td></tr>';
	$content .= '<tr><td><span class="badge" style="background-color:#6c757d;color:#fff">Ignored</span></td><td>Link is in ignore list</td><td>No action needed</td></tr>';
	$content .= '</tbody></table>';
	$content .= '</div>';

	// HTTP Status Codes Section
	$content .= '<div class="separator"><div>' . t('Common HTTP Status Codes') . '</div></div>';

	$content .= '<div class="table-responsive" style="margin-bottom:20px">';
	$content .= '<table class="table table-striped">';
	$content .= '<thead><tr><th>' . t('Code') . '</th><th>' . t('Meaning') . '</th></tr></thead>';
	$content .= '<tbody>';
	$content .= '<tr><td>200</td><td>OK - Link is working</td></tr>';
	$content .= '<tr><td>301</td><td>Moved Permanently - Permanent redirect</td></tr>';
	$content .= '<tr><td>302</td><td>Found - Temporary redirect</td></tr>';
	$content .= '<tr><td>403</td><td>Forbidden - Access denied</td></tr>';
	$content .= '<tr><td>404</td><td>Not Found - Page does not exist</td></tr>';
	$content .= '<tr><td>500</td><td>Internal Server Error - Server problem</td></tr>';
	$content .= '<tr><td>503</td><td>Service Unavailable - Server temporarily down</td></tr>';
	$content .= '<tr><td>0</td><td>Connection Failed - Unable to reach server</td></tr>';
	$content .= '</tbody></table>';
	$content .= '</div>';

	// Ignore List Section
	$content .= '<div class="separator"><div>' . t('Using the Ignore List') . '</div></div>';

	$content .= '<p>The ignore list allows you to exclude specific URLs or patterns from link checking. This is useful for:</p>';
	$content .= '<ul>';
	$content .= '<li>Links that require authentication (login pages, admin areas)</li>';
	$content .= '<li>Links that block automated requests</li>';
	$content .= '<li>Temporary or development URLs</li>';
	$content .= '<li>Third-party services with rate limiting</li>';
	$content .= '</ul>';

	$content .= '<p><strong>Ignored URLs</strong> - Enter complete URLs, one per line:</p>';
	$content .= '<pre>https://example.com/members-only/
https://secure.example.com/login</pre>';

	$content .= '<p><strong>Ignored Patterns</strong> - Use wildcards (*) to match multiple URLs:</p>';
	$content .= '<pre>*/admin/*
*/private/*
*.facebook.com/*
https://example.com/temp-*</pre>';

	// Troubleshooting Section
	$content .= '<div class="separator" style="margin-top:30px"><div>' . t('Troubleshooting') . '</div></div>';

	$content .= '<p><strong>Scan Takes Too Long</strong></p>';
	$content .= '<ul>';
	$content .= '<li>Increase the Max Execution Time in Settings</li>';
	$content .= '<li>Reduce the number of enabled tables</li>';
	$content .= '<li>Use Quick Scan instead of Full Scan</li>';
	$content .= '<li>Disable external link checking temporarily</li>';
	$content .= '</ul>';

	$content .= '<p><strong>False Positives (Links Marked as Broken)</strong></p>';
	$content .= '<ul>';
	$content .= '<li>Some sites block automated requests - add them to ignore list</li>';
	$content .= '<li>Increase Request Timeout for slow servers</li>';
	$content .= '<li>Check if the link requires authentication</li>';
	$content .= '<li>Verify the link manually in a browser</li>';
	$content .= '</ul>';

	$content .= '<p><strong>Email Notifications Not Sending</strong></p>';
	$content .= '<ul>';
	$content .= '<li>Verify email address is correct in Settings</li>';
	$content .= '<li>Check your server\'s email configuration</li>';
	$content .= '<li>Look for emails in spam/junk folder</li>';
	$content .= '<li>Enable "Send Only If Issues" if you only want alerts for broken links</li>';
	$content .= '</ul>';

	$content .= '<p><strong>Scans Not Running Automatically</strong></p>';
	$content .= '<ul>';
	$content .= '<li>Ensure Auto Scan is enabled in Settings</li>';
	$content .= '<li>Verify your server\'s cron job is configured</li>';
	$content .= '<li>Check the scan frequency setting</li>';
	$content .= '<li>Review scan history to see when last scan occurred</li>';
	$content .= '</ul>';

	// Best Practices Section
	$content .= '<div class="separator"><div>' . t('Best Practices') . '</div></div>';

	$content .= '<ul>';
	$content .= '<li>Run a full scan immediately after installation to establish a baseline</li>';
	$content .= '<li>Set up automatic scans to run during off-peak hours</li>';
	$content .= '<li>Enable email notifications to stay informed of broken links</li>';
	$content .= '<li>Review and fix broken links promptly to maintain site quality</li>';
	$content .= '<li>Use the ignore list for known problematic domains</li>';
	$content .= '<li>Consider disabling external link checking if you have many external links</li>';
	$content .= '<li>Keep scan history for at least 90 days to track trends</li>';
	$content .= '<li>Run quick scans more frequently than full scans</li>';
	$content .= '</ul>';

	// Requirements Section
	$content .= '<div class="separator"><div>' . t('Requirements') . '</div></div>';

	$content .= '<ul>';
	$content .= '<li>CMS Builder 3.50 or higher</li>';
	$content .= '<li>PHP 8.0 or higher</li>';
	$content .= '<li>cURL extension enabled</li>';
	$content .= '<li>Sufficient PHP execution time for large scans</li>';
	$content .= '<li>MySQL database with permissions to create tables</li>';
	$content .= '</ul>';

	// Version Info
	$content .= '<div class="separator"><div>' . t('Version Information') . '</div></div>';

	$content .= '<p><strong>Version:</strong> ' . ($GLOBALS['LINKCHECKER_VERSION'] ?? '1.00') . '</p>';
	$content .= '<p><strong>Author:</strong> <a href="https://www.sagentic.com" target="_blank" rel="noopener">Sagentic Web Design <span class="sr-only">' . t('(opens in new tab)') . '</span></a></p>';

	$adminUI['CONTENT'] = $content;

	\adminUI($adminUI);
	exit;
}
