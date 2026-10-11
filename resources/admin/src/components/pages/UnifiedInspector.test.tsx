import type {PageSection} from '../../types';
import {writeFileSync} from 'node:fs';
import {sectionContentFields} from '../../cms/contentCapabilities';
import {repeatedItemFields} from '../../cms/repeatedItemContract';
// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from './SectionInspector';
import {SECTION_TEMPLATES} from './AddSectionModal';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(value:number)=>String(value)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
const props={pageId:'sample',onChange:vi.fn(),onMediaUpload:vi.fn(),onDelete:vi.fn(),onClose:vi.fn()};
it.each(SECTION_TEMPLATES)('mounts $title with applicable controls and three categories',(template)=>{
  const html=renderToStaticMarkup(<SectionInspector {...props} section={{...template.defaultData,id:'sample',isVisible:true}}/>);
  expect(html,template.title).toContain('Inspector categories');
  const dom=document.createElement('div');dom.innerHTML=html;
  expect(dom.querySelector('select[aria-label="Layout preset"]'),template.title).toBeNull();
  expect(dom.querySelector('select[aria-label="Collection layout preset"]'),template.title).toBeNull();
  expect(html.match(/role="tab"/g),template.title).toHaveLength(3);
  expect(html,template.title).toContain('Section content width');
  expect(html,template.title).toContain('Follow Page');
});
it('keeps image assets in Content, geometry in Design and container exceptions in Advanced',async()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const template=SECTION_TEMPLATES.find(item=>item.title==='Editorial Cover')!;
 await act(async()=>root.render(<SectionInspector {...props} selectedElement={{target:'title',role:'title',revision:1}} section={{...template.defaultData,id:'sample',isVisible:true}}/>));
 const tabs=[...node.querySelectorAll<HTMLButtonElement>('[role="tab"]')];
 expect(tabs[0].getAttribute('aria-selected')).toBe('true');
 expect(node.querySelector('[aria-label="Cover introduction"]')?.closest('[hidden]')).toBeNull();
 act(()=>tabs[1].click());
 expect(tabs[1].getAttribute('aria-selected')).toBe('true');
 const fontButton=[...node.querySelectorAll('button')].find(button=>button.textContent?.includes('Change font'));
 expect(fontButton).toBeDefined();expect(fontButton!.closest('[hidden]')).toBeNull();
 const assets=[...node.querySelectorAll<HTMLInputElement>('[aria-label="Image alternative text"]')];
 expect(assets).toHaveLength(1);expect(assets[0].closest('[hidden]')).not.toBeNull();
 const ratios=[...node.querySelectorAll('[aria-label="Image ratio"]')];expect(ratios).toHaveLength(1);
 act(()=>tabs[2].click());
 expect(node.querySelector('[data-inspector-container]')?.closest('[hidden]')).toBeNull();
 act(()=>root.unmount());node.remove();
});
it('location item controls do not depend on accidental saved fields',()=>{
 const html=renderToStaticMarkup(<SectionInspector {...props} section={{id:'location',isVisible:true,type:'store_locator',title:'Location',contactPreset:'location',items:[{id:'a',title:'Hours',description:'Open'},{id:'b',title:'Appointment',description:'Call',badge:'Legacy badge',icon:'Calendar'}]}}/>);
 expect(html.match(/aria-label="Choose icon"/g)).toHaveLength(3);
 expect(html).not.toContain('>Badge</span>');
 expect(html).toContain('Address icon');
});
it('all textual Delete and Clear actions use accessible icon buttons',()=>{
 for(const template of SECTION_TEMPLATES){
  const html=renderToStaticMarkup(<SectionInspector {...props} section={{...template.defaultData,id:'sample'}}/>);
  expect(html,template.title).not.toMatch(/>\s*(Delete(?: Image)?|Clear)\s*<\/button>/);
 }
});

it('every registered repeated-item preset declares its editable capabilities',()=>{
 for(const template of SECTION_TEMPLATES){
  if(template.defaultData.items?.length)expect(repeatedItemFields({...template.defaultData,id:'sample'}),template.title).not.toHaveLength(0);
 }
});

it('refreshes Content capabilities on Story Cards -> Classic -> Story Cards without dropping entries',async()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');const root=createRoot(node);
 const base={id:'switch',type:'media_text_split' as const,title:'Keep heading',isVisible:true,items:[{id:'entry',title:'Keep story',description:'Keep copy',imageUrl:'/saved.png',linkUrl:'/shop'}]};
 const render=async(layoutPreset:'default'|'editorial-story-cards')=>{await act(async()=>root.render(<SectionInspector {...props} section={{...base,layoutPreset}}/>));};
 await render('editorial-story-cards');expect(node.textContent).toContain('Add story');
 await render('default');expect(node.textContent).toContain('Add Card');expect(node.querySelector('input[value="Keep story"]')).not.toBeNull();
 await render('editorial-story-cards');expect(node.textContent).toContain('Add story');
 expect(node.querySelector('textarea')?.value||node.textContent).toBeTruthy();act(()=>root.unmount());
});
it('registers all twelve media text layouts as independently selectable starters',()=>{
 const presets=SECTION_TEMPLATES.filter(t=>t.type==='media_text_split').map(t=>t.defaultData.layoutPreset??'default');
 expect(new Set(presets)).toEqual(new Set(['editorial-journal-rows','artisan-story','kids-discovery','default','editorial-hero','editorial-story','editorial-story-cards','story-with-steps','editorial-cover','flush-split','producer-story','tech-feature']));
});

it.each(['editorial-hero','editorial-story','editorial-cover'] as const)('exposes empty supported Content fields for %s without pre-existing saved keys',async(layoutPreset)=>{
 const node=document.createElement('div');const root=createRoot(node);
 await act(async()=>root.render(<SectionInspector {...props} section={{id:'empty',type:'media_text_split',title:'Title',isVisible:true,layoutPreset}}/>));
 expect(node.querySelector('[data-inspector-field="content"]')).not.toBeNull();
 expect(node.querySelector('[data-inspector-field="subtitle"]')).not.toBeNull();
 expect(node.querySelector('[aria-label="Image alternative text"]')).not.toBeNull();
 expect(node.querySelector('[data-inspector-field="leadText"]')!==null).toBe(layoutPreset!=='editorial-story');
 expect(node.textContent).not.toContain('Add Card');
 act(()=>root.unmount());
});

const sparseSection=(template:typeof SECTION_TEMPLATES[number]):PageSection=>({
 id:'sparse',type:template.type,title:'Sparse heading',isVisible:true,
 ...Object.fromEntries(Object.entries(template.defaultData).filter(([key])=>key.endsWith('Preset')||['valuesShowHeading','showSectionHeading'].includes(key))),
});
it.each(SECTION_TEMPLATES)('offers empty Content entries for $title independently of saved optional values',template=>{
 const section=sparseSection(template);
 const node=document.createElement('div');node.innerHTML=renderToStaticMarkup(<SectionInspector {...props} section={section}/>);
 const noHeading=['announcement_bar','chapter_navigation','stats_counter','icon_list'];
 const headingHidden=section.type==='feature_cards'&&section.featureCardsPreset==='service-highlights'||section.type==='hero_slider'&&['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??'')||section.showSectionHeading===false||section.type==='chapter_block'&&section.chapterPreset==='principles'||(section.type==='values_list'||section.featureCardsPreset==='values-list')&&!section.valuesShowHeading;
 expect(!!node.querySelector('[data-inspector-field="title"]'),template.title).toBe(!noHeading.includes(section.type)&&!headingHidden);
 if(['hero_banner','split_hero','featured_deal','newsletter_signup'].includes(section.type))expect(node.querySelector('[data-inspector-field="buttonText"]')).not.toBeNull();
 if(section.type==='product_listing'&&section.listingPreset==='editorial-grid'||section.type==='contact_form'&&section.contactPreset==='enquiry')expect(node.querySelector('[data-inspector-field="buttonLink"]')).not.toBeNull();
 if(section.type==='media_text_split'){
  if(!['editorial-cover','kids-discovery'].includes(section.layoutPreset??''))expect(node.querySelector('[data-inspector-field="buttonText"]')).not.toBeNull();
  expect(node.textContent).toContain(section.layoutPreset==='editorial-story-cards'?'Add story':section.layoutPreset==='story-with-steps'?'Add Step':'Text & headings');
 }
 if(section.type==='chapter_block'&&['perspective','delivery'].includes(section.chapterPreset??''))expect(node.textContent).toContain('Add Photo');
 if(section.type==='collection_tabs')expect(node.textContent).toContain('Add Tab');
 if(section.type==='video_embed')expect(node.textContent).toContain('Video');
 if(section.type==='contact_form')expect(node.textContent).toMatch(/Add (Topic|Category)/);
});
it('allows empty classic slider video editing and generic card reorder',()=>{
 const html=renderToStaticMarkup(<SectionInspector {...props} section={{id:'slider',isVisible:true,type:'hero_slider',title:'Slider',items:[{id:'one',title:'One'},{id:'two',title:'Two'}]}}/>);
 expect(html).toContain('Slide video URL / File Upload');expect(html).toContain('Move item 1 down');expect(html).toContain('Move item 2 up');
});

const repeatableTemplates=SECTION_TEMPLATES.filter(template=>repeatedItemFields({...template.defaultData,id:'audit'}).length);
it.each(repeatableTemplates)('exposes renderer-backed repeated fields for $title',template=>{
 const section={...template.defaultData,id:'audit',deliverySource:'manual' as const};
 const fields=repeatedItemFields(section);
 const item:Record<string,unknown>={id:'audit-item',title:'AUDIT-title'};
 for(const field of fields){
  if(['sourceType','sourceId','icon','imageUrl','authorAvatar','videoUrl'].includes(field))continue;
  item[field]=field==='price'?37:field==='rating'?4:field==='isHighlighted'?true:field==='features'?['AUDIT-features']:'AUDIT-'+field;
 }
 if(section.layoutPreset==='kids-discovery')item.badge='care';
 if(fields.includes('imageUrl')&&!['policy_content','chapter_block'].includes(section.type))item.imageUrl='https://images.unsplash.com/photo-audit';
 if(fields.includes('authorAvatar'))item.authorAvatar='https://images.unsplash.com/photo-audit';
 section.items=[item as unknown as NonNullable<typeof section.items>[number]];
 if(section.type==='comparison_matrix'&&section.comparisonPreset==='device-table')section.items=[...['one','two','three'].map(id=>({id,title:id,subtitle:'device'})),...section.items];
 if(fields.includes('imageUrl')&&['policy_content','chapter_block'].includes(section.type))section.items.push({id:'audit-photo',title:'Photo description',imageUrl:'https://images.unsplash.com/photo-audit'});
 const node=document.createElement('div');node.innerHTML=renderToStaticMarkup(<SectionInspector {...props} section={section}/>);
 const values=[...node.querySelectorAll<HTMLInputElement|HTMLTextAreaElement|HTMLSelectElement>('input,textarea,select')].map(control=>control.value);
 const unsupported: string[]=[];
 for(const field of fields){
  if(['sourceType','sourceId','icon','imageUrl','authorAvatar','videoUrl','isHighlighted'].includes(field))continue;
  if(section.type==='chapter_block'&&section.chapterPreset==='perspective'&&field==='title')continue;
  const expected=section.layoutPreset==='kids-discovery'&&field==='badge'?'care':field==='price'?'37':field==='rating'?'4':field==='features'?'AUDIT-features':'AUDIT-'+field;
  if(!values.includes(expected))unsupported.push(field);
 }
 expect(unsupported,template.title+' missing editable entry fields').toEqual([]);
});
it('exports the reviewed starter and consumer capability matrix for canonical/backend acceptance',()=>{
 const records=SECTION_TEMPLATES.map((template,index)=>{
  const section={...template.defaultData,id:'audit-'+index};
  return {name:template.title,type:template.type,section,contentFields:sectionContentFields(section),itemFields:repeatedItemFields(section)};
 });
 expect(new Set(records.map(row=>row.type)).size).toBe(35);
 writeFileSync('../../output/cms-page-style/full-content-capability-matrix.json',JSON.stringify(records,null,2));
});

it.each(repeatableTemplates)('can add, edit, reorder and delete entries in mounted $title Content',async template=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');const root=createRoot(node);
 let saved:PageSection={...template.defaultData,id:'interaction',items:[],deliverySource:'manual' as const};
 function Harness(){const [section,setSection]=useState(saved);return <SectionInspector {...props} section={section} onChange={next=>{saved=next;setSection(next)}}/>;}
 await act(async()=>root.render(<Harness/>));
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(button=>/^Add (Card|Slide|story|Step|Photo|Principle|Destination|Entry|Navigation Link|Column|Value|device|specification row)$/.test(button.textContent?.trim()??'')&&!button.closest('[hidden]'));
 expect(add(),template.title).toBeDefined();
 await act(async()=>add()!.click());await act(async()=>add()!.click());
 expect(saved.items,template.title).toHaveLength(2);
 expect(new Set(saved.items!.map(item=>item.id)).size,template.title).toBe(2);
 const firstId=saved.items![0].id;
 const container=node.querySelector(`[data-inspector-item="${firstId}"]`)!;
 const editor=(container.matches('input,textarea')?container:container.querySelector('input:not([type="file"]),textarea')) as HTMLInputElement|HTMLTextAreaElement;
 expect(editor,template.title+' entry editor').not.toBeNull();
 const value=editor instanceof HTMLInputElement&&editor.type==='number'?'37':'EDITED ENTRY';
 await act(async()=>{Object.getOwnPropertyDescriptor(editor instanceof HTMLTextAreaElement?HTMLTextAreaElement.prototype:HTMLInputElement.prototype,'value')!.set!.call(editor,value);editor.dispatchEvent(new Event('input',{bubbles:true}));});
 expect(Object.values(saved.items![0]).flat().map(String),template.title+' edit result').toContain(value);
 const move=[...node.querySelectorAll<HTMLButtonElement>('button')].find(button=>[button.getAttribute('aria-label'),button.title,button.textContent?.trim()].some(name=>/^(Up|Down|Move (?:item|value) [0-9]+ (?:up|down)|Move device up|Move device down|Move row up|Move row down)$/.test(name??''))&&!button.disabled&&!button.closest('[hidden]'));
 expect(move,template.title+' reorder').toBeDefined();await act(async()=>move!.click());expect(saved.items![1].id,template.title+' retained stable identity').toBe(firstId);

 const remove=[...node.querySelectorAll<HTMLButtonElement>('button')].find(button=>(/^Delete(?: |$)/.test(button.getAttribute('aria-label')??button.title)||/^Remove (device|row)$/.test(button.getAttribute('aria-label')??button.textContent?.trim()??''))&&!button.closest('[hidden]')&&!['Delete section','Delete Section'].includes(button.getAttribute('aria-label')??button.title));
 expect(remove,template.title).toBeDefined();await act(async()=>remove!.click());
 expect(saved.items,template.title).toHaveLength(1);act(()=>root.unmount());
});

it.each(['guidelines','choices','steps','process-flow','timeline','tabs','columns','document','directory'] as const)('keeps embedded Chapter Policy %s Content editable with empty entries',policyPreset=>{
 const node=document.createElement('div');node.innerHTML=renderToStaticMarkup(<SectionInspector {...props} section={{id:'policy',isVisible:true,type:'chapter_block',title:'Policy chapter',chapterPreset:'policy',policyPreset}}/>);
 expect(node.textContent).toContain('Add Entry');expect(node.textContent).toContain('Add Image');expect(node.querySelector('[data-inspector-field="content"]')).not.toBeNull();
});
it('exposes Manifesto text while hiding the dormant quote editor',()=>{
 const html=renderToStaticMarkup(<SectionInspector {...props} section={{id:'manifesto',isVisible:true,type:'rich_text',title:'Manifesto',richTextPreset:'manifesto'}}/>);
 expect(html).toContain('data-inspector-field="content"');expect(html).not.toContain('aria-label="Quote"');
});
