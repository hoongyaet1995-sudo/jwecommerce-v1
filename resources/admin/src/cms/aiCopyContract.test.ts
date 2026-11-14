import {describe,it,expect} from 'vitest';
import {copyCapability,writableCopy} from './aiCopyContract';
import {sectionImageDirection} from './aiImageDirection';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import manifest from '../../../cms/ai-copy-capabilities.v1.json';
import type {PageSection} from '../types';
describe('AI Inspector copy contract',()=>{
 it('matches every registered archetype across visibility states',()=>{for(const template of SECTION_TEMPLATES)for(const h of [undefined,true,false])for(const v of [undefined,true,false]){const section={...template.defaultData,id:'x',type:template.type,title:'',...(h===undefined?{}:{showSectionHeading:h}),...(v===undefined?{}:{valuesShowHeading:v})} as PageSection;const selector=(manifest.selectors as Record<string,string>)[section.type],variants=(manifest.sections as Record<string,Record<string,Record<string,unknown>>>)[section.type];const preset=selector?(section as unknown as Record<string,string>)[selector]??'':'';const flags=(variants[preset]??variants['']);expect(flags[`${section.showSectionHeading===undefined?'unset':section.showSectionHeading}:${section.valuesShowHeading===undefined?'unset':section.valuesShowHeading}`], JSON.stringify(section)).toEqual(copyCapability(section));}});
 it('allows optional supported copy but requires existing CTA destination',()=>{const s={id:'x',type:'hero_banner',title:'',isVisible:true} as PageSection;expect(writableCopy(s)).toContain('eyebrow');expect(writableCopy(s)).not.toContain('buttonText');expect(writableCopy({...s,buttonLink:'/shop'})).toContain('buttonText');expect(writableCopy({...s,buttonLink:'javascript:alert(1)'})).not.toContain('buttonText')});
 it('keeps protected answers and unsupported headings unavailable',()=>{expect(copyCapability({id:'x',type:'faq_accordion',title:'FAQ'} as PageSection).items).toEqual([]);expect(copyCapability({id:'x',type:'cta_strip',title:'',ctaPreset:'industrial-outline'} as PageSection).section).not.toContain('subtitle')});
 it('uses accepted section and item copy in image direction',()=>{const s={id:'x',type:'media_gallery',title:'Section',items:[{id:'a',title:'A quiet workspace',description:'Soft daylight'}]} as PageSection;expect(sectionImageDirection(s,'item:a:imageUrl','Shop','Explore','Minimal','4:3')).toContain('Soft daylight')});
});
