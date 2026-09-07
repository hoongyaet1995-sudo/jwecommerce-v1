import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { parseDraftImport } from '../pageImport';

const fixture = JSON.parse(readFileSync(new URL('../../../../../tests/Fixtures/Cms/page-document-all-sections.v1.json', import.meta.url), 'utf8'));

describe('CMS ZIP 31 motion contract', () => {
  it('keeps existing documents without a motion field valid and unchanged', () => {
    expect(parseDraftImport(JSON.stringify(fixture), 'motion-test')).toEqual(fixture);
  });

  it.each(['subtle-lift', 'fade-in', 'smooth-zoom', 'dynamic-pop', 'none'])('preserves %s for every section type', (motionPreset) => {
    const document = { ...fixture, sections: fixture.sections.map((section: object) => ({ ...section, motionPreset })) };
    expect(parseDraftImport(JSON.stringify(document), 'motion-test')).toEqual(document);
  });

  it('rejects unknown motion presets', () => {
    const document = { ...fixture, sections: fixture.sections.map((section: object) => ({ ...section, motionPreset: 'invented-effect' })) };
    expect(() => parseDraftImport(JSON.stringify(document), 'motion-test')).toThrow();
  });
});
