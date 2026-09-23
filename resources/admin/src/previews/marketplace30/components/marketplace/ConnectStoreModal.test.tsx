// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import {ConnectStoreModal} from './ConnectStoreModal';

it('sends the chosen Shopee environment without asking for an unverified name or region', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  const authorize = vi.fn().mockResolvedValue(undefined);
  try {
    await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}}
      requestAuthorization={authorize} oauthAvailability={{shopee:true}}/>));
    expect(container.textContent).not.toContain('Store Display Name');
    expect(container.textContent).not.toContain('Platform Region / Country');
    const production = Array.from(container.querySelectorAll('button')).find(button => button.textContent?.includes('Production'))!;
    await act(async () => production.click());
    const proceed = Array.from(container.querySelectorAll('button')).find(button => button.textContent?.includes('Proceed to Official Authorization'))!;
    await act(async () => proceed.click());
    expect(authorize).toHaveBeenCalledExactlyOnceWith('shopee', undefined, 'production');
  } finally {
    await act(async () => root.unmount());
    vi.unstubAllGlobals();
  }
});

// Operational modal must render without loading or mounting the demo AdminProvider.

it('keeps Lazada reconnect bound to the existing store and platform', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  const authorize = vi.fn().mockResolvedValue(undefined);
  try {
    await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} mode="reauthorize"
      existingConnection={{id:'42', platform:'lazada', shopName:'Existing MY Store', shopId:'7001', region:'MY',
        status:'expired', appKey:'', appSecret:'', accessToken:'', refreshToken:'', tokenExpiresAt:'',
        lastSyncAt:'', autoSyncStock:false, autoPullOrders:false}}
      requestAuthorization={authorize} oauthAvailability={{shopee:true, lazada:true}}/>));
    const button = (label: string) => Array.from(container.querySelectorAll('button')).find(b => b.textContent?.includes(label))!;
    expect(button('Shopee').disabled).toBe(true);
    await act(async () => button('Proceed to Official Authorization').click());
    expect(authorize).toHaveBeenCalledExactlyOnceWith('lazada', '42', undefined);
    expect(container.textContent).not.toContain('Store Connected Successfully!');
  } finally {
    await act(async () => root.unmount());
    vi.unstubAllGlobals();
  }
});

it('selects available Lazada and submits its authorization without simulating a connection', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  const authorize = vi.fn().mockResolvedValue(undefined);
  try {
    await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}}
      requestAuthorization={authorize} oauthAvailability={{shopee: true, lazada: true}}/>));
    const button = (label: string) => Array.from(container.querySelectorAll('button')).find(b => b.textContent?.includes(label))!;
    expect(button('Lazada').disabled).toBe(false);
    await act(async () => button('Lazada').click());
    await act(async () => button('Proceed to Official Authorization').click());
    expect(authorize).toHaveBeenCalledExactlyOnceWith('lazada', undefined, undefined);
    expect(container.textContent).not.toContain('Store Connected Successfully!');
    expect(container.textContent).not.toContain('Permissions Requested by JW ERP');
  } finally {
    await act(async () => root.unmount());
    vi.unstubAllGlobals();
  }
});

it('renders server-confirmed connection without claiming automatic sync or a fixed token lifetime', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} requestAuthorization={vi.fn()}
    authorizationResult={{status:'success', store:{id:'7', platform:'shopee', shopId:'227482706', shopName:'Verified Sandbox'}}}/>));
  expect(container.textContent).toContain('Store Connected Successfully!');
  expect(container.textContent).toContain('Verified Sandbox');
  expect(container.textContent).toContain('227482706');
  expect(container.textContent).not.toContain('365 Days');
  expect(container.textContent).not.toContain('synchronization is now active');
  expect(container.textContent).not.toContain('Catalog pull & webhook listeners');
  await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} requestAuthorization={vi.fn()}
    authorizationResult={{status:'failed', store:null}}/>));
  expect(container.textContent).toContain('OAuth Handshake Failed');
  expect(container.textContent).not.toContain('Store Connected Successfully!');
  await act(async () => root.unmount());
  vi.unstubAllGlobals();
});

it('uses injected authorization without entering simulated seller consent', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  document.body.appendChild(container);
  const root = createRoot(container);
  const authorize = vi.fn().mockResolvedValue(undefined);
  await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} requestAuthorization={authorize} oauthAvailability={{shopee: true}}/>));
  const button = Array.from(container.querySelectorAll('button')).find(b => b.textContent?.includes('Proceed to Official Authorization'))!;
  await act(async () => button.click());
  expect(authorize).toHaveBeenCalledWith('shopee', undefined, 'sandbox');
  expect(container.textContent).not.toContain('Permissions Requested by JW ERP');
  expect(container.textContent).not.toContain('Store Connected Successfully!');
  await act(async () => root.unmount());
  container.remove();
  vi.unstubAllGlobals();
});

it('retries real authorization after failure without exposing simulated consent', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  const authorize = vi.fn().mockRejectedValue(new Error('Contact the system administrator.'));
  await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} requestAuthorization={authorize} oauthAvailability={{shopee: true}}/>));
  const click = async (label: string) => {
    const button = Array.from(container.querySelectorAll('button')).find(b => b.textContent?.includes(label))!;
    await act(async () => button.click());
  };
  await click('Proceed to Official Authorization');
  await click('Retry Handshake');
  expect(authorize).toHaveBeenCalledTimes(2);
  expect(container.textContent).not.toContain('Permissions Requested by JW ERP');
  expect(container.textContent).not.toContain('Developer Handshake Simulation');
  expect(container.textContent).not.toContain('.env');
  await act(async () => root.unmount());
  vi.unstubAllGlobals();
});

it('fails closed until backend declares OAuth available and keeps unavailable platforms disabled', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  const authorize = vi.fn();
  await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} requestAuthorization={authorize}/>));
  const proceed = () => Array.from(container.querySelectorAll('button')).find(b => b.textContent?.includes('Proceed to Official Authorization'))!;
  expect(proceed().disabled).toBe(true);
  expect(container.textContent).not.toContain('Live R1');
  await act(async () => proceed().click());
  expect(authorize).not.toHaveBeenCalled();
  await act(async () => root.render(<ConnectStoreModal isOpen onClose={() => {}} requestAuthorization={authorize} oauthAvailability={{shopee: true, lazada: false}}/>));
  expect(proceed().disabled).toBe(false);
  const lazada = Array.from(container.querySelectorAll('button')).find(b => b.textContent?.includes('Lazada'))!;
  expect(lazada.disabled).toBe(true);
  expect(lazada.textContent).toContain('Unavailable');
  const identityInputs = Array.from(container.querySelectorAll('input'));
  expect(identityInputs).toHaveLength(0);
  await act(async () => root.unmount());
  vi.unstubAllGlobals();
});

it('renders persisted multi-store progress and retries only failed stores', async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const container=document.createElement('div'); const root=createRoot(container); const retry=vi.fn().mockResolvedValue(undefined);
  await act(async()=>root.render(<ConnectStoreModal isOpen onClose={()=>{}} requestAuthorization={vi.fn()} retryBatch={retry}
    authorizationResult={{status:'partial',store:null,batch:{id:'batch-id',status:'partial',total:2,connected:1,reauthorized:0,failed:1,processing:0,
      stores:[{shopId:'101',storeId:'1',status:'connected',message:null},{shopId:'202',storeId:null,status:'requires_attention',message:'Retry this store.'}]}}}/>));
  expect(container.textContent).toContain('1 of 2 Shopee stores connected');
  expect(container.textContent).toContain('Requires Attention');
  const button=Array.from(container.querySelectorAll('button')).find(item=>item.textContent?.includes('Retry Failed Stores'))!;
  await act(async()=>button.click()); expect(retry).toHaveBeenCalledExactlyOnceWith('batch-id');
  await act(async()=>root.unmount()); vi.unstubAllGlobals();
});
