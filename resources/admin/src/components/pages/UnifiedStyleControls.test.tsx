// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from './SectionInspector';
import {SectionStyleSettings} from './SectionStyleSettings';
import {SectionButtonSettings} from './SectionButtonSettings';
import {PageStyleSettings} from './PageStyleSettings';
import {resolveStyledSection,stylePresets} from '../../cms/pageStyle';
import type {CmsPage,PageSection} from '../../types';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../../cms/useCmsBrand',()=>({useCmsBrand:()=>({palette:stylePresets[0].palette})}));
vi.mock('../../components/search/EntitySearchPanel',()=>({EntitySearchPanel:()=>null}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('deduplicates controls, preserves local styles through page palette edits, and resets the exact scope',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({palettes:[]})})));
 let latest:CmsPage;const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const legacy:PageSection={id:'s',type:'rich_text',richTextPreset:'industrial-split',title:'Keep content',subtitle:'Keep label',isVisible:true,surface:'contrast',typography:{title:{fontSize:36,color:'#ffcc99'}},motionPreset:'subtle-lift'};
 function Editor(){const [page,set]=useState<CmsPage>({id:'qa',title:'QA',slug:'qa',status:'draft',template:'standard',sections:[legacy],style:{palette:stylePresets[0].palette,colorSource:'custom',typography:{title:{fontSize:50,fontWeight:700}}}} as CmsPage);latest=page;return <><PageStyleSettings page={page} onChange={patch=>set(p=>({...p,...patch}))} onClose={vi.fn()}/><div data-test-section><SectionInspector section={page.sections[0]} pageId="qa" pageTypography={page.style?.typography} pagePalette={page.style?.palette} onChange={section=>set(p=>({...p,sections:[section]}))} onClose={vi.fn()} onDelete={vi.fn()} onMediaUpload={vi.fn()}/></div></>;}
 await act(async()=>root.render(<Editor/>));const button=(scope:Element,label:string)=>[...scope.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.getAttribute('aria-label')===label||b.textContent===label)!;
 const local=host.querySelector('[data-test-section]')!,pagePanel=host.querySelector('[data-cms-page-settings]')!;
 await act(async()=>button(pagePanel,'Page palette: Clean modern').click());expect(latest!.sections[0].typography?.title).toEqual({fontSize:36,color:'#ffcc99'});expect(latest!.style?.palette).toEqual(stylePresets[1].palette);expect(button(pagePanel,'Page palette: Clean modern').getAttribute('aria-pressed')).toBe('true');
 const design=[...local.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!;await act(async()=>design.click());expect(local.textContent).not.toContain('Quick Theme Presets');expect(local.textContent).not.toContain('Micro Color Controls');expect(local.querySelectorAll('[data-section-surface]')).toHaveLength(1);expect(local.querySelector('[data-section-action-colors]')).toBeNull();expect(pagePanel.querySelectorAll('[data-page-palette-picker]')).toHaveLength(1);
 await act(async()=>button(local,'Reset heading to page default').click());expect(latest!.sections[0].surface).toBe('contrast');expect(resolveStyledSection(latest!.sections[0],latest!.style).typography?.title?.fontSize).toBe(50);
 await act(async()=>button(local,'Restore shared section defaults').click());expect(latest!.sections[0].surface).toBeUndefined();expect(latest!.sections[0].title).toBe('Keep content');expect(latest!.sections[0].motionPreset).toBe('subtle-lift');
 await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();
});

it('keeps button color overrides separate from retained surface seeds',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({id:'colors',type:'cta_strip',title:'Title',isVisible:true,surface:'contrast',customColors:{primaryBtnBg:'#ffcc99'},buttonColors:{primaryBg:'#abcdef'}});latest=s;return <SectionButtonSettings section={s} onChange={set}/>;}
 await act(async()=>root.render(<Editor/>));const input=host.querySelector<HTMLInputElement>('[aria-label="Primary button background"]')!,picker=host.querySelector<HTMLInputElement>('[aria-label="Primary button background picker"]')!;
 expect(input.value).toBe(picker.value);expect(input.value).toBe('#abcdef');
 await act(async()=>{input.value='#123456';input.dispatchEvent(new FocusEvent('focusout',{bubbles:true}));});expect(latest!.buttonColors?.primaryBg).toBe('#123456');expect(latest!.customColors?.primaryBtnBg).toBe('#ffcc99');
 await act(async()=>root.unmount());host.remove();
});

it('has one page hover owner and preserves theme inheritance while customizing it',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({palettes:[]})})));
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let latest:CmsPage;
 function Editor(){const [page,set]=useState<CmsPage>({id:'qa',title:'QA',slug:'qa',status:'draft',template:'standard',sections:[],style:{palette:stylePresets[0].palette,colorSource:'theme'}} as CmsPage);latest=page;return <PageStyleSettings page={page} onChange={patch=>set(p=>({...p,...patch}))} onClose={()=>{}}/>;}
 await act(async()=>root.render(<Editor/>));expect(host.querySelector('[aria-label="Page color hover"]')).toBeNull();
 await act(async()=>(host.querySelector('[aria-label="Customize page hover"]') as HTMLButtonElement).click());
 const input=host.querySelector<HTMLInputElement>('[aria-label="Page button hover color"]')!;
 await act(async()=>{input.value='#654321';input.dispatchEvent(new FocusEvent('focusout',{bubbles:true}));});
 expect(latest!.style?.buttonHoverColor).toBe('#654321');expect(latest!.style?.colorSource).toBe('theme');expect(latest!.style?.palette).toEqual(stylePresets[0].palette);
 await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();
});
