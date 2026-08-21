import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import {
  assertInventoryCompleteness,
  collectComponentPaths,
  normalizeComponentPath,
} from '../../../scripts/componentInventory.mjs';
import { describe, expect, it } from 'vitest';

describe('component inventory functions', () => {
  it('collects nested project-supported component extensions with normalized paths', () => {
    const root = mkdtempSync(join(tmpdir(), 'jw-admin-components-'));

    try {
      mkdirSync(join(root, 'src/components/Nested'), { recursive: true });
      writeFileSync(join(root, 'src/components/Primary.tsx'), 'export {};');
      writeFileSync(join(root, 'src/components/Nested/Widget.JSX'), 'export {};');
      writeFileSync(join(root, 'src/components/Nested/Panel.ts'), 'export {};');
      writeFileSync(join(root, 'src/components/Legacy.JS'), 'export {};');
      writeFileSync(join(root, 'src/components/Ignore.css'), '');

      expect(collectComponentPaths(join(root, 'src/components'), root)).toEqual([
        'src/components/Legacy.JS',
        'src/components/Nested/Panel.ts',
        'src/components/Nested/Widget.JSX',
        'src/components/Primary.tsx',
      ]);
      expect(normalizeComponentPath('.\\src\\components\\Nested\\Widget.JSX')).toBe('src/components/Nested/Widget.JSX');
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });

  it('rejects case-fold collisions before a manifest can be emitted', () => {
    expect(() => assertInventoryCompleteness(
      ['src/components/Foo.tsx', 'src/components/foo.TSX'],
      ['src/components/Foo.tsx'],
    )).toThrow(/case-fold collision/i);
  });

  it('rejects a missing independently required component even when another source file replaces it', () => {
    expect(() => assertInventoryCompleteness(
      ['src/components/Replacement.tsx'],
      ['src/components/Required.tsx'],
    )).toThrow(/missing required component/i);

    expect(() => assertInventoryCompleteness(
      ['src/components/required.TSX'],
      ['src/components/Required.tsx'],
    )).toThrow(/missing required component/i);
  });
});
