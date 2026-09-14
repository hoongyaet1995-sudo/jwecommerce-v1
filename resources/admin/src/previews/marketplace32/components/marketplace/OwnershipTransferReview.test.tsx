// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import * as transport from '../../../../integration/marketplaceData';
import {OwnershipTransferReview} from './OwnershipTransferReview';
it('requires a server review and reason before enabling a move',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const api=vi.spyOn(transport,'marketplaceRequest').mockResolvedValue({token:'review',sku:'QA',fromTitle:'Old',fromId:53,toTitle:'New',toId:52});
 const host=document.createElement('div');const root=createRoot(host);
 try{
  await act(async()=>root.render(<OwnershipTransferReview listingId="4" productId="52"/>));
  expect(api).not.toHaveBeenCalled();
  await act(async()=>host.querySelector('button')!.click());
  expect(api).toHaveBeenCalledWith('/listings/4/import-variant','POST',{productId:52,reviewTransfer:true});
  expect(host.textContent).toContain('Old #53');expect(host.textContent).toContain('New #52');
  expect(host.querySelector('button')!.disabled).toBe(true);expect(api).toHaveBeenCalledTimes(1);
 }finally{await act(async()=>root.unmount());vi.restoreAllMocks();vi.unstubAllGlobals();}
});
