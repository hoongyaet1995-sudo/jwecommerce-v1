import {describe,it,expect} from 'vitest';
import {createPageTemplateSections} from './pageTemplates';
import {deliveryRows} from './deliveryInformation';
describe('Shipping Editorial',()=>{
 it('creates six independent sections with real anchors and reusable presets',()=>{
  const first=createPageTemplateSections('shipping-editorial',{},'shipping-a');
  const second=createPageTemplateSections('shipping-editorial',{},'shipping-b');
  expect(first.map(s=>s.type)).toEqual(['media_text_split','feature_cards','delivery_table','media_text_split','faq_accordion','cta_strip']);
  expect(first[0].buttonLink).toBe('#section-shipping-a-delivery');
  expect(first[0].secondaryButtonLink).toBe('/order-tracking');
  expect(first[2].deliverySource).toBe('settings');
  expect(first[3].items).toHaveLength(3);
  first[3].items![0].title='Changed';expect(second[3].items![0].title).not.toBe('Changed');
 });
 it('projects only enabled rates without making up fees or estimates',()=>{
  const rows=deliveryRows([{id:'a',name:'Standard',zoneRegion:'peninsular',isActive:true,rateType:'flat',flatRateCost:5},{id:'b',name:'Hidden',zoneRegion:'sabah',isActive:false,rateType:'flat'}]);
  expect(rows).toEqual([{id:'a',title:'Peninsular Malaysia — Standard',deliveryEstimate:'Confirm at checkout',deliveryFee:'Calculated at checkout'}]);
 });
});
