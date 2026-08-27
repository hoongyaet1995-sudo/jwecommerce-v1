import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const source = readFileSync(fileURLToPath(new URL('./ProductEditorModal.tsx', import.meta.url)), 'utf8');

describe('ProductEditorModal Variant Matrix source fidelity', () => {
  it('keeps URL Slug in Tab 1 with the storefront address prefix and an editable handle field', () => {
    const basicStart = source.indexOf('{activeTab === \'basic\'');
    const categoryStart = source.indexOf('Primary Category', basicStart);
    const urlSlug = source.indexOf('URL Slug', basicStart);
    const storefrontPrefix = source.indexOf('/products/', basicStart);

    expect(urlSlug).toBeGreaterThan(basicStart);
    expect(storefrontPrefix).toBeGreaterThan(urlSlug);
    expect(urlSlug).toBeLessThan(categoryStart);
  });

  it('keeps each option control row above its visual-value row from the approved ZIP', () => {
    const optionStart = source.indexOf('{formData.options.map((opt) =>');
    const header = source.indexOf('border-b border-neutral-100 dark:border-neutral-800 pb-2', optionStart);
    const displayMode = source.indexOf('Display Mode:', optionStart);
    const values = source.indexOf('Values with 2-choose-1 Visual Swatches', optionStart);
    const removeDimension = source.indexOf('title="Remove Dimension"', optionStart);

    expect(optionStart).toBeGreaterThan(-1);
    expect(header).toBeGreaterThan(optionStart);
    expect(displayMode).toBeGreaterThan(header);
    expect(removeDimension).toBeGreaterThan(displayMode);
    expect(values).toBeGreaterThan(removeDimension);
  });

  it('auto-assigns the ZIP colour visual when a recognised option value is added', () => {
    const addValueStart = source.indexOf('const addOptionValue');
    const addValueEnd = source.indexOf('const removeOptionValue', addValueStart);
    const addValue = source.slice(addValueStart, addValueEnd);

    expect(addValue).toContain('const guessed = guessColorCode(val)');
    expect(addValue).toContain("newVisuals[val] = { visualType: 'color', colorCode: guessed }");
    expect(addValue).toContain('valueVisuals: newVisuals');
  });

  it('preserves a typed zero package dimension for server-side validation', () => {
    const packageInputs = source.slice(source.indexOf('placeholder="L"'), source.indexOf('checked={variant.enabled}'));

    expect(packageInputs).toContain("packageDimensionValue(e.target.value)");
  });
});
