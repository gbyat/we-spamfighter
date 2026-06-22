const fs = require('fs');
const path = require('path');
const { rootDir, readLf, writeLf } = require('./process');

/**
 * Update "Tested up to" version across all files in the project
 * 
 * Usage:
 *   node scripts/update-tested-version.js 6.9
 *   node scripts/update-tested-version.js 6.10
 */

const newVersion = process.argv[2];

if (!newVersion || !/^\d+\.\d+(\.\d+)?$/.test(newVersion)) {
    console.error('❌ Invalid version format');
    console.error('   Usage: node scripts/update-tested-version.js <version>');
    console.error('   Example: node scripts/update-tested-version.js 6.9');
    process.exit(1);
}

console.log(`🔄 Updating "Tested up to" to ${newVersion}...\n`);

// Files to update with their patterns
const filesToUpdate = [
    {
        path: path.join(rootDir, 'we-spamfighter.php'),
        pattern: /Tested up to:\s*\d+\.\d+(\.\d+)?/,
        replacement: `Tested up to: ${newVersion}`
    },
    {
        path: path.join(rootDir, 'README.md'),
        pattern: /\*\*Tested up to:\*\*\s*\d+\.\d+(\.\d+)?/,
        replacement: `**Tested up to:** ${newVersion}`
    },
    {
        path: path.join(rootDir, 'README.txt'),
        pattern: /Tested up to:\s*\d+\.\d+(\.\d+)?/,
        replacement: `Tested up to: ${newVersion}`
    },
    {
        path: path.join(rootDir, 'includes', 'core', 'class-updater.php'),
        patterns: [
            {
                // Pattern for: $tested = ... ?? '6.9'
                pattern: /(\$tested\s*=\s*[^?]+\?\s*\([^)]+\)\s*:\s*\([^)]+\s*\?\?\s*')(\d+\.\d+(\.\d+)?)('\))/,
                replacement: `$1${newVersion}$4`
            },
            {
                // Pattern for: 'tested' => $this->plugin['Tested up to'] ?? '6.9'
                pattern: /('tested'\s*=>\s*\$this->plugin\['Tested up to'\]\s*\?\?\s*')(\d+\.\d+(\.\d+)?)(')/,
                replacement: `$1${newVersion}$4`
            }
        ]
    }
];

let updatedFiles = 0;
let totalReplacements = 0;

filesToUpdate.forEach(fileInfo => {
    const filePath = fileInfo.path;

    if (!fs.existsSync(filePath)) {
        console.warn(`⚠️  File not found: ${filePath}`);
        return;
    }

    let content = readLf(filePath);
    let fileChanged = false;
    let replacements = 0;

    if (fileInfo.patterns) {
        // Multiple patterns for one file
        fileInfo.patterns.forEach(({ pattern, replacement }) => {
            const matches = content.match(pattern);
            if (matches) {
                content = content.replace(pattern, replacement);
                fileChanged = true;
                replacements += matches.length;
            }
        });
    } else if (fileInfo.pattern) {
        // Single pattern
        const matches = content.match(fileInfo.pattern);
        if (matches) {
            content = content.replace(fileInfo.pattern, fileInfo.replacement);
            fileChanged = true;
            replacements = matches.length;
        }
    }

    if (fileChanged) {
        writeLf(filePath, content);
        const relativePath = path.relative(process.cwd(), filePath);
        console.log(`✅ Updated ${relativePath} (${replacements} replacement(s))`);
        updatedFiles++;
        totalReplacements += replacements;
    } else {
        const relativePath = path.relative(process.cwd(), filePath);
        console.log(`ℹ️  No changes needed in ${relativePath}`);
    }
});

console.log(`\n✅ Done! Updated ${updatedFiles} file(s) with ${totalReplacements} total replacement(s)`);
console.log(`\n📋 Summary:`);
console.log(`   New "Tested up to" version: ${newVersion}`);
console.log(`   Files checked: ${filesToUpdate.length}`);
console.log(`   Files updated: ${updatedFiles}`);

