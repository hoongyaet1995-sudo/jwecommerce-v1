import {it,expect} from 'vitest';
import {splitCompositeHero,websiteBuilderHero} from './websiteBuilderHero';
import {renderedColorFields,supportsDesignField} from './sectionDesignCapabilities';
import {websiteBuilderOverview} from './websiteBuilderOverview';
import {websiteBuilderStory} from './websiteBuilderStory';
import {websiteBuilderScenarios} from './websiteBuilderScenarios';
import {websiteBuilderWorkflow} from './websiteBuilderWorkflow';
import {websiteBuilderConnections} from './websiteBuilderConnections';
import {websiteBuilderStarterFaq} from './websiteBuilderStarterFaq';
import {gradientClosing} from './gradientClosing';
it('neutralizes only the reusable Hero starter and keeps approved page content independent',()=>{
 const generic=splitCompositeHero('new');expect(generic.buttonLink).toBe('');expect(generic.secondaryButtonLink).toBe('');expect(JSON.stringify(generic)).not.toContain('jwaistudio');expect(JSON.stringify(generic)).not.toContain('#section-');
 expect(websiteBuilderHero('f01').secondaryButtonLink).toBe('#section-f01-builder');expect(websiteBuilderHero('f01').buttonLink).toContain('jwaistudio');
});
it('gates colors by actual eight-preset rendering, irrespective of retained saved colors',()=>{
 for(const s of [websiteBuilderOverview('s'),websiteBuilderStory('s'),websiteBuilderScenarios('s'),websiteBuilderWorkflow('s'),websiteBuilderConnections('s'),websiteBuilderStarterFaq('s')]){
  expect(renderedColorFields(s)).toEqual(['customBgColor','titleColor','contentColor']);expect(supportsDesignField(s,'columnsCount')).toBe(false);
 }
 expect(renderedColorFields(gradientClosing('s'))).toContain('primaryBtnBg');expect(renderedColorFields(gradientClosing('s'))).not.toContain('secondaryBtnBg');expect(renderedColorFields(websiteBuilderHero('s'))).toContain('eyebrowColor');
 expect(supportsDesignField(websiteBuilderOverview('s'),'textAlignment')).toBe(false);expect(supportsDesignField(websiteBuilderScenarios('s'),'textAlignment')).toBe(true);
});
