import { describe, expect, it, vi } from 'vitest';
import { activateTaxClass } from './taxClassData';

describe('taxClassData', () => {
  it('activates through the canonical endpoint and returns the rehydrated document', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      data: { classes: [], activeTaxClassId: '2' },
    }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    vi.stubGlobal('fetch', fetchMock);

    const result = await activateTaxClass({ csrfToken: 'token', taxClassesUrl: '/admin/api/tax-classes' }, '2');

    expect(fetchMock).toHaveBeenCalledWith('/admin/api/tax-classes/2/activate', expect.objectContaining({ method: 'POST' }));
    expect(result.activeTaxClassId).toBe('2');
  });
});
