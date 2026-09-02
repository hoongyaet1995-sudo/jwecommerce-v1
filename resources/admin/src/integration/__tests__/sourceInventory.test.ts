import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { assertInventoryCompleteness, collectComponentPaths } from '../../../scripts/componentInventory.mjs';
import { describe, expect, it } from 'vitest';

const manifest = readFileSync(
  resolve(process.cwd(), '../../docs/admin-react/component-manifest.md'),
  'utf8',
);
const componentFiles = [
  ...manifest.matchAll(/\| `((?:src\/components\/)[^`]+\.(?:tsx|jsx|ts|js))` \|/gi),
].map((match) => match[1]);
const componentsRoot = resolve(process.cwd(), 'src/components');
const requiredComponents = JSON.parse(readFileSync(
  resolve(process.cwd(), 'component-inventory.required.json'),
  'utf8',
)).requiredComponentPaths as string[];
const archiveCompanionFiles = [
  '.env.example',
  'bun.lock',
  'metadata.json',
  'README.md',
  'assets/.aistudio/.gitignore',
];

describe('Google AI Studio component inventory', () => {
  it('is a reproducible exact inventory of all 120 current source components', () => {
    expect(manifest).toContain('npm run inventory:components');
    expect(componentFiles).toHaveLength(120);
    expect(componentFiles).toEqual(collectComponentPaths(componentsRoot, process.cwd()));
    expect(() => assertInventoryCompleteness(componentFiles, requiredComponents)).not.toThrow();
    expect(componentFiles.filter((file) => !existsSync(resolve(process.cwd(), file)))).toEqual([]);
  });

  it('retains the archive companion files at their original paths', () => {
    expect(
      archiveCompanionFiles.filter(
        (file) => !existsSync(resolve(process.cwd(), file)),
      ),
    ).toEqual([]);
  });
});
