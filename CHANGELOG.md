# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
