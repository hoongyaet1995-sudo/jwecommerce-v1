import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {execFileSync} from 'node:child_process';
import {bundle} from '@remotion/bundler';
import {renderMedia,renderStill,selectComposition} from '@remotion/renderer';
import {buildTimeline} from './timeline.mjs';
import {transformSync} from 'esbuild';
import {assertHybridInput} from './hybrid-contract.mjs';
import {assertSceneInput} from './scene-contract.mjs';

const [directory,browserExecutable,ffmpeg,ffprobe]=process.argv.slice(2);
if(directory==='--check'){
 transformSync(await fs.readFile(new URL('./entry.tsx',import.meta.url),'utf8'),{loader:'tsx'});
 transformSync(await fs.readFile(new URL('./hybrid.tsx',import.meta.url),'utf8'),{loader:'tsx'});
 transformSync(await fs.readFile(new URL('./scene-composition.tsx',import.meta.url),'utf8'),{loader:'tsx'});
 process.exit(0);
}
if(!directory||!path.isAbsolute(directory)||!browserExecutable||!ffmpeg||!ffprobe)throw Error('renderer_configuration_missing');
const started=performance.now();const input=JSON.parse(await fs.readFile(path.join(directory,'input.json'),'utf8'));
const hybrid=input.manifest.recipe==='use-scenario-hybrid-v1';
const dynamic=input.manifest.recipe==='capability-plan-v1';
if(hybrid)assertHybridInput(input.manifest);
else if(dynamic)assertSceneInput(input.manifest);
else if(input.manifest.recipe!=='existing-images-voice-v1')throw Error('unsupported_recipe');
let alignment=null;let duration=0;
if(input.manifest.audio.requests){
 alignment=JSON.parse(await fs.readFile(path.join(directory,'alignment.json'),'utf8'));
 const probe=JSON.parse(execFileSync(ffprobe,['-v','error','-show_format','-of','json',path.join(directory,'public/voice.mp3')],{encoding:'utf8'}));
 duration=Number(probe.format.duration);
}
const timeline=buildTimeline(input.manifest.scenes,alignment,duration);
if(dynamic)for(const scene of timeline.scenes)if(scene.videos?.length&&scene.frames/24>scene.videos[0].duration_seconds+.1)throw Error('saved_clip_too_short_for_measured_timeline');
for(const scene of timeline.scenes){
 if(scene.audio)execFileSync(ffmpeg,['-v','error','-y','-i',path.join(directory,'public/voice.mp3'),'-ss',String(scene.audio.start),'-t',String(scene.audio.end-scene.audio.start),'-c:a','pcm_s16le',path.join(directory,'public',scene.audio.file)],{stdio:'ignore'});
}
const inputProps={scenes:timeline.scenes,title:input.title,cta:input.manifest.cta,revision:input.revision,qa:input.qa,recipe:input.manifest.recipe,presentation:input.manifest.presentation};
const own=path.dirname(fileURLToPath(import.meta.url));
const serveUrl=await bundle({entryPoint:path.join(own,'entry.tsx'),publicDir:path.join(directory,'public')});
const composition=await selectComposition({serveUrl,id:'MarketingVideo',inputProps,browserExecutable});
await renderMedia({serveUrl,composition,inputProps,browserExecutable,codec:'h264',audioCodec:'aac',pixelFormat:'yuv420p',outputLocation:path.join(directory,'output.mp4'),concurrency:2,crf:23,logLevel:'warn'});
if((hybrid||dynamic)&&input.manifest.audio.requests){
 execFileSync(ffmpeg,['-v','error','-y','-i',path.join(directory,'output.mp4'),'-c:v','copy','-af','loudnorm=I=-16:TP=-1.5:LRA=11','-c:a','aac','-b:a','192k','-ar','48000','-movflags','+faststart',path.join(directory,'normalized.mp4')],{stdio:'ignore'});
 await fs.rename(path.join(directory,'normalized.mp4'),path.join(directory,'output.mp4'));
}
await renderStill({serveUrl,composition,inputProps,browserExecutable,frame:Math.min(48,timeline.frames-1),output:path.join(directory,'poster.png'),logLevel:'warn'});
await fs.writeFile(path.join(directory,'result.json'),JSON.stringify({frames:timeline.frames,elapsed_seconds:(performance.now()-started)/1000,scene_timings:timeline.scenes.map(s=>({number:s.number,start_frame:s.start,frames:s.frames,audio_start:s.audio?.start??null,audio_end:s.audio?.end??null}))},null,2));
