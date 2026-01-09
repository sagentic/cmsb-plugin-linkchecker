<?php

/**
 * Link Checker Scans Table Schema
 *
 * Stores history of all scan operations
 */
return [
	'menuName'            => 'Link Checker Scans',
	'_tableName'          => '_linkchecker_scans',
	'_primaryKey'         => 'num',
	'menuType'            => 'multi',
	'listPageFields'      => 'scanType, tablesScanned, linksChecked, broken, warnings, scanDate',
	'listPageOrder'       => 'scanDate DESC',
	'listPageSearchFields' => '_all_',
	'_filenameFields'     => 'num',
	'_disableView'        => 1,
	'_disableAdd'         => 1,
	'_disableModify'      => 1,
	'menuHidden'          => 1,
	'menuOrder'           => 9999999999,

	'num' => [
		'type'          => 'none',
		'label'         => 'Record Number',
		'isSystemField' => 1,
	],

	'createdDate' => [
		'type'          => 'none',
		'label'         => 'Created Date',
		'isSystemField' => 1,
	],

	'scanDate' => [
		'label'            => 'Scan Date',
		'type'             => 'none',
		'customColumnType' => 'DATETIME NOT NULL',
		'indexed'          => 1,
	],

	'scanType' => [
		'label'            => 'Scan Type',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "full|Full Scan\nquick|Quick Scan\nselected|Selected Tables\nmanual|Manual\nscheduled|Scheduled",
		'customColumnType' => "ENUM('full','quick','selected','manual','scheduled') NOT NULL",
	],

	'tablesScanned' => [
		'label'            => 'Tables Scanned',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'recordsScanned' => [
		'label'            => 'Records Scanned',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'linksChecked' => [
		'label'            => 'Links Checked',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'broken' => [
		'label'            => 'Broken Links',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'warnings' => [
		'label'            => 'Warnings',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'invalid' => [
		'label'            => 'Invalid Links',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'timeouts' => [
		'label'            => 'Timeouts',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],

	'duration' => [
		'label'            => 'Duration (seconds)',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) DEFAULT 0',
	],
];
