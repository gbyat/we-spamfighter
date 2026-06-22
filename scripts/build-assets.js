/**
 * Minify CSS and JavaScript assets (pure Node — no shell).
 */

const fs = require('fs');
const path = require('path');
const CleanCSS = require('clean-css');
const { minify } = require('terser');
const { rootDir, writeLf, readLf } = require('./process');

const assetsDir = path.join(rootDir, 'assets');
const cssDir = path.join(assetsDir, 'css');
const jsDir = path.join(assetsDir, 'js');

console.log('Building minified assets...\n');

/**
 * @param {string} inputPath
 * @param {string} outputPath
 */
function minifyCssFile(inputPath, outputPath) {
	const input = readLf(inputPath);
	const output = new CleanCSS({}).minify(input);
	if (output.errors && output.errors.length > 0) {
		throw new Error(output.errors.join('; '));
	}
	writeLf(outputPath, `${output.styles}\n`);
}

/**
 * @param {string} inputPath
 * @param {string} outputPath
 */
async function minifyJsFile(inputPath, outputPath) {
	const input = readLf(inputPath);
	const result = await minify(input, { compress: true, mangle: true });
	if (!result.code) {
		throw new Error(`Terser returned empty output for ${inputPath}`);
	}
	writeLf(outputPath, `${result.code}\n`);
}

/**
 * @param {string} dir
 * @param {string} extension
 * @param {string} minExtension
 * @return {string[]}
 */
function listSourceFiles(dir, extension, minExtension) {
	if (!fs.existsSync(dir)) {
		return [];
	}

	return fs.readdirSync(dir).filter(
		(file) => file.endsWith(extension) && !file.endsWith(minExtension)
	);
}

async function main() {
	if (fs.existsSync(cssDir)) {
		console.log('Minifying CSS files...');
		const cssFiles = listSourceFiles(cssDir, '.css', '.min.css');

		cssFiles.forEach((file) => {
			const inputPath = path.join(cssDir, file);
			const outputPath = path.join(cssDir, file.replace('.css', '.min.css'));

			try {
				minifyCssFile(inputPath, outputPath);
				const originalSize = fs.statSync(inputPath).size;
				const minifiedSize = fs.statSync(outputPath).size;
				const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
				console.log(`   OK ${file} -> ${file.replace('.css', '.min.css')} (${savings}% smaller)`);
			} catch (error) {
				console.error(`   Failed to minify ${file}: ${error.message}`);
				process.exit(1);
			}
		});
	}

	if (fs.existsSync(jsDir)) {
		console.log('\nMinifying JavaScript files...');
		const jsFiles = listSourceFiles(jsDir, '.js', '.min.js');

		for (const file of jsFiles) {
			const inputPath = path.join(jsDir, file);
			const outputPath = path.join(jsDir, file.replace('.js', '.min.js'));

			try {
				await minifyJsFile(inputPath, outputPath);
				const originalSize = fs.statSync(inputPath).size;
				const minifiedSize = fs.statSync(outputPath).size;
				const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
				console.log(`   OK ${file} -> ${file.replace('.js', '.min.js')} (${savings}% smaller)`);
			} catch (error) {
				console.error(`   Failed to minify ${file}: ${error.message}`);
				process.exit(1);
			}
		}
	}

	console.log('\nAsset build complete.');
}

main().catch((error) => {
	console.error(error instanceof Error ? error.message : String(error));
	process.exit(1);
});
