const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

/**
 * Pre-commit hook script to automatically update CHANGELOG.md
 * with commit messages since the last release.
 *
 * This script:
 * 1. Checks if there are uncommitted changes to tracked files
 * 2. Gets the last version from CHANGELOG.md or git tag
 * 3. Extracts commit messages since the last version
 * 4. Adds them to CHANGELOG.md in an "Unreleased" section
 *
 * Usage:
 * - Run manually: node scripts/update-changelog.js
 * - As git hook: ln -s ../../scripts/update-changelog.js .git/hooks/pre-commit
 */

const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');

/**
 * Get the last version from CHANGELOG.md or git tag
 */
function getLastVersion() {
    try {
        // Try to get last tag
        const lastTag = execSync('git describe --tags --abbrev=0 2>/dev/null', {
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'ignore']
        }).trim().replace(/^v/, '');
        return lastTag;
    } catch (e) {
        // No tags, try to get from CHANGELOG.md
        if (fs.existsSync(changelogPath)) {
            const changelogContent = fs.readFileSync(changelogPath, 'utf8');
            const versionMatch = changelogContent.match(/## \[(\d+\.\d+\.\d+)\]/);
            if (versionMatch) {
                return versionMatch[1];
            }
        }
        return null;
    }
}

/**
 * Get commit messages since last version
 */
function getCommitsSinceVersion(version) {
    try {
        const range = version ? `v${version}..HEAD` : 'HEAD';
        const commits = execSync(`git log ${range} --oneline --pretty=format:"%s"`, {
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'ignore']
        }).trim().split('\n').filter(line => line.trim() && !line.includes('Release v'));

        return commits;
    } catch (e) {
        return [];
    }
}

/**
 * Get unreleased changes from CHANGELOG.md
 */
function getUnreleasedChanges() {
    if (!fs.existsSync(changelogPath)) {
        return [];
    }

    const changelogContent = fs.readFileSync(changelogPath, 'utf8');
    const unreleasedMatch = changelogContent.match(/## \[Unreleased\][\s\S]*?(?=## \[|$)/);

    if (unreleasedMatch) {
        const unreleasedSection = unreleasedMatch[0];
        const lines = unreleasedSection.split('\n').filter(line =>
            line.trim() &&
            !line.startsWith('#') &&
            !line.startsWith('---')
        );
        return lines.map(line => line.replace(/^[-\*]+\s*/, '')).filter(line => line.trim());
    }

    return [];
}

/**
 * Update CHANGELOG.md with new commits
 */
function updateChangelog() {
    const lastVersion = getLastVersion();
    const newCommits = getCommitsSinceVersion(lastVersion);
    const existingUnreleased = getUnreleasedChanges();

    // Combine existing and new commits (remove duplicates)
    const allChanges = [...new Set([...existingUnreleased, ...newCommits])];

    if (allChanges.length === 0) {
        console.log('ℹ️  No new commits to add to CHANGELOG.md');
        return;
    }

    let changelogContent = '';
    if (fs.existsSync(changelogPath)) {
        changelogContent = fs.readFileSync(changelogPath, 'utf8');
    } else {
        changelogContent = `# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

`;
    }

    // Create unreleased section
    const unreleasedSection = `## [Unreleased]

${allChanges.map(change => `- ${change}`).join('\n')}

`;

    // Check if unreleased section already exists
    if (changelogContent.includes('## [Unreleased]')) {
        // Replace existing unreleased section
        changelogContent = changelogContent.replace(
            /## \[Unreleased\][\s\S]*?(?=## \[|$)/,
            unreleasedSection
        );
    } else {
        // Insert after main heading
        changelogContent = changelogContent.replace(
            /(# Changelog.*?\n\n)/s,
            `$1${unreleasedSection}`
        );
    }

    fs.writeFileSync(changelogPath, changelogContent);
    console.log(`✅ Updated CHANGELOG.md with ${allChanges.length} change(s)`);
    console.log(`   Added: ${newCommits.length} new commit(s)`);
}

// Run if called directly
if (require.main === module) {
    try {
        updateChangelog();
    } catch (error) {
        console.error('❌ Error updating CHANGELOG:', error.message);
        process.exit(1);
    }
}

module.exports = { updateChangelog, getLastVersion, getCommitsSinceVersion };

