import test from 'node:test';
import assert from 'node:assert/strict';
import { formatScriptSummary, parseScriptSummary } from '../lib/affiliate-script-summary.ts';

const scenes = [{ text: 'Catch attention.' }, { text: 'Show the product.' }, { text: 'Visit the link.' }];

test('summary sections remain a view of scene narration', () => {
  const summary = formatScriptSummary(scenes);
  assert.match(summary, /^HOOK\n\nCatch attention\./);
  assert.match(summary, /CONTENT 1\n\nShow the product\./);
  assert.match(summary, /FINISHING \/ CTA\n\nVisit the link\./);
  assert.deepEqual(parseScriptSummary(summary.replace('Show the product.', 'Show the bridge.'), scenes), ['Catch attention.', 'Show the bridge.', 'Visit the link.']);
});

test('missing sections or empty/oversized narration cannot be submitted', () => {
  const summary = formatScriptSummary(scenes);
  assert.equal(parseScriptSummary(summary.replace('CONTENT 1', 'OTHER'), scenes), null);
  assert.equal(parseScriptSummary(summary.replace('Show the product.', ''), scenes), null);
  assert.equal(parseScriptSummary(summary.replace('Show the product.', 'x'.repeat(501)), scenes), null);
});
