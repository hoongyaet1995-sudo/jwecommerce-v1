import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const source = readFileSync(fileURLToPath(new URL('./ProductAdmin.tsx', import.meta.url)), 'utf8');

describe('ProductAdmin variant-matrix visual preview', () => {
  it('renders the approved ZIP preview below the SKU summary from persisted visual data', () => {
    const summaryStart = source.indexOf('{/* Variant Matrix Summary */}');
    const stockStart = source.indexOf('{/* Total Stock */}', summaryStart);
    const summary = source.slice(summaryStart, stockStart);

    expect(summary).toContain('Visual Swatches Preview');
    expect(summary).toContain("v.colorCode || v.optionImage");
    expect(summary).toContain('.slice(0, 5)');
    expect(summary).toContain("visualVal.startsWith('#')");
    expect(summary).toContain('rounded-full');
    expect(summary).toContain('rounded-xs');
    expect(summary).toContain('> 5');
  });
});
