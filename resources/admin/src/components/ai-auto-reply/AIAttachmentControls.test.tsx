// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {AIAttachmentControls} from './AIAttachmentControls';
it('independent switches respect provider availability and preserve limits',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'),root=createRoot(host),change=vi.fn();
 const value={image:false,document:false,audio:false,pdfPages:10,audioSeconds:120,retentionDays:30,transcriptionModel:'gpt-4o-mini-transcribe' as const};
 try{
  await act(async()=>root.render(<AIAttachmentControls mode="capabilities" value={value} readiness={{image:true,document:true,audio:false}} onChange={change}/>));
  expect(host.querySelectorAll('[role="switch"]')).toHaveLength(3);
  expect((host.querySelector('[aria-label="Voice transcription"]') as HTMLInputElement).disabled).toBe(true);
  await act(async()=>(host.querySelector('[aria-label="Image understanding"]') as HTMLInputElement).click());
  expect(change).toHaveBeenCalledWith({...value,image:true});
 }finally{await act(async()=>root.unmount());}
});
