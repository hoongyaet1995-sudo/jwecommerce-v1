import {expect,it} from 'vitest';
import {orderedPublishingVariants} from './marketplaceVariantOrdering';

const options=[
 {name:'Color',values:['Black','Blue']},
 {name:'Size',values:['S','M','L']},
 {name:'Edition',values:['Standard','Premium']},
];

it('orders every option dimension using the JW product option definition',()=>{
 const variants=[
  {id:1,sku:'BLUE-L-PREMIUM',price_minor:100,initial_quantity:0,options:{Color:'Blue',Size:'L',Edition:'Premium'}},
  {id:2,sku:'BLACK-M-PREMIUM',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'M',Edition:'Premium'}},
  {id:3,sku:'BLACK-M-STANDARD',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'M',Edition:'Standard'}},
  {id:4,sku:'BLACK-S-STANDARD',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'S',Edition:'Standard'}},
  {id:5,sku:'BLUE-S-STANDARD',price_minor:100,initial_quantity:0,options:{Color:'Blue',Size:'S',Edition:'Standard'}},
 ];

 expect(orderedPublishingVariants(options,variants).map(variant=>variant.sku)).toEqual([
  'BLACK-S-STANDARD','BLACK-M-STANDARD','BLACK-M-PREMIUM','BLUE-S-STANDARD','BLUE-L-PREMIUM',
 ]);
});

it('puts unknown or incomplete option values after defined variants with a stable SKU fallback',()=>{
 const variants=[
  {id:1,sku:'Z-UNKNOWN',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'XL',Edition:'Standard'}},
  {id:2,sku:'A-MISSING',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'S'}},
  {id:3,sku:'BLACK-S-STANDARD',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'S',Edition:'Standard'}},
  {id:4,sku:'B-UNKNOWN',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'XXL',Edition:'Standard'}},
 ];

 expect(orderedPublishingVariants(options,variants).map(variant=>variant.sku)).toEqual([
  'BLACK-S-STANDARD','A-MISSING','Z-UNKNOWN','B-UNKNOWN',
 ]);
});
