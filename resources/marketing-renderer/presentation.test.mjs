import test from 'node:test';
import assert from 'node:assert/strict';
import {presentationStyle,activeCaption} from './presentation.mjs';

test('immersive presentation is explicit, old movies retain the legacy style',()=>{
 assert.equal(presentationStyle({brand:{name:'JTG'}}),'legacy');
 assert.equal(presentationStyle({style:'immersive-v1'}),'immersive-v1');
 assert.throws(()=>presentationStyle({style:'invented'}),/unsupported_presentation/);
});
test('captions follow measured speech, disappear in gaps, and never substitute director or headline text',()=>{
 const scene={visual:'internal direction',subtitle:'headline',captions:[{start:.5,end:1,text:'Actual speech'},{start:2,end:3,text:'Next phrase'}]};
 assert.equal(activeCaption(scene,0),'');
 assert.equal(activeCaption(scene,12),'Actual speech');
 assert.equal(activeCaption(scene,24),'');
 assert.equal(activeCaption(scene,48),'Next phrase');
 assert.equal(activeCaption(scene,72),'');
 assert.equal(activeCaption({...scene,captions:[]},48),'');
});
