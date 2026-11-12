import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';
import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {insetSurfacePanel,centeredWideVisual,splitVisualSurface,wideVisualEditorial} from './wideVisualEditorial';
import {WideVisualEditorial} from '../components/pages/WideVisualEditorial';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {supportsDesignField,renderedColorFields} from './sectionDesignCapabilities';
import {mediaTextSplitPresetPatch} from './mediaTextSplitPresets';
import {resolveStyledSection,generatePalette} from './pageStyle';
it('bounds the sparse preset to real text and one image, never dormant items/actions',()=>{
 const s=wideVisualEditorial('a');expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');
 expect(sectionContentFields(s)).toEqual(['subtitle','title','content','mediaUrl','mediaAlt']);expect(repeatedItemFields(s)).toEqual([]);
 expect(imageTargets(s).map(t=>t.id)).toEqual(['mediaUrl']);expect(supportsDesignField(s,'mediaPosition')).toBe(false);expect(supportsMediaWidth(s)).toBe(false);
 expect(renderedColorFields(s)).toContain('customBgColor');
 const html=renderToStaticMarkup(<WideVisualEditorial section={{...s,title:'Heading',content:'Editable body',mediaUrl:'/example.gif',items:[{id:'hidden',title:'Dormant'}],buttonText:'Hidden action',buttonLink:'/hidden'}}/>);
 expect(html).toContain('src="/example.gif"');expect(html).toContain('Editable body');expect(html).not.toContain('Hidden action');expect(html).not.toContain('Dormant');
});
it('preserves authored content and image slots on layout switches and inherits typography/surface',()=>{
 const s={...wideVisualEditorial('b'),mediaUrl:'/media.gif',imageSlots:[{target:'mediaUrl',mediaAlt:'Meaningful image'}]};
 const other={...s,...mediaTextSplitPresetPatch(s,'corporate-builder-overview')};const restored={...other,...mediaTextSplitPresetPatch(other,'wide-visual-editorial')};expect(restored.mediaUrl).toBe(s.mediaUrl);expect(restored.imageSlots).toEqual(s.imageSlots);expect(restored.customColors).toBeUndefined();
 const styled=resolveStyledSection({...s,surface:'contrast'},{palette:generatePalette('#2255ee','#7924dd'),typography:{title:{fontSize:34,fontWeight:600},content:{fontSize:17}}});
 const html=renderToStaticMarkup(<WideVisualEditorial section={styled}/>);expect(html).toContain('font-weight:600');expect(html).toContain('font-size:');expect(styled.customColors?.titleColor).toBe('#ffffff');
});
it('surface panel has only editable title, body and one image, inheriting page colours',()=>{
 const s=splitVisualSurface('panel');expect(s.customColors).toBeUndefined();expect(sectionContentFields(s)).toEqual(['title','content','mediaUrl','mediaAlt']);expect(repeatedItemFields(s)).toEqual([]);expect(imageTargets(s)).toHaveLength(1);expect(supportsMediaWidth(s)).toBe(false);
 const styled=resolveStyledSection({...s,surface:'contrast',subtitle:'Dormant eyebrow',items:[{id:'x',title:'Hidden card'}],buttonText:'Hidden CTA',buttonLink:'/hidden'},{palette:generatePalette('#2358dd','#8245ee'),typography:{title:{fontSize:36,fontWeight:600}}});
 const html=renderToStaticMarkup(<WideVisualEditorial section={styled} editing/>);expect(html).toContain('data-wide-visual="surface-panel"');expect(html).toContain('--surface-paper:#2358dd');expect(html).toContain('font-weight:600');expect(html).not.toContain('Dormant eyebrow');expect(html).not.toContain('Hidden CTA');expect(html).not.toContain('Hidden card');
});
it('centered single visual has bounded live copy and media without dormant eyebrow/cards/actions',()=>{
 const s=centeredWideVisual('center');expect(s.customColors).toBeUndefined();expect(imageTargets(s)[0].geometryLocked).toBe(false);expect(sectionContentFields(s)).toEqual(['title','content','mediaUrl','mediaAlt']);expect(repeatedItemFields(s)).toEqual([]);expect(imageTargets(s)).toHaveLength(1);expect(supportsMediaWidth(s)).toBe(false);
 const styled=resolveStyledSection({...s,subtitle:'Hidden eyebrow',items:[{id:'x',title:'Hidden card'}],buttonText:'Hidden CTA',buttonLink:'/hidden',mediaUrl:'/image.webp'},{palette:generatePalette('#2958df','#8645dd'),typography:{title:{fontSize:36,fontWeight:600},content:{fontSize:17}}});
 const html=renderToStaticMarkup(<WideVisualEditorial section={styled}/>);expect(html).toContain('data-wide-visual="centered"');expect(html).toContain('font-weight:600');expect(html).toContain('/image.webp');expect(html).not.toContain('Hidden eyebrow');expect(html).not.toContain('Hidden card');expect(html).not.toContain('Hidden CTA');
});

it('single-image wide and surface layouts expose editable geometry and render authored width/shape',()=>{
 for(const make of [insetSurfacePanel,wideVisualEditorial,splitVisualSurface,centeredWideVisual]){
  const section={...make('geometry'),mediaUrl:'/art.webp',imageSlots:[{target:'mediaUrl',imageWidth:'50' as const,imageShape:'circle' as const,imageFit:'cover' as const}]};
  expect(imageTargets(section)[0].geometryLocked).toBe(false);
  const html=renderToStaticMarkup(<WideVisualEditorial section={section}/>);
  expect(html).toContain('width:50%');expect(html).toContain('border-radius:50%');expect(html).toContain('aspect-ratio:1 / 1');
 }
});
