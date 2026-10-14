const test = require('node:test');
const assert = require('node:assert/strict');
const { validateGlob } = require('./build-inputs.cjs');

test('repository glob patterns are accepted', () => {
    assert.doesNotThrow(() => validateGlob('./resources/**/*.{blade.php,js}'));
    assert.doesNotThrow(() => validateGlob('./app/Filament/**/*.php'));
});
test('deep nesting is rejected before the dependency parses it', () => {
    assert.throws(() => validateGlob('{'.repeat(500) + 'a,b' + '}'.repeat(500)), /nesting depth/);
});
test('oversized input is rejected before the dependency parses it', () => {
    assert.throws(() => validateGlob('a'.repeat(5000)), /allowed size/);
});
