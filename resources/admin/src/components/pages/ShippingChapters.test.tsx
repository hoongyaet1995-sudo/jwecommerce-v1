import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {createPageTemplateSections} from '../../cms/pageTemplates';
import {PageRuntimeProvider,createAdminPageRuntimePayload} from '../../cms/PageRuntimeContext';
import {SectionRenderer} from './SectionRenderer';
import {WidthChoiceCards} from './WidthChoiceCards';
it('renders seven Shipping Chapters with real navigation, live settings and separate media width',()=>{
 const sections=createPageTemplateSections('shipping-chapters',{},'chapters');
 const payload=createAdminPageRuntimePayload({id:'qa',title:'QA',slug:'qa',status:'draft',template:'standard',description:'',createdAt:'',updatedAt:'',sections},{products:[],categories:[],collections:[]},'MYR',[{id:'live',name:'Configured courier',isActive:true,zoneRegion:'sabah',rateType:'flat'}]);
 const html=renderToStaticMarkup(<PageRuntimeProvider payload={payload}>{sections.map(section=><SectionRenderer key={section.id} section={section}/>)}</PageRuntimeProvider>);
 expect(sections).toHaveLength(7);expect(sections.every(s=>s.motionPreset==='subtle-lift')).toBe(true);
 expect(sections[0].containerMode).toBe('page');expect(sections[0].mediaContainerMode).toBe('full-bleed');
 expect(html).toContain('data-media-width="full-bleed"');expect(html).toContain('#section-chapters-preparation');expect(html).toContain('#section-chapters-delivery');expect(html).toContain('#section-chapters-tracking');
 expect(html).toContain('Sabah — Configured courier');expect(html).toContain('Confirm at checkout');expect(html).toContain('Calculated at checkout');expect(html).toContain('/order-tracking');expect(html).toContain('#storefront-support-widget');
});
it('shows diagram choices with accessible selected state',()=>{
 const html=renderToStaticMarkup(<WidthChoiceCards label="Media width" value="full-bleed" onChange={()=>{}}/>);
 expect(html).toContain('<fieldset');expect(html).toContain('Media width');expect(html.match(/aria-pressed="true"/g)).toHaveLength(1);expect(html).toContain('Edge to edge');
});
