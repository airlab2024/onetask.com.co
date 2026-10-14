const MAX_LENGTH = 4096;
const MAX_DEPTH = 16;

function validateGlob(pattern) {
    if (typeof pattern !== 'string' || pattern.length > MAX_LENGTH) {
        throw new Error('Build glob exceeds the allowed size.');
    }
    let depth = 0;
    for (let i = 0; i < pattern.length; i++) {
        if (pattern[i] === '\\') { i++; continue; }
        if (pattern[i] === '{' && ++depth > MAX_DEPTH) {
            throw new Error('Build glob exceeds the allowed brace nesting depth.');
        }
        if (pattern[i] === '}' && depth > 0) { depth--; }
    }
}

module.exports = { validateGlob };
