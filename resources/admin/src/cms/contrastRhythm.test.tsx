import {it,expect} from 'vitest';
import {resolveStyledSection,generatePalette} from './pageStyle';
import {imageTargets} from './imageTargets';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
it('opt-in contrast panel uses page ink without changing existing panel semantics',()=>{
 const style={palette:generatePalette('#2860ff','#8524ff')};const s={id:'a',type:'media_text_split',title:'Panel',isVisible:true,surface:'contrast',layoutPreset:'inset-surface-panel'} as any;
 expect(resolveStyledSection(s,style).customColors?.customBgColor).toBe(style.palette.ink);
 expect(resolveStyledSection({...s,layoutPreset:'split-visual-surface'},style).customColors?.customBgColor).toBe(style.palette.accent);
 expect(sectionContentFields(s)).toEqual(['title','content','mediaUrl','mediaAlt']);expect(imageTargets(s)[0].geometryLocked).toBe(false);
});
it('contrast cards retain light section heading and three independently editable pictures',()=>{
 const style={palette:generatePalette('#2860ff','#8524ff')};const s={id:'c',type:'media_gallery',title:'Cards',isVisible:true,surface:'contrast',galleryPreset:'image-cards-contrast'} as any;
 expect(resolveStyledSection(s,style).customColors?.titleColor).toBe(style.palette.ink);
 expect(repeatedItemFields(s)).toEqual(['title','description','imageUrl']);expect(imageTargets(s)).toHaveLength(3);
});
