const fs = require('fs');
const path = require('path');
const { rootDir, readLf, writeLf, runGit } = require('./process');

const packagePath = path.join(rootDir, 'package.json');
const packageData = JSON.parse(readLf(packagePath));
const version = packageData.version;

console.log(`Syncing version to ${version}...`);

const pluginPath = path.join(rootDir, 'we-spamfighter.php');
let pluginContent = readLf(pluginPath);

pluginContent = pluginContent.replace(
	/Version:\s*\d+\.\d+\.\d+/,
	`Version: ${version}`
);

pluginContent = pluginContent.replace(
	/define\(\s*'WE_SPAMFIGHTER_VERSION',\s*'[^']*'\s*\);/,
	`define('WE_SPAMFIGHTER_VERSION', '${version}');`
);

writeLf(pluginPath, pluginContent);
console.log('Updated we-spamfighter.php');

const readmeMdPath = path.join(rootDir, 'README.md');
if (fs.existsSync(readmeMdPath)) {
	let readmeContent = readLf(readmeMdPath);
	readmeContent = readmeContent.replace(
		/\*\*Stable tag:\*\*\s*\d+\.\d+\.\d+/,
		`**Stable tag:** ${version}`
	);
	writeLf(readmeMdPath, readmeContent);
	console.log(`Updated README.md stable tag to ${version}`);
}

const readmeTxtPath = path.join(rootDir, 'README.txt');
if (fs.existsSync(readmeTxtPath)) {
	let readmeTxtContent = readLf(readmeTxtPath);
	readmeTxtContent = readmeTxtContent.replace(
		/Stable tag:\s*\d+\.\d+\.\d+/,
		`Stable tag: ${version}`
	);
	writeLf(readmeTxtPath, readmeTxtContent);
	console.log(`Updated README.txt stable tag to ${version}`);
}

/**
 * @param {string} lastTag
 * @param {number} limit
 * @return {string}
 */
function getGitCommitLog(lastTag, limit) {
	const separator = '|||COMMIT_SEPARATOR|||';
	const args = lastTag
		? ['log', `${lastTag}..HEAD`, `--pretty=format:${separator}%B`, '--no-merges']
		: ['log', `-${limit}`, `--pretty=format:${separator}%B`, '--no-merges'];

	const result = runGit(args, { encoding: 'utf8', stdio: 'pipe', allowFailure: true });
	if (result.status !== 0) {
		return '';
	}

	return (result.stdout || '').trim();
}

/**
 * @param {string} rawLog
 * @param {Set<string>} existingCommits
 * @param {number} [maxEntries]
 * @return {string}
 */
function formatCommitLog(rawLog, existingCommits, maxEntries = 0) {
	const separator = '|||COMMIT_SEPARATOR|||';
	const allCommits = rawLog
		.split(separator)
		.map((commit) => commit.trim())
		.filter((commit) => commit.length > 0)
		.map((commit) => {
			const lines = commit.split('\n');
			const cleaned = lines.filter((line) => line.trim().length > 0);
			return cleaned.join('\n').trim();
		})
		.filter((commit) => {
			const trimmed = commit.trim();
			return (
				trimmed &&
				!trimmed.match(/^Release v\d+\.\d+\.\d+$/i) &&
				!trimmed.match(/^Bump version/i) &&
				!trimmed.match(/^Version update$/i)
			);
		});

	const newCommits = allCommits.filter((commit) => {
		const firstLine = commit.split('\n')[0].trim();
		return !existingCommits.has(firstLine);
	});

	if (0 === newCommits.length) {
		return '';
	}

	const limitedCommits = maxEntries > 0 ? newCommits.slice(0, maxEntries) : newCommits;

	return limitedCommits
		.map((commit) => {
			const lines = commit.split('\n');
			const subject = lines[0].trim();
			const body = lines.slice(1).filter((line) => line.trim().length > 0);

			if (body.length > 0) {
				return `- ${subject}\n  ${body.map((line) => line.trim()).join('\n  ')}`;
			}

			return `- ${subject}`;
		})
		.join('\n');
}

const changelogPath = path.join(rootDir, 'CHANGELOG.md');
if (!fs.existsSync(changelogPath)) {
	const initialContent = `# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [${version}] - ${new Date().toISOString().split('T')[0]}

### Added
- Initial release of WE Spamfighterin

`;
	writeLf(changelogPath, initialContent);
	console.log('Created CHANGELOG.md');
} else {
	let changelogContent = readLf(changelogPath);
	const versionPattern = new RegExp(`## \\[${version.replace(/\./g, '\\.')}\\]`);

	if (!versionPattern.test(changelogContent)) {
		const dateStr = new Date().toISOString().split('T')[0];
		const existingCommits = new Set();

		changelogContent.split('\n').forEach((line) => {
			const match = line.match(/^-\s+(.+)$/);
			if (match && !line.startsWith('  ')) {
				existingCommits.add(match[1].trim());
			}
		});

		let lastTag = '';
		const tagResult = runGit(['describe', '--tags', '--abbrev=0'], {
			encoding: 'utf8',
			stdio: 'pipe',
			allowFailure: true,
		});
		if (tagResult.status === 0) {
			lastTag = (tagResult.stdout || '').trim();
		}

		let gitLog = '';
		try {
			gitLog = formatCommitLog(getGitCommitLog(lastTag, 20), existingCommits);
		} catch (error) {
			gitLog = '- Version update';
		}

		const unreleasedMatch = changelogContent.match(/## \[Unreleased\]([\s\S]*?)(?=## \[|$)/);
		let unreleasedContent = '';
		if (unreleasedMatch && unreleasedMatch[1]) {
			unreleasedContent = unreleasedMatch[1].trim();
		}

		let changelogEntry = '';
		if (unreleasedContent) {
			changelogEntry = unreleasedContent;
		} else if (gitLog) {
			changelogEntry = gitLog;
		} else {
			try {
				changelogEntry = formatCommitLog(getGitCommitLog('', 20), existingCommits, 10) || '- Version update';
			} catch (error) {
				changelogEntry = '- Version update';
			}
		}

		const newEntry = `## [${version}] - ${dateStr}

${changelogEntry}

`;

		const lines = changelogContent.split('\n');
		const firstHeadingIndex = lines.findIndex((line) => line.startsWith('## ['));

		if (-1 !== firstHeadingIndex) {
			lines.splice(firstHeadingIndex, 0, newEntry);
			changelogContent = lines.join('\n');
		} else {
			changelogContent = changelogContent.replace(/(# Changelog.*?\n\n)/s, `$1${newEntry}`);
		}

		changelogContent = changelogContent.replace(/## \[Unreleased\][\s\S]*?(?=## \[|$)/, '');

		if (!changelogContent.includes(`[${version}]:`)) {
			const releaseLink = `\n[${version}]: https://github.com/gbyat/we-spamfighter/releases/tag/v${version}\n`;
			changelogContent = `${changelogContent.trim()}${releaseLink}`;
		}

		writeLf(changelogPath, changelogContent);
		console.log(`Updated CHANGELOG.md with version ${version}`);
	} else {
		console.log(`Version ${version} already exists in CHANGELOG.md`);
	}
}

console.log(`Version synchronized to ${version}`);
