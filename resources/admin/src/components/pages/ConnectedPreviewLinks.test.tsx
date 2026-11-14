import React from 'react';
import {expect,it,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {connectedPageSections} from '../../cms/connectedPageTemplates';
import {ConnectedFamilySection} from './ConnectedFamilySection';
import {SectionRenderer} from './SectionRenderer';
vi.mock('../../cms/PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[],style:{}}},links:{'/pages/jw-connected-commerce':'/admin/pages/12/preview'},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:()=>{}})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
it('uses existing CMS preview destinations without changing stored public URLs',()=>{
 const section=connectedPageSections('jw-connected-home',{connectedPageDestinations:{'jw-connected-commerce':'/pages/jw-connected-commerce'}},'link-check')[0];
 const before=JSON.stringify(section);
 const html=renderToStaticMarkup(<ConnectedFamilySection section={section} links={{'/pages/jw-connected-commerce':'/admin/pages/12/preview'}}/>);
 expect(html).toContain('href="/admin/pages/12/preview"');
 expect(JSON.stringify(section)).toBe(before);
 expect(renderToStaticMarkup(<ConnectedFamilySection section={section}/>)).toContain('href="/pages/jw-connected-commerce"');
});

it('wires the authoritative preview map through the actual SectionRenderer',()=>{
 const section=connectedPageSections('jw-connected-home',{connectedPageDestinations:{'jw-connected-commerce':'/pages/jw-connected-commerce'}},'renderer-link')[0];
 expect(renderToStaticMarkup(<SectionRenderer section={section}/>)).toContain('href="/admin/pages/12/preview"');
});
