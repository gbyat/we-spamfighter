# WE Spamfighterin

**Contributors:** webentwicklerin  
**Tags:** spam, contact-form-7, comments, ai, openai, spam-protection, security, form-protection  
**Requires at least:** 6.0  
**Tested up to:** 6.9  
**Requires PHP:** 8.0  
**Stable tag:** 1.5.2  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Advanced spam protection for WordPress using AI-powered and heuristic detection.

## Description

Protects Contact Form 7 forms and WordPress comments from spam submissions with intelligent analysis. Works with or without OpenAI - includes local heuristic detection for cost-effective spam filtering.

## Features

### 🤖 Multi-Layer Spam Detection

- **Heuristic Detection** (Local, Free): Advanced local spam detection without external APIs
  - Link analysis (URL shorteners, suspicious domains, excessive links)
  - Character pattern detection (repeated characters, ALL CAPS, mixed case spam)
  - Known spam phrase detection (multi-language)
  - Email pattern analysis (suspicious providers, random patterns)
  - Referrer analysis (missing referrer, suspicious referrer domains, URL shorteners)
  - User agent analysis (bot detection, missing user agent, suspicious patterns)
  - Content length analysis (very short or extremely long content detection)
  - Mixed script detection (different character sets like Cyrillic + Latin)
  - Unicode anomalies (zero-width characters, control characters, homoglyphs)
  - Numbers/letters only detection (content containing only numbers or letters)
  - IP address in content (IP addresses found in text, not URLs)
- **Language Detection** (Local, Free): Automatically detects and flags submissions in different languages
  - Works without OpenAI using heuristic language detection
  - Can use OpenAI's language detection when available
  - Configurable score boost for language mismatches
- **AI-Powered Detection** (Optional): OpenAI integration for advanced analysis
  - Uses GPT-4o-mini (or other OpenAI models) to analyze form submissions and comments
  - Detects spam patterns, AI-generated content, SEO spam, and suspicious links
  - Only called when needed (cost-efficient: local checks run first)
- **Smart Detection Order**: Heuristic → Language → OpenAI (saves API costs)
  - Local checks run first (instant, free)
  - OpenAI only called for uncertain cases
  - Plugin works completely without OpenAI
- **Configurable Thresholds**: Adjustable spam score thresholds for each detection method

### 📋 Form Integration

- **Contact Form 7**: Full integration with Contact Form 7 plugin
  - **Submission Logging**: Unlike CF7 (which doesn't store submissions by default), this plugin logs all CF7 form submissions for review and analysis
  - **Spam Protection**: Blocks spam submissions before they reach your inbox
- **WordPress Comments**: Native WordPress comment spam protection
- **Automatic Blocking**: Blocks spam submissions before they reach your inbox
- **Custom Messages**: Configurable "Thank you" message for blocked spam submissions (default: "Thank you for your message.")

### 📊 Dashboard & Analytics

- **Submission Log**: View all Contact Form 7 submissions (comments are managed by WordPress)
  - **Unique Feature**: CF7 doesn't store submissions by default - this plugin adds submission logging as a bonus feature
- **Spam Analytics**: Track spam detection statistics for CF7 forms
- **Comment Spam Stats**: View spam comment count from WordPress (with link to comment management)
- **Filtering**: Filter submissions by spam status (via tabs)
- **Submission Details**: View full submission data, spam scores, and detection reasoning
- **Activity Log** (Optional): Track important plugin events and operations
  - View recent activities (weekly summaries, table maintenance, email notifications, etc.)
  - Automatic cleanup (max 100 entries, respects log retention days)
  - Manual clear option
  - Only shows when enabled in settings
  - Email notification tracking (daily/weekly summary emails sent/failed)

### 🎨 User Experience

- **Smart Form Disabling**: Automatically disables form fields after submission (success, failure, or spam)
- **Visual Feedback**: Clear visual distinction between active and disabled form fields
- **Accessibility**: Maintains screen reader compatibility and ARIA attributes
- **Performance Optimized**: Minified CSS/JS in production, inline CSS for faster loading

### 🔒 Security & Privacy

- **Database Storage**: All submissions are logged in secure database tables
- **IP Tracking**: Optional IP address and user agent logging
- **Multi-site Support**: Full WordPress multisite compatibility
- **Secure API Keys**: API keys can be stored in wp-config.php for better security
- **GDPR/Privacy (OpenAI only)**: When OpenAI is active, personal data is sent to OpenAI (USA)
  - Configurable privacy passage on the privacy policy page (filter, manual shortcode/block, or none)
  - Optional form notice at comment and CF7 forms
  - Suggested text for the Privacy Policy Guide (Settings → Privacy)
  - Shortcode `[we_spamfighter_privacy]` for manual placement

### ⚡ Performance

- **Caching**: Intelligent caching of database queries
- **Optimized Assets**: Minified CSS/JS in production environments
- **Non-blocking Scripts**: JavaScript loads in footer for better page performance
- **Efficient Database Queries**: Optimized queries with proper indexing

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **OpenAI API Key**: Optional - enables AI-powered detection. Plugin works fully with local heuristic detection only.
- **Contact Form 7**: Optional, for form protection (version 6.0+ recommended)

## Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file
4. Click **Activate Plugin**

### Via FTP/File Manager

1. Upload the `we-spamfighter` folder to `/wp-content/plugins/`
2. Go to **Plugins** in WordPress admin
3. Find **WE Spamfighterin** and click **Activate**

### Via Composer

```bash
composer require gbyat/we-spamfighter
```

## Configuration

### 1. OpenAI API Key Setup

**Option 1: Via WordPress Settings (Recommended for testing)**

1. Go to **WE Spamfighterin → Settings**
2. Enter your OpenAI API key in the **OpenAI API Key** field
3. Click **Save Changes**

**Option 2: Via wp-config.php (Recommended for production)**
Add this line to your `wp-config.php` file:

```php
define('WE_SPAMFIGHTER_OPENAI_KEY', 'your-api-key-here');
```

This method is more secure as the API key is not stored in the database.

### 2. Plugin Settings

Navigate to **WE Spamfighterin → Settings** (organized in tabs) to configure:

#### General Tab

- **Enable Contact Form 7 Protection**: Toggle CF7 spam detection
- **Enable Comments Protection**: Toggle comment spam detection
- **Auto-Mark Pingbacks/Trackbacks**: Automatically mark pingbacks/trackbacks as spam
- **Mark Different Language as Spam**: Automatically flag submissions in different languages
- **Language Mismatch Score Boost**: Amount to increase spam score when language doesn't match (0.1 - 1.0, default: 0.3)
- **Spam Blocked Message**: Custom message displayed to users when spam is detected (default: "Thank you for your message.")

#### Heuristic Detection Tab

- **Enable Heuristic Detection**: Use local spam detection (works without OpenAI)
- **Heuristic Spam Threshold**: Threshold for heuristic detection (0.0 - 1.0, default: 0.6)
- **Enable Link Check**: Enable suspicious link detection (enabled by default when heuristic detection is active)
- **Enable Character Pattern Check**: Enable character pattern detection (enabled by default when heuristic detection is active)
- **Enable Spam Phrase Check**: Enable known spam phrase detection (enabled by default when heuristic detection is active)
- **Enable Email Pattern Check**: Enable email pattern detection (enabled by default when heuristic detection is active)
- **Enable Referrer Check**: Enable referrer analysis (missing or suspicious referrer detection, enabled by default when heuristic detection is active)
- **Enable User Agent Check**: Enable user agent analysis (bot and suspicious user agent detection, enabled by default when heuristic detection is active)
- **Enable Content Length Check**: Enable content length analysis (very short or extremely long content, enabled by default when heuristic detection is active)
- **Enable Mixed Script Check**: Enable mixed script detection (different character sets like Cyrillic + Latin, enabled by default when heuristic detection is active)
- **Enable Unicode Anomalies Check**: Enable Unicode anomalies detection (zero-width characters, control characters, homoglyphs, enabled by default when heuristic detection is active)
- **Enable Numbers/Letters Only Check**: Enable detection of content containing only numbers or only letters (enabled by default when heuristic detection is active)
- **Enable IP Address in Content Check**: Enable detection of IP addresses in content (not in URLs, enabled by default when heuristic detection is active)

**Note**: When you enable Heuristic Detection, all individual checks are automatically activated by default. You can disable specific checks if needed. When Heuristic Detection is disabled, all checks are automatically deactivated as well.

#### OpenAI Tab

- **Enable OpenAI Detection**: Enable/disable AI-powered spam detection (optional)
- **OpenAI API Key**: Enter your OpenAI API key
- **OpenAI Model**: Choose the OpenAI model (default: gpt-4o-mini)
- **AI Spam Threshold**: Adjust spam score threshold (0.0 - 1.0, default: 0.7). Higher values mean fewer submissions are flagged as spam (less strict). Lower values mean more submissions are flagged as spam (more strict).

#### Notifications Tab

- **Notification Email**: Email address for spam notifications
- **Notification Type**: Choose frequency (none, immediate, daily, weekly)

#### Privacy Tab (GDPR/DSGVO)

_These options only take effect when OpenAI is enabled and an API key is configured._

- **Privacy passage on privacy policy page**: How to include the privacy passage
  - **Filter**: Append automatically to the configured privacy policy page (default)
  - **Manual**: Use shortcode `[we_spamfighter_privacy]` or block for manual placement
  - **None**: Do not add (at your own risk)
- **Form notice**: Show a notice at comment and CF7 forms when OpenAI is active (default: enabled)

The plugin provides suggested privacy policy text under **Settings → Privacy** in WordPress admin. The passage explains that form data may be transmitted to OpenAI (USA) for spam checking, based on legitimate interest (Art. 6(1)(f) GDPR) and Standard Contractual Clauses.

#### Maintenance Tab

- **Log Retention**: Days to keep logs (default: 30)
- **Keep Data on Uninstall**: Option to preserve data when uninstalling
- **Enable Activity Log**: Optional activity logging to track important plugin events (e.g., weekly summaries sent, table maintenance). When enabled, adds an "Activity Log" menu item under WE Spamfighterin for viewing events and provides a clear button in the Maintenance tab.
- **Enable GitHub Updates**: Optional automatic updates from GitHub releases. **⚠️ Activate at your own risk** - Updates will be installed automatically without additional confirmation. Disabled by default for security.

### 3. Contact Form 7 Integration

The plugin automatically integrates with Contact Form 7 when:

- Contact Form 7 is installed and active
- Contact Form 7 protection is enabled in settings

No additional configuration is required for basic functionality.

**Important Note**: Contact Form 7 does **not** store form submissions by default. This plugin adds this functionality as a bonus feature - all CF7 form submissions (spam and legitimate) are logged in the plugin's database for review, analysis, and spam detection tracking.

### 4. Comments Integration

WordPress comment spam protection works when:

- Comments protection is enabled in settings
- Heuristic detection and/or OpenAI detection is enabled

**Important**: Comments are NOT saved in the plugin's database. They are handled by WordPress's native comment system:

- Spam comments are marked as spam and stored in WordPress
- You can manage spam comments in **Comments → Spam** in WordPress admin
- The plugin dashboard shows the spam comment count with a link to WordPress comment management
- Only Contact Form 7 submissions are stored in the plugin's database
- Detection works with or without OpenAI (local heuristic detection is available)

## Usage

### Viewing Submissions

1. Go to **WE Spamfighterin → Dashboard**
2. View all Contact Form 7 submissions in the main table (comments are managed by WordPress)
   - **Note**: CF7 doesn't store submissions by default - this plugin logs them for you
3. Use tabs to filter submissions by spam status (Normal Mails / Spam)
4. Click on a submission to view detailed information
5. View spam comment count in statistics (with link to WordPress comment management)

### Managing Spam

#### Contact Form 7 Submissions

- **Mark as Spam**: Manually mark a CF7 submission as spam
- **Mark as Not Spam**: Mark a false positive as legitimate
- **Delete Submissions**: Remove unwanted submissions from the database

#### WordPress Comments

- **Manage in WordPress**: Spam comments are managed in **Comments → Spam** in WordPress admin
- **Spam Count**: View spam comment count in the plugin dashboard statistics
- **Direct Link**: Click on spam comment count to go to WordPress comment management

### Viewing Activity Log

1. Go to **WE Spamfighterin → Settings → Maintenance** tab
2. Enable **Enable Activity Log** option and save changes
3. A new **Activity Log** menu item will appear under **WE Spamfighterin** in the admin menu
4. Click on **WE Spamfighterin → Activity Log** to view recent plugin events (weekly summaries sent, table maintenance, etc.)
5. Use **Clear Activity Log** button to manually clear all activity log entries

**Note**: Activity log is optional and disabled by default. The log automatically cleans old entries based on the "Log Retention" setting and keeps a maximum of 100 entries. You can also clear the log directly from the **Maintenance** tab in Settings (button appears when entries exist). If no events have occurred yet, a helpful message will be displayed.

### Custom Messages

When a spam submission is detected:

- The form submission is blocked before sending
- A customizable "Thank you" message is displayed to the user
- Default message: "Thank you for your message."
- The message can be configured in **Settings → General** tab under "Spam Blocked Message"
- The form is automatically disabled after submission

## Frontend Behavior

### Form Disabling

After a form submission (success, failure, or spam detection):

1. **Submit Button**: Automatically hidden
2. **Form Fields**: Set to `readonly` or `disabled`
   - Text inputs, textareas → `readonly`
   - Selects, checkboxes, radios → `disabled`
3. **Visual Feedback**: Disabled fields have:
   - Grayed-out appearance
   - Reduced opacity
   - "Not allowed" cursor
   - No focus states
4. **Accessibility**: Maintains proper ARIA attributes and screen reader compatibility

### Styling

The plugin includes optimized CSS that:

- Distinguishes disabled fields from active ones
- Prevents interaction with disabled fields
- Maintains accessibility standards
- Loads inline in the `<head>` for optimal performance

## Development

### Code Structure

```
we-spamfighter/
├── assets/
│   ├── css/          # Stylesheets (admin, dashboard, frontend)
│   └── js/           # JavaScript files (admin, dashboard, frontend)
├── includes/
│   ├── admin/        # Admin interface classes
│   ├── core/         # Core functionality (database, logger)
│   ├── detection/    # Spam detection engines
│   └── integration/  # Integration classes (CF7, Comments)
├── languages/        # Translation files
└── we-spamfighter.php # Main plugin file
```

### Namespace

All plugin code uses the `WeSpamfighter` namespace following WordPress coding standards.

### Hooks & Filters

#### Actions

- `we_spamfighter_before_spam_check`: Fires before spam detection
- `we_spamfighter_after_spam_check`: Fires after spam detection
- `we_spamfighter_spam_detected`: Fires when spam is detected
- `we_spamfighter_submission_saved`: Fires when submission is saved

#### Filters

- `we_spamfighter_spam_threshold`: Modify spam threshold dynamically
- `we_spamfighter_openai_model`: Change OpenAI model
- `we_spamfighter_detection_result`: Modify detection results
- `we_spamfighter_abort_message`: Customize spam abort message

### Database Schema

The plugin creates a custom table `wp_we_spamfighter_submissions`:

**Note**: Only Contact Form 7 submissions are stored in this table. Comments are handled by WordPress's native comment system and stored in WordPress tables.

**Automatic Recovery**: The plugin automatically detects and repairs missing tables or columns:

- If the table is accidentally deleted, it will be automatically recreated on the next database operation
- If columns are missing, they will be automatically added without data loss
- Weekly maintenance runs CHECK TABLE and OPTIMIZE TABLE to ensure consistency and performance

```sql
- id (bigint) - Primary key
- submission_type (varchar) - 'cf7' (comments are not stored here)
- form_id (bigint) - Form ID
- submission_data (longtext) - JSON submission data
- is_spam (tinyint) - Spam flag (0/1)
- spam_score (float) - Spam score (0.0-1.0)
- detection_method (varchar) - Detection method used
- detection_details (longtext) - JSON detection details
- user_ip (varchar) - User IP address
- user_agent (text) - User agent string
- site_id (bigint) - Multisite site ID
- email_sent (tinyint) - Email sent flag
- created_at (datetime) - Submission timestamp
```

## Performance Optimization

### Asset Loading

- **Production Mode**: Automatically loads `.min` versions of CSS/JS files
- **Development Mode**: Loads unminified files when `WP_DEBUG` is enabled
- **Inline CSS**: CSS is injected inline in `<head>` to reduce HTTP requests
- **Footer Scripts**: JavaScript loads in footer for non-blocking execution

### Database Optimization

- **Query Caching**: Submissions list is cached for 5 minutes
- **Single Submission Cache**: Individual submissions cached for 1 hour
- **Cache Invalidation**: Automatic cache clearing on new submissions
- **Indexed Queries**: Database indexes on frequently queried columns
- **Automatic Table Repair**: Missing tables or columns are automatically detected and restored
- **Weekly Maintenance**: Automatic table consistency checks and optimization (CHECK TABLE, OPTIMIZE TABLE)

## Troubleshooting

### Spam Detection Not Working

**Problem**: Spam submissions are not being blocked

- **Solution**: Check that at least one detection method is enabled:
  - Heuristic Detection (works without OpenAI)
  - Language Detection (works without OpenAI)
  - OpenAI Detection (requires API key)
- **Note**: The plugin works completely without OpenAI using local detection methods

### OpenAI API Issues (Optional)

**Problem**: "OpenAI API key not configured"

- **Solution**: Add your API key in Settings → OpenAI tab or wp-config.php. Or disable OpenAI and use local heuristic detection only.

**Problem**: "API rate limit exceeded"

- **Solution**: The plugin includes rate limiting and only calls OpenAI when needed. Wait a few minutes or check your OpenAI account limits. Local checks reduce API usage.

**Problem**: "API request failed"

- **Solution**: Check your API key validity and internet connection. The plugin will fall back to local detection if OpenAI fails.

### Form Not Disabling

**Problem**: Form fields remain active after submission

- **Solution**: Clear browser cache and ensure JavaScript is enabled
- **Check**: Verify `frontend.js` is loading in the page source

### Submissions Not Logged

**Problem**: CF7 submissions not appearing in dashboard

- **Solution**: Check that "Store All Submissions" is enabled in settings
- **Check**: Verify database table exists (plugin activation creates it)
- **Auto-Repair**: If the table or columns are missing, the plugin will automatically attempt to repair them on the next operation
- **Note**: Comments are not stored in the plugin database - they are managed by WordPress. Check **Comments → Spam** in WordPress admin for spam comments.

### Database Table Issues

**Problem**: Database errors or missing table/columns

- **Solution**: The plugin automatically detects and repairs missing tables or columns
  - Missing table: Automatically recreated on next database operation
  - Missing columns: Automatically added using dbDelta (no data loss)
  - Weekly maintenance: Runs every Sunday at 3 AM to check table integrity and optimize performance
- **Manual Repair**: You can also manually repair by deactivating and reactivating the plugin (this runs table creation)

### Activity Log Not Showing

**Problem**: Activity Log menu item not visible in admin menu

- **Solution**: Enable "Enable Activity Log" in Settings → Maintenance tab and save changes
- **Note**: Activity log is optional and disabled by default
- **Empty Log**: If the log is enabled but empty, a message will be displayed indicating that no events have occurred yet. Events will appear as they happen (e.g., weekly summary emails sent, table maintenance performed)
- **Clear Button**: The clear button in the Maintenance tab only appears when there are entries to clear

### Plugin Updates

**Q: How do I update the plugin?**

**A**: You have two options:

1. **Manual Updates (Recommended)**: Download the latest release from [GitHub](https://github.com/gbyat/we-spamfighter/releases) and install manually via WordPress admin
2. **Automatic Updates (Optional)**: Enable "Enable GitHub Updates" in **Settings → Maintenance** tab. **⚠️ Warning**: Automatic updates will install without additional confirmation. Enable at your own risk.

**Q: Are automatic updates enabled by default?**

**A**: No, automatic GitHub updates are **disabled by default** for security. You must manually enable them in Settings → Maintenance tab if you want automatic updates.

### CSS Not Loading

**Problem**: Disabled fields don't look different

- **Solution**: Clear browser cache
- **Check**: Verify CSS is injected in page `<head>` (view page source)

## Security Best Practices

1. **Store API Key in wp-config.php**: More secure than database storage (if using OpenAI)
2. **Use Local Detection**: Heuristic detection works without external APIs, reducing security surface
3. **Regular Updates**: Keep the plugin updated for security patches
   - **Manual Updates** (Recommended): Download and install updates manually from GitHub releases
   - **Automatic Updates** (Optional): Enable GitHub updates in Settings → Maintenance tab at your own risk
4. **Review Submissions**: Regularly review spam submissions for false positives
5. **Database Access**: Limit database access to trusted administrators
6. **Rate Limiting**: Monitor API usage to prevent abuse (OpenAI has built-in rate limiting)
7. **Language Filtering**: Enable language mismatch detection for single-language websites

## Multisite Support

The plugin fully supports WordPress Multisite:

- Each site has its own settings
- Submissions are stored with site ID
- Dashboard shows site-specific submissions
- Network admin can manage all sites

## Translation

The plugin is translation-ready and includes:

- German (de_DE) translation files
- English (default) strings
- Translation template (.pot file)

To add your own translation:

1. Copy `languages/we-spamfighter.pot` to your language
2. Translate the strings
3. Save as `we-spamfighter-{locale}.po`
4. Generate `.mo` file using Poedit or similar tool
5. Place in `languages/` directory

## Changelog

### Version 1.5.0+

- **Privacy / GDPR (OpenAI)**: When OpenAI is active, DSGVO-compliant privacy measures
  - New Privacy tab in Settings with configurable options
  - Privacy passage: Filter (auto-append to privacy page), Manual (shortcode `[we_spamfighter_privacy]`), or None
  - Form notice: Optional notice at comment and CF7 forms informing users about OpenAI data transfer
  - Suggested text for the WordPress Privacy Policy Guide
  - Options only active when OpenAI is enabled

### Version 1.3.3+

- **Enhanced Heuristic Detection**: Added multiple new spam detection methods
  - Referrer analysis: Detects missing referrers (direct access/bots), suspicious referrer domains, URL shorteners in referrers
  - User agent analysis: Detects bots, missing user agents, suspicious user agent patterns
  - Content length analysis: Detects very short (< 10 chars) or extremely long (> 5000 chars) content
  - Mixed script detection: Detects mixed character sets (e.g., Cyrillic + Latin), especially effective against spam using Cyrillic characters
  - Unicode anomalies: Detects zero-width characters, control characters, and homoglyph attacks
  - Numbers/letters only: Detects content containing only numbers or only letters (common bot pattern)
  - IP address in content: Detects IP addresses in text (not URLs), often used by spammers
  - All checks are enabled by default when Heuristic Detection is activated
  - Individual checks can be disabled in Settings → Heuristic Detection tab if needed
- **Improved Email Notification Debugging**: Email notifications now logged in Activity Log
  - Daily and weekly summary emails are logged with success/failure status
  - Helps diagnose email delivery issues
  - Only active when Activity Log is enabled

### Version 1.2.2+

- **Activity Log**: Optional activity logging to track important plugin events
  - View recent activities (weekly summaries, table maintenance, etc.)
  - Automatic cleanup (max 100 entries, respects log retention days)
  - Manual clear option
  - Only active when enabled in settings
- **Optional GitHub Updates**: GitHub update functionality is now optional and disabled by default
  - Enable in Settings → Maintenance tab
  - Warning displayed when enabling automatic updates
- **Enhanced Database Maintenance**: Improved database consistency and auto-repair
  - Automatic detection and repair of missing tables or columns
  - Weekly maintenance runs CHECK TABLE and OPTIMIZE TABLE
  - Multisite compatible table name handling
- **Improved Email Notifications**: Weekly summary emails now sent even when no spam is detected (shows "all clear" message)
  - Settings are dynamically reloaded for cron jobs to reflect latest changes
  - Fixed timezone handling for daily and weekly summary calculations

### Version 1.0.8+

- **Heuristic Detection**: Local spam detection without external APIs
  - Link analysis (URL shorteners, suspicious domains)
  - Character pattern detection (repeated chars, ALL CAPS)
  - Known spam phrase detection
  - Email pattern analysis
- **Language Detection**: Automatic language mismatch detection (with/without OpenAI)
- **Optimized Detection Order**: Local checks first, OpenAI only when needed (saves costs)
- **Modern Settings UI**: Tab-based navigation with toggle switches
- **Plugin Works Without OpenAI**: Fully functional with local detection only

### Version 1.0.0

- Initial release
- Contact Form 7 integration
- WordPress Comments integration
- OpenAI spam detection
- Dashboard with submission management
- Performance optimizations
- Frontend form disabling
- Multisite support

## Support

- **GitHub Issues**: [https://github.com/gbyat/we-spamfighter/issues](https://github.com/gbyat/we-spamfighter/issues)
- **Author Website**: [https://webentwicklerin.at](https://webentwicklerin.at)

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 webentwicklerin, Gabriele Laesser

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Credits

- **Author**: webentwicklerin, Gabriele Laesser
- **Author URI**: https://webentwicklerin.at
- **GitHub**: [@gbyat](https://github.com/gbyat)
- **OpenAI**: Powered by OpenAI GPT models for spam detection

---

Made with ❤️ for WordPress
