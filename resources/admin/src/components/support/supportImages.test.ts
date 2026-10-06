import {expect,it} from 'vitest';
import {imageValidation, imageFromClipboard} from './supportImages';
it('accepts images and rejects unsupported formats or files over 5 MB',()=>{
 expect(imageValidation({type:'image/png',size:1024} as File)).toBe('');
 expect(imageValidation({type:'image/svg+xml',size:1024} as File)).not.toBe('');
 expect(imageValidation({type:'image/jpeg',size:5242881} as File)).not.toBe('');
});
it('extracts only an image from clipboard items',()=>{
 const file={type:'image/png'} as File;
 expect(imageFromClipboard([{type:'text/plain',getAsFile:()=>null},{type:'image/png',getAsFile:()=>file}] as any)).toBe(file);
 expect(imageFromClipboard([{type:'text/plain',getAsFile:()=>null}] as any)).toBeNull();
});
