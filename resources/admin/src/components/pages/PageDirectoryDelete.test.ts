import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

describe('CMS directory deletion confirmation', () => {
  it('mounts the confirmation in the directory branch where delete is requested', () => {
    const source = readFileSync(new URL('./PageAdmin.tsx', import.meta.url), 'utf8');
    const directory = source.split('// VIEW 1: PAGE DIRECTORY')[1].split('// VIEW 2: VISUAL LAYOUT EDITOR')[0];
    expect(directory).toContain('setPageToDelete(page)');
    expect(directory).toContain('isOpen={!!pageToDelete}');
    expect(directory).toContain('deletePage(pageToDelete.id)');
    expect(directory).toContain('disabled={page.isHomepage}');
  });
});
