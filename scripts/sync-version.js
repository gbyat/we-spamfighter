const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Read package.json
const packagePath = path.join(__dirname, '..', 'package.json');
const packageData = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
const version = packageData.version;

console.log(`📦 Syncing version to ${version}...`);

// Read plugin file
const pluginPath = path.join(__dirname, '..', 'we-spamfighter.php');
let pluginContent = fs.readFileSync(pluginPath, 'utf8');

// Update version in plugin file header
pluginContent = pluginContent.replace(
    /Version:\s*\d+\.\d+\.\d+/,
    `Version: ${version}`
);

// Update WE_SPAMFIGHTER_VERSION constant
pluginContent = pluginContent.replace(
    /define\(\s*'WE_SPAMFIGHTER_VERSION',\s*'[^']*'\s*\);/,
    `define('WE_SPAMFIGHTER_VERSION', '${version}');`
);

// Write updated plugin file
fs.writeFileSync(pluginPath, pluginContent);
console.log(`✅ Updated we-spamfighter.php`);

// Update CHANGELOG.md
const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');
if (!fs.existsSync(changelogPath)) {
    // Create initial CHANGELOG.md
    const initialContent = `# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [${version}] - ${new Date().toISOString().split('T')[0]}

### Added
- Initial release of WE Spamfighter

`;
    fs.writeFileSync(changelogPath, initialContent);
    console.log(`📝 Created CHANGELOG.md`);
} else {
    let changelogContent = fs.readFileSync(changelogPath, 'utf8');

    // Check if this version already exists in changelog
    const versionPattern = new RegExp(`## \\[${version.replace(/\./g, '\\.')}\\]`);
    if (!versionPattern.test(changelogContent)) {
        // Get current date
        const dateStr = new Date().toISOString().split('T')[0];

        // Get git commits since last tag
        let gitLog = '';
        try {
            // First, try to get the last tag
            let lastTag = '';
            try {
                lastTag = execSync('git describe --tags --abbrev=0', {
                    encoding: 'utf8',
                    stdio: ['pipe', 'pipe', 'ignore']
                }).trim();
            } catch (e) {
                // No tags yet, use all commits
                lastTag = '';
            }

            // Get commits since last tag (or last 10 if no tags)
            const gitCommand = lastTag
                ? `git log ${lastTag}..HEAD --oneline --pretty=format:"- %s"`
                : 'git log -10 --oneline --pretty=format:"- %s"';

            gitLog = execSync(gitCommand, {
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'ignore']
            }).trim();
        } catch (e) {
            // Fallback if git fails
            gitLog = '- Version update';
        }

        // Create new changelog entry
        const newEntry = `## [${version}] - ${dateStr}

${gitLog || '- Version update'}

`;

        // Insert after the first heading (main title)
        const lines = changelogContent.split('\n');
        const firstHeadingIndex = lines.findIndex(line => line.startsWith('## ['));

        if (firstHeadingIndex !== -1) {
            lines.splice(firstHeadingIndex, 0, newEntry);
            changelogContent = lines.join('\n');
        } else {
            // No existing entries, add after main heading
            changelogContent = changelogContent.replace(
                /(# Changelog.*?\n\n)/s,
                `$1${newEntry}`
            );
        }

        // Add release link at the bottom if it doesn't exist
        if (!changelogContent.includes(`[${version}]:`)) {
            const releaseLink = `\n[${version}]: https://github.com/gbyat/we-spamfighter/releases/tag/v${version}\n`;
            changelogContent = changelogContent.trim() + releaseLink;
        }

        fs.writeFileSync(changelogPath, changelogContent);
        console.log(`📝 Updated CHANGELOG.md with version ${version}`);
    } else {
        console.log(`ℹ️  Version ${version} already exists in CHANGELOG.md`);
    }
}

console.log(`✅ Version synchronized to ${version}`);

