// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {MarketplaceImageAttribute} from './MarketplaceImageAttribute';

globalThis.IS_REACT_ACT_ENVIRONMENT=true;
let host:HTMLDivElement|undefined;
afterEach(()=>{host?.remove();host=undefined;});

it('selects existing media, previews it, and removes the selection',async()=>{
 host=document.createElement('div');document.body.append(host);const root=createRoot(host);const change=vi.fn();
 await act(async()=>root.render(<MarketplaceImageAttribute productId={3} label="Color Thumbnail" required={false} images={[{id:7,path:'/storage/front.jpg'}]} value={{kind:'empty'}} onChange={change}/>));
 await act(async()=>host!.querySelector<HTMLButtonElement>('[data-marketplace-image="7"]')!.click());
 expect(change).toHaveBeenCalledWith({kind:'image',imageId:7,url:'/storage/front.jpg'});
 await act(async()=>host!.querySelector<HTMLButtonElement>('[aria-label="Preview Color Thumbnail image 7"]')!.click());
 expect(document.body.textContent).toContain('Color Thumbnail Preview');
 await act(async()=>host!.querySelector<HTMLButtonElement>('[aria-label="Remove Color Thumbnail"]')!.click());
 expect(change).toHaveBeenLastCalledWith({kind:'empty'});
 await act(async()=>root.unmount());
});

it('shows pending state, appends a confirmed upload, and retains prior value after an error',async()=>{
 host=document.createElement('div');document.body.append(host);const root=createRoot(host);const change=vi.fn();const uploaded=vi.fn();
 let resolveUpload!:(value:{imageId:number;url:string;alt:string;sourceRevision:string})=>void;
 const upload=vi.fn(()=>new Promise<{imageId:number;url:string;alt:string;sourceRevision:string}>(resolve=>{resolveUpload=resolve;}));
 await act(async()=>root.render(<MarketplaceImageAttribute productId={3} label="Size Chart" required images={[]} value={{kind:'image',imageId:5,url:'/storage/old.jpg'}} onChange={change} onUploaded={uploaded} upload={upload}/>));
 const input=host.querySelector<HTMLInputElement>('input[type="file"]')!;
 const file=new File(['new'],'chart.png',{type:'image/png'});
 Object.defineProperty(input,'files',{configurable:true,value:[file]});
 await act(async()=>input.dispatchEvent(new Event('change',{bubbles:true})));
 expect(host.textContent).toContain('Uploading');
 await act(async()=>resolveUpload({imageId:9,url:'/storage/chart.png',alt:'chart.png',sourceRevision:'a'.repeat(64)}));
 expect(change).toHaveBeenCalledWith({kind:'image',imageId:9,url:'/storage/chart.png'});
 expect(uploaded).toHaveBeenCalledWith(expect.objectContaining({imageId:9,sourceRevision:'a'.repeat(64)}));
 expect(host.querySelector('[data-marketplace-image="9"]')).not.toBeNull();

 const failed=vi.fn().mockRejectedValue(new Error('Upload rejected.'));
 await act(async()=>root.render(<MarketplaceImageAttribute productId={3} label="Size Chart" required images={[{id:5,path:'/storage/old.jpg'}]} value={{kind:'image',imageId:5,url:'/storage/old.jpg'}} onChange={change} upload={failed}/>));
 const failedInput=host.querySelector<HTMLInputElement>('input[type="file"]')!;
 Object.defineProperty(failedInput,'files',{configurable:true,value:[file]});
 await act(async()=>failedInput.dispatchEvent(new Event('change',{bubbles:true})));
 expect(host.textContent).toContain('Upload rejected.');
 expect(change).not.toHaveBeenCalledWith({kind:'empty'});
 await act(async()=>root.unmount());
});
