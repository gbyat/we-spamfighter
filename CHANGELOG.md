# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-12-29

- Enhance heuristic detection capabilities and improve email notification logging
  - Added multiple new spam detection methods including referrer analysis, user agent analysis, content length analysis, mixed script detection, Unicode anomalies detection, numbers/letters only detection, and IP address detection in content.
  - Migrated existing settings from disable_* to enable_* for heuristic checks, ensuring all checks are enabled by default when heuristic detection is activated.
  - Improved email notification logging in the Activity Log to track success/failure status of daily and weekly summary emails.
  - Updated documentation in README.md and README.txt to reflect new features and changes in settings.


## [1.3.3] - 2025-12-17

- Version update


## [1.3.2] - 2025-12-17

- Version update


## [1.3.1] - 2025-12-16

- Enhance activity log feature with improved documentation and admin interface
  - Updated README.md and README.txt to clarify the functionality and access of the Activity Log, including the addition of a new menu item in the admin interface.
  - Improved the settings page to conditionally display the Activity Log submenu based on its enabled status.
  - Added functionality to clear the activity log directly from the Maintenance tab and provided user feedback upon clearing.
  - Enhanced the Activity Log page to display recent entries and a clear button, improving user experience.


## [1.3.0] - 2025-12-16

- Implement activity log feature with management options
  - Added an optional activity log to track significant plugin events, including weekly summaries and table maintenance.
  - Introduced settings to enable/disable the activity log and manage log retention.
  - Implemented AJAX functionality for clearing the activity log entries.
  - Updated documentation in README.md and README.txt to reflect new features and usage instructions.
  - Enhanced database maintenance routines to include activity log management.
- Add activity logging feature to track plugin events
  - Introduced an activity log to record significant plugin events such as daily and weekly spam summaries sent, table maintenance, and old logs cleaned.
  - Added a new setting to enable or disable the activity log in the plugin settings.
  - Updated the admin interface to display recent activity log entries.
  - Enhanced email notifications to confirm the sending of daily and weekly summaries even when no spam is detected.
  - Updated language files to include new strings for the activity log feature.


## [1.2.1] - 2025-12-15

- Enhance database management features with automatic table repair and maintenance
  - Added functionality to automatically detect and repair missing database tables or columns.
  - Implemented weekly maintenance tasks to check table integrity and optimize performance.
  - Updated documentation in README.md and README.txt to reflect new features and troubleshooting steps.
  - Refactored database interaction methods to handle missing tables/columns gracefully.


## [1.2.0] - 2025-12-15

- Add GitHub Updates feature with settings and documentation
  - Introduced an option to enable automatic updates from GitHub releases in the plugin settings.
  - Updated README.md and README.txt to include instructions for enabling GitHub updates.
  - Implemented logic in the updater class to check if GitHub updates are enabled before performing updates.
  - Added corresponding language translations for the new feature.


## [1.1.10] - 2025-12-10

- Update GitHub Actions workflow to use action-gh-release@v2 for improved release handling


## [1.1.9] - 2025-12-10

- Refactor release notes handling in GitHub Actions workflow
  - Simplified the extraction of release notes by removing inline output handling.
  - Updated the release process to directly use the generated release notes file.


## [1.1.8] - 2025-12-10

- Refactor release notes extraction in GitHub Actions workflow
  - Replaced inline extraction logic with a dedicated script for improved reliability and maintainability.
  - Updated output handling for release notes to use a consistent delimiter for multiline support.


## [1.1.7] - 2025-12-10

- Update package.json, workflows, and updater for version management
  - Added a new script in package.json to update the tested version.
  - Enhanced GitHub Actions workflow to extract release notes from CHANGELOG.md, improving release note generation.
  - Updated the default 'Tested up to' version in the updater class to 6.9 for better compatibility.


## [1.1.6] - 2025-12-10

- Update workflows and scripts for improved checks and dependency management
  - Updated .gitignore to require package-lock.json for reproducible builds.
  - Enhanced package.json with new check scripts for PHP and JavaScript.
  - Modified CodeQL workflow to run only on pull requests and updated to use the latest actions.
  - Improved PHP linter checks in workflows to indicate optional checks and refined dependency installation logic.


## [1.1.5] - 2025-12-10

- Update dependencies, scripts, and improve asset handling
  - Added new build script for assets in package.json.
  - Updated devDependencies to include clean-css-cli and terser for asset optimization.
  - Updated README.md and we-spamfighter.php to reflect compatibility with WordPress 6.9.
  - Enhanced GitHub Actions workflow to build minified assets before release.
  - Improved asset loading in admin dashboard and settings by checking for file existence and debug mode.
  - Updated release script to include asset building and POT file updates before release.


## [1.1.4] - 2025-12-10

- Refactor changelog generation in sync-version script
  - Improved commit message extraction to avoid duplicates by checking only the subject line.
  - Enhanced handling of multi-line commit messages for better formatting in the CHANGELOG.
  - Removed the outdated update-changelog.js script to streamline changelog management.


## [1.1.3] - 2025-12-06

- Enhance spam detection heuristics and language mismatch handling


## [1.1.2] - 2025-12-01

- Version update


## [1.1.1] - 2025-12-01

- Version update


## [1.1.0] - 2025-12-01

- Add auto-marking of pingbacks/trackbacks as spam and enhance admin settings UI
- Extract WordPress compatibility data from release ZIP file to display correct compatibility information in update checks
- Add compatibility fields (tested, requires, requires_php) to update transient for proper WordPress compatibility display
- Fix plugin compatibility display by reading tested up to from plugin header instead of hardcoded value
- Fix release workflow to use explicit file copying instead of rsync, prevent release-temp directory in ZIP, update tested up to 6.8.3
- Update actions/upload-artifact to v4 to fix deprecation warning
- Remove CI workflow and improve release workflow to ensure ZIP file is attached
- Remove RELEASE-FILES.md and RELEASE.md documentation files as part of repository cleanup
- Add remote repository existence check before pushing to GitHub


## [1.0.8] - 2025-11-27

- Update CHANGELOG.md and enhance changelog generation script
- Enhance changelog generation in sync-version script
- Refactor pagination in admin dashboard and update styles


## [1.0.7] - 2025-11-23

- Add auto-marking of pingbacks/trackbacks as spam and enhance admin settings UI

## [1.0.6] - 2025-11-22

- Extract WordPress compatibility data from release ZIP file to display correct compatibility information in update checks

## [1.0.5] - 2025-11-22

- Add compatibility fields (tested, requires, requires_php) to update transient for proper WordPress compatibility display

## [1.0.4] - 2025-11-22

- Fix plugin compatibility display by reading tested up to from plugin header instead of hardcoded value

## [1.0.3] - 2025-11-22

- Fix release workflow to use explicit file copying instead of rsync, prevent release-temp directory in ZIP, update tested up to 6.8.3

## [1.0.2] - 2025-11-22

- Update actions/upload-artifact to v4 to fix deprecation warning

## [1.0.1] - 2025-11-22

- Remove CI workflow and improve release workflow to ensure ZIP file is attached

- **Comment Storage**: Comments are no longer stored in the plugin's database. They are now handled entirely by WordPress's native comment system:
- - Spam comments are marked as spam and stored in WordPress tables
- - The plugin dashboard shows spam comment count from WordPress
- - Direct link to WordPress comment management in dashboard statistics
- - More efficient: No duplicate storage of comment data
- **Dashboard**: Now displays only Contact Form 7 submissions (comments managed by WordPress)
- **Statistics**: Dashboard statistics show spam comment count from WordPress with link to comment management
- **README.md**: Updated to clarify that:
- - CF7 doesn't store submissions by default - this plugin adds submission logging as a bonus feature
- - Comments are managed by WordPress, not stored in plugin database
- Removed redundant comment storage to prevent duplicate data management
- Improved dashboard clarity by separating CF7 submissions from WordPress comments
- Refactor pagination in admin dashboard and update styles

## [1.0.0] - 2024-11-21

### Added

- Initial release
- Contact Form 7 integration with spam detection
- WordPress Comments integration with spam protection
- OpenAI-powered spam detection using GPT models
- Dashboard with submission overview and statistics
- Settings page with comprehensive configuration options
- Frontend form disabling after submission (readonly/disabled fields)
- Email notifications (immediate, daily, weekly summaries)
- Performance optimizations (caching, minified assets)
- Multisite support
- Uninstall functionality with option to keep data
- German translation (de_DE)
- Comprehensive README.md documentation
- LICENSE file (GPL v2)
- npm scripts for POT file generation
- .gitignore file

### Features

- AI-powered spam detection with configurable threshold
- Automatic form field disabling after submission
- Submission logging and management
- Spam statistics and analytics
- Email notifications with customizable frequency
- Log retention settings
- API key management (database or wp-config.php)

### Security

- Secure API key storage (wp-config.php option)
- Rate limiting for API requests
- SQL injection protection (prepared statements)
- XSS protection (data sanitization)
- CSRF protection (nonces)

### Performance

- Database query caching
- Minified CSS/JS in production
- Inline CSS for faster loading
- Optimized database queries with proper indexing
- Non-blocking JavaScript execution

---

[1.0.0]: https://github.com/gbyat/we-spamfighter/releases/tag/1.0.0
[1.0.1]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.1
[1.0.2]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.2
[1.0.3]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.3
[1.0.4]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.4
[1.0.5]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.5
[1.0.6]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.6
[1.0.7]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.7
[1.0.8]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.8
[1.1.0]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.0
[1.1.1]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.1
[1.1.2]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.2
[1.1.3]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.3
[1.1.4]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.4
[1.1.5]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.5
[1.1.6]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.6
[1.1.7]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.7
[1.1.8]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.8
[1.1.9]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.9
[1.1.10]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.1.10
[1.2.0]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.2.0
[1.2.1]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.2.1
[1.3.0]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.3.0
[1.3.1]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.3.1
[1.3.2]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.3.2
[1.3.3]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.3.3
[1.4.0]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.4.0
