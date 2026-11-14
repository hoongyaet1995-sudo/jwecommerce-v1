import {parseDraftImport} from './pageImport';
import {writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {describe,it,expect} from 'vitest';
import {pricingSampleSections} from './unifiedPricingSample';
import {resolveUnifiedPricing,pricingAmount,reconcilePricingGroups,movePricingEntry,validatePricingMatrix} from './unifiedPricing';
describe('canonical unified pricing',()=>{
it('shares source plans and all matrix rows',()=>{const sections=pricingSampleSections('a'),facts=resolveUnifiedPricing(sections[1],sections)!;expect(facts.plans).toHaveLength(4);expect(facts.groups).toHaveLength(11);expect(facts.groups.flatMap(g=>g.rows)).toHaveLength(74);expect(validatePricingMatrix(sections[0].pricingMatrix!,facts.plans)).toEqual([]);expect(facts.plans[0]).toBe(sections[0].items![0]);});
it('never invents annual prices and supports empty arrays',()=>{const s=pricingSampleSections('b');expect(pricingAmount(s[0].items![0],'yearly','RM')).toBe('On request');s[0].items=[];s[0].pricingMatrix!.groups=[];expect(resolveUnifiedPricing(s[1],s)?.plans).toEqual([]);expect(reconcilePricingGroups([],[])).toEqual([]);});
it('adds/deletes cells by plan ID without mutating source',()=>{const [s]=pricingSampleSections('c'),plans=s.items!;const next=reconcilePricingGroups(s.pricingMatrix!.groups,[plans[1],{id:'new',title:'New'}]);expect(Object.keys(next[0].rows[0].cells)).toEqual([plans[1].id,'new']);expect(next[0].rows[0].cells.new).toEqual({type:'text',value:''});expect(movePricingEntry(plans,plans[0].id,1)[1].id).toBe(plans[0].id);expect(s.pricingMatrix!.groups[0].rows[0].cells).not.toHaveProperty('new');});
it('preserves source linkage through JSON and never uses stale missing-source values',()=>{const s=JSON.parse(JSON.stringify(pricingSampleSections('d')));expect(resolveUnifiedPricing(s[1],s)?.plans[0].title).toBe('Starter');expect(resolveUnifiedPricing(s[1],[])?.status).toBe('missing-source');expect(resolveUnifiedPricing(s[1],[])?.plans).toEqual([]);});
});

it('imports a complete canonical pricing document and exports PHP fixture evidence',()=>{
 const document={title:'Commerce pricing',slug:'commerce-pricing',template:'standard',headerPresentation:{mode:'normal',tone:'light'},sections:pricingSampleSections('canonical-pricing')};
 const parsed=parseDraftImport(JSON.stringify(document),'pricing-acceptance');expect(parsed).toEqual(document);expect(parseDraftImport(JSON.stringify(parsed),'pricing-acceptance')).toEqual(document);
 writeFileSync(resolve(process.cwd(),'../../output/jw-cms-integration-20261114/pricing-canonical-fixture.json'),JSON.stringify(document,null,2)+'\n');
});
