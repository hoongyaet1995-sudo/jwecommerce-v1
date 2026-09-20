import {describe, expect, it} from 'vitest';
import type {PublishingSource} from '../../integration/marketplacePublishing';
import {
  normalizeLazadaAttributes,
  serializeLazadaAttributes,
  validateAttributeValue,
} from './lazada';
import type {MarketplaceAttributeValue} from './types';

const source:PublishingSource={
  product_id:41,
  title:'Tekat Gold',
  description:'Handmade jewellery',
  brand:'JW',
  revision:'a'.repeat(64),
  images:[{id:91,path:'/storage/tekat.jpg'}],
  variants:[
    {id:2,sku:'TEKAT 5G',price_minor:663796,initial_quantity:0,weight_grams:5},
    {id:1,sku:'TEKAT 1G',price_minor:132759,initial_quantity:0,weight_grams:1},
  ],
};

const schema=[
  {name:'title',label:'Title',attribute_type:'normal',input_type:'text',is_mandatory:1},
  {name:'description',label:'Description',attribute_type:'normal',input_type:'richText',is_mandatory:1},
  {name:'main_diamond_carat',label:'Main diamond carat',attribute_type:'sku',input_type:'numeric',is_mandatory:1},
  {name:'manufacture_date',label:'Manufacture date',attribute_type:'normal',input_type:'date',is_mandatory:1},
  {name:'material',label:'Material',attribute_type:'normal',input_type:'singleSelect',is_mandatory:1,options:[{en_name:'Gold'},{en_name:'Silver'}]},
  {name:'occasion',label:'Occasion',attribute_type:'normal',input_type:'multiSelect',is_mandatory:0,options:[{en_name:'Wedding'},{en_name:'Gift'}]},
  {name:'model_type',label:'Model Type',attribute_type:'normal',input_type:'enumInput',is_mandatory:0,options:[{en_name:'Classic'}]},
  {name:'gem_type',label:'Gem Type',attribute_type:'normal',input_type:'multiEnumInput',is_mandatory:0,options:[{en_name:'Ruby'}]},
  {name:'color_thumbnail',label:'Color Thumbnail',attribute_type:'sku',input_type:'img',is_mandatory:1},
  {name:'chart_asset',label:'Size Chart',attribute_type:'normal',input_type:'numeric',is_mandatory:1},
];

describe('normalizeLazadaAttributes',()=>{
  it('maps all documented Lazada input types and keeps SKU identities separate',()=>{
    const fields=normalizeLazadaAttributes(schema,source,new Map(),[]);
    expect(fields).toEqual(expect.arrayContaining([
      expect.objectContaining({key:'lazada:product:manufacture_date',control:'date',required:true}),
      expect.objectContaining({key:'lazada:variant:TEKAT 1G:color_thumbnail',control:'image',sku:'TEKAT 1G'}),
      expect.objectContaining({key:'lazada:variant:TEKAT 5G:color_thumbnail',control:'image',sku:'TEKAT 5G'}),
      expect.objectContaining({providerName:'description',control:'textarea'}),
      expect.objectContaining({providerName:'occasion',control:'multi',allowCustom:false}),
      expect.objectContaining({providerName:'model_type',control:'single',allowCustom:true}),
      expect.objectContaining({providerName:'gem_type',control:'multi',allowCustom:true}),
      expect.objectContaining({key:'lazada:size-chart:chart_asset',control:'image',purpose:'size-chart'}),
    ]));
  });

  it('marks unknown mandatory metadata unsupported instead of falling back to text',()=>{
    const [field]=normalizeLazadaAttributes([
      {name:'certificate',label:'Certificate',attribute_type:'normal',input_type:'file',is_mandatory:1},
    ],source,new Map(),[]);
    expect(field).toMatchObject({supported:false,required:true});
    expect(validateAttributeValue(field,{kind:'empty'})).toContain('cannot be completed');
  });

  it('validates finite numbers, ISO dates, options, uniqueness, and cardinality',()=>{
    const fields=normalizeLazadaAttributes(schema,source,new Map(),[]);
    const numeric=fields.find(field=>field.providerName==='main_diamond_carat')!;
    const date=fields.find(field=>field.providerName==='manufacture_date')!;
    const material=fields.find(field=>field.providerName==='material')!;
    const occasion={...fields.find(field=>field.providerName==='occasion')!,maxValues:2};

    expect(validateAttributeValue(numeric,{kind:'number',value:'Infinity'})).toContain('finite');
    expect(validateAttributeValue(date,{kind:'date',value:'20/09/2026'})).toContain('valid date');
    expect(validateAttributeValue(material,{kind:'single',value:{id:null,label:'Bronze',custom:false}})).toContain('provided options');
    expect(validateAttributeValue(occasion,{kind:'multi',values:[
      {id:null,label:'Wedding',custom:false},
      {id:null,label:'Wedding',custom:false},
    ]})).toContain('duplicate');
    expect(validateAttributeValue(occasion,{kind:'multi',values:[
      {id:null,label:'Wedding',custom:false},
      {id:null,label:'Gift',custom:false},
      {id:null,label:'Other',custom:true},
    ]})).toContain('at most 2');
  });
});

describe('serializeLazadaAttributes',()=>{
  it('serializes provider strings at the boundary and keeps image IDs internal',()=>{
    const fields=normalizeLazadaAttributes(schema,source,new Map(),[]);
    const values=new Map<string,MarketplaceAttributeValue>([
      ['lazada:product:manufacture_date',{kind:'date',value:'2026-09-20'}],
      ['lazada:product:occasion',{kind:'multi',values:[
        {id:null,label:'Wedding',custom:false},
        {id:null,label:'Gift',custom:false},
      ]}],
      ['lazada:variant:TEKAT 1G:main_diamond_carat',{kind:'number',value:'1'}],
      ['lazada:variant:TEKAT 5G:main_diamond_carat',{kind:'number',value:'5'}],
      ['lazada:variant:TEKAT 1G:color_thumbnail',{kind:'image',imageId:91,url:'/storage/tekat.jpg'}],
      ['lazada:size-chart:chart_asset',{kind:'image',imageId:91,url:'/storage/tekat.jpg'}],
    ]);

    expect(serializeLazadaAttributes(fields,values)).toEqual({
      normal:{manufacture_date:'2026-09-20',occasion:'Wedding,Gift'},
      skus:{
        'TEKAT 1G':{main_diamond_carat:'1'},
        'TEKAT 5G':{main_diamond_carat:'5'},
      },
      imageBindings:{'lazada:variant:TEKAT 1G:color_thumbnail':91,'lazada:size-chart:chart_asset':91},
    });
  });
});
