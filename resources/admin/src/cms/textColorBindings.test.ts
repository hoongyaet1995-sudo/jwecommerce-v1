import {actionAppearanceCss} from './actionAppearance';
import {resolveStyledSection} from './pageStyle';
import {duplicateSection} from './duplicateSection';
import {isTextColorBindings} from './textColorBindings';
import {expect,it} from 'vitest';
import {setTextColorBinding,textColorBindingCss,getTextColorBinding} from './textColorBindings';
import {stylePresets} from './pageStyle';
import type {PageSection} from '../types';
const section:PageSection={id:'test-colors',type:'feature_cards',title:'Preserved',isVisible:true,items:[{id:'a',title:'Dark card'},{id:'b',title:'Light card'}],typography:{itemTitle:{fontSize:30,color:'#123456',colorOverride:true}}};
it('binds only the chosen stable item and role, preserving old role typography and sibling data',()=>{
 const next=setTextColorBinding(section,'itemTitle','a',{source:'light'});
 expect(next.typography).toEqual(section.typography);expect(next.items).toEqual(section.items);
 expect(getTextColorBinding(next,'itemTitle','b')).toBeUndefined();
 expect(next.textColorBindings).toEqual({a:{itemTitle:{source:'light'}}});
 const css=textColorBindingCss(next,stylePresets[1].palette);
 expect(css).toContain('[data-cms-item="a"]');expect(css).toContain('[data-cms-text="itemTitle"]');expect(css).toContain('#ffffff');expect(css).not.toContain('[data-cms-item="b"]');
});
it('resolves bound colors against the live palette and Auto against local surface tokens',()=>{
 const light=setTextColorBinding(section,'itemTitle','a',{source:'light'});
 expect(textColorBindingCss(light,{...stylePresets[1].palette,light:'#fff9f2'})).toContain('#fff9f2');
 const dark=setTextColorBinding(light,'itemTitle','b',{source:'dark'});
 expect(textColorBindingCss(dark,{...stylePresets[1].palette,ink:'#44352e'})).toContain('#44352e');
 const auto=setTextColorBinding(dark,'itemTitle','a',{source:'auto'});
 expect(textColorBindingCss(auto,stylePresets[1].palette)).toContain('var(--cms-auto-ink)');
 const custom=setTextColorBinding(auto,'itemContent','a',{source:'custom',color:'#aabbcc'});
 expect(textColorBindingCss(custom,stylePresets[1].palette)).toContain('#aabbcc');
 expect(getTextColorBinding(custom,'itemTitle','a')).toEqual({source:'auto'});
});
it('does not invent bindings for legacy documents',()=>{
 expect(textColorBindingCss(section,stylePresets[1].palette)).toBe('');
});

it('rejects malformed bindings and never stores a palette snapshot as a bound color',()=>{
 expect(isTextColorBindings({a:{itemTitle:{source:'custom'}}})).toBe(false);
 expect(isTextColorBindings({a:{itemTitle:{source:'light',color:'#ffffff'}}})).toBe(false);
 expect(isTextColorBindings({a:{unknown:{source:'dark'}}})).toBe(false);
 expect(isTextColorBindings({'a"]{}':{itemTitle:{source:'dark'}}})).toBe(false);
 expect(isTextColorBindings({a:{itemTitle:{source:'custom',color:'#aabbcc'}}})).toBe(true);
});
it('remaps color ownership with a duplicated item without changing the original',()=>{
 const original=setTextColorBinding(section,'itemTitle','a',{source:'light'});
 const copy=duplicateSection(original);const id=copy.items![0].id;
 expect(id).not.toBe('a');expect(getTextColorBinding(copy,'itemTitle',id)).toEqual({source:'light'});
 expect(getTextColorBinding(copy,'itemTitle','a')).toBeUndefined();expect(getTextColorBinding(original,'itemTitle','a')).toEqual({source:'light'});
});

it('does not flatten Auto panel ink with an unowned page typography color, but keeps authored page colors',()=>{
 const raw={...section,typography:undefined,starterDesign:'neutral-v1' as const};
 const page={palette:stylePresets[1].palette,typography:{itemTitle:{color:'#18232b'}}};
 const automatic=actionAppearanceCss(raw,resolveStyledSection(raw,page),page);
 expect(automatic).not.toContain('[data-cms-text="itemTitle"]{color:#18232b!important;}');
 const authored={...page,typography:{itemTitle:{color:'#18232b',colorOverride:true}}};
 expect(actionAppearanceCss(raw,resolveStyledSection(raw,authored),authored)).toContain('[data-cms-text="itemTitle"]{color:#18232b!important;}');
});
import {requiresTextColorItem} from './textColorBindings';

it('separates fixed section labels from repeatable item labels',()=>{
 expect(requiresTextColorItem({id:'marquee',type:'media_gallery',galleryPreset:'photo-marquee'} as PageSection,'label')).toBe(false);
 expect(requiresTextColorItem({id:'chapter',type:'chapter_block'} as PageSection,'number')).toBe(false);
 expect(requiresTextColorItem({id:'cards',type:'feature_cards'} as PageSection,'label')).toBe(true);
 expect(requiresTextColorItem({id:'cards',type:'feature_cards'} as PageSection,'itemTitle')).toBe(true);
});
