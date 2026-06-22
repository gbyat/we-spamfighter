const { runNode } = require('./process');

runNode('seed-locale.js');
runNode('update-po.js');
