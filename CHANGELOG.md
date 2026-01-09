# Link Checker Plugin - Changelog

\*\*\* January 8, 2026 - Version 1.00 (Initial Release)

REQUIREMENTS: PHP 8.0+ and CMS Builder 3.78+

INITIAL FEATURES

-   Automatic detection of broken internal and external links
-   Image link validation (internal images)
-   Email link validation (mailto: format checking)
-   Phone link validation (tel: format checking)
-   Smart auto-ignore system for domains that block bots (403, 999 status codes)
-   Manual ignore list management (URLs and domains)
-   Scheduled scanning system (daily, weekly, monthly)
-   Email notifications with configurable options
-   Comprehensive admin dashboard with statistics
-   Scan results page with filtering and sorting
-   Direct "Edit Record" links for quick fixes
-   Mark as Fixed / Mark as Ignored functionality
-   Scan history tracking
-   Settings page with full configuration options
-   Help page with comprehensive documentation

ADMIN PAGES

-   Dashboard - Overview and quick actions
-   Run Scan - Manual scan execution
-   Scan Results - Detailed results with filtering
-   Settings - Full configuration interface
-   Scan History - Audit trail of scans
-   Help - Comprehensive documentation

SCAN OPTIONS

-   Configurable link types (internal, external, images, email, phone)
-   Table selection (all or specific tables)
-   Field type support (wysiwyg, textbox, textfield)
-   Pattern extraction for HTML links, images, and background images
-   Automatic table filtering to exclude system/utility tables

STATUS TYPES

-   `broken` - 404, 500, connection failed (requires fixing)
-   `warning` - 301/302 redirects (consider updating)
-   `invalid` - Malformed mailto: or tel: format (requires fixing)
-   `timeout` - Request timed out (investigate)
-   `ok` - Link working (not logged to save space)

FOR PROGRAMMERS

-   Namespace: LinkChecker
-   PSR-12 code standards
-   cURL-based HTTP checking with HEAD requests for performance
-   Configurable request timeout (default: 10s)
-   Log retention management (default: 90 days)
-   Automatic cleanup via daily cron
-   Cron jobs registered via addCronJob():
    -   "Link Checker - Scheduled Scan" (0 0 \* \* \*)
    -   "Link Checker - Cleanup Old Results" (0 1 \* \* \*)
-   Requires server cron configured to run /cmsb/cron.php

UI/ACCESSIBILITY

-   Bootstrap 3/4 compatible UI
-   FontAwesome 7+ icon support
-   WCAG 2.1 AA accessibility compliance
-   Multi-language support via t() function

---
