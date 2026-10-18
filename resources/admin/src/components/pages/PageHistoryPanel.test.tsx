// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot,type Root} from 'react-dom/client';
import {afterEach,describe,it,expect,vi} from 'vitest';
import {PageHistoryPanel} from './PageHistoryPanel';
import type {CmsPage,PageDocument} from '../../types';
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({csrfToken:'test'})}));
vi.mock('./HistoryDocumentPreview',()=>({HistoryDocumentPreview:({document}:{document:PageDocument})=><div>{document.title}</div>}));
(globalThis as typeof globalThis&{IS_REACT_ACT_ENVIRONMENT:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
let root:Root;let host:HTMLDivElement;
afterEach(()=>{if(root)act(()=>root.unmount());host?.remove();vi.unstubAllGlobals();});
const doc={title:'Saved old heading',slug:'history',template:'standard',sections:[]} as PageDocument;
const page={...doc,id:'31',title:'Current heading',lockVersion:8,status:'published'} as CmsPage;
const revision={id:'42',version:3,kind:'checkpoint',name:'Client approved',actor:'JW',createdAt:'2026-10-18 03:00:00',isCurrent:false,isLive:false};
const response=(body:unknown,status=200)=>new Response(JSON.stringify(body),{status});
async function mount(fetcher:ReturnType<typeof vi.fn>){vi.stubGlobal('fetch',fetcher);host=document.createElement('div');document.body.append(host);root=createRoot(host);const restored=vi.fn();await act(async()=>root.render(<PageHistoryPanel page={page} onClose={()=>{}} onRestored={restored} onReload={async()=>{}}/>));return restored;}
async function click(text:string){const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.includes(text));expect(button).toBeTruthy();await act(async()=>button!.click());}
describe('CMS history mounted flow',()=>{
 it('previews without writing then confirms restoring with the current lock version',async()=>{
  const fetcher=vi.fn(async(url:string,init?:RequestInit)=>url.endsWith('/restore')?response({data:{pageId:'31',lockVersion:9,document:doc}}):url.endsWith('/42')?response({data:{id:'42',document:doc}}):response({data:[revision]}));
  const restored=await mount(fetcher);await click('Client approved');await click('Preview selected version');
  expect(host.textContent).toContain('Viewing v3');expect(fetcher.mock.calls.every(([,init])=>init?.method==='GET')).toBe(true);
  await click('Restore to draft');expect(host.querySelector('[role="dialog"]')?.textContent).toContain('live page is unchanged');
  const modal=host.querySelector('[role="dialog"]')!;await act(async()=>[...modal.querySelectorAll('button')].find(b=>b.textContent?.startsWith('Restore to draft'))!.click());
  expect(restored).toHaveBeenCalledOnce();const call=fetcher.mock.calls.find(([url])=>url.endsWith('/restore'))!;expect(JSON.parse(call[1]!.body as string)).toEqual({lockVersion:8});
  expect(host.textContent).toContain('as a new draft');
 });
 it('shows conflict without claiming restoration succeeded',async()=>{
  const fetcher=vi.fn(async(url:string)=>url.endsWith('/restore')?response({message:'Page changed'},409):url.endsWith('/42')?response({data:{id:'42',document:doc}}):response({data:[revision]}));
  const restored=await mount(fetcher);await click('Client approved');await click('Preview selected version');await click('Restore to draft');
  await act(async()=>[...host.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')].find(b=>b.textContent?.startsWith('Restore to draft'))!.click());
  expect(host.textContent).toContain('This page has newer changes');expect(restored).not.toHaveBeenCalled();
 });
});
