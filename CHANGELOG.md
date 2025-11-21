# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
