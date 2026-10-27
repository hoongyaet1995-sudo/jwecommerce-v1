import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {bundle} from '@remotion/bundler';
import {renderMedia,renderStill,selectComposition} from '@remotion/renderer';
const [directory,browserExecutable]=process.argv.slice(2);
if(!directory||!path.isAbsolute(directory)||!browserExecutable)throw Error('runtime_missing');
const input=JSON.parse(await fs.readFile(path.join(directory,'input.json'),'utf8'));
if(!Array.isArray(input.scenes)||input.scenes.length<1||input.scenes.length>12||typeof input.with_audio!=='boolean'||typeof input.cta!=='string')throw Error('invalid_composition');
const edited=input.version==='workspace-film-edits-v1';
if(input.version&&!edited)throw Error('unknown_recipe');
if(edited&&(!Number.isInteger(input.cta_hold_frames)||input.cta_hold_frames<24||input.cta_hold_frames>120))throw Error('invalid_hold');
for(const [i,s] of input.scenes.entries()){
 if(s.file!==`${i+1}.mp4`||!Number.isInteger(s.frames)||s.frames<(edited?24:96)||s.frames>360||typeof s.subtitle!=='string'||typeof s.with_audio!=='boolean')throw Error('invalid_scene');
 if(edited&&(!Number.isInteger(s.source_frames)||s.source_frames<96||s.source_frames>360||!Number.isInteger(s.in_frame)||!Number.isInteger(s.out_frame)||s.in_frame<0||s.out_frame>s.source_frames||s.out_frame-s.in_frame!==s.frames))throw Error('invalid_range');
}
const frames=input.scenes.reduce((sum,s)=>sum+s.frames,0);if(frames>2880)throw Error('film_too_long');
const serveUrl=await bundle({entryPoint:path.join(path.dirname(fileURLToPath(import.meta.url)),'entry.tsx'),publicDir:path.join(directory,'public')});
const composition=await selectComposition({serveUrl,id:'WorkspaceVideo',inputProps:input,browserExecutable});
await renderMedia({serveUrl,composition,inputProps:input,browserExecutable,codec:'h264',audioCodec:'aac',muted:!input.with_audio,pixelFormat:'yuv420p',outputLocation:path.join(directory,'rendered.mp4'),concurrency:2,crf:20,logLevel:'warn'});
await renderStill({serveUrl,composition,inputProps:input,browserExecutable,frame:Math.min(48,frames-1),output:path.join(directory,'poster.png'),logLevel:'warn'});
