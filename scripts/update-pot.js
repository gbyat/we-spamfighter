/**
 * Update POT file for WordPress translations.
 *
 * @package WeSpamfighter
 */

const wpPot = require('wp-pot');
const path = require('path');

wpPot({
    package: 'WE Spamfighter',
    domain: 'we-spamfighter',
    destFile: path.join(__dirname, '../languages/we-spamfighter.pot'),
    relativeTo: path.join(__dirname, '../'),
    src: [
        '**/*.php',
        '!node_modules/**',
        '!vendor/**',
        '!assets/**',
        '!scripts/**'
    ],
    bugReport: 'https://github.com/gbyat/we-spamfighter/issues',
    team: 'webentwicklerin, Gabriele Laesser',
    lastTranslator: 'webentwicklerin, Gabriele Laesser <info@webentwicklerin.at>',
    headers: {
        'Report-Msgid-Bugs-To': 'https://github.com/gbyat/we-spamfighter/issues',
        'Language-Team': '',
        'Last-Translator': '',
        'Content-Type': 'text/plain; charset=UTF-8'
    }
});

console.log('✓ POT file updated: languages/we-spamfighter.pot');

