/**
 * Build or update the POT file via WP-CLI.
 */

const fs = require('fs');
const path = require('path');
const { loadConfig, rootDir } = require('./load-config');
const { runWp } = require('./wp-cli');

const config = loadConfig();
const languagesDir = path.join(rootDir, 'languages');
const slug = String(config.slug);
const textDomain = String(config.textDomain || slug);
const potFile = path.join(languagesDir, `${slug}.pot`);

if (!fs.existsSync(languagesDir)) {
	fs.mkdirSync(languagesDir, { recursive: true });
}

const headers = {
	'Report-Msgid-Bugs-To': `https://github.com/${config.githubRepo}/issues`,
	'Language-Team': 'webentwicklerin <hello@webentwicklerin.at>',
	'Last-Translator': 'Gabriele Laesser <hello@webentwicklerin.at>',
};

try {
	runWp([
		'i18n',
		'make-pot',
		'.',
		potFile,
		`--domain=${textDomain}`,
		`--exclude=${config.potExclude || 'node_modules,vendor,scripts,assets/vendor'}`,
		'--skip-block-json',
		`--headers=${JSON.stringify(headers)}`,
	]);
	console.log(`POT file updated: ${potFile}`);
} catch (error) {
	console.error('POT build failed.');
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
}
