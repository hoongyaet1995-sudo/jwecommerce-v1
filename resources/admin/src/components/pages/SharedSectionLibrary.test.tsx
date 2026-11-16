import {describe,it,expect} from 'vitest';
import {SECTION_TEMPLATES,matchesSectionSearch} from './AddSectionModal';
import {sharedSectionFamilies,createSharedSection} from '../../cms/sharedSectionLibrary';
import {isConnectedFamily} from '../../cms/connectedFamilies';
import {validateUnifiedPricingSection} from '../../cms/unifiedPricing';

describe('shared section library',()=>{
 it('keeps 161 legacy cards and exposes twelve additional discoverable families',()=>{
  expect(SECTION_TEMPLATES).toHaveLength(173);
  expect(sharedSectionFamilies).toHaveLength(12);
  for(const family of sharedSectionFamilies){const card=SECTION_TEMPLATES.find(t=>t.sharedFamily===family.id)!;expect(card).toBeDefined();for(const layout of family.layouts)expect(matchesSectionSearch(card,layout.label)).toBe(true);}
 });
 it('creates independent renderer-backed starters for every layout, including standalone matrix',()=>{
  for(const family of sharedSectionFamilies)for(const layout of family.layouts){
   const a=createSharedSection(family.id,layout.id),b=createSharedSection(family.id,layout.id);
   expect(a.id).not.toBe(b.id);expect(a.type).toBe(family.type);expect(a.containerMode).toBe('page');
   expect(a.items?.some(x=>b.items?.some(y=>x.id===y.id))).not.toBe(true);
   if(family.id==='pricing'){expect(a.pricingMatrix?.sourceSectionId).toBeUndefined();expect(validateUnifiedPricingSection(a,[a])).toEqual([]);expect(a.items?.length).toBeGreaterThan(0);}
   else expect(isConnectedFamily(a)).toBe(true);
  }
 });
});
