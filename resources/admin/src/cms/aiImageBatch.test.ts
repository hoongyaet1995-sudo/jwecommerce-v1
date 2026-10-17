import {describe,it,expect,vi} from 'vitest';
import {generateImageBatch} from './aiImageBatch';
import type {PageDocument} from '../types';
import type {GeneratorImage} from '../components/pages/AiPageGenerator';

describe('paid image failure recovery',()=>{
 it('retains successful media, stops uncertain work and retries only the remaining target with its stable request ID',async()=>{
  let document:PageDocument={title:'Draft',slug:'draft',template:'standard',sections:[]};
  let images:GeneratorImage[]=['a','b'].map(sectionId=>({sectionId,target:'mediaUrl',url:'existing-'+sectionId,prompt:'Scene',ratio:'16:9',generate:true}));
  const failed=vi.fn();const first=vi.fn(async(image:GeneratorImage,doc:PageDocument)=>{if(image.sectionId==='b')throw new Error('Network lost');return {document:{...doc,heroImage:'managed-a'},url:'managed-a'};});
  const save=(doc:PageDocument,state:GeneratorImage[])=>{document=structuredClone(doc);images=structuredClone(state);};
  await generateImageBatch(document,images,images,first,save,failed);
  expect(first).toHaveBeenCalledTimes(2);expect(images[0]).toMatchObject({url:'managed-a',generate:false});expect(images[1].url).toBe('existing-b');
  const stableId=images[1].requestId;expect(stableId).toBeTruthy();
  const retry=vi.fn(async(image:GeneratorImage,doc:PageDocument)=>{expect(doc.heroImage).toBe('managed-a');expect(image.requestId).toBe(stableId);return {document:doc,url:'managed-b'};});
  await generateImageBatch(document,images,images,retry,save,failed);
  expect(retry).toHaveBeenCalledTimes(1);expect(images.map(i=>i.url)).toEqual(['managed-a','managed-b']);expect(images.every(i=>!i.generate)).toBe(true);
 });
});
