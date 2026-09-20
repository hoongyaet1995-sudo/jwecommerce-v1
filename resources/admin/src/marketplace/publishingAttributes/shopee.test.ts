import {describe,expect,it} from 'vitest';
import type {MarketplaceAttributeValue} from './types';
import {
  normalizeShopeeAttributes,
  serializeShopeeAttributes,
  validateShopeeAttributeValue,
  type ShopeeAttributeDefinition,
} from './shopee';

const definition=(attribute_info:ShopeeAttributeDefinition['attribute_info'],mandatory=true):ShopeeAttributeDefinition=>({
  attribute_id:Number(attribute_info.input_type)*10+(attribute_info.input_validation_type??0),
  mandatory,
  attribute_info,
  attribute_value_list:[],
});

describe('normalizeShopeeAttributes',()=>{
  it('maps input and validation types to canonical controls',()=>{
    const fields=normalizeShopeeAttributes([
      definition({name:'Single',input_type:1}),
      definition({name:'Combo',input_type:2}),
      definition({name:'Integer',input_type:3,input_validation_type:1}),
      definition({name:'Text',input_type:3,input_validation_type:2}),
      definition({name:'Decimal',input_type:3,input_validation_type:3}),
      definition({name:'Date',input_type:3,input_validation_type:4,date_format_type:0}),
      definition({name:'Multi',input_type:4,max_value_count:2}),
      definition({name:'Multi combo',input_type:5,max_value_count:3}),
    ],{});

    expect(fields.map(field=>[field.label,field.control,field.allowCustom])).toEqual([
      ['Single','single',false],['Combo','single',true],['Integer','integer',true],
      ['Text','text',true],['Decimal','decimal',true],['Date','date',true],
      ['Multi','multi',false],['Multi combo','multi',true],
    ]);
  });

  it('carries units, provider search, limits, and only active dependent fields',()=>{
    const child=definition({name:'Battery Capacity',input_type:3,input_validation_type:1,format_type:2,attribute_unit_list:['mAh']});
    const parent:ShopeeAttributeDefinition={
      attribute_id:1,mandatory:true,attribute_info:{name:'Connection',input_type:1,support_search_value:true},
      attribute_value_list:[
        {value_id:11,name:'Wireless',child_attribute_list:[child]},
        {value_id:12,name:'Wired'},
      ],
    };
    const fields=normalizeShopeeAttributes([parent],{1:[{value_id:11,original_value_name:'Wireless',value_unit:''}]});
    expect(fields).toEqual(expect.arrayContaining([
      expect.objectContaining({providerId:'1',supportSearch:true}),
      expect.objectContaining({label:'Battery Capacity',control:'integer',units:['mAh'],dependent:true}),
    ]));
    expect(normalizeShopeeAttributes([parent],{1:[{value_id:12,original_value_name:'Wired',value_unit:''}]})).toHaveLength(1);
  });

  it('marks malformed mandatory metadata unsupported',()=>{
    const [field]=normalizeShopeeAttributes([definition({name:'Mystery',input_type:9})],{});
    expect(field).toMatchObject({required:true,supported:false});
  });
});

describe('Shopee typed validation and serialization',()=>{
  it('requires allowed units and rejects duplicate or excessive multi values',()=>{
    const [unitField]=normalizeShopeeAttributes([
      definition({name:'Battery Capacity',input_type:3,input_validation_type:1,format_type:2,attribute_unit_list:['mAh']})
    ],{});
    expect(validateShopeeAttributeValue(unitField,{kind:'number',value:'800'},'')).toContain('unit');
    expect(validateShopeeAttributeValue(unitField,{kind:'number',value:'800'},'mAh')).toBeNull();

    const multi:ShopeeAttributeDefinition={attribute_id:5,mandatory:true,attribute_info:{name:'Compatibility',input_type:4,max_value_count:2},attribute_value_list:[
      {value_id:50,name:'Mobile'},{value_id:51,name:'Laptop'},
    ]};
    const [multiField]=normalizeShopeeAttributes([multi],{});
    expect(validateShopeeAttributeValue(multiField,{kind:'multi',values:[
      {id:'50',label:'Mobile',custom:false},{id:'50',label:'Mobile',custom:false},
    ]},'')).toContain('duplicate');
  });

  it('preserves option IDs, custom values, units, and provider date formats',()=>{
    const multi:ShopeeAttributeDefinition={attribute_id:5,mandatory:true,attribute_info:{name:'Compatibility',input_type:5,max_value_count:2},attribute_value_list:[
      {value_id:50,name:'Mobile'},
    ]};
    const date=definition({name:'Release Date',input_type:3,input_validation_type:4,date_format_type:0});
    const fields=normalizeShopeeAttributes([multi,date],{});
    const values=new Map<string,MarketplaceAttributeValue>([
      ['shopee:product:5',{kind:'multi',values:[
        {id:'50',label:'Mobile',custom:false},{id:null,label:'Console',custom:true},
      ]}],
      [`shopee:product:${date.attribute_id}`,{kind:'date',value:'2026-09-20'}],
    ]);
    const serialized=serializeShopeeAttributes(fields,values,new Map());
    expect(serialized.find(row=>row.attribute_id===5)?.attribute_value_list).toEqual([
      {value_id:50,original_value_name:'Mobile',value_unit:''},
      {value_id:0,original_value_name:'Console',value_unit:''},
    ]);
    expect(serialized.find(row=>row.attribute_id===date.attribute_id)?.attribute_value_list[0]).toEqual({
      value_id:0,original_value_name:'20/09/2026',value_unit:'',
    });
  });
});
