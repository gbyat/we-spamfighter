const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

/**
 * Install Git pre-commit hook for automatic changelog updates.
 *
 * This script:
 * 1. Checks if a Git repository exists
 * 2. Creates .git/hooks directory if needed
 * 3. Creates pre-commit hook that runs update-changelog.js
 * 4. Makes the hook executable
 *
 * Usage:
 *   node scripts/install-git-hook.js
 */

const projectRoot = path.join(__dirname, '..');
const gitDir = path.join(projectRoot, '.git');
const hooksDir = path.join(gitDir, 'hooks');
const preCommitHook = path.join(hooksDir, 'pre-commit');
const updateChangelogScript = path.join(__dirname, 'update-changelog.js');

console.log('🔧 Installing Git pre-commit hook for automatic changelog updates...\n');

// Check if Git repository exists
if (!fs.existsSync(gitDir)) {
    console.error('❌ Error: No Git repository found!');
    console.error('   Please run "git init" first.\n');
    process.exit(1);
}

// Create hooks directory if it doesn't exist
if (!fs.existsSync(hooksDir)) {
    fs.mkdirSync(hooksDir, { recursive: true });
    console.log('✅ Created .git/hooks directory');
}

// Create pre-commit hook
const hookContent = `#!/bin/sh
# Git pre-commit hook for automatic changelog updates
# This hook automatically updates CHANGELOG.md with commit messages

# Run the changelog update script
node "${updateChangelogScript.replace(/\\/g, '/')}"

# Stage the updated CHANGELOG.md
git add CHANGELOG.md

# Allow the commit to proceed
exit 0
`;

// Windows version (for Git Bash)
const hookContentWindows = `#!/bin/sh
# Git pre-commit hook for automatic changelog updates
# This hook automatically updates CHANGELOG.md with commit messages

# Get the project root directory
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

# Run the changelog update script
node "${updateChangelogScript.replace(/\\/g, '/')}"

# Stage the updated CHANGELOG.md
git add CHANGELOG.md

# Allow the commit to proceed
exit 0
`;

try {
    // Write the hook file
    fs.writeFileSync(preCommitHook, hookContentWindows, 'utf8');
    console.log('✅ Created pre-commit hook');

    // Make it executable (Unix/Linux/Mac)
    try {
        execSync(`chmod +x "${preCommitHook}"`, { stdio: 'ignore' });
        console.log('✅ Made hook executable');
    } catch (e) {
        // On Windows, chmod might not work, but Git will handle it
        console.log('ℹ️  Note: On Windows, Git will handle hook execution');
    }

    console.log('\n✅ Pre-commit hook installed successfully!');
    console.log('\n📝 What happens now:');
    console.log('   - Every time you commit, CHANGELOG.md will be automatically updated');
    console.log('   - Commit messages will be added to the "Unreleased" section');
    console.log('   - The updated CHANGELOG.md will be automatically staged');
    console.log('\n💡 Tip: To skip the hook temporarily, use:');
    console.log('   git commit --no-verify\n');

} catch (error) {
    console.error('❌ Error installing hook:', error.message);
    process.exit(1);
}

