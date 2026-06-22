/**
 * Create a release commit and tag, then push to origin (Node/git only — no shell).
 */

const path = require('path');
const { rootDir, readLf, writeLf, runGit, runNode, bumpSemver } = require('./process');

const releaseType = process.argv[2] || 'patch';

if (!['patch', 'minor', 'major'].includes(releaseType)) {
	console.error('Invalid release type. Use: patch, minor, or major');
	process.exit(1);
}

console.log(`Creating ${releaseType} release for WE Spamfighterin...`);

try {
	const packagePath = path.join(rootDir, 'package.json');
	const packageData = JSON.parse(readLf(packagePath));
	const currentVersion = packageData.version;
	const newVersion = bumpSemver(currentVersion, releaseType);

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

	console.log('Adding all changes to git...');
	runGit(['add', '-A']);

	console.log('Committing changes...');
	try {
		runGit(['commit', '-m', `Release v${newVersion}`]);
	} catch (error) {
		console.log("Nothing to commit (that's okay)");
	}

	try {
		console.log('Removing existing tag if it exists...');
		runGit(['tag', '-d', `v${newVersion}`], { allowFailure: true, stdio: 'pipe' });
		runGit(['push', 'origin', `:refs/tags/v${newVersion}`], { allowFailure: true, stdio: 'pipe' });
	} catch (error) {
		// Tag may not exist.
	}

	console.log('Creating tag...');
	runGit(['tag', '-a', `v${newVersion}`, '-m', `Release v${newVersion}`]);

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
		console.error('Check remote URL (git remote -v) and permissions.');
		process.exit(1);
	}

	console.log('Pushing to GitHub...');
	console.log(`   Branch: ${branch}`);
	console.log(`   Tag: v${newVersion}`);
	runGit(['push', 'origin', branch]);
	runGit(['push', 'origin', `v${newVersion}`]);

	console.log('');
	console.log(`Release v${newVersion} successfully created.`);
	console.log('GitHub Actions will build we-spamfighter.zip and publish the release.');
	console.log('https://github.com/gbyat/we-spamfighter/actions');
	console.log('');
} catch (error) {
	console.error('Error during release:', error.message);
	process.exit(1);
}
