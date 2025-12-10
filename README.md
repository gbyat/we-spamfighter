# WE Spamfighter

**Contributors:** webentwicklerin  
**Tags:** spam, contact-form-7, comments, ai, openai, spam-protection, security, form-protection  
**Requires at least:** 6.0  
**Tested up to:** 6.9  
**Requires PHP:** 8.0  
**Stable tag:** 1.1.6  
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
- **Custom Messages**: Configurable "Thank you" message for blocked spam submissions

### 📊 Dashboard & Analytics

- **Submission Log**: View all Contact Form 7 submissions (comments are managed by WordPress)
  - **Unique Feature**: CF7 doesn't store submissions by default - this plugin adds submission logging as a bonus feature
- **Spam Analytics**: Track spam detection statistics for CF7 forms
- **Comment Spam Stats**: View spam comment count from WordPress (with link to comment management)
- **Filtering**: Filter submissions by spam status, form ID, date range
- **Export**: Export submission data for analysis
- **Submission Details**: View full submission data, spam scores, and detection reasoning

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
3. Find **WE Spamfighter** and click **Activate**

### Via Composer

```bash
composer require gbyat/we-spamfighter
```

## Configuration

### 1. OpenAI API Key Setup

**Option 1: Via WordPress Settings (Recommended for testing)**

1. Go to **WE Spamfighter → Settings**
2. Enter your OpenAI API key in the **OpenAI API Key** field
3. Click **Save Changes**

**Option 2: Via wp-config.php (Recommended for production)**
Add this line to your `wp-config.php` file:

```php
define('WE_SPAMFIGHTER_OPENAI_KEY', 'your-api-key-here');
```

This method is more secure as the API key is not stored in the database.

### 2. Plugin Settings

Navigate to **WE Spamfighter → Settings** (organized in tabs) to configure:

#### General Tab

- **Enable Contact Form 7 Protection**: Toggle CF7 spam detection
- **Enable Comments Protection**: Toggle comment spam detection
- **Auto-Mark Pingbacks/Trackbacks**: Automatically mark pingbacks/trackbacks as spam
- **Mark Different Language as Spam**: Automatically flag submissions in different languages
- **Language Mismatch Score Boost**: Amount to increase spam score when language doesn't match (0.1 - 1.0, default: 0.3)

#### Heuristic Detection Tab

- **Enable Heuristic Detection**: Use local spam detection (works without OpenAI)
- **Heuristic Spam Threshold**: Threshold for heuristic detection (0.0 - 1.0, default: 0.6)
- **Disable Link Check**: Option to disable suspicious link detection
- **Disable Character Pattern Check**: Option to disable character pattern detection
- **Disable Spam Phrase Check**: Option to disable known spam phrase detection
- **Disable Email Pattern Check**: Option to disable email pattern detection

#### OpenAI Tab

- **Enable OpenAI Detection**: Enable/disable AI-powered spam detection (optional)
- **OpenAI API Key**: Enter your OpenAI API key
- **OpenAI Model**: Choose the OpenAI model (default: gpt-4o-mini)
- **AI Spam Threshold**: Adjust spam score threshold (0.0 - 1.0, default: 0.7). Higher values mean fewer submissions are flagged as spam (less strict). Lower values mean more submissions are flagged as spam (more strict).

#### Notifications Tab

- **Notification Email**: Email address for spam notifications
- **Notification Type**: Choose frequency (none, immediate, daily, weekly)

#### Maintenance Tab

- **Log Retention**: Days to keep logs (default: 30)
- **Keep Data on Uninstall**: Option to preserve data when uninstalling

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

1. Go to **WE Spamfighter → Dashboard**
2. View all Contact Form 7 submissions in the main table (comments are managed by WordPress)
   - **Note**: CF7 doesn't store submissions by default - this plugin logs them for you
3. Use filters to find specific submissions:
   - Filter by spam status
   - Filter by form ID
   - Search by content
   - Filter by date range
4. Click on a submission to view detailed information
5. View spam comment count in statistics (with link to WordPress comment management)

### Managing Spam

#### Contact Form 7 Submissions

- **Mark as Spam**: Manually mark a CF7 submission as spam
- **Mark as Not Spam**: Mark a false positive as legitimate
- **Delete Submissions**: Remove unwanted submissions from the database
- **Export Data**: Export submissions for external analysis

#### WordPress Comments

- **Manage in WordPress**: Spam comments are managed in **Comments → Spam** in WordPress admin
- **Spam Count**: View spam comment count in the plugin dashboard statistics
- **Direct Link**: Click on spam comment count to go to WordPress comment management

### Custom Messages

When a spam submission is detected:

- The form submission is blocked before sending
- A customizable "Thank you" message is displayed to the user
- Default message: "Thank you for your message."
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
- **Note**: Comments are not stored in the plugin database - they are managed by WordPress. Check **Comments → Spam** in WordPress admin for spam comments.

### CSS Not Loading

**Problem**: Disabled fields don't look different

- **Solution**: Clear browser cache
- **Check**: Verify CSS is injected in page `<head>` (view page source)

## Security Best Practices

1. **Store API Key in wp-config.php**: More secure than database storage (if using OpenAI)
2. **Use Local Detection**: Heuristic detection works without external APIs, reducing security surface
3. **Regular Updates**: Keep the plugin updated for security patches
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
