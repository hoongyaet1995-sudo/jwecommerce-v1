import test from 'node:test';
import assert from 'node:assert/strict';
import {assertHybridInput} from './hybrid-contract.mjs';
const h='a'.repeat(64);
const valid=()=>({recipe:'use-scenario-hybrid-v1',copy_contract:'storyboard-viewer-copy-v1',audio:{reuse_only:true},presentation:{brand:{name:'Another brand',descriptor:'A business'},scenes:['need_open','product_intro','use_map','business_uses','requirements','cta_close'].map(layout=>({layout,heading:'A clear heading',lines:['Equipment','Materials','Goods'],body:'Facts only.'}))},scenes:Array.from({length:6},(_,i)=>({number:i+1,subtitle:'Viewer copy',viewer_copy:{text:'Viewer copy',source:`document.scenes.${i}.subtitle`},images:[1,4].includes(i)?[{file:h+'.png'}]:[],videos:i===0?[{file:h+'.mp4'}]:[]}))});
test('bounded hybrid recipe accepts other brands without changing shot identity',()=>assert.doesNotThrow(()=>assertHybridInput(valid())));
test('hybrid media never resolves remote or arbitrary paths',()=>{const m=valid();m.scenes[0].videos[0].file='https://example.org/clip.mp4';assert.throws(()=>assertHybridInput(m));});
test('wrong scene order, missing product image and paid voice fallback are rejected',()=>{for(const mutate of [m=>m.presentation.scenes[0].layout='cta_close',m=>m.scenes[1].images=[],m=>m.audio.reuse_only=false]){const m=valid();mutate(m);assert.throws(()=>assertHybridInput(m));}});
test('explicit photos cannot disappear into a graphic-only layout',()=>{const m=valid();m.scenes[2].images=[{file:h+'.png'}];assert.throws(()=>assertHybridInput(m));});
