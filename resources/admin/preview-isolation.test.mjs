import { test } from 'vitest';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const base = new URL('./src/previews/marketplace28/', import.meta.url);
test('preview provider has no ambient persistence or network mutations', () => {
  const provider = fs.readFileSync(new URL('context/AdminContext.tsx', base), 'utf8');
  assert.doesNotMatch(provider, /\blocalStorage\b|\bfetch\s*\(|XMLHttpRequest|axios|\/admin\/api\//);
  assert.match(provider, /previewStorage/);
  assert.match(provider, /PreviewContext/);
});
