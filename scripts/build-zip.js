/**
 * Build assets then package the plugin ZIP into releases/.
 */

const { runNode } = require('./process');

runNode('build-assets.js');
runNode('build-release-zip.js');
