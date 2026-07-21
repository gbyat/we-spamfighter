=== WE Spamfighterin ===
Contributors: webentwicklerin
Tags: spam, contact-form-7, comments, ai, openai, spam-protection, security, form-protection
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced spam protection for WordPress using AI-powered and heuristic detection.

== Description ==

Protects Contact Form 7 forms and WordPress comments from spam submissions with intelligent analysis. Works with or without OpenAI - includes local heuristic detection for cost-effective spam filtering.

= Multi-Layer Spam Detection =

* Heuristic Detection (Local, Free): Advanced local spam detection without external APIs
  * Link analysis (URL shorteners, suspicious domains, excessive links)
  * Character pattern detection (repeated characters, ALL CAPS, mixed case spam)
  * Known spam phrase detection (multi-language)
  * Email pattern analysis (suspicious providers, random patterns)
  * Referrer analysis (missing referrer, suspicious referrer domains, URL shorteners)
  * User agent analysis (bot detection, missing user agent, suspicious patterns)
  * Content length analysis (very short or extremely long content detection)
  * Mixed script detection (different character sets like Cyrillic + Latin)
  * Unicode anomalies (zero-width characters, control characters, homoglyphs)
  * Numbers/letters only detection (content containing only numbers or letters)
  * IP address in content (IP addresses found in text, not URLs)
* Language Detection (Local, Free): Automatically detects and flags submissions in different languages
  * Works without OpenAI using heuristic language detection
  * Configurable score boost for language mismatches
* AI-Powered Detection (Optional): OpenAI integration for advanced analysis
  * Uses GPT-4o-mini (or other OpenAI models) to analyze form submissions and comments
  * Detects spam patterns, AI-generated content, SEO spam, and suspicious links
  * Only called when needed (cost-efficient: local checks run first)
* Smart Detection Order: Heuristic -> Language -> OpenAI (saves API costs)
  * Local checks run first (instant, free)
  * OpenAI only called for uncertain cases
  * Plugin works completely without OpenAI

= Form Integration =

* Contact Form 7: Full integration with Contact Form 7 plugin
  * Submission Logging: Unlike CF7 (which doesn't store submissions by default), this plugin logs all CF7 form submissions for review and analysis
  * Spam Protection: Blocks spam submissions before they reach your inbox
* WordPress Comments: Native WordPress comment spam protection
* Automatic Blocking: Blocks spam submissions before they reach your inbox
* Custom Messages: Configurable "Thank you" message for blocked spam submissions (default: "Thank you for your message.")

= Dashboard & Analytics =

* Submission Log: View all Contact Form 7 submissions (comments are managed by WordPress)
  * Unique Feature: CF7 doesn't store submissions by default - this plugin adds submission logging as a bonus feature
* Spam Analytics: Track spam detection statistics for CF7 forms
* Comment Spam Stats: View spam comment count from WordPress (with link to comment management)
* Filtering: Filter submissions by spam status (via tabs)
* Submission Details: View full submission data, spam scores, and detection reasoning
* Activity Log (Optional): Track important plugin events and operations
  * View recent activities (weekly summaries, table maintenance, etc.)
  * Automatic cleanup (max 100 entries, respects log retention days)
  * Manual clear option
  * Only shows when enabled in settings
* Privacy / GDPR (OpenAI only): When OpenAI is active, DSGVO-compliant privacy measures
  * Configurable privacy passage on the privacy policy page (filter, manual shortcode/block, or none)
  * Optional form notice at comment and CF7 forms
  * Shortcode [we_spamfighter_privacy] for manual placement
  * Suggested text for the Privacy Policy Guide

== Installation ==

= Via WordPress Admin =

1. Download the plugin ZIP file
2. Go to Plugins -> Add New -> Upload Plugin
3. Upload the ZIP file
4. Click Activate Plugin

= Via FTP/File Manager =

1. Upload the we-spamfighter folder to /wp-content/plugins/
2. Go to Plugins in WordPress admin
3. Find WE Spamfighterin and click Activate

== Frequently Asked Questions ==

= Does this plugin work without OpenAI? =

Yes! The plugin works completely without OpenAI. It includes local heuristic detection that runs before any API calls, making it fully functional and cost-effective even without an OpenAI API key.

= Does the plugin store Contact Form 7 submissions? =

Yes! Unlike Contact Form 7 (which doesn't store submissions by default), this plugin logs all CF7 form submissions for review and analysis. This is a unique bonus feature.

= Where are spam comments stored? =

Spam comments are stored in WordPress's native comment system, not in the plugin database. You can manage them in Comments -> Spam in WordPress admin. The plugin dashboard shows the spam comment count with a link to WordPress comment management.

= How does the detection work? =

The plugin uses a smart detection order to save costs:
1. Heuristic Detection (local, free) - runs first
2. Language Detection (local, free) - checks language mismatches
3. OpenAI Detection (optional) - only called when needed

This ensures maximum spam detection while minimizing API costs.

= How do I update the plugin? =

You have two options:

1. Manual Updates (Recommended): Download the latest release from GitHub and install manually via WordPress admin
2. Automatic Updates (Optional): Enable "Enable GitHub Updates" in Settings -> Maintenance tab. WARNING: Automatic updates will install without additional confirmation. Enable at your own risk.

Automatic GitHub updates are disabled by default for security. You must manually enable them in Settings -> Maintenance tab if you want automatic updates.

= What happens if the database table is accidentally deleted? =

The plugin automatically detects and repairs missing tables or columns:
- Missing table: Automatically recreated on next database operation
- Missing columns: Automatically added without data loss
- Weekly maintenance: Runs every Sunday at 3 AM to check table integrity and optimize performance (CHECK TABLE, OPTIMIZE TABLE)

= Privacy / GDPR (OpenAI) - What are the privacy options? =

When OpenAI is enabled, personal form data is transmitted to OpenAI (USA). For GDPR/DSGVO compliance, the plugin provides:

* Privacy Tab in Settings: Configure how privacy information is displayed
  * Privacy passage on privacy policy page: Filter (auto-append), Manual (shortcode), or None
  * Form notice: Show/hide notice at comment and CF7 forms
* The options only take effect when OpenAI is enabled and an API key is configured
* Suggested privacy policy text is available under Settings -> Privacy in WordPress admin
* Shortcode [we_spamfighter_privacy] for manual placement in classic editor or wherever shortcodes are supported

= What is the Activity Log? =

The Activity Log is an optional feature that tracks important plugin events (e.g., weekly summaries sent, table maintenance performed, email notifications). It is disabled by default and can be enabled in Settings -> Maintenance tab. When enabled, a new "Activity Log" menu item appears under WE Spamfighterin in the admin menu. You can view all recent events there and manually clear the log using the "Clear Activity Log" button. You can also clear the log directly from the Maintenance tab in Settings (button appears when entries exist). The log automatically cleans old entries based on the log retention setting and keeps a maximum of 100 entries. Email notifications (daily/weekly summaries) are automatically logged with success/failure status for debugging.

== Screenshots ==

1. Dashboard with submission list
2. Settings page with tabbed interface
3. Submission details view
4. Spam analytics

== Changelog ==

= 1.5.0 =
* Privacy / GDPR (OpenAI): DSGVO-compliant privacy measures when OpenAI is active
  * New Privacy tab in Settings with configurable options
  * Privacy passage: Filter (auto-append to privacy page), Manual (shortcode [we_spamfighter_privacy]), or None
  * Form notice: Optional notice at comment and CF7 forms about OpenAI data transfer
  * Suggested text for the WordPress Privacy Policy Guide
  * Options only active when OpenAI is enabled

= 1.3.3 =
* Enhanced Heuristic Detection: Added multiple new spam detection methods
  * Referrer analysis: Detects missing referrers (direct access/bots), suspicious referrer domains, URL shorteners in referrers
  * User agent analysis: Detects bots, missing user agents, suspicious user agent patterns
  * Content length analysis: Detects very short (< 10 chars) or extremely long (> 5000 chars) content
  * Mixed script detection: Detects mixed character sets (e.g., Cyrillic + Latin), especially effective against spam using Cyrillic characters
  * Unicode anomalies: Detects zero-width characters, control characters, and homoglyph attacks
  * Numbers/letters only: Detects content containing only numbers or only letters (common bot pattern)
  * IP address in content: Detects IP addresses in text (not URLs), often used by spammers
  * All checks are enabled by default when Heuristic Detection is activated
  * Individual checks can be disabled in Settings -> Heuristic Detection tab if needed
  * When Heuristic Detection is disabled, all checks are automatically deactivated
* Improved Email Notification Debugging: Email notifications now logged in Activity Log
  * Daily and weekly summary emails are logged with success/failure status
  * Helps diagnose email delivery issues
  * Only active when Activity Log is enabled

= 1.2.2 =
* Added Activity Log: Optional activity logging to track important plugin events
  * View recent activities (weekly summaries, table maintenance, etc.)
  * Automatic cleanup (max 100 entries, respects log retention days)
  * Manual clear option
  * Only active when enabled in settings
* GitHub Updates: Update functionality is now optional and disabled by default
  * Enable in Settings -> Maintenance tab
  * Warning displayed when enabling automatic updates
* Enhanced Database Maintenance: Improved database consistency and auto-repair
  * Automatic detection and repair of missing tables or columns
  * Weekly maintenance runs CHECK TABLE and OPTIMIZE TABLE
  * Multisite compatible table name handling
* Improved Email Notifications: Weekly summary emails now sent even when no spam is detected (shows "all clear" message)
  * Settings are dynamically reloaded for cron jobs to reflect latest changes
  * Fixed timezone handling for daily and weekly summary calculations

= 1.1.4 =
* Multi-layer spam detection improvements
* Enhanced heuristic detection
* Better language detection

= 1.0.8 =
* Added Heuristic Detection (local spam detection without external APIs)
* Added Language Detection (automatic language mismatch detection)
* Optimized detection order (local checks first, OpenAI only when needed)
* Modern Settings UI with tabbed navigation
* Plugin works fully without OpenAI

= 1.0.0 =
* Initial release
* Contact Form 7 integration
* WordPress Comments integration
* OpenAI spam detection
* Dashboard with submission management

== Upgrade Notice ==

= 1.5.0 =
Recommended update: New Privacy tab for GDPR/DSGVO compliance when using OpenAI. Existing installations get privacy notices by default (filter + form notice).

= 1.3.3 =
Recommended update: Enhanced heuristic spam detection with referrer and user agent analysis for better bot and spam detection. Improved email notification debugging in Activity Log.

= 1.2.2 =
Recommended update: Added optional Activity Log for tracking plugin events, improved database maintenance, and enhanced email notifications. GitHub updates are now optional and disabled by default for security.

= 1.1.4 =
Recommended update for improved spam detection and performance.

= 1.0.8 =
Major update: Added local heuristic detection and language detection. Plugin now works without OpenAI. Modern UI improvements.

== Support ==

* GitHub Issues: https://github.com/gbyat/we-spamfighter/issues
* Author Website: https://webentwicklerin.at

== Credits ==

* Author: webentwicklerin, Gabriele Laesser
* Author URI: https://webentwicklerin.at
* GitHub: @gbyat
* OpenAI: Powered by OpenAI GPT models for spam detection (optional)
