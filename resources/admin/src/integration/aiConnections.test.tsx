// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { aiRequest, useAIConnections, verifyAISettings } from './aiConnections';

const fetchMock = vi.fn();
const response = (data: unknown, ok = true) => ({ ok, json: async () => data }) as Response;
let root: Root | undefined;
beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock); fetchMock.mockReset();
  (globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;
  document.head.innerHTML = '<meta name="csrf-token" content="fixture-csrf">';
});
afterEach(async () => { if (root) await act(async () => root!.unmount()); root = undefined; vi.unstubAllGlobals(); });

describe('AI credential API boundary', () => {
  it('saves a new key before verifying the new server version without enabling AI', async () => {
    fetchMock.mockResolvedValueOnce(response({ connection: { version: 7 } }));
    fetchMock.mockResolvedValueOnce(response({ connection: { verified_at: 'fixture-time' } }));
    const saved = vi.fn();
    await verifyAISettings('openai', 6, { api_key: 'fixture-secret', enabled: true }, saved);
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ api_key: 'fixture-secret', enabled: false, version: 6 });
    expect(fetchMock.mock.calls[1][0]).toBe('/admin/api/integration/ai/openai/verify');
    expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({ version: 7 });
    expect(saved).toHaveBeenCalledOnce();
  });
  it('does not verify or clear input when saving the key fails', async () => {
    fetchMock.mockResolvedValueOnce(response({ message: 'Settings changed. Reload.' }, false));
    const saved = vi.fn();
    await expect(verifyAISettings('openai', 6, { api_key: 'fixture-secret' }, saved)).rejects.toThrow('Settings changed');
    expect(fetchMock).toHaveBeenCalledOnce(); expect(saved).not.toHaveBeenCalled();
  });
  it('verifies an already saved key without rewriting its settings', async () => {
    fetchMock.mockResolvedValueOnce(response({ connection: { verified_at: 'fixture-time' } }));
    await verifyAISettings('openai', 6);
    expect(fetchMock).toHaveBeenCalledOnce();
    expect(fetchMock.mock.calls[0][1].method).toBe('POST');
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ version: 6 });
  });
  it('sends credentials only in same-origin CSRF-protected body and prevents response caching', async () => {
    fetchMock.mockResolvedValue(response({ connection: { has_key: true } }));
    await aiRequest('/openai', 'PUT', { api_key: 'fixture-secret' });
    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/admin/api/integration/ai/openai');
    expect(url).not.toContain('fixture-secret');
    expect(options.credentials).toBe('same-origin'); expect(options.cache).toBe('no-store');
    expect(options.headers['X-CSRF-TOKEN']).toBe('fixture-csrf');
    expect(JSON.parse(options.body)).toEqual({ api_key: 'fixture-secret' });
  });
  it('surfaces server validation and never reports a failed operation as success', async () => {
    fetchMock.mockResolvedValue(response({ message: 'Invalid request', errors: { enabled: ['Verify the key first.'] } }, false));
    await expect(aiRequest('/openai', 'PUT', { enabled: true })).rejects.toThrow('Verify the key first.');
  });
  it('loads only server-owned connections and keeps permission failures unavailable', async () => {
    let state: ReturnType<typeof useAIConnections>;
    function Harness() { state = useAIConnections(); return null; }
    fetchMock.mockResolvedValueOnce(response({ message: 'Only the store owner can manage AI credentials.' }, false));
    root = createRoot(document.createElement('div'));
    await act(async () => root!.render(<Harness />));
    expect(state!.loading).toBe(false); expect(state!.connections).toEqual([]);
    expect(state!.error).toContain('store owner');
    fetchMock.mockResolvedValueOnce(response({ connections: [{ provider: 'openai', has_key: true, version: 4 }], compatible_base_urls: [] }));
    await act(async () => state!.reload());
    expect(state!.connections[0].version).toBe(4); expect(state!.error).toBe('');
  });
});
