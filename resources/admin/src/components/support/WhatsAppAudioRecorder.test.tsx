// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {WhatsAppAudioRecorder} from './WhatsAppAudioRecorder';
it('blocks restarting until stop finalizes and releases microphone on conversation switch',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const track={stop:vi.fn()},stream={getTracks:()=>[track]},getUserMedia=vi.fn().mockResolvedValue(stream);
 Object.defineProperty(navigator,'mediaDevices',{configurable:true,value:{getUserMedia}});
 let instance:any;
 class Recorder {static isTypeSupported(){return true;}state='inactive';mimeType='audio/webm';onstop:any;ondataavailable:any;onerror:any;constructor(){instance=this;}start(){this.state='recording';}stop(){this.state='inactive';}}
 vi.stubGlobal('MediaRecorder',Recorder);
 const host=document.createElement('div'),root=createRoot(host),onFile=vi.fn(),onError=vi.fn();
 const render=async(id:string)=>act(async()=>root.render(<WhatsAppAudioRecorder conversationId={id} disabled={false} onFile={onFile} onError={onError}/>));
 await render('one');await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Record voice message"]')!.click());
 await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Stop recording and preview"]')!.click());
 expect(host.querySelector('[aria-label="Record voice message"]')).toBeNull();expect(host.querySelector('[aria-label="Cancel recording request"]')).not.toBeNull();
 await render('two');await act(async()=>{instance.ondataavailable({data:new Blob(['audio'])});instance.onstop();});
 expect(onFile).not.toHaveBeenCalled();expect(track.stop).toHaveBeenCalled();expect(getUserMedia).toHaveBeenCalledTimes(1);
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});
it('reports microphone denial without creating a recording',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 Object.defineProperty(navigator,'mediaDevices',{configurable:true,value:{getUserMedia:vi.fn().mockRejectedValue(new Error('Microphone denied'))}});
 vi.stubGlobal('MediaRecorder',class {});
 const host=document.createElement('div'),root=createRoot(host),onError=vi.fn();
 await act(async()=>root.render(<WhatsAppAudioRecorder conversationId="one" disabled={false} onFile={vi.fn()} onError={onError}/>));
 await act(async()=>host.querySelector<HTMLButtonElement>('button')!.click());
 expect(onError).toHaveBeenCalledWith('Microphone denied');expect(host.querySelector('[aria-label="Cancel recording"]')).toBeNull();
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});
