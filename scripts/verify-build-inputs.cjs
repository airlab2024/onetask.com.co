const { validateGlob } = require('./build-inputs.cjs');
const config = require('../tailwind.config.js');
const files = Array.isArray(config.content) ? config.content : config.content?.files;
if (!Array.isArray(files)) { throw new Error('Build inputs must be an explicit list of repository globs.'); }
files.forEach(validateGlob);
console.log(`Verified ${files.length} bounded build patterns. Runtime uploads and database values are not build inputs.`);
