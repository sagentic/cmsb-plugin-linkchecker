<?php

/**
 * Link Checker Plugin - Helper Functions
 *
 * @package LinkChecker
 */


/**
 * Get the path to the settings JSON file
 *
 * @return string Settings file path
 */
function linkChecker_getSettingsFilePath(): string
{
	return __DIR__ . '/linkChecker_settings.json';
}

/**
 * Load plugin settings from JSON file
 *
 * @return array Settings array
 */
function linkChecker_loadPluginSettings(): array
{
	$settingsFile = linkChecker_getSettingsFilePath();
	$defaults = [
		'enabledTables' => [],
		'checkInternalLinks' => true,
		'checkExternalLinks' => true,
		'checkImages' => true,
		'checkEmailLinks' => true,
		'checkPhoneLinks' => true,
		'scheduledScan' => true,
		'scanFrequency' => 'daily',
		'emailNotifications' => true,
		'emailOnlyOnProblems' => true,
		'notificationEmail' => '',
		'requestTimeout' => 10,
		'userAgent' => 'CMSB-LinkChecker/1.0',
		'ignoredUrls' => [
			// Common domains that block automated checkers but work fine for users
			['pattern' => 'linkedin.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
			['pattern' => 'facebook.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
			['pattern' => 'instagram.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
			['pattern' => 'twitter.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
			['pattern' => 'x.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
			['pattern' => 'youtube.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
			['pattern' => 'amazon.com', 'type' => 'domain', 'reason' => 'Blocks HEAD requests', 'addedBy' => 'default'],
			['pattern' => 'google', 'type' => 'domain', 'reason' => 'All Google domains block automated checkers', 'addedBy' => 'default'],
			['pattern' => 'canva.com', 'type' => 'domain', 'reason' => 'Blocks automated requests', 'addedBy' => 'default'],
		],
		'logRetentionDays' => 90,
		'lastScanDate' => null,
		'lastScanResults' => [],
	];

	if (!file_exists($settingsFile) || !is_readable($settingsFile)) {
		return $defaults;
	}

	$content = @file_get_contents($settingsFile);
	if ($content === false) {
		return $defaults;
	}

	$settings = @json_decode($content, true);

	if (!is_array($settings)) {
		return $defaults;
	}

	return array_merge($defaults, $settings);
}

/**
 * Save plugin settings to JSON file
 *
 * @param array $settings Settings to save
 * @return bool True on success
 */
function linkChecker_savePluginSettings(array $settings): bool
{
	$settingsFile = linkChecker_getSettingsFilePath();
	$json = json_encode($settings, JSON_PRETTY_PRINT);
	return @file_put_contents($settingsFile, $json) !== false;
}

/**
 * Get the site's base URL
 *
 * @return string Base URL without trailing slash
 */
function linkChecker_getBaseUrl(): string
{
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
	return $protocol . '://' . $host;
}

/**
 * Check if table should be permanently ignored
 *
 * @param string $tableName Table name to check
 * @return bool True if should be ignored
 */
function linkChecker_shouldIgnoreTable(string $tableName): bool
{
	// Always ignore these common system/utility tables
	$ignoredTables = [
		'accounts',
		'uploads',
		'convert_to_webp',
		'indexnow',
		'indexnow_log',
		'linkchecker_results',
		'linkchecker_scans',
	];

	// Check exact match
	if (in_array(strtolower($tableName), $ignoredTables)) {
		return true;
	}

	// Check if table name contains _menugroup or _menulink
	if (stripos($tableName, '_menugroup') !== false || stripos($tableName, 'menugroup') !== false) {
		return true;
	}
	if (stripos($tableName, '_menulink') !== false || stripos($tableName, 'menulink') !== false) {
		return true;
	}

	// Check schema for menugroup or link type
	$schema = \loadSchema($tableName);
	$menuType = $schema['menuType'] ?? '';
	if ($menuType === 'menugroup' || $menuType === 'link') {
		return true;
	}

	return false;
}

/**
 * Get list of content tables with text fields
 *
 * @return array List of table names
 */
function linkChecker_getContentTablesWithTextFields(): array
{
	$tableNames = \getSchemaTables();
	$contentTables = [];

	foreach ($tableNames as $tableName) {
		// Skip system tables (starting with _)
		if (str_starts_with($tableName, '_')) {
			continue;
		}

		// Skip permanently ignored tables
		if (linkChecker_shouldIgnoreTable($tableName)) {
			continue;
		}

		$schema = \loadSchema($tableName);
		$hasTextField = false;

		foreach ($schema as $field) {
			if (!is_array($field)) continue;
			$fieldType = $field['type'] ?? '';
			if (in_array($fieldType, ['wysiwyg', 'textbox', 'textfield'])) {
				$hasTextField = true;
				break;
			}
		}

		if ($hasTextField) {
			$contentTables[] = $tableName;
		}
	}

	sort($contentTables);
	return $contentTables;
}

/**
 * Get text fields from schema
 *
 * @param array $schema Table schema
 * @return array List of field names
 */
function linkChecker_getTextFieldsFromSchema(array $schema): array
{
	$textFields = [];
	foreach ($schema as $fieldName => $field) {
		if (!is_array($field)) continue;
		$fieldType = $field['type'] ?? '';
		if (in_array($fieldType, ['wysiwyg', 'textbox', 'textfield'])) {
			$textFields[] = $fieldName;
		}
	}
	return $textFields;
}

/**
 * Extract links from content
 *
 * @param string $content Content to scan
 * @param string $fieldType Field type
 * @return array Array of links with url and type
 */
function linkChecker_extractLinks(string $content, string $fieldType): array
{
	$links = [];

	// HTML links: <a href="...">
	preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
	foreach ($matches[1] as $url) {
		$links[] = ['url' => $url, 'type' => linkChecker_categorizeLink($url)];
	}

	// Images: <img src="...">
	preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
	foreach ($matches[1] as $url) {
		$links[] = ['url' => $url, 'type' => 'image'];
	}

	// Background images: style="background-image: url(...)"
	preg_match_all('/url\(["\']?([^"\')\s]+)["\']?\)/i', $content, $matches);
	foreach ($matches[1] as $url) {
		$links[] = ['url' => $url, 'type' => 'image'];
	}

	return $links;
}

/**
 * Categorize a link by type
 *
 * @param string $url URL to categorize
 * @return string Link type (email, phone, internal, external, image)
 */
function linkChecker_categorizeLink(string $url): string
{
	if (preg_match('/^mailto:/i', $url)) return 'email';
	if (preg_match('/^tel:/i', $url)) return 'phone';
	if (linkChecker_isInternalUrl($url)) return 'internal';
	return 'external';
}

/**
 * Check if URL is internal
 *
 * @param string $url URL to check
 * @return bool True if internal
 */
function linkChecker_isInternalUrl(string $url): bool
{
	$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

	// Relative URLs are internal
	if (!preg_match('/^https?:\/\//i', $url)) {
		return true;
	}

	// Check if host matches
	$urlHost = parse_url($url, PHP_URL_HOST);
	$urlHost = preg_replace('/^www\./', '', strtolower($urlHost));
	$currentHost = preg_replace('/^www\./', '', strtolower($host));

	return $urlHost === $currentHost;
}

/**
 * Convert relative URL to absolute
 *
 * @param string $url URL to convert
 * @param string $baseUrl Base URL
 * @return string Absolute URL
 */
function linkChecker_makeAbsoluteUrl(string $url, string $baseUrl): string
{
	// Already absolute
	if (preg_match('/^https?:\/\//i', $url)) {
		return $url;
	}

	// Protocol-relative URL
	if (str_starts_with($url, '//')) {
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		return $protocol . ':' . $url;
	}

	// Absolute path
	if (str_starts_with($url, '/')) {
		return rtrim($baseUrl, '/') . $url;
	}

	// Relative path
	return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}

/**
 * Convert URL to file path
 *
 * @param string $url URL to convert
 * @return string|null File path or null if not local
 */
function linkChecker_urlToFilePath(string $url): ?string
{
	$webRootDir = \settings('webRootDir');
	if (!$webRootDir) return null;

	$parsedUrl = parse_url($url);
	$path = $parsedUrl['path'] ?? '';

	if (empty($path)) return null;

	return rtrim($webRootDir, '/') . $path;
}

/**
 * Check internal link
 *
 * @param string $url URL to check
 * @return array Status result
 */
function linkChecker_checkInternalLink(string $url): array
{
	$settings = linkChecker_loadPluginSettings();
	$baseUrl = linkChecker_getBaseUrl();
	$fullUrl = linkChecker_makeAbsoluteUrl($url, $baseUrl);

	// Check if URL is in ignore list
	foreach ($settings['ignoredUrls'] as $ignored) {
		// Handle both string format (simple URL) and array format (with type/pattern/reason)
		if (is_string($ignored)) {
			// Simple string comparison - check against both relative and absolute URL
			if ($ignored === $url || $ignored === $fullUrl) {
				return ['status' => 'ok', 'httpCode' => 0, 'skipped' => true, 'reason' => 'Manually ignored'];
			}
		}
	}

	// Check if file exists (for static files)
	$path = linkChecker_urlToFilePath($fullUrl);
	if ($path && file_exists($path)) {
		return ['status' => 'ok', 'httpCode' => 200];
	}

	// HTTP request for dynamic pages
	return linkChecker_httpCheck($fullUrl);
}

/**
 * Check external link
 *
 * @param string $url URL to check
 * @return array Status result
 */
function linkChecker_checkExternalLink(string $url): array
{
	$settings = linkChecker_loadPluginSettings();

	// Parse URL components once for reuse
	$domain = parse_url($url, PHP_URL_HOST);
	$path = parse_url($url, PHP_URL_PATH);

	// Check if URL matches any ignored pattern
	foreach ($settings['ignoredUrls'] as $ignored) {
		// Handle both string format (simple URL) and array format (with type/pattern/reason)
		if (is_string($ignored)) {
			// Simple string comparison
			if ($ignored === $url) {
				return ['status' => 'ok', 'httpCode' => 0, 'skipped' => true, 'reason' => 'Manually ignored'];
			}
		} elseif (is_array($ignored)) {
			// Complex pattern matching
			if ($ignored['type'] === 'domain' && stripos($domain, $ignored['pattern']) !== false) {
				return ['status' => 'ok', 'httpCode' => 0, 'skipped' => true, 'reason' => $ignored['reason']];
			}
			if ($ignored['type'] === 'path' && stripos($path, $ignored['pattern']) !== false) {
				return ['status' => 'ok', 'httpCode' => 0, 'skipped' => true, 'reason' => $ignored['reason']];
			}
		}
	}

	$result = linkChecker_httpCheck($url);

	// Auto-add to ignore list if domain blocks bots
	if (linkChecker_shouldAutoIgnore($result['httpCode'], $domain)) {
		linkChecker_autoAddToIgnoreList($domain, $result['httpCode']);
		return ['status' => 'ok', 'httpCode' => $result['httpCode'], 'skipped' => true, 'reason' => 'Auto-ignored: blocks bots'];
	}

	return $result;
}

/**
 * Check if HTTP code should auto-ignore domain
 *
 * @param int $httpCode HTTP response code
 * @param string $domain Domain name
 * @return bool True if should auto-ignore
 */
function linkChecker_shouldAutoIgnore(int $httpCode, string $domain): bool
{
	// Common "block bots" response codes
	$botBlockCodes = [403, 406, 429, 999];
	return in_array($httpCode, $botBlockCodes);
}

/**
 * Auto-add domain to ignore list
 *
 * @param string $domain Domain to ignore
 * @param int $httpCode HTTP code that triggered ignore
 */
function linkChecker_autoAddToIgnoreList(string $domain, int $httpCode): void
{
	$settings = linkChecker_loadPluginSettings();

	// Check if already in list
	foreach ($settings['ignoredUrls'] as $ignored) {
		if (is_string($ignored)) {
			// Simple string - check if it contains the domain
			if (stripos($ignored, $domain) !== false) {
				return; // Already ignored
			}
		} elseif (is_array($ignored)) {
			// Complex pattern - check type and pattern
			if ($ignored['type'] === 'domain' && stripos($domain, $ignored['pattern']) !== false) {
				return; // Already ignored
			}
		}
	}

	// Determine reason based on code
	$reasons = [
		403 => 'Blocks bot requests (403 Forbidden)',
		406 => 'Blocks bot requests (406 Not Acceptable)',
		429 => 'Rate limiting bots (429 Too Many Requests)',
		999 => 'Blocks bot requests (LinkedIn 999)',
	];
	$reason = $reasons[$httpCode] ?? "Blocks bot requests (HTTP {$httpCode})";

	// Add to ignore list (as array format for auto-added entries to preserve metadata)
	$settings['ignoredUrls'][] = [
		'pattern' => $domain,
		'type' => 'domain',
		'reason' => $reason,
		'addedDate' => date('Y-m-d'),
		'addedBy' => 'auto'
	];

	linkChecker_savePluginSettings($settings);
}

/**
 * Make HTTP request to check URL
 *
 * @param string $url URL to check
 * @return array Status result with success, httpCode, error, redirectUrl
 */
function linkChecker_httpCheck(string $url): array
{
	$settings = linkChecker_loadPluginSettings();

	// Try HEAD request first (faster)
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_NOBODY => true,  // HEAD request only
		CURLOPT_TIMEOUT => $settings['requestTimeout'],
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_FOLLOWLOCATION => false,  // Don't auto-follow redirects
		CURLOPT_USERAGENT => $settings['userAgent'],
		CURLOPT_SSL_VERIFYPEER => true,
	]);

	curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
	$error = curl_error($ch);
	curl_close($ch);

	// If HEAD request returned 405 (Method Not Allowed), retry with GET
	if ($httpCode === 405) {
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_NOBODY => false,  // GET request
			CURLOPT_TIMEOUT => $settings['requestTimeout'],
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_USERAGENT => $settings['userAgent'],
			CURLOPT_SSL_VERIFYPEER => true,
		]);

		curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
		$error = curl_error($ch);
		curl_close($ch);
	}

	if ($error) {
		if (strpos($error, 'timed out') !== false) {
			return ['status' => 'timeout', 'httpCode' => 0, 'error' => $error];
		}
		return ['status' => 'broken', 'httpCode' => 0, 'error' => $error];
	}

	if ($httpCode >= 200 && $httpCode < 300) {
		return ['status' => 'ok', 'httpCode' => $httpCode];
	}

	// 301/302 redirects are warnings, not errors
	if ($httpCode >= 300 && $httpCode < 400) {
		return ['status' => 'warning', 'httpCode' => $httpCode, 'redirectUrl' => $redirectUrl];
	}

	return ['status' => 'broken', 'httpCode' => $httpCode];
}

/**
 * Check email link
 *
 * @param string $url mailto: URL
 * @return array Status result
 */
function linkChecker_checkEmailLink(string $url): array
{
	// Extract email from mailto:
	$email = preg_replace('/^mailto:/i', '', $url);
	$email = preg_replace('/\?.*$/', '', $email);  // Remove query params

	if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return ['status' => 'ok', 'httpCode' => 0];
	}

	return ['status' => 'invalid', 'httpCode' => 0, 'error' => 'Invalid email format'];
}

/**
 * Check phone link
 *
 * @param string $url tel: URL
 * @return array Status result
 */
function linkChecker_checkPhoneLink(string $url): array
{
	// Extract phone from tel:
	$phone = preg_replace('/^tel:/i', '', $url);

	// Remove common formatting characters
	$digits = preg_replace('/[^0-9+]/', '', $phone);

	// Should have at least 10 digits (US) or start with + for international
	if (strlen($digits) >= 10 || preg_match('/^\+[0-9]{10,}$/', $digits)) {
		return ['status' => 'ok', 'httpCode' => 0];
	}

	return ['status' => 'invalid', 'httpCode' => 0, 'error' => 'Invalid phone format'];
}

/**
 * Check link based on type
 *
 * @param string $url URL to check
 * @param string $type Link type
 * @return array Status result
 */
function linkChecker_checkLink(string $url, string $type): array
{
	switch ($type) {
		case 'internal':
			return linkChecker_checkInternalLink($url);
		case 'external':
			return linkChecker_checkExternalLink($url);
		case 'image':
			return linkChecker_isInternalUrl($url) ? linkChecker_checkInternalLink($url) : linkChecker_checkExternalLink($url);
		case 'email':
			return linkChecker_checkEmailLink($url);
		case 'phone':
			return linkChecker_checkPhoneLink($url);
		default:
			return ['status' => 'broken', 'httpCode' => 0, 'error' => 'Unknown link type'];
	}
}

/**
 * Check if link type should be checked based on settings
 *
 * @param string $linkType Link type
 * @param array $settings Plugin settings
 * @return bool True if should check
 */
function linkChecker_shouldCheckLinkType(string $linkType, array $settings): bool
{
	$mapping = [
		'internal' => 'checkInternalLinks',
		'external' => 'checkExternalLinks',
		'image' => 'checkImages',
		'email' => 'checkEmailLinks',
		'phone' => 'checkPhoneLinks',
	];

	$settingKey = $mapping[$linkType] ?? null;
	if (!$settingKey) return false;

	return $settings[$settingKey] ?? true;
}

/**
 * Create database tables if they don't exist
 */
function linkChecker_createTablesIfNeeded(): void
{
	global $TABLE_PREFIX;

	// Check if results table exists
	$tableExists = false;
	$result = \mysqli()->query("SHOW TABLES LIKE '{$TABLE_PREFIX}_linkchecker_results'");
	if ($result && $result->num_rows > 0) {
		$tableExists = true;
	}

	if (!$tableExists) {
		$sql = "CREATE TABLE IF NOT EXISTS `{$TABLE_PREFIX}_linkchecker_results` (
			`num` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`createdDate` datetime DEFAULT NULL,
			`scanDate` datetime DEFAULT NULL,
			`tableName` varchar(255) DEFAULT NULL,
			`recordNum` int(10) unsigned DEFAULT NULL,
			`fieldName` varchar(255) DEFAULT NULL,
			`linkType` enum('internal','external','image','email','phone') DEFAULT NULL,
			`url` text,
			`status` enum('broken','warning','invalid','timeout','ok','ignored') DEFAULT NULL,
			`httpCode` int(5) DEFAULT NULL,
			`errorMessage` text,
			`redirectUrl` text,
			`fixed` tinyint(1) DEFAULT 0,
			`fixedDate` datetime DEFAULT NULL,
			`ignored` tinyint(1) DEFAULT 0,
			PRIMARY KEY (`num`),
			KEY `scanDate` (`scanDate`),
			KEY `tableName` (`tableName`),
			KEY `status` (`status`),
			KEY `fixed` (`fixed`),
			KEY `linkType` (`linkType`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		\mysqli()->query($sql);
	}

	// Check if scans table exists
	$tableExists = false;
	$result = \mysqli()->query("SHOW TABLES LIKE '{$TABLE_PREFIX}_linkchecker_scans'");
	if ($result && $result->num_rows > 0) {
		$tableExists = true;
	}

	if (!$tableExists) {
		$sql = "CREATE TABLE IF NOT EXISTS `{$TABLE_PREFIX}_linkchecker_scans` (
			`num` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`createdDate` datetime DEFAULT NULL,
			`scanType` enum('manual','scheduled') DEFAULT NULL,
			`startTime` datetime DEFAULT NULL,
			`endTime` datetime DEFAULT NULL,
			`tablesScanned` int(10) DEFAULT 0,
			`recordsScanned` int(10) DEFAULT 0,
			`linksChecked` int(10) DEFAULT 0,
			`brokenLinks` int(10) DEFAULT 0,
			`warnings` int(10) DEFAULT 0,
			`invalidLinks` int(10) DEFAULT 0,
			`timeouts` int(10) DEFAULT 0,
			`status` enum('running','completed','failed') DEFAULT 'running',
			`errorMessage` text,
			PRIMARY KEY (`num`),
			KEY `createdDate` (`createdDate`),
			KEY `status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		\mysqli()->query($sql);
	}
}

/**
 * Create scan record
 *
 * @param string $scanType Scan type (manual or scheduled)
 * @return int Scan ID
 */
function linkChecker_createScanRecord(string $scanType): int
{
	$data = [
		'createdDate=' => 'NOW()',
		'scanType' => $scanType,
		'startTime=' => 'NOW()',
		'status' => 'running',
	];

	return \mysql_insert('_linkchecker_scans', $data);
}

/**
 * Complete scan record
 *
 * @param int $scanId Scan ID
 * @param array $results Scan results
 */
function linkChecker_completeScanRecord(int $scanId, array $results): void
{
	$data = [
		'endTime=' => 'NOW()',
		'tablesScanned' => $results['tablesScanned'] ?? 0,
		'recordsScanned' => $results['recordsScanned'] ?? 0,
		'linksChecked' => $results['linksChecked'] ?? 0,
		'brokenLinks' => $results['broken'] ?? 0,
		'warnings' => $results['warnings'] ?? 0,
		'invalidLinks' => $results['invalid'] ?? 0,
		'timeouts' => $results['timeouts'] ?? 0,
		'status' => 'completed',
	];

	\mysql_update('_linkchecker_scans', $scanId, null, $data);
}

/**
 * Fail scan record
 *
 * @param int $scanId Scan ID
 * @param string $errorMessage Error message
 */
function linkChecker_failScanRecord(int $scanId, string $errorMessage): void
{
	$data = [
		'endTime=' => 'NOW()',
		'status' => 'failed',
		'errorMessage' => $errorMessage,
	];

	\mysql_update('_linkchecker_scans', $scanId, null, $data);
}

/**
 * Log link result
 *
 * @param int $scanId Scan ID
 * @param string $tableName Table name
 * @param int $recordNum Record number
 * @param string $fieldName Field name
 * @param array $link Link info
 * @param array $checkResult Check result
 */
function linkChecker_logLinkResult(int $scanId, string $tableName, int $recordNum, string $fieldName, array $link, array $checkResult): void
{
	// Don't log links that were skipped (ignored or in ignore list)
	if (!empty($checkResult['skipped'])) {
		return;
	}

	// Get scan date from scan record
	$scan = \mysql_get('_linkchecker_scans', $scanId);
	$scanDate = $scan['startTime'] ?? date('Y-m-d H:i:s');

	$data = [
		'createdDate=' => 'NOW()',
		'scanDate' => $scanDate,
		'tableName' => $tableName,
		'recordNum' => $recordNum,
		'fieldName' => $fieldName,
		'linkType' => $link['type'],
		'url' => $link['url'],
		'status' => $checkResult['status'],
		'httpCode' => $checkResult['httpCode'] ?? 0,
		'errorMessage' => $checkResult['error'] ?? '',
		'redirectUrl' => $checkResult['redirectUrl'] ?? '',
		'fixed' => 0,
		'ignored' => 0,
	];

	\mysql_insert('_linkchecker_results', $data);
}

/**
 * Perform scan
 *
 * @param array $tables Tables to scan (empty = all)
 * @param int $scanId Scan ID
 * @return array Scan results
 */
function linkChecker_performScan(array $tables, int $scanId): array
{
	$settings = linkChecker_loadPluginSettings();
	$results = [
		'tablesScanned' => 0,
		'recordsScanned' => 0,
		'linksChecked' => 0,
		'broken' => 0,
		'warnings' => 0,
		'invalid' => 0,
		'timeouts' => 0,
	];

	// Get tables to scan
	if (empty($tables)) {
		$tables = empty($settings['enabledTables']) ? linkChecker_getContentTablesWithTextFields() : $settings['enabledTables'];
	}

	foreach ($tables as $tableName) {
		$schema = \loadSchema($tableName);
		$textFields = linkChecker_getTextFieldsFromSchema($schema);

		if (empty($textFields)) continue;

		$results['tablesScanned']++;
		$records = \mysql_select($tableName, "1=1");

		foreach ($records as $record) {
			$results['recordsScanned']++;

			foreach ($textFields as $fieldName) {
				$content = $record[$fieldName] ?? '';
				if (empty($content)) continue;

				$links = linkChecker_extractLinks($content, $schema[$fieldName]['type']);

				foreach ($links as $link) {
					// Skip if link type not enabled
					if (!linkChecker_shouldCheckLinkType($link['type'], $settings)) continue;

					$checkResult = linkChecker_checkLink($link['url'], $link['type']);
					$results['linksChecked']++;

					// Only log problems (broken, warnings, invalid, timeout)
					if ($checkResult['status'] !== 'ok') {
						linkChecker_logLinkResult($scanId, $tableName, $record['num'], $fieldName, $link, $checkResult);

						// Map status to result counter
						$statusKey = match($checkResult['status']) {
							'broken' => 'broken',
							'warning' => 'warnings',
							'invalid' => 'invalid',
							'timeout' => 'timeouts',
							default => null
						};
						if ($statusKey) $results[$statusKey]++;
					}
				}
			}
		}
	}

	// Update settings with last scan info
	$settings['lastScanDate'] = date('Y-m-d H:i:s');
	$settings['lastScanResults'] = $results;
	linkChecker_savePluginSettings($settings);

	return $results;
}

/**
 * Run manual scan
 *
 * @param array $selectedTables Tables to scan
 * @return array Scan results
 */
function linkChecker_runManualScan(array $selectedTables = []): array
{
	$scanId = linkChecker_createScanRecord('manual');

	try {
		$results = linkChecker_performScan($selectedTables, $scanId);
		linkChecker_completeScanRecord($scanId, $results);
		return $results;
	} catch (\Exception $e) {
		linkChecker_failScanRecord($scanId, $e->getMessage());
		throw $e;
	}
}

/**
 * Run scheduled scan
 *
 * @return array Scan results
 */
function linkChecker_runScheduledScan(): array
{
	$scanId = linkChecker_createScanRecord('scheduled');

	try {
		$results = linkChecker_performScan([], $scanId);
		linkChecker_completeScanRecord($scanId, $results);
		return $results;
	} catch (\Exception $e) {
		linkChecker_failScanRecord($scanId, $e->getMessage());
		throw $e;
	}
}

/**
 * Send notification email
 *
 * @param array $results Scan results
 */
function linkChecker_sendNotificationEmail(array $results): void
{
	$settings = linkChecker_loadPluginSettings();

	if (!$settings['emailNotifications']) return;

	// Only email if problems found (when setting enabled)
	// Warnings (redirects) don't count as "problems" for notification purposes
	$hasProblems = ($results['broken'] + $results['invalid'] + $results['timeouts']) > 0;
	if ($settings['emailOnlyOnProblems'] && !$hasProblems) return;

	$to = $settings['notificationEmail'] ?: getAdminEmail();
	$domain = $_SERVER['HTTP_HOST'];
	$subject = "Link Checker Report: {$domain}";

	// Build email body
	$body = "Link Checker Scan Results for {$domain}\n";
	$body .= "Scan Date: " . date('Y-m-d H:i:s') . "\n\n";
	$body .= "Summary:\n";
	$body .= "- Links Checked: {$results['linksChecked']}\n";
	$body .= "- Broken Links: {$results['broken']}\n";
	$body .= "- Warnings (Redirects): {$results['warnings']}\n";
	$body .= "- Invalid Format: {$results['invalid']}\n";
	$body .= "- Timeouts: {$results['timeouts']}\n\n";

	if ($hasProblems) {
		$body .= "Action Required: Log into the CMS to view and fix broken links.\n";
		$adminUrl = \settings('adminUrl') ?? '/cmsb/';
		$body .= "Dashboard: https://{$domain}{$adminUrl}?_pluginAction=linkChecker_adminDashboard\n";
	}

	mail($to, $subject, $body);
}

/**
 * Get admin email
 *
 * @return string Admin email address
 */
function linkChecker_getAdminEmail(): string
{
	global $SETTINGS;
	return $SETTINGS['adminEmail'] ?? 'admin@' . $_SERVER['HTTP_HOST'];
}

/**
 * Get edit link for record
 *
 * @param string $tableName Table name
 * @param int $recordNum Record number
 * @return string Edit URL
 */
function linkChecker_getEditLinkForRecord(string $tableName, int $recordNum): string
{
	$adminUrl = \settings('adminUrl') ?? '/cmsb/';
	return "{$adminUrl}?menu={$tableName}&action=edit&num={$recordNum}";
}

/**
 * Get last scan info
 *
 * @return array|null Last scan info or null
 */
function linkChecker_getLastScanInfo(): ?array
{
	$scans = \mysql_select('_linkchecker_scans', "1=1 ORDER BY `createdDate` DESC LIMIT 1");
	return $scans[0] ?? null;
}

/**
 * Get scan statistics
 *
 * @return array Statistics array
 */
function linkChecker_getScanStats(): array
{
	global $TABLE_PREFIX;

	$stats = [
		'total' => 0,
		'broken' => 0,
		'warnings' => 0,
		'invalid' => 0,
		'timeouts' => 0,
		'ignored' => 0,
		'fixed' => 0,
	];

	// Get current unfixed, unignored issues
	$stats['broken'] = (int)\mysql_count('_linkchecker_results', "`status` = 'broken' AND `fixed` = 0 AND `ignored` = 0");
	$stats['warnings'] = (int)\mysql_count('_linkchecker_results', "`status` = 'warning' AND `fixed` = 0 AND `ignored` = 0");
	$stats['invalid'] = (int)\mysql_count('_linkchecker_results', "`status` = 'invalid' AND `fixed` = 0 AND `ignored` = 0");
	$stats['timeouts'] = (int)\mysql_count('_linkchecker_results', "`status` = 'timeout' AND `fixed` = 0 AND `ignored` = 0");
	$stats['ignored'] = (int)\mysql_count('_linkchecker_results', "`ignored` = 1");
	$stats['fixed'] = (int)\mysql_count('_linkchecker_results', "`fixed` = 1");
	$stats['total'] = $stats['broken'] + $stats['warnings'] + $stats['invalid'] + $stats['timeouts'];

	return $stats;
}

/**
 * Get recent broken links
 *
 * @param int $limit Number of entries to retrieve
 * @return array Recent broken links
 */
function linkChecker_getRecentBrokenLinks(int $limit = 10): array
{
	return \mysql_select('_linkchecker_results', "`fixed` = 0 AND `ignored` = 0 ORDER BY `createdDate` DESC LIMIT {$limit}");
}

/**
 * Run link check (wrapper for runManualScan)
 *
 * @param string $scanType Scan type (full, quick, selected)
 * @param array $selectedTables Selected tables
 * @return array Scan results
 */
function linkChecker_runLinkCheck(string $scanType = 'full', array $selectedTables = []): array
{
	return linkChecker_runManualScan($selectedTables);
}

/**
 * Cleanup old scans
 */
function linkChecker_cleanupOldScans(): void
{
	global $TABLE_PREFIX;

	$settings = linkChecker_loadPluginSettings();
	$retentionDays = $settings['logRetentionDays'];
	if ($retentionDays <= 0) {
		return; // Retention disabled
	}

	$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

	// Clean up scans table
	$query = "DELETE FROM `{$TABLE_PREFIX}_linkchecker_scans` WHERE `createdDate` < ?";
	\mysqli()->query(\mysql_escapef($query, $cutoffDate));
}

/**
 * Get all content tables (for settings page)
 *
 * @return array List of table names with info
 */
function linkChecker_getAllTables(): array
{
	$tableNames = \getSchemaTables();
	$tables = [];

	foreach ($tableNames as $tableName) {
		// Skip system tables (starting with _)
		if (str_starts_with($tableName, '_')) {
			continue;
		}

		// Skip permanently ignored tables
		if (linkChecker_shouldIgnoreTable($tableName)) {
			continue;
		}

		$schema = \loadSchema($tableName);
		$tables[] = [
			'name' => $tableName,
			'menuName' => $schema['menuName'] ?? $tableName,
			'menuType' => $schema['menuType'] ?? 'multi',
		];
	}

	return $tables;
}

/**
 * Get ignored URLs list
 *
 * @return array Ignored URLs
 */
function linkChecker_getIgnoredUrls(): array
{
	$settings = linkChecker_loadPluginSettings();
	return $settings['ignoredUrls'] ?? [];
}

/**
 * Add URL to ignore list
 *
 * @param string $pattern URL pattern to ignore
 * @param string $type Type (domain or path)
 * @param string $reason Reason for ignoring
 * @return bool True on success
 */
function linkChecker_addIgnoredUrl(string $pattern, string $type, string $reason): bool
{
	$settings = linkChecker_loadPluginSettings();

	// Check if already exists
	foreach ($settings['ignoredUrls'] as $ignored) {
		if ($ignored['pattern'] === $pattern && $ignored['type'] === $type) {
			return false; // Already exists
		}
	}

	$settings['ignoredUrls'][] = [
		'pattern' => $pattern,
		'type' => $type,
		'reason' => $reason,
		'addedDate' => date('Y-m-d'),
		'addedBy' => 'manual'
	];

	return linkChecker_savePluginSettings($settings);
}

/**
 * Delete URL from ignore list
 *
 * @param int $index Index in ignore list
 * @return bool True on success
 */
function linkChecker_deleteIgnoredUrl(int $index): bool
{
	$settings = linkChecker_loadPluginSettings();

	if (!isset($settings['ignoredUrls'][$index])) {
		return false;
	}

	array_splice($settings['ignoredUrls'], $index, 1);

	return linkChecker_savePluginSettings($settings);
}

/**
 * Mark links as fixed
 *
 * @param array $linkIds Array of link result IDs
 * @return int Number of links marked
 */
function linkChecker_markLinksAsFixed(array $linkIds): int
{
	$count = 0;
	foreach ($linkIds as $linkId) {
		$data = [
			'fixed' => 1,
			'fixedDate=' => 'NOW()',
		];
		\mysql_update('_linkchecker_results', intval($linkId), null, $data);
		$count++;
	}
	return $count;
}

/**
 * Mark links as ignored
 *
 * @param array $linkIds Array of link result IDs
 * @return int Number of links marked
 */
function linkChecker_markLinksAsIgnored(array $linkIds): int
{
	global $TABLE_PREFIX;

	if (empty($linkIds)) {
		return 0;
	}

	// Get URLs from these link IDs
	$settings = linkChecker_loadPluginSettings();
	$ignoredUrls = $settings['ignoredUrls'] ?? [];

	$count = 0;
	foreach ($linkIds as $linkId) {
		$result = \mysql_get('_linkchecker_results', intval($linkId));
		if (!$result) {
			continue;
		}

		// Add URL to ignore list if not already there
		$url = $result['url'];
		if (!in_array($url, $ignoredUrls)) {
			$ignoredUrls[] = $url;
		}

		// Mark as ignored in database
		$data = [
			'ignored' => 1,
			'status' => 'ignored',
		];
		\mysql_update('_linkchecker_results', intval($linkId), null, $data);
		$count++;
	}

	// Save updated ignore list
	if ($count > 0) {
		$settings['ignoredUrls'] = $ignoredUrls;
		linkChecker_savePluginSettings($settings);
	}

	return $count;
}

/**
 * Recheck links
 *
 * @param array $linkIds Array of link result IDs
 * @return int Number of links rechecked
 */
function linkChecker_recheckLinks(array $linkIds): int
{
	$count = 0;
	foreach ($linkIds as $linkId) {
		$result = \mysql_get('_linkchecker_results', intval($linkId));
		if (!$result) continue;

		// Skip if already marked as ignored - don't recheck ignored links
		if ($result['ignored'] == 1 || $result['status'] === 'ignored') {
			continue;
		}

		// Check if the link still exists in the source record
		$linkStillExists = false;
		if ($result['tableName'] && $result['recordNum']) {
			$record = \mysql_get($result['tableName'], intval($result['recordNum']));
			if ($record && $result['fieldName']) {
				$fieldContent = $record[$result['fieldName']] ?? '';
				// Check if the URL still appears in the field content
				$linkStillExists = (stripos($fieldContent, $result['url']) !== false);
			}
		}

		// If link no longer exists in source content, mark as fixed (removed/replaced)
		if (!$linkStillExists) {
			$data = [
				'status' => 'ok',
				'fixed' => 1,
				'fixedDate=' => 'NOW()',
				'scanDate=' => 'NOW()',
				'errorMessage' => 'Link removed or replaced in content',
			];
			\mysql_update('_linkchecker_results', intval($linkId), null, $data);
			$count++;
			continue;
		}

		// Link still exists, so recheck it
		$checkResult = linkChecker_checkLink($result['url'], $result['linkType']);

		// Update the result
		$data = [
			'status' => $checkResult['status'],
			'httpCode' => $checkResult['httpCode'] ?? 0,
			'errorMessage' => $checkResult['error'] ?? '',
			'redirectUrl' => $checkResult['redirectUrl'] ?? '',
			'scanDate=' => 'NOW()',
		];

		// If link is now OK, mark as fixed
		if ($checkResult['status'] === 'ok') {
			$data['fixed'] = 1;
			$data['fixedDate='] = 'NOW()';
			// Clear ignored flag if it was previously ignored but now works
			$data['ignored'] = 0;
		}

		\mysql_update('_linkchecker_results', intval($linkId), null, $data);
		$count++;
	}
	return $count;
}

/**
 * Get enabled tables from settings
 *
 * @return array List of enabled table names
 */
function linkChecker_getEnabledTables(): array
{
	$settings = linkChecker_loadPluginSettings();
	$enabledTables = $settings['enabledTables'] ?? [];

	// If no tables enabled, return all content tables with text fields
	if (empty($enabledTables)) {
		return linkChecker_getContentTablesWithTextFields();
	}

	return $enabledTables;
}

/**
 * Get content tables (wrapper for backward compatibility)
 *
 * @return array List of table names
 */
function linkChecker_getContentTables(): array
{
	$tableNames = \getSchemaTables();
	$contentTables = [];

	foreach ($tableNames as $tableName) {
		// Skip system tables (starting with _)
		if (str_starts_with($tableName, '_')) {
			continue;
		}

		// Skip permanently ignored tables
		if (linkChecker_shouldIgnoreTable($tableName)) {
			continue;
		}

		$contentTables[] = $tableName;
	}

	sort($contentTables);
	return $contentTables;
}

/**
 * Clear all link check results and scan history from the database
 *
 * This removes all entries from both the results and scans tables, providing
 * a completely fresh start. Also resets the last scan status in settings.
 * Useful for starting fresh after configuration changes or bulk updates.
 *
 * @return int Number of results cleared
 */
function linkChecker_clearAllResults(): int
{
	global $TABLE_PREFIX;

	// Get count before deleting
	$result = \mysqli()->query("SELECT COUNT(*) as count FROM `{$TABLE_PREFIX}_linkchecker_results`");
	$count = 0;
	if ($result) {
		$row = $result->fetch_assoc();
		$count = intval($row['count']);
	}

	// Delete all results
	\mysqli()->query("TRUNCATE TABLE `{$TABLE_PREFIX}_linkchecker_results`");

	// Also clear scan history since there are no results
	\mysqli()->query("TRUNCATE TABLE `{$TABLE_PREFIX}_linkchecker_scans`");

	// Reset last scan status in settings
	$settings = linkChecker_loadPluginSettings();
	$settings['lastScanDate'] = null;
	$settings['lastScanResults'] = [];
	linkChecker_savePluginSettings($settings);

	return $count;
}
