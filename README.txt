=== WE Spamfighter ===
Contributors: webentwicklerin
Tags: spam, contact-form-7, comments, ai, openai, spam-protection, security, form-protection
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.4
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
* Custom Messages: Configurable "Thank you" message for blocked spam submissions

= Dashboard & Analytics =

* Submission Log: View all Contact Form 7 submissions (comments are managed by WordPress)
  * Unique Feature: CF7 doesn't store submissions by default - this plugin adds submission logging as a bonus feature
* Spam Analytics: Track spam detection statistics for CF7 forms
* Comment Spam Stats: View spam comment count from WordPress (with link to comment management)
* Filtering: Filter submissions by spam status, form ID, date range
* Export: Export submission data for analysis
* Submission Details: View full submission data, spam scores, and detection reasoning

== Installation ==

= Via WordPress Admin =

1. Download the plugin ZIP file
2. Go to Plugins -> Add New -> Upload Plugin
3. Upload the ZIP file
4. Click Activate Plugin

= Via FTP/File Manager =

1. Upload the we-spamfighter folder to /wp-content/plugins/
2. Go to Plugins in WordPress admin
3. Find WE Spamfighter and click Activate

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

== Screenshots ==

1. Dashboard with submission list
2. Settings page with tabbed interface
3. Submission details view
4. Spam analytics

== Changelog ==

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
