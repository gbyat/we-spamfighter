# Release System

This document describes the GitHub Release System for WE Spamfighter.

## Overview

The release system provides:

- **Automatic WordPress Dashboard Updates**: Users receive update notifications in WordPress admin
- **GitHub Releases**: Automated releases with ZIP files for manual installation
- **CI/CD Pipeline**: Code validation before releases
- **Changelog Management**: Automatic changelog updates with commit messages

## How It Works

### 1. WordPress Dashboard Updates

The plugin includes a built-in updater (`includes/core/class-updater.php`) that:

- Checks GitHub for new releases
- Shows update notifications in WordPress admin
- Allows one-click updates from the plugins page
- Displays changelog in the update popup

**Requirements:**

- Plugin must be installed from GitHub
- GitHub repository must be public (or provide GitHub token in settings)

### 2. GitHub Releases

When you create a version tag, GitHub Actions automatically:

1. Validates the code (syntax, constants, plugin header)
2. Creates a ZIP file (`we-spamfighter.zip`)
3. Creates a GitHub Release
4. Attaches the ZIP file to the release

### 3. CI/CD Pipeline

Two GitHub Actions workflows:

**CI Workflow** (`.github/workflows/ci.yml`):

- Runs on every push to `main` or `develop`
- Validates code quality
- Checks PHP syntax
- Validates plugin constants

**Release Workflow** (`.github/workflows/release.yml`):

- **Important:** Runs only when a version tag is pushed (e.g., `v1.0.1`)
- Does **not** run on regular code pushes
- Creates release ZIP file
- Publishes GitHub Release

**Note:** If you push code without a tag, no release will be created. You must push a version tag to trigger the release workflow.

## Usage

### Creating Your First Release (v1.0.0)

**Important:** GitHub Releases are only created when a version tag (e.g., `v1.0.0`) is pushed to GitHub. Simply pushing code without a tag will **not** create a release.

For the first release, use:

```bash
npm run release:initial
```

This will:
1. Use the current version from `package.json` (should be `1.0.0` for first release)
2. Sync version to `we-spamfighter.php`
3. Check if version exists in `CHANGELOG.md`
4. Commit any pending changes
5. Create and push a Git tag (`v1.0.0`)
6. Trigger GitHub Actions to create the release

**After pushing your code to GitHub for the first time, run this command to create the initial release.**

### Creating Subsequent Releases

#### Option 1: Using npm scripts (Recommended)

```bash
# Patch release (1.0.0 → 1.0.1)
npm run release:patch

# Minor release (1.0.0 → 1.1.0)
npm run release:minor

# Major release (1.0.0 → 2.0.0)
npm run release:major
```

This script will:

1. Bump version in `package.json`
2. Sync version to `we-spamfighter.php`
3. Update `CHANGELOG.md` with commit messages
4. Commit all changes
5. Create and push a git tag
6. Trigger GitHub Actions to create the release

#### Option 2: Manual Release

1. Update version in `package.json`
2. Run `npm run sync-version` to sync version to plugin file
3. Update `CHANGELOG.md` manually
4. Commit changes:
   ```bash
   git add -A
   git commit -m "Release v1.0.1"
   ```
5. Create and push tag:
   ```bash
   git tag -a "v1.0.1" -m "Release v1.0.1"
   git push origin main
   git push origin v1.0.1
   ```

### Automatic Changelog Updates

Before committing, you can update the changelog with commit messages:

```bash
# Manually update changelog with commits since last release
node scripts/update-changelog.js
```

This will:

- Get commits since the last version tag
- Add them to an "Unreleased" section in `CHANGELOG.md`
- Preserve existing unreleased changes

**As Git Hook (Recommended):**

After initializing your Git repository, install the pre-commit hook:

```bash
# Initialize Git repository (if not already done)
git init

# Install the pre-commit hook
npm run install-hook
# or
node scripts/install-git-hook.js
```

This will automatically:

- Update the changelog before each commit
- Add commit messages to the "Unreleased" section
- Stage the updated CHANGELOG.md automatically

## Release Process

### First Release (v1.0.0)

1. **Push your code to GitHub**:

   ```bash
   git remote add origin https://github.com/gbyat/we-spamfighter.git
   git push -u origin main
   ```

2. **Create the initial release**:

   ```bash
   npm run release:initial
   ```

3. **GitHub Actions** will automatically:

   - Validate code
   - Create ZIP file
   - Create GitHub Release v1.0.0
   - Attach ZIP file

### Subsequent Releases

1. **Make your changes** and commit them:

   ```bash
   git add .
   git commit -m "Add new feature"
   ```

2. **Update changelog** (optional, if not using hook):

   ```bash
   node scripts/update-changelog.js
   git add CHANGELOG.md
   git commit -m "Update changelog"
   ```

3. **Create release**:

   ```bash
   npm run release:patch  # or minor/major
   ```

4. **GitHub Actions** will automatically:

   - Validate code
   - Create ZIP file
   - Create GitHub Release
   - Attach ZIP file

5. **Users** will see:
   - Update notification in WordPress admin
   - One-click update option
   - Changelog in update popup

## Changelog Format

The `CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
## [Unreleased]

- New feature
- Bug fix

## [1.0.1] - 2024-11-21

- Fixed issue with form validation
- Improved performance

[1.0.1]: https://github.com/gbyat/we-spamfighter/releases/tag/v1.0.1
```

## CI/CD Validation

Before a release is created, the following checks run:

- ✅ Plugin header validation
- ✅ PHP syntax check
- ✅ Plugin constants validation
- ✅ CHANGELOG format check
- ✅ Optional: PHP CodeSniffer (if available)

**Releases only happen if all checks pass.**

## GitHub Token (Optional)

If you want to increase the API rate limit:

1. Create a GitHub Personal Access Token (classic)

   - Go to: Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Scope: `public_repo` (read-only is sufficient)

2. Add to WordPress options:
   ```php
   // In wp-config.php or plugin settings
   update_option('we_spamfighter_github_token', 'your-token-here');
   ```

**Note:** Token is optional. Without it, you get 60 requests/hour. With it, 5000 requests/hour.

## Troubleshooting

### Release not showing in WordPress

1. Check if release was created on GitHub
2. Verify ZIP file is attached to release
3. Check GitHub API rate limits (see above)
4. Verify plugin is active

### GitHub Actions failing

1. Check Actions tab in GitHub
2. Look at failed step for error details
3. Common issues:
   - Missing CHANGELOG entry for version
   - Syntax errors in PHP
   - Missing plugin constants

### Version not syncing

Run manually:

```bash
npm run sync-version
```

This will sync version from `package.json` to:

- `we-spamfighter.php` (header and constant)
- `CHANGELOG.md` (if version missing)

## Best Practices

1. **Always update CHANGELOG.md** before releasing
2. **Use semantic versioning** (patch/minor/major)
3. **Test locally** before releasing
4. **Check CI/CD** passes before pushing tags
5. **Keep unreleased changes** in "[Unreleased]" section
6. **Write descriptive commit messages** (they appear in changelog)

## File Structure

```
.github/
  workflows/
    ci.yml              # CI checks on push
    release.yml         # Release creation on tag
scripts/
  release.js           # Release creation script
  sync-version.js      # Version synchronization
  update-changelog.js  # Changelog update script
includes/
  core/
    class-updater.php  # WordPress dashboard updater
```

## Support

For issues or questions about the release system:

- GitHub Issues: https://github.com/gbyat/we-spamfighter/issues
- Documentation: See this file (`RELEASE.md`)
