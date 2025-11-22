const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

/**
 * Create initial release (v1.0.0) without version bumping.
 * 
 * This script is for the FIRST release only.
 * For subsequent releases, use: npm run release:patch/minor/major
 * 
 * Usage:
 *   node scripts/release-initial.js
 */

console.log('🚀 Creating initial release (v1.0.0) for WE Spamfighter...\n');

try {
    // Read current version from package.json
    const packagePath = path.join(__dirname, '..', 'package.json');
    const packageData = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    const currentVersion = packageData.version;

    console.log(`📦 Current version: ${currentVersion}`);

    // Make sure version is synced to plugin file
    console.log('🔄 Syncing version to plugin file...');
    execSync('node scripts/sync-version.js', { stdio: 'inherit' });

    // Check if CHANGELOG has this version
    const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');
    if (fs.existsSync(changelogPath)) {
        const changelogContent = fs.readFileSync(changelogPath, 'utf8');
        const versionPattern = new RegExp(`## \\[${currentVersion.replace(/\./g, '\\.')}\\]`);
        if (!versionPattern.test(changelogContent)) {
            console.log('⚠️  WARNING: Version not found in CHANGELOG.md');
            console.log(`   Please make sure CHANGELOG.md has an entry for version ${currentVersion}`);
        }
    }

    // Add all changes
    console.log('📦 Adding all changes to git...');
    execSync('git add -A', { stdio: 'inherit' });

    // Check if there are changes to commit
    try {
        const status = execSync('git status --porcelain', { encoding: 'utf8' }).trim();
        if (status) {
            console.log('💾 Committing changes...');
            execSync(`git commit -m "Prepare release v${currentVersion}"`, { stdio: 'inherit' });
        } else {
            console.log('ℹ️  No changes to commit');
        }
    } catch (e) {
        console.log('ℹ️  Nothing to commit (that\'s okay)');
    }

    // Check if tag already exists
    try {
        execSync(`git tag -l "v${currentVersion}"`, { stdio: 'pipe' });
        console.log(`⚠️  WARNING: Tag v${currentVersion} already exists locally!`);
        console.log('   Do you want to delete and recreate it? (Manual step required)');
    } catch (e) {
        // Tag doesn't exist, that's fine
    }

    // Delete existing tag if it exists (local and remote)
    try {
        console.log('🗑️  Removing existing tag if it exists...');
        execSync(`git tag -d v${currentVersion}`, { stdio: 'pipe' });
        execSync(`git push origin :refs/tags/v${currentVersion}`, { stdio: 'pipe' });
    } catch (e) {
        // Tag doesn't exist, that's fine
    }

    // Create annotated tag
    console.log(`🏷️  Creating tag v${currentVersion}...`);
    execSync(`git tag -a "v${currentVersion}" -m "Release v${currentVersion}"`, { stdio: 'inherit' });

    // Detect current branch
    let branch = 'main';
    try {
        branch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
    } catch (e) {
        // Fallback to main
    }

    // Check if remote repository exists
    console.log('🔍 Checking if GitHub repository exists...');
    try {
        execSync('git ls-remote origin', { stdio: 'pipe' });
        console.log('✅ Repository is accessible');
    } catch (e) {
        console.error('');
        console.error('❌ ==========================================');
        console.error('❌ ERROR: GitHub repository not found!');
        console.error('❌ ==========================================');
        console.error('');
        console.error('The remote repository does not exist or is not accessible.');
        console.error('');
        console.error('Please check:');
        console.error('  1. Does the repository exist on GitHub?');
        console.error('  2. Is the remote URL correct? (Check with: git remote -v)');
        console.error('  3. Do you have access to the repository?');
        console.error('');
        console.error('To remove the remote, use: git remote remove origin');
        console.error('To add a new remote, use: git remote add origin <url>');
        console.error('');
        process.exit(1);
    }

    // Push to GitHub
    console.log('⬆️  Pushing to GitHub...');
    console.log(`   Pushing branch: ${branch}`);
    console.log(`   Pushing tag: v${currentVersion}`);

    execSync(`git push origin ${branch}`, { stdio: 'inherit' });
    execSync(`git push origin v${currentVersion}`, { stdio: 'inherit' });

    console.log('');
    console.log('✅ ==========================================');
    console.log(`✅ Initial release v${currentVersion} successfully created!`);
    console.log('✅ ==========================================');
    console.log('');
    console.log('🎉 GitHub Actions will now:');
    console.log('   1. Run CI/CD checks (PHP, linting, etc.)');
    console.log('   2. Create we-spamfighter.zip');
    console.log(`   3. Create GitHub Release v${currentVersion}`);
    console.log('   4. Attach the ZIP file to the release');
    console.log('');
    console.log('🔗 Check progress at:');
    console.log('   https://github.com/gbyat/we-spamfighter/actions');
    console.log('');
    console.log('📝 For future releases, use:');
    console.log('   npm run release:patch   (1.0.0 → 1.0.1)');
    console.log('   npm run release:minor   (1.0.0 → 1.1.0)');
    console.log('   npm run release:major   (1.0.0 → 2.0.0)');
    console.log('');

} catch (error) {
    console.error('❌ Error during release:', error.message);
    process.exit(1);
}

