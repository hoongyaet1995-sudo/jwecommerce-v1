import {describe,it,expect} from 'vitest';
import {imageTargets,updateImageTarget,pruneImageSlots} from './imageTargets';
import type {PageSection} from '../types';
const gallery:PageSection={id:'gallery',type:'media_gallery',title:'QA',isVisible:true,items:[{id:'a',title:'One',imageUrl:'/one.png'},{id:'b',title:'Two',imageUrl:'/two.png'}]};
describe('canonical image targets',()=>{
  it('keeps independent overrides with stable item identities after reorder',()=>{
    const changed=updateImageTarget(gallery,'item:a:imageUrl',{imageShape:'rounded',imageRatio:'1:1'});
    const reordered={...changed,items:[changed.items![1],changed.items![0]]};
    expect(imageTargets(reordered).map(t=>t.id)).toEqual(['item:b:imageUrl','item:a:imageUrl']);
    expect(reordered.imageSlots).toEqual([{target:'item:a:imageUrl',imageShape:'rounded',imageRatio:'1:1'}]);
    expect(changed.items![1]).toEqual(gallery.items![1]);
  });
  it('writes the selected real asset path and prunes removed targets',()=>{
    const changed=updateImageTarget(gallery,'item:b:imageUrl',{mediaUrl:'/replacement.png',mediaAlt:'Two alt'});
    expect(changed.items![1].imageUrl).toBe('/replacement.png');expect(changed.items![0].imageUrl).toBe('/one.png');
    expect(pruneImageSlots({...changed,items:[changed.items![0]]}).imageSlots).toEqual([]);
  });
  it('excludes catalog images and videos and locks background geometry',()=>{
    expect(imageTargets({...gallery,type:'product_grid'})).toEqual([]);
    expect(imageTargets({...gallery,type:'video_embed'})).toEqual([]);
    expect(imageTargets({...gallery,type:'hero_banner',mediaUrl:'/banner.png'})[0].geometryLocked).toBe(true);
    expect(imageTargets({...gallery,type:'before_after',beforeImageUrl:'/before.png',afterImageUrl:'/after.png'}).map(t=>t.id)).toEqual(['beforeImageUrl','afterImageUrl']);
  });
});
