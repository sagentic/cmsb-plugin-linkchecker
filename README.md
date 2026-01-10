# Link Checker Plugin for CMS Builder

> **Note:** This plugin only works with CMS Builder, available for download at https://www.interactivetools.com/download/

Automatically scan database content fields for broken links, missing images, and malformed contact links. Helps webmasters identify and fix link issues before they impact SEO or user experience.

## Features

-   **Comprehensive Link Checking**: Scans internal links, external links, images, email addresses (mailto:), and phone numbers (tel:)
-   **Smart Auto-Ignore**: Automatically ignores domains that block bot requests (403, 999 status codes)
-   **Default Ignore List**: Pre-configured with 9 common domains (LinkedIn, Facebook, Google, etc.)
-   **Scheduled Scanning**: Configurable automatic scans (daily, weekly, monthly)
-   **Email Notifications**: Get notified when broken links are found
-   **Detailed Reports**: Track broken links, redirects, invalid formats, and timeouts
-   **Direct Edit Links**: Click to edit the record containing broken links (opens in new tab)
-   **Bulk Actions**: Mark multiple links as fixed, ignored, or recheck them all at once
-   **Single-Link Actions**: Quick recheck and ignore buttons on individual links
-   **Smart Recheck**: Automatically detects removed links and marks them as fixed
-   **Preserved Status**: Ignored links stay ignored during rescans and rechecks
-   **Advanced Actions Menu**: Quick access to scans and cleanup on all pages
-   **Clear All Results**: Complete reset feature with confirmation
-   **Ignore List Management**: Manually ignore specific URLs or domains
-   **Flexible Settings**: Choose which tables and link types to check
-   **Scan History**: Complete audit trail of all scan activities

## Installation

1.  Copy the `linkChecker` folder to your plugins directory:

        - `/cmsb/plugins/linkChecker/` (standard CMSB installation)

2.  Ensure PHP files have proper permissions (readable by web server):

        ```bash
        chmod 644 /path/to/plugins/linkChecker/*.php
        ```

3.  Log into the CMS admin area

4.  The plugin will automatically:

        - Create the database tables for results and scan history
        - Initialize default settings

5.  Verify installation by visiting **Plugins > Link Checker > Dashboard**

6.  Go to **Plugins > Link Checker > Settings** to configure scan options

## Configuration

All settings are configured through the admin interface at **Plugins > Link Checker > Settings**.

### Settings Reference

| Setting                | Description                                   | Default              |
| ---------------------- | --------------------------------------------- | -------------------- |
| Enabled Tables         | Tables to scan (empty = all with text fields) | [] (all)             |
| Check Internal Links   | Scan internal page links                      | Enabled              |
| Check External Links   | Scan external URLs                            | Enabled              |
| Check Images           | Scan image src attributes                     | Enabled              |
| Check Email Links      | Validate mailto: format                       | Enabled              |
| Check Phone Links      | Validate tel: format                          | Enabled              |
| Scheduled Scan         | Enable automatic scanning                     | Enabled              |
| Scan Frequency         | How often to scan (daily/weekly/monthly)      | Daily                |
| Email Notifications    | Send email reports                            | Enabled              |
| Email Only On Problems | Only email when issues found                  | Enabled              |
| Notification Email     | Email address (falls back to admin email)     | (admin email)        |
| Request Timeout        | HTTP timeout in seconds                       | 10                   |
| User Agent             | User agent for requests                       | CMSB-LinkChecker/1.0 |
| Ignored URLs           | URLs/domains to skip with reasons             | []                   |
| Log Retention Days     | Days to keep scan results                     | 90                   |

### Link Status Types

The plugin reports four types of issues:

| Status    | Description                             | Action Required |
| --------- | --------------------------------------- | --------------- |
| `broken`  | 404, 500, connection failed             | Fix immediately |
| `warning` | 301/302 redirects                       | Consider update |
| `invalid` | Malformed mailto: or tel: format        | Fix immediately |
| `timeout` | Request timed out                       | Investigate     |
| `ok`      | Link is working (not stored in results) | None            |

### Ignore List

The plugin maintains a smart ignore list with two formats:

#### Default Ignore List (Pre-configured)

The plugin comes with 9 domains that commonly block automated checkers:

-   **linkedin.com** - Blocks automated requests
-   **facebook.com** - Blocks automated requests
-   **instagram.com** - Blocks automated requests
-   **twitter.com** - Blocks automated requests
-   **x.com** - Blocks automated requests
-   **youtube.com** - Blocks automated requests
-   **amazon.com** - Blocks HEAD requests
-   **google** - All Google domains (google.com, blog.google, domains.google.com, etc.)
-   **canva.com** - Blocks automated requests

These defaults prevent false positives on social media and other bot-blocking sites.

#### Ignore List Behavior

-   **Auto-Ignore**: Domains returning 403, 406, 429, or 999 are automatically added during scans
-   **Manual Ignore**: Add specific URLs via Settings page or by marking individual links as ignored
-   **Pattern Types**: - `domain` - Match entire domain (e.g., "facebook.com") - `path` - Match URL path (e.g., "/old-page/")
-   **Dual Format Support**: - Array format (with metadata): `{"pattern": "linkedin.com", "type": "domain", "reason": "...", "addedBy": "auto"}` - String format (manual URLs): Simple URL strings for manually ignored links

#### Example ignore list structure:

```json
{
	"ignoredUrls": [
		{
			"pattern": "linkedin.com",
			"type": "domain",
			"reason": "Blocks bot requests",
			"addedBy": "default"
		},
		{
			"pattern": "google",
			"type": "domain",
			"reason": "All Google domains block automated checkers",
			"addedBy": "default"
		},
		"https://example.com/specific-page-to-ignore",
		"/articles/old-article-with-redirect/"
	]
}
```

## Usage

### Dashboard

View scan statistics, last scan date, and recent broken links. Quick access to:

-   Run Full Scan Now
-   View All Results
-   Configure Settings

**Recent Issues Table** includes action buttons for each link:

-   **Edit** (blue button): Opens record in new tab for editing the content
-   **Recheck** (default button): Re-validates the specific link immediately
-   **Ignore** (warning button): Marks link as ignored and adds to ignore list

### Run Scan

Manually execute scans:

-   **Full Scan**: Check all enabled tables
-   **Quick Scan**: Check only tables modified recently
-   **Selected Tables**: Choose specific tables to scan

### Scan Results

View and manage all detected issues:

-   Filter by: status, link type, table, date range
-   Sort by any column
-   **Bulk Actions**: Select multiple links and apply actions: - Mark as Fixed - Mark as Ignored - Recheck Selected
-   **Individual Actions**: Each link has Edit, Recheck, and Ignore buttons
-   Direct "Edit Record" links open in new tabs
-   Export to CSV (planned)

**Note**: Ignored links are automatically skipped during future scans and bulk recheck operations.

### Advanced Actions Menu

Available on all pages (Dashboard, Results, Settings, Run Scan, Scan History, Ignored URLs):

-   **Quick Scan**: Run immediate scan without navigating to Run Scan page
-   **Clear Old Scans**: Remove old scan history based on retention settings
-   **Clear All Results**: Complete reset - removes all results and scan history (requires confirmation)

### Settings

Configure all plugin options including:

-   What to check (internal/external/images/emails/phones)
-   When to check (schedule and frequency)
-   Who to notify (email settings)
-   What to ignore (ignore list management)
-   Which tables to scan

### Scan History

View complete audit trail of all scans with:

-   Date and time
-   Scan type (manual or scheduled)
-   Summary statistics
-   Duration

## How It Works

### Automatic Table Filtering

The plugin automatically excludes these tables from scanning:

-   **System tables**: All tables starting with `_`
-   **Common utility tables**: `accounts`, `uploads`, `convert_to_webp`, `indexnow`, `indexnow_log`, `linkchecker_results`, `linkchecker_scans`
-   **Menu groups**: Any table with `_menugroup` or `menugroup` in the name, or with `menuType = 'menugroup'`

This ensures only content tables with actual links are scanned, improving performance and avoiding false positives.

### Link Extraction

The plugin scans these field types:

-   `wysiwyg` - Full HTML content
-   `textbox` - Plain text that may contain URLs
-   `textfield` - Single line text

It extracts:

-   HTML links: `<a href="...">`
-   Images: `<img src="...">`
-   Background images: `style="background-image: url(...)"`
-   Email links: `mailto:email@example.com`
-   Phone links: `tel:+1-555-1234`

### Link Validation

**Internal Links:**

1. Check against ignore list
2. Check if file exists (for static files)
3. Make HTTP request for dynamic pages

**External Links:**

1. Check against ignore list (both manual URLs and domain patterns)
2. Make HTTP HEAD request (fallback to GET on 405 errors)
3. Auto-ignore if domain blocks bots (403, 406, 429, 999)

**Email Validation:**

-   Extract email from `mailto:` URL
-   Validate format using PHP `filter_var()`

**Phone Validation:**

-   Extract digits from `tel:` URL
-   Validate minimum 10 digits or international format

**Recheck Intelligence:**

-   Before rechecking, verifies link still exists in source content
-   If link removed/replaced, automatically marks as fixed
-   Preserves ignored status - ignored links are skipped during recheck
-   Updates scan date and HTTP codes for rechecked links

### Scheduled Scanning

The plugin registers two cron jobs with CMSB's cron system:

1.  **Link Checker - Scheduled Scan** (runs daily at midnight)

        - Checks if a scan is due based on your frequency setting (daily/weekly/monthly)
        - Only scans if enough time has passed since last scan
        - Sends email notification after completion

2.  **Link Checker - Cleanup Old Results** (runs daily at 1 AM) - Removes old scan results based on retention setting - Keeps database clean and performant

**Important:** CMSB's cron system requires a server cron job to be set up:

```bash
* * * * * /usr/local/bin/php /path/to/cmsb/cron.php yourdomain.com
```

To verify cron is working:

-   Visit **Admin > Background Tasks** in your CMS
-   Check the cron log for recent executions
-   Look for "Link Checker" entries

### Email Notifications

Email sent when:

-   Email notifications are enabled
-   Scan completes
-   Problems found (if "Only On Problems" enabled)

**Note:** Warnings (redirects) don't count as "problems" for email purposes.

Email includes:

-   Summary statistics
-   Direct link to dashboard
-   Date/time of scan

## HTTP Response Codes

| Code | Meaning                    | Plugin Action |
| ---- | -------------------------- | ------------- |
| 200  | OK                         | Success       |
| 301  | Moved Permanently          | Warning (log) |
| 302  | Found (temporary redirect) | Warning (log) |
| 403  | Forbidden                  | Auto-ignore   |
| 404  | Not Found                  | Broken (log)  |
| 406  | Not Acceptable             | Auto-ignore   |
| 429  | Too Many Requests          | Auto-ignore   |
| 500  | Internal Server Error      | Broken (log)  |
| 999  | LinkedIn bot block         | Auto-ignore   |
| 0    | Connection failed/timeout  | Timeout (log) |

## Requirements

-   CMS Builder 3.50 or higher
-   PHP 8.0 or higher
-   cURL extension enabled
-   Write access to plugin directory (for settings storage)
-   **Server cron job** configured to run `/cmsb/cron.php` (required for scheduled scanning)

## Troubleshooting

### No Links Found

-   Verify tables have `wysiwyg`, `textbox`, or `textfield` fields
-   Check that tables are enabled in Settings
-   Ensure content contains HTML links

### Too Many False Positives

-   Add domains to ignore list (Settings > Ignore List)
-   Plugin auto-ignores domains that block bots (403, 999)
-   Adjust request timeout if getting timeouts

### External Links Always Fail

-   Check server has outbound internet access
-   Verify cURL extension is enabled
-   Check firewall/security settings
-   Some sites block bot requests (will auto-ignore)

### Scans Taking Too Long

-   Reduce number of enabled tables
-   Disable external link checking temporarily
-   Increase request timeout
-   Use "Selected Tables" scan instead of full scan

### Settings Not Saving

-   Verify write permissions on plugin directory
-   Check that `linkChecker_settings.json` is writable
-   Look for PHP errors in server logs

### Scheduled Scans Not Running

-   Verify server cron job is configured: `* * * * * /usr/local/bin/php /path/to/cmsb/cron.php yourdomain.com`
-   Check **Admin > Background Tasks** to see if cron is running
-   Look for "Link Checker" entries in cron log
-   Ensure "Scheduled Scan" is enabled in plugin Settings
-   Check that your scan frequency setting is met (daily/weekly/monthly)
-   Manual scans always work - this only affects automatic scheduling

## Maintenance Tools

### Clear All Results

Via the admin interface (Advanced Actions > Clear All Results):

-   Removes all link check results from the database
-   Clears complete scan history
-   Resets last scan status in settings
-   Requires confirmation to prevent accidental deletion
-   Useful after major content updates or configuration changes
-   Provides a completely fresh start for testing

### Reset Installation

For troubleshooting or testing, you can completely reset the plugin installation:

```bash
cd /path/to/cmsb/plugins/linkChecker/
php reset_installation.php
```

This script will:

1. Drop existing `linkchecker_results` and `linkchecker_scans` tables
2. Backup current settings to `linkChecker_settings_backup_YYYY-MM-DD_HHMMSS.json`
3. Delete the current settings file
4. Create fresh settings with all defaults (including 9 default ignored domains)
5. Recreate both tables with clean schema

**Warning:** This deletes ALL scan data and history! Use only for troubleshooting or fresh starts.

**When to use:**

-   Plugin tables become corrupted
-   Testing fresh installation behavior
-   Resetting after development/testing phase
-   Starting over with new ignore list defaults

## Best Practices

**Recommended for Link Checking:**

-   Blog posts and articles
-   Product pages
-   Service descriptions
-   Landing pages
-   Any content-heavy sections

**Not Recommended:**

-   Upload galleries (unless you want to check captions)
-   Settings/configuration tables
-   Menu items (use separate menu checking)
-   Tables without rich content

**Scan Frequency:**

-   **Daily**: For active blogs or frequently updated content
-   **Weekly**: For most business websites
-   **Monthly**: For rarely updated static sites

**Ignore List Usage:**

-   Social media domains (often block bots)
-   Internal dev/staging URLs
-   Known redirects you can't fix
-   Third-party services with aggressive bot blocking

## File Structure

```
linkChecker/
├── linkChecker.php                     # Main plugin file, hooks registration
├── linkChecker_admin.php               # Admin UI pages (6 pages)
├── linkChecker_functions.php           # Helper functions (51 functions)
├── linkChecker_settings.json           # Settings storage (auto-created)
├── reset_installation.php              # Reset tool for fresh starts
├── LICENSE                             # MIT License
├── CHANGELOG.md                        # Version history
├── README.md                           # This file
└── REVIEW_SUMMARY.md                   # Code quality review (security/accessibility)
```

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Author

Sagentic Web Design
https://www.sagentic.com

## License

MIT License - See [LICENSE](LICENSE) file for details.
