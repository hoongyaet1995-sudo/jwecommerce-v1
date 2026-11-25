// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import { AIConnectPage } from './IntegrationPages';
vi.mock('../../context/AdminContext', () => ({ useAdmin: () => ({ setCurrentSection: vi.fn() }) }));
it('keeps the requested enable state after verification, then saves it explicitly', async () => {
  (globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;
  let connection = { provider:'openai', label:'OpenAI', has_key:true, enabled:false, support_enabled:true, builder_enabled:false, base_url:null, text_model:'fixture', image_model:null, verified_at:'verified', version:1, models:[{id:'fixture',text:true,image:false}] };
  const fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
    const body = init?.body ? JSON.parse(String(init.body)) : {};
    if (init?.method === 'PUT') connection = {...connection, ...body, version:connection.version+1};
    if (init?.method === 'POST') connection = {...connection, version:connection.version+1};
    return {ok:true,json:async()=>init?.method === 'GET' ? {connections:[{...connection}]} : {connection:{...connection}}} as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
  try {
    await act(async()=>root.render(<AIConnectPage/>));
    const toggle=host.querySelector('[role="switch"]') as HTMLButtonElement;
    await act(async()=>toggle.click());
    const verify=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Verify key & load models'))!;
    await act(async()=>verify.click());
    expect(toggle.getAttribute('aria-checked')).toBe('true');
    expect(connection.enabled).toBe(false);
    expect(host.querySelectorAll('button[type=submit]')).toHaveLength(1);
    const save=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Save settings'))!;
    await act(async()=>save.click());
    expect(connection.enabled).toBe(true);
  } finally {await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('keeps activation locked after a rejected key and unlocks it only after text model selection', async () => {
  (globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;
  let connection = {provider: 'openai', has_key: true, enabled: false, support_enabled: false,
    builder_enabled: false, base_url: null, text_model: null, image_model: null,
    verified_at: null as string | null, version: 1, models: [] as {id: string; text: boolean; image: boolean}[]};
  let reject = true;
  const fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
    if (init?.method === 'POST' && reject) return {ok: false, json: async () => ({message: 'The provider rejected this API key.'})} as Response;
    if (init?.method === 'POST') connection = {...connection, verified_at: 'verified', version: 2,
      models: [{id: 'gpt-5.6-luna', text: true, image: false}, {id: 'other', text: true, image: false}]};
    return {ok: true, json: async () => init?.method === 'GET' ? {connections: [{...connection}]} : {connection}} as Response;
  });
  vi.stubGlobal('fetch', fetchMock);
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  try {
    await act(async () => root.render(<AIConnectPage/>));
    const verify = Array.from(host.querySelectorAll('button')).find(b => b.textContent?.includes('Verify key & load models'))!;
    const toggle = host.querySelector<HTMLButtonElement>('[role=switch]')!;
    const save = host.querySelector<HTMLButtonElement>('button[type=submit]')!;
    const model = host.querySelector<HTMLSelectElement>('#ai-text-model')!;
    await act(async () => verify.click());
    expect(host.querySelector('[role=alert]')?.textContent).toContain('Verification failed. The provider rejected this API key.');
    expect([model.disabled, toggle.disabled, save.disabled]).toEqual([true, true, true]);
    reject = false;
    await act(async () => verify.click());
    expect([...model.options].map(option => option.value)).toEqual(['', 'gpt-5.6-luna', 'other']);
    expect([model.disabled, toggle.disabled, save.disabled]).toEqual([false, true, true]);
    await act(async () => {
      model.value = 'other';
      model.dispatchEvent(new Event('change', {bubbles: true}));
    });
    expect([toggle.disabled, save.disabled]).toEqual([false, false]);
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'PUT')).toBe(false);
  } finally {
    await act(async () => root.unmount());
    host.remove();
    vi.unstubAllGlobals();
  }
});
