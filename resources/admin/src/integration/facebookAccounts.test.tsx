// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,beforeEach,expect,it,vi} from 'vitest';
import {useFacebookAccounts} from './facebookAccounts';
let state:ReturnType<typeof useFacebookAccounts>;let root:ReturnType<typeof createRoot>;
const fetchMock=vi.fn();const response=(data:unknown)=>({ok:true,json:async()=>data});
const account={id:'1',state:'connected',messagingReady:false};
function Harness(){state=useFacebookAccounts(false);return null;}
beforeEach(async()=>{vi.stubGlobal('fetch',fetchMock);fetchMock.mockReset();(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;document.head.innerHTML='<meta name="csrf-token" content="csrf">';root=createRoot(document.createElement('div'));await act(async()=>root.render(<Harness/>));});
afterEach(async()=>{await act(async()=>root.unmount());vi.unstubAllGlobals();});
it('read-only refresh never activates and manager retry merges actual provider state',async()=>{
 fetchMock.mockResolvedValueOnce(response({canManage:true,accounts:[account],pages:[]}));
 await act(async()=>{await state.refresh();});expect(fetchMock).toHaveBeenCalledTimes(1);
 fetchMock.mockResolvedValueOnce(response({canManage:true,accounts:[account,{id:'2',state:'disconnected'}],pages:[]})).mockResolvedValueOnce(response({account:{...account,messagingReady:true}}));
 await act(async()=>{await state.refresh(true);});
 expect(fetchMock.mock.calls[2][0]).toBe('/admin/api/social/facebook/1/check');expect(fetchMock.mock.calls[2][1].method).toBe('POST');expect(state.data?.accounts[0].messagingReady).toBe(true);expect(fetchMock).toHaveBeenCalledTimes(3);
});
it('staff cannot trigger activation through bulk refresh',async()=>{
 fetchMock.mockResolvedValueOnce(response({canManage:false,accounts:[account],pages:[]}));await act(async()=>{await state.refresh(true);});expect(fetchMock).toHaveBeenCalledTimes(1);
});
