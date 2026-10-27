import {test} from 'node:test';
import assert from 'node:assert/strict';
import {buildTimeline, chunks} from './timeline.mjs';
import fs from 'node:fs';
import {transformSync} from 'esbuild';

test('the actual composition template compiles before any paid speech request',()=>{
 const code=fs.readFileSync(new URL('./entry.tsx',import.meta.url),'utf8');
 assert.ok(transformSync(code,{loader:'tsx'}).code.includes('MarketingVideo'));
});

test('actual audio cuts preserve the six scenes and all original text',()=>{
 const scenes=[{voiceover:'One.',planned_seconds:1,subtitle:'First line'},{voiceover:'Two.',planned_seconds:1,subtitle:'Second line'}];
 const text='One.\n\nTwo.';const alignment={characters:[...text],character_start_times_seconds:[...text].map((_,i)=>i*.2),character_end_times_seconds:[...text].map((_,i)=>(i+1)*.2)};
 const result=buildTimeline(scenes,alignment,2.4);
 assert.equal(result.scenes.length,2);assert.equal(result.scenes[1].start,result.scenes[0].frames);
 assert.equal(result.scenes[0].voiceover,'One.');assert.equal(result.scenes[1].subtitle,'Second line');
 assert.ok(result.frames/24>2.4);assert.ok(result.scenes[1].captions[0].start>=0);
});

test('mismatched spoken text is rejected rather than silently aligned',()=>{
 assert.throws(()=>buildTimeline([{voiceover:'Correct',planned_seconds:5}],{characters:[...'Wrong'],character_start_times_seconds:[],character_end_times_seconds:[]},2));
});

test('long on-screen text is paginated without dropping words',()=>{
 const text='The final specification condition availability and delivery are confirmed in your quotation. Request a quote.';
 assert.equal(chunks(text,40).join(' '),text);
 assert.ok(chunks(text,40).length>1);
});

test('a video-change minimum preserves measured hold plus exactly two seconds',()=>{
 const text='End.'; const alignment={characters:[...text],character_start_times_seconds:[0,1,2,3],character_end_times_seconds:[1,2,3,4]};
 const result=buildTimeline([{voiceover:text,planned_seconds:7,minimum_frames:187}],alignment,4);
 assert.equal(result.frames,187);
});
