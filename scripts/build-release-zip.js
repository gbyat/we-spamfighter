/**
 * Build the distributable plugin ZIP (local or CI).
 *
 * Usage:
 *   node scripts/build-release-zip.js
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { loadConfig, rootDir } = require('./load-config');

const config = loadConfig();
const slug = String(config.slug);
const zipInclude = config.zipInclude || { files: [], directories: [] };
const stagingDir = path.join(rootDir, slug);
const zipPath = path.join(rootDir, `${slug}.zip`);

/**
 * @param {string} source
 * @param {string} target
 */
function copyIfExists(source, target) {
	const sourcePath = path.join(rootDir, source);
	if (!fs.existsSync(sourcePath)) {
		return;
	}

	fs.cpSync(sourcePath, target, { recursive: true });
}

/**
 * @return {Promise<void>}
 */
function createZipArchive() {
	return new Promise((resolve, reject) => {
		if (fs.existsSync(zipPath)) {
			fs.unlinkSync(zipPath);
		}

		const output = fs.createWriteStream(zipPath);
		const archive = archiver('zip', { zlib: { level: 9 } });

		output.on('close', resolve);
		archive.on('error', reject);
		archive.pipe(output);
		archive.directory(stagingDir, slug);
		archive.finalize();
	});
}

async function main() {
	if (fs.existsSync(stagingDir)) {
		fs.rmSync(stagingDir, { recursive: true, force: true });
	}
	fs.mkdirSync(stagingDir, { recursive: true });

	(zipInclude.files || []).forEach((fileName) => {
		copyIfExists(fileName, path.join(stagingDir, fileName));
	});

	(zipInclude.directories || []).forEach((dirName) => {
		copyIfExists(dirName, path.join(stagingDir, dirName));
	});

	try {
		await createZipArchive();
	} catch (error) {
		console.error('ZIP creation failed.');
		console.error(error instanceof Error ? error.message : String(error));
		process.exit(1);
	}

	fs.rmSync(stagingDir, { recursive: true, force: true });
	console.log(`Release ZIP created: ${slug}.zip`);
}

main().catch((error) => {
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
});
