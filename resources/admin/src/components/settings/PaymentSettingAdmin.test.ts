import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const source = readFileSync(fileURLToPath(new URL('./PaymentSettingAdmin.tsx', import.meta.url)), 'utf8');

describe('payment settings form hydration contract', () => {
  it('re-hydrates editable bank and COD forms after AdminContext loads persisted configuration', () => {
    expect(source).toContain("import React, { useEffect, useState } from 'react';");
    expect(source).toContain('useEffect(() => {\n    setBankForm({ ...bankTransferConfig });\n  }, [bankTransferConfig]);');
    expect(source).toContain('useEffect(() => {\n    setCodForm({ ...codConfig });\n  }, [codConfig]);');
  });

  it('allows zero as the unlimited COD maximum accepted by the Laravel eligibility rule', () => {
    expect(source).toContain('min="0"\n                    value={codForm.maxOrderAmount}');
  });
});
