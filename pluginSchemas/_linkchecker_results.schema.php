<?php

/**
 * Link Checker Results Table Schema
 *
 * Stores broken/warning/invalid links found during scans
 */
return [
	'menuName'            => 'Link Checker Results',
	'_tableName'          => '_linkchecker_results',
	'_primaryKey'         => 'num',
	'menuType'            => 'multi',
	'listPageFields'      => 'url, status, httpCode, scanDate, tableName',
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

	'url' => [
		'label'            => 'URL',
		'type'             => 'textbox',
		'customColumnType' => 'TEXT NOT NULL',
	],

	'status' => [
		'label'            => 'Status',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "broken|Broken\nwarning|Warning\ninvalid|Invalid\ntimeout|Timeout\nignored|Ignored",
		'customColumnType' => "ENUM('broken','warning','invalid','timeout','ignored') NOT NULL",
		'indexed'          => 1,
	],

	'httpCode' => [
		'label'            => 'HTTP Code',
		'type'             => 'textfield',
		'customColumnType' => 'INT(5)',
		'indexed'          => 1,
	],

	'errorMessage' => [
		'label'            => 'Error Message',
		'type'             => 'textbox',
		'customColumnType' => 'TEXT',
	],

	'linkType' => [
		'label'            => 'Link Type',
		'type'             => 'list',
		'listType'         => 'pulldown',
		'optionsType'      => 'text',
		'optionsText'      => "internal|Internal\nexternal|External\nimage|Image\nemail|Email\nphone|Phone",
		'customColumnType' => "ENUM('internal','external','image','email','phone') NOT NULL",
		'indexed'          => 1,
	],

	'tableName' => [
		'label'            => 'Table Name',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(255) NOT NULL',
		'indexed'          => 1,
	],

	'fieldName' => [
		'label'            => 'Field Name',
		'type'             => 'textfield',
		'customColumnType' => 'VARCHAR(255) NOT NULL',
	],

	'recordNum' => [
		'label'            => 'Record Number',
		'type'             => 'textfield',
		'customColumnType' => 'INT(10) UNSIGNED NOT NULL',
		'indexed'          => 1,
	],

	'scanDate' => [
		'label'            => 'Scan Date',
		'type'             => 'none',
		'customColumnType' => 'DATETIME NOT NULL',
		'indexed'          => 1,
	],
];
