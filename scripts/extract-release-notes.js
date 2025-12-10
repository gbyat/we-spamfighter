const fs = require('fs');
const path = require('path');

/**
 * Extract release notes from CHANGELOG.md for a specific version
 * Usage: node scripts/extract-release-notes.js <version> <output-file>
 */

const version = process.argv[2];
const outputFile = process.argv[3] || 'release_notes.txt';

if (!version) {
    console.error('❌ Version argument required');
    console.error('   Usage: node scripts/extract-release-notes.js <version> [output-file]');
    process.exit(1);
}

const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');

if (!fs.existsSync(changelogPath)) {
    console.error(`❌ CHANGELOG.md not found at ${changelogPath}`);
    process.exit(1);
}

const changelogContent = fs.readFileSync(changelogPath, 'utf8');

// Escape version for regex (e.g., 1.1.6 becomes 1\.1\.6)
const escapedVersion = version.replace(/\./g, '\\.');
const versionPattern = new RegExp(`## \\[${escapedVersion}\\] - [0-9-]+([\\s\\S]*?)(?=## \\[|$)`);
const match = changelogContent.match(versionPattern);

let notes = '';

if (match && match[1]) {
    notes = match[1].trim();
    // Remove excessive empty lines
    notes = notes.replace(/\n{3,}/g, '\n\n');
} else {
    notes = `Release v${version}`;
    console.warn(`⚠️  Version ${version} not found in CHANGELOG.md, using default`);
}

// Write to output file
fs.writeFileSync(outputFile, notes, 'utf8');
console.log(`✅ Release notes extracted to ${outputFile}`);

