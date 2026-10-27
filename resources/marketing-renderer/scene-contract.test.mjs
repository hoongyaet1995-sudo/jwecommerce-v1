import test from 'node:test';
import assert from 'node:assert/strict';
import {assertSceneInput} from './scene-contract.mjs';
const media=(ext)=>({file:'a'.repeat(64)+'.'+ext,mime:ext==='mp4'?'video/mp4':'image/png',width:720,height:1280,...(ext==='mp4'?{duration_seconds:10,has_audio:false}:{})});
function sample(count){return {recipe:'capability-plan-v1',execution_profile:'local-only-v1',ratio:'9:16',presentation:{brand:{name:'JTG',descriptor:'Container & Logistics'}},cta:'Request a quote',audio:{requests:0,new_requests:0,reuse_only:true},budget:{cap_microusd:0,ai_video_requests:0},scenes:Array.from({length:count},(_,i)=>({number:i+1,scene_id:`scene-${i+1}`,mode:i===1?'existing_video':i===2?'existing_images':'text_card',images:i===2?[media('png')]:[],videos:i===1?[media('mp4')]:[],voiceover:'',subtitle:`Message ${i}`,viewer_copy:{text:`Message ${i}`,source:`document.scenes.${i}.subtitle`},planned_seconds:5}))};}
test('three five six scenes allow video and image away from fixed old positions',()=>{for(const n of [3,5,6])assert.doesNotThrow(()=>assertSceneInput(sample(n)));});
test('missing media remote path internal instructions and paid fallback are rejected',()=>{
 for(const mutate of [m=>m.scenes[1].videos=[],m=>m.scenes[1].videos[0].file='https://example.com/video.mp4',m=>m.scenes[0].visual='Internal director instructions',m=>m.audio.reuse_only=false,m=>m.budget.ai_video_requests=1,m=>m.scenes[1].videos[0].has_audio=true,m=>m.scenes[0].viewer_copy.text='Injected display text']){
  const m=sample(3);mutate(m);assert.throws(()=>assertSceneInput(m));
 }
});
test('wrong scene binding or duration cannot silently reshape approved story',()=>{for(const mutate of [m=>m.scenes[0].scene_id='scene-2',m=>m.scenes[0].planned_seconds=80,m=>m.presentation.brand.name='']){const m=sample(3);mutate(m);assert.throws(()=>assertSceneInput(m));}});

test('single generated shot requires a completed matching receipt and bounded generation budget',()=>{
 const m=sample(3);m.execution_profile='single-shot-hybrid-v1';m.budget={cap_microusd:1100000,ai_video_requests:1};m.generation_receipts=[{node_key:'generate-scene-2',status:'completed',sha256:'a'.repeat(64)}];assert.doesNotThrow(()=>assertSceneInput(m));
 for(const mutate of [x=>x.generation_receipts=[],x=>x.generation_receipts[0].sha256='b'.repeat(64),x=>x.generation_receipts[0].status='pending',x=>x.budget.cap_microusd=1200000,x=>x.budget.ai_video_requests=2]){const c=structuredClone(m);mutate(c);assert.throws(()=>assertSceneInput(c));}
});
