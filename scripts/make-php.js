/**
 * Compile PHP translation files (.l10n.php) from PO/MO catalogs.
 */

const fs = require('fs');
const path = require('path');
const { rootDir } = require('./load-config');
const { runWp } = require('./wp-cli');

const languagesDir = path.join(rootDir, 'languages');

if (!fs.existsSync(languagesDir)) {
	console.error(`Languages directory not found: ${languagesDir}`);
	process.exit(1);
}

try {
	runWp(['i18n', 'make-php', languagesDir]);
	console.log(`PHP translation files updated in: ${languagesDir}`);
} catch (error) {
	console.error('WP-CLI PHP translation build failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
