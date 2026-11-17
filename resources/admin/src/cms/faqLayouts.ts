import type {PageSection} from '../types';
import {isConnectedFamily,connectedFamilyMetadata,type ConnectedSection} from './connectedFamilies';
export const faqLayoutOptions=[['faq-list','FAQ List'],['framed-faq','Framed FAQ'],['pricing-card-faq','Pricing Card & FAQ — External Heading'],['pricing-band-faq','Pricing Band & FAQ'],['corporate-starter-faq','Pricing Card & FAQ'],['saas-split','SaaS Split FAQ'],['default','Classic — separate cards'],['industrial-split','Industrial Split — heading beside answers'],['minimal','Minimal — clean divided list']] as const;
export function changeFaqLayout(section:PageSection,value:string):PageSection{
 if(section.type!=='faq_accordion'||!faqLayoutOptions.some(([id])=>id===value))return section;
 const current=section as ConnectedSection;
 if(value==='faq-list'){
  if(isConnectedFamily(section))return section;
  const remembered=current.connectedLayoutMemory?.['faq-list'];
  const diagram=remembered&&connectedFamilyMetadata[remembered]?.family==='faq'?remembered:'faq-composition-1';
  return {...section,connectedDiagram:diagram,connectedLayout:'faq-list',items:section.items?.map(item=>({...item,layoutGroup:'c0',layoutVariant:(item as any).layoutVariant??0}))} as ConnectedSection;
 }
 const next:ConnectedSection={...current,faqPreset:value as PageSection['faqPreset']};
 if(isConnectedFamily(section))next.connectedLayoutMemory={...section.connectedLayoutMemory,'faq-list':section.connectedDiagram!};
 next.connectedLayout=undefined;next.connectedDiagram=undefined;
 return next;
}
