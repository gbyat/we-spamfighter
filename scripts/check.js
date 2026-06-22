/**
 * Lightweight check hints (Node-only).
 */

const target = process.argv[2] || 'all';

if ('php' === target || 'all' === target) {
	console.log('PHP checks: install phpcs locally or use composer require --dev wp-coding-standards/wpcs');
}

if ('js' === target || 'all' === target) {
	console.log('JavaScript checks: run node scripts/build-assets.js');
}
