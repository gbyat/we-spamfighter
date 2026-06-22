/**
 * Full i18n pipeline (Node-only, no npm shell chains).
 */

const { runNode } = require('./process');

const steps = [
	'build-assets.js',
	'update-pot.js',
	'seed-locale.js',
	'update-po.js',
	'make-mo.js',
	'make-php.js',
];

steps.forEach((script) => {
	runNode(script);
});

try {
	runNode('normalize-translation-encoding.js', ['--check']);
} catch (error) {
	console.error('i18n encoding check failed.');
	throw error;
}

console.log('i18n pipeline complete.');
