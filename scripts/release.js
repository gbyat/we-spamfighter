/**
 * Create a release commit and tag (Node/git only — no shell).
 *
 * Usage:
 *   node scripts/release.js patch
 *   node scripts/release.js minor --local
 *   node scripts/release.js major --no-push
 */

const path = require('path');
const { rootDir, readLf, writeLf, runGit, runNode, bumpSemver } = require('./process');
const { loadConfig } = require('./load-config');

const args = process.argv.slice(2);
const releaseType = args.find((arg) => !arg.startsWith('--')) || 'patch';
const localOnly = args.includes('--local') || args.includes('--no-push');

if (!['patch', 'minor', 'major'].includes(releaseType)) {
	console.error('Invalid release type. Use: patch, minor, or major');
	console.error('Optional flags: --local (or --no-push) to tag and zip without pushing.');
	process.exit(1);
}

const config = loadConfig();

console.log(`Creating ${releaseType} release for ${config.name || 'WE Spamfighterin'}...`);
if (localOnly) {
	console.log('Mode: local only (no push to origin)');
}

try {
	const packagePath = path.join(rootDir, 'package.json');
	const packageData = JSON.parse(readLf(packagePath));
	const currentVersion = packageData.version;
	const newVersion = bumpSemver(currentVersion, releaseType);
	const tag = `v${newVersion}`;

	console.log(`Bumping ${releaseType} version from ${currentVersion} to ${newVersion}...`);
	packageData.version = newVersion;
	writeLf(packagePath, `${JSON.stringify(packageData, null, 2)}\n`);

	console.log('Syncing version to plugin file...');
	runNode('sync-version.js');

	console.log('Updating translation files...');
	try {
		runNode('i18n-all.js');
		console.log('Translation files updated');
	} catch (error) {
		console.log('i18n update failed (non-critical)');
	}

	console.log('Building minified assets...');
	try {
		runNode('build-assets.js');
		console.log('Assets built');
	} catch (error) {
		console.log('Asset build failed (non-critical)');
	}

	const existingTag = runGit(['tag', '-l', tag], {
		encoding: 'utf8',
		stdio: 'pipe',
		allowFailure: true,
	});
	if ((existingTag.stdout || '').trim() === tag) {
		console.error(`Tag ${tag} already exists. Bump again or delete the tag manually.`);
		process.exit(1);
	}

	console.log('Adding all changes to git...');
	runGit(['add', '-A']);

	console.log('Committing changes...');
	try {
		runGit(['commit', '-m', `Release ${tag}`]);
	} catch (error) {
		console.log("Nothing to commit (that's okay)");
	}

	console.log('Creating tag...');
	runGit(['tag', '-a', tag, '-m', `Release ${tag}`]);

	console.log('Building local release ZIP...');
	runNode('build-release-zip.js');

	if (localOnly) {
		console.log('');
		console.log(`Release ${tag} created locally (no push).`);
		console.log(`ZIP: releases/${config.slug}-${newVersion}.zip`);
		console.log(`Push later with: git push origin HEAD && git push origin ${tag}`);
		console.log('');
		process.exit(0);
	}

	let branch = 'main';
	try {
		const branchResult = runGit(['rev-parse', '--abbrev-ref', 'HEAD'], {
			encoding: 'utf8',
			stdio: 'pipe',
		});
		branch = (branchResult.stdout || '').trim() || branch;
	} catch (error) {
		// Keep default branch name.
	}

	console.log('Checking if GitHub repository exists...');
	try {
		runGit(['ls-remote', 'origin'], { stdio: 'pipe' });
		console.log('Repository is accessible');
	} catch (error) {
		console.error('');
		console.error('ERROR: GitHub repository not found or not accessible.');
		console.error('Check remote URL (git remote -v) and permissions, or use --local.');
		process.exit(1);
	}

	console.log('Pushing to GitHub...');
	console.log(`   Branch: ${branch}`);
	console.log(`   Tag: ${tag}`);
	runGit(['push', 'origin', branch]);
	runGit(['push', 'origin', tag]);

	console.log('');
	console.log(`Release ${tag} successfully created and pushed.`);
	console.log('GitHub Actions will build the ZIP and publish the release.');
	console.log(`Local ZIP also kept at: releases/${config.slug}-${newVersion}.zip`);
	console.log('https://github.com/gbyat/we-spamfighter/actions');
	console.log('');
} catch (error) {
	console.error('Error during release:', error.message);
	process.exit(1);
}
