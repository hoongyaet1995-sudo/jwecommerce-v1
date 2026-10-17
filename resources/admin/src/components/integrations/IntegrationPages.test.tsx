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
    const verify=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Save & Verify Models'))!;
    await act(async()=>verify.click());
    expect(toggle.getAttribute('aria-checked')).toBe('true');
    expect(connection.enabled).toBe(false);
    const save=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Save AI Settings'))!;
    await act(async()=>save.click());
    expect(connection.enabled).toBe(true);
  } finally {await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
