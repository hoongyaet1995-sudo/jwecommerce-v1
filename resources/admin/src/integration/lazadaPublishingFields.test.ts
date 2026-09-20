import {expect,it} from 'vitest';
import {lazadaPublishingFields,lazadaPublishingValues} from './lazadaPublishingFields';
import type {PublishingSource} from './marketplacePublishing';
const source:PublishingSource={product_id:1,title:'JW item',description:'Saved description',brand:'No Brand',revision:'a'.repeat(64),images:[],variants:[
 {id:1,sku:'RED',price_minor:1599,initial_quantity:0,weight_grams:250},
 {id:2,sku:'BLUE',price_minor:1699,initial_quantity:0,weight_grams:350},
]};
const schema=[
 {name:'brand',label:'Brand',attribute_type:'normal',input_type:'singleSelect',is_mandatory:1,options:[{en_name:'No Brand'}]},
 {name:'package_weight',attribute_type:'sku',input_type:'numeric',is_mandatory:1},
 {name:'color_family',label:'Color',attribute_type:'sku',input_type:'singleSelect',is_mandatory:0,is_sale_prop:1,options:[{en_name:'Red'},{en_name:'Blue'}]},
 {name:'care',label:'Care',attribute_type:'normal',input_type:'text',is_mandatory:0},
];
it('uses the separate Lazada brand list when category schema omits brand options',()=>{
 const brandSchema=[{name:'brand',label:'Brand',attribute_type:'normal',input_type:'singleSelect',is_mandatory:1}];
 const fields=lazadaPublishingFields(brandSchema,source,{},['ABBA']);
 expect(fields[0].options).toEqual(['No Brand','ABBA']);
 expect(fields[0].complete).toBe(true);
 expect(lazadaPublishingFields(brandSchema,source,{'normal:brand':'Invented'},['ABBA'])[0].complete).toBe(false);
});
it('keeps per-variant weights and prices and asks for missing sale attributes',()=>{
 const fields=lazadaPublishingFields(schema,source,{});
 expect(fields.find(f=>f.name==='brand')?.value).toBe('No Brand');
 expect(fields.filter(f=>f.name==='package_weight').map(f=>f.value)).toEqual(['0.35','0.25']);
 expect(fields.filter(f=>f.name==='color_family').every(f=>f.required&&!f.complete)).toBe(true);
 expect(fields.find(f=>f.name==='care')?.required).toBe(false);
 const values=lazadaPublishingValues(fields);
 expect(values.skus.RED.package_weight).toBe('0.25');
 expect(values.skus.BLUE.package_weight).toBe('0.35');
 expect(values.normal.brand).toBe('No Brand');
});
it('validates selected English options and rejects unsupported schema shapes',()=>{
 const fields=lazadaPublishingFields(schema,source,{'sku:RED:color_family':'Red','sku:BLUE:color_family':'Wrong'});
 expect(fields.find(f=>f.key==='sku:RED:color_family')?.complete).toBe(true);
 expect(fields.find(f=>f.key==='sku:BLUE:color_family')?.complete).toBe(false);
 expect(()=>lazadaPublishingFields([{name:'x'}],source,{})).toThrow();
 expect(()=>lazadaPublishingFields([...schema,schema[0]],source,{})).toThrow();
});

it('uses the JW option order for all SKU-level Lazada fields',()=>{
 const orderedSource:PublishingSource={...source,options:[
  {name:'Color',values:['Black','Blue']},{name:'Size',values:['S','M','L']},{name:'Edition',values:['Standard','Premium']},
 ],variants:[
  {id:1,sku:'BLUE-L',price_minor:100,initial_quantity:0,options:{Color:'Blue',Size:'L',Edition:'Standard'}},
  {id:2,sku:'BLACK-M',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'M',Edition:'Premium'}},
  {id:3,sku:'BLACK-S',price_minor:100,initial_quantity:0,options:{Color:'Black',Size:'S',Edition:'Standard'}},
 ]};
 const fields=lazadaPublishingFields([{name:'color_family',attribute_type:'sku',input_type:'singleSelect',is_mandatory:1,
  options:[{en_name:'Black'},{en_name:'Blue'}]}],orderedSource,{});

 expect(fields.map(field=>field.sku)).toEqual(['BLACK-S','BLACK-M','BLUE-L']);
});

it('recognizes Lazada size chart metadata and never serializes it as a raw category value',()=>{
 const fields=lazadaPublishingFields([{name:'size_chart',label:'Size Chart',attribute_type:'normal',input_type:'numeric',is_mandatory:1}],source,{});
 expect(fields[0]).toMatchObject({sizeChart:true,required:true,supported:true});
 expect(lazadaPublishingValues(fields).normal).not.toHaveProperty('size_chart');
});

it('recognizes date and image metadata without treating image values as user-entered URLs',()=>{
 const fields=lazadaPublishingFields([
  {name:'manufacture_date',label:'Manufacture Date',attribute_type:'normal',input_type:'date',is_mandatory:1},
  {name:'color_thumbnail',label:'Color Thumbnail',attribute_type:'sku',input_type:'img',is_mandatory:1},
 ],source,{'normal:manufacture_date':'2026-09-20'});

 expect(fields.find(field=>field.name==='manufacture_date')).toMatchObject({supported:true,complete:true});
 expect(fields.filter(field=>field.name==='color_thumbnail')).toEqual([
  expect.objectContaining({sku:'BLUE',supported:true,complete:false}),
  expect.objectContaining({sku:'RED',supported:true,complete:false}),
 ]);
});
