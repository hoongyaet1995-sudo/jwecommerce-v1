// @vitest-environment jsdom
import {it,expect,vi,afterEach} from 'vitest';
import {uploadSupportMedia} from './supportMediaUpload';
afterEach(()=>vi.unstubAllGlobals());
it('reports transport progress separately from completed response and supports cancellation',async()=>{
 let xhr:any;class Fake {upload:any={};status=201;responseText='';onload:any;onabort:any;open=vi.fn();setRequestHeader=vi.fn();send=vi.fn();abort(){this.onabort();}constructor(){xhr=this;}}
 vi.stubGlobal('XMLHttpRequest',Fake);const controller=new AbortController(),progress=vi.fn();
 const promise=uploadSupportMedia('/upload',new File(['pdf'],'a.pdf'),'document',progress,controller.signal);
 xhr.upload.onprogress({lengthComputable:true,loaded:5,total:10});expect(progress).toHaveBeenCalledWith(50);xhr.responseText=JSON.stringify({attachment:{id:'one',type:'document'}});xhr.onload();expect((await promise).id).toBe('one');
 const next=new AbortController(),aborted=uploadSupportMedia('/upload',new File(['pdf'],'a.pdf'),'document',progress,next.signal);next.abort();await expect(aborted).rejects.toMatchObject({name:'AbortError'});
});
it('rejects failed response and malformed success without returning a draft',async()=>{
 let xhr:any;class Fake {upload:any={};status=422;responseText='{"message":"Invalid PDF"}';onload:any;open(){}setRequestHeader(){}send(){}constructor(){xhr=this;}}
 vi.stubGlobal('XMLHttpRequest',Fake);const p=uploadSupportMedia('/upload',new File(['x'],'a.pdf'),'document',()=>{},new AbortController().signal);xhr.onload();await expect(p).rejects.toThrow('Invalid PDF');
});
