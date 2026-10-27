import fs from 'node:fs/promises';import path from 'node:path';import {fileURLToPath} from 'node:url';
import {bundle} from '@remotion/bundler';import {selectComposition,renderMedia,renderStill} from '@remotion/renderer';
const [directory,browserExecutable]=process.argv.slice(2);if(!path.isAbsolute(directory)||!browserExecutable)throw Error('runtime_missing');
const input=JSON.parse(await fs.readFile(path.join(directory,'subtitles.json'),'utf8'));
if(!input.scenes?.length||input.scenes.length>12||input.scenes.some((s,i)=>s.file!==`${i+1}.mp4`||!Number.isInteger(s.frames)||s.frames<1))throw Error('invalid_subtitle_recipe');
const serveUrl=await bundle({entryPoint:path.join(path.dirname(fileURLToPath(import.meta.url)),'entry.jsx'),publicDir:path.join(directory,'public')});
const composition=await selectComposition({serveUrl,id:'Subtitles',inputProps:input,browserExecutable});
await renderMedia({serveUrl,composition,inputProps:input,browserExecutable,codec:'h264',muted:true,pixelFormat:'yuv420p',outputLocation:path.join(directory,'subtitled.mp4'),concurrency:2,crf:18,logLevel:'warn'});
await renderStill({serveUrl,composition,inputProps:input,browserExecutable,frame:48,output:path.join(directory,'subtitle-poster.png'),logLevel:'warn'});
