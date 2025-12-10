const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

/**
 * Build script to minify CSS and JavaScript assets
 * Creates .min versions of all CSS and JS files in assets/
 */

const assetsDir = path.join(__dirname, '..', 'assets');
const cssDir = path.join(assetsDir, 'css');
const jsDir = path.join(assetsDir, 'js');

console.log('🔨 Building minified assets...\n');

// Check if required tools are available (prefer local node_modules)
function checkTool(globalCommand, localCommand, installCommand) {
    // Try local first (from node_modules/.bin)
    const localPath = path.join(__dirname, '..', 'node_modules', '.bin', localCommand);
    if (fs.existsSync(localPath)) {
        return localPath;
    }

    // Try global
    try {
        execSync(`${globalCommand} --version`, { stdio: 'ignore' });
        return globalCommand;
    } catch (e) {
        console.warn(`⚠️  ${globalCommand} not found. Install with: ${installCommand}`);
        return null;
    }
}

const cleanCssCmd = checkTool('cleancss', 'cleancss', 'npm install -D clean-css-cli');
const terserCmd = checkTool('terser', 'terser', 'npm install -D terser');
const hasCleanCss = cleanCssCmd !== null;
const hasTerser = terserCmd !== null;

if (!hasCleanCss && !hasTerser) {
    console.error('❌ No minification tools found!');
    console.error('   Install with: npm install -g clean-css-cli terser');
    process.exit(1);
}

// Minify CSS files
if (hasCleanCss && fs.existsSync(cssDir)) {
    console.log('📦 Minifying CSS files...');
    const cssFiles = fs.readdirSync(cssDir).filter(file =>
        file.endsWith('.css') && !file.endsWith('.min.css')
    );

    cssFiles.forEach(file => {
        const inputPath = path.join(cssDir, file);
        const outputFile = file.replace('.css', '.min.css');
        const outputPath = path.join(cssDir, outputFile);

        try {
            execSync(`"${cleanCssCmd}" -o "${outputPath}" "${inputPath}"`, { stdio: 'pipe' });
            const originalSize = fs.statSync(inputPath).size;
            const minifiedSize = fs.statSync(outputPath).size;
            const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
            console.log(`   ✅ ${file} → ${outputFile} (${savings}% smaller)`);
        } catch (e) {
            console.error(`   ❌ Failed to minify ${file}`);
        }
    });
}

// Minify JavaScript files
if (hasTerser && fs.existsSync(jsDir)) {
    console.log('\n📦 Minifying JavaScript files...');
    const jsFiles = fs.readdirSync(jsDir).filter(file =>
        file.endsWith('.js') && !file.endsWith('.min.js')
    );

    jsFiles.forEach(file => {
        const inputPath = path.join(jsDir, file);
        const outputFile = file.replace('.js', '.min.js');
        const outputPath = path.join(jsDir, outputFile);

        try {
            execSync(`"${terserCmd}" "${inputPath}" -o "${outputPath}" --compress --mangle`, { stdio: 'pipe' });
            const originalSize = fs.statSync(inputPath).size;
            const minifiedSize = fs.statSync(outputPath).size;
            const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
            console.log(`   ✅ ${file} → ${outputFile} (${savings}% smaller)`);
        } catch (e) {
            console.error(`   ❌ Failed to minify ${file}`);
        }
    });
}

console.log('\n✅ Asset build complete!');

