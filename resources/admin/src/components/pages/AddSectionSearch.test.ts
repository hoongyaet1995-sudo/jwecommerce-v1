import {describe,expect,it} from 'vitest';
import {matchesSectionSearch,SECTION_TEMPLATES} from './AddSectionModal';

describe('section library discovery',()=>{
 it.each(['Policy Content','policy_content',' POLICY   CONTENT ','policy-content'])('finds every policy layout using the Inspector type %s',query=>{
  const policies=SECTION_TEMPLATES.filter(template=>template.type==='policy_content');
  expect(policies.length).toBeGreaterThan(0);
  expect(SECTION_TEMPLATES.filter(template=>matchesSectionSearch(template,query))).toEqual(policies);
 });
 it('still searches layout names and preserves nonmatching results',()=>{
  const columns=SECTION_TEMPLATES.find(template=>template.type==='policy_content'&&template.defaultData.policyPreset==='columns')!;
  expect(matchesSectionSearch(columns,columns.title)).toBe(true);
  expect(matchesSectionSearch(columns,'unknown-section-name')).toBe(false);
  expect(matchesSectionSearch(columns,'')).toBe(true);
 });
});
