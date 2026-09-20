import type {MarketplaceAttributeField, MarketplaceAttributeValue} from './types';

export interface ShopeeAttributeSelectionValue {
  value_id:number;
  original_value_name:string;
  value_unit:string;
}

export interface ShopeeAttributeDefinition {
  attribute_id:number;
  mandatory:boolean;
  attribute_info:{
    name?:string;
    display_name?:string;
    input_type:number;
    input_validation_type?:number;
    date_format_type?:number;
    max_value_count?:number;
    attribute_unit_list?:string[];
    format_type?:number;
    support_search_value?:boolean;
  };
  attribute_value_list:{
    value_id:number;
    name:string;
    value_unit?:string;
    child_attribute_list?:ShopeeAttributeDefinition[];
  }[];
}

export interface ShopeeSerializedAttribute {
  attribute_id:number;
  attribute_value_list:ShopeeAttributeSelectionValue[];
}

const keyFor=(id:number)=>`shopee:product:${id}`;

const fieldControl=(definition:ShopeeAttributeDefinition):MarketplaceAttributeField['control']|null=>{
  const input=definition.attribute_info.input_type;
  if(input===1||input===2)return 'single';
  if(input===4||input===5)return 'multi';
  if(input!==3)return null;
  switch(definition.attribute_info.input_validation_type??2){
    case 0:case 2:return 'text';
    case 1:return 'integer';
    case 3:return 'decimal';
    case 4:return 'date';
    default:return null;
  }
};

const activeDefinitions=(
  tree:ShopeeAttributeDefinition[],
  selections:Record<number,ShopeeAttributeSelectionValue[]>,
):{definition:ShopeeAttributeDefinition;dependent:boolean}[]=>{
  const result=new Map<number,{definition:ShopeeAttributeDefinition;dependent:boolean}>();
  const visit=(nodes:ShopeeAttributeDefinition[],depth:number,dependent:boolean)=>{
    if(depth>20)throw new Error('Category requirements could not be read.');
    for(const definition of nodes){
      if(!definition||!Number.isSafeInteger(definition.attribute_id)||definition.attribute_id<=0
        ||typeof definition.mandatory!=='boolean'||!definition.attribute_info
        ||!Array.isArray(definition.attribute_value_list))throw new Error('Category requirements could not be read.');
      result.set(definition.attribute_id,{definition,dependent});
      const selected=new Set((selections[definition.attribute_id]??[]).map(value=>value.value_id));
      for(const option of definition.attribute_value_list){
        if(selected.has(option.value_id))visit(option.child_attribute_list??[],depth+1,true);
      }
    }
  };
  visit(tree,0,false);
  return [...result.values()];
};

export function normalizeShopeeAttributes(
  tree:ShopeeAttributeDefinition[],
  selections:Record<number,ShopeeAttributeSelectionValue[]>,
):MarketplaceAttributeField[] {
  return activeDefinitions(tree,selections).map(({definition,dependent})=>{
    const info=definition.attribute_info;
    const control=fieldControl(definition);
    const input=info.input_type;
    const multi=input===4||input===5;
    const max=multi&&Number.isSafeInteger(info.max_value_count)&&Number(info.max_value_count)>0
      ?Number(info.max_value_count):1;
    const metadataSupported=Boolean(control)
      && (!multi||(Number.isSafeInteger(info.max_value_count)&&Number(info.max_value_count)>0))
      && (info.format_type!==2||(Array.isArray(info.attribute_unit_list)&&info.attribute_unit_list.length>0))
      && (info.input_validation_type!==4||[0,1].includes(Number(info.date_format_type)));
    return {
      key:keyFor(definition.attribute_id),provider:'shopee',scope:'product',
      providerName:info.name??String(definition.attribute_id),providerId:String(definition.attribute_id),
      label:info.display_name??info.name??`Attribute ${definition.attribute_id}`,
      control:control??'text',required:definition.mandatory,
      options:definition.attribute_value_list.map(option=>({id:String(option.value_id),label:option.name})),
      allowCustom:[2,3,5].includes(input),maxValues:max,
      units:Array.isArray(info.attribute_unit_list)?info.attribute_unit_list.filter(unit=>typeof unit==='string'&&unit.trim()):[],
      supported:metadataSupported,
      unsupportedReason:metadataSupported?undefined:`Shopee input type ${input} cannot be completed yet.`,
      inputType:input,validationType:info.input_validation_type??(input===3?2:undefined),
      dateFormatType:info.date_format_type,formatType:info.format_type,
      supportSearch:Boolean(info.support_search_value),dependent,
    };
  });
}

const isoDateValid=(value:string,monthOnly:boolean):boolean=>{
  if(monthOnly)return /^\d{4}-(0[1-9]|1[0-2])$/.test(value);
  if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;
  const parsed=new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(parsed.valueOf())&&parsed.toISOString().slice(0,10)===value;
};

const choiceAllowed=(field:MarketplaceAttributeField,item:{id:string|null;label:string;custom:boolean}):boolean=>{
  if(!item.label.trim())return false;
  if(item.custom)return field.allowCustom;
  return item.id!==null&&field.options.some(option=>option.id===item.id&&option.label===item.label);
};

export function validateShopeeAttributeValue(
  field:MarketplaceAttributeField,
  value:MarketplaceAttributeValue,
  unit:string,
):string|null {
  if(!field.supported)return field.required?`${field.label} cannot be completed yet.`:null;
  if(value.kind==='empty')return field.required?`${field.label} is required.`:null;
  if(field.formatType===2&&!field.units.includes(unit))return `${field.label} requires an allowed unit.`;
  if(field.formatType!==2&&unit)return `${field.label} does not accept a unit.`;
  if(field.control==='integer'||field.control==='decimal'){
    if(value.kind!=='number'||!value.value.trim()||!Number.isFinite(Number(value.value)))return `${field.label} requires a finite number.`;
    if(field.control==='integer'&&!/^[+-]?\d+$/.test(value.value))return `${field.label} requires an integer.`;
    return null;
  }
  if(field.control==='date')return value.kind==='date'&&isoDateValid(value.value,field.dateFormatType===1)
    ?null:`${field.label} requires a valid date.`;
  if(field.control==='text'||field.control==='textarea')return value.kind==='text'&&value.value.trim()?null:`${field.label} requires text.`;
  if(field.control==='single')return value.kind==='single'&&choiceAllowed(field,value.value)?null:`${field.label} requires an allowed value.`;
  if(field.control==='multi'){
    if(value.kind!=='multi'||!value.values.length)return field.required?`${field.label} requires at least one value.`:null;
    if(value.values.length>field.maxValues)return `${field.label} accepts at most ${field.maxValues} values.`;
    const identities=value.values.map(item=>`${item.custom?'custom':item.id}:${item.label.trim().toLocaleLowerCase()}`);
    if(new Set(identities).size!==identities.length)return `${field.label} contains a duplicate value.`;
    return value.values.every(item=>choiceAllowed(field,item))?null:`${field.label} contains an unverified value.`;
  }
  return `${field.label} cannot be completed yet.`;
}

const formatDate=(value:string,dateFormatType:number|undefined):string=>{
  if(dateFormatType===1){const [year,month]=value.split('-');return `${month}/${year}`;}
  const [year,month,day]=value.split('-');return `${day}/${month}/${year}`;
};

export function serializeShopeeAttributes(
  fields:MarketplaceAttributeField[],
  values:ReadonlyMap<string,MarketplaceAttributeValue>,
  units:ReadonlyMap<string,string>,
):ShopeeSerializedAttribute[] {
  return fields.flatMap(field=>{
    const value=values.get(field.key);
    if(!value||value.kind==='empty')return [];
    const unit=units.get(field.key)??'';
    if(validateShopeeAttributeValue(field,value,unit)!==null)return [];
    let selections:ShopeeAttributeSelectionValue[]=[];
    if(value.kind==='single'||value.kind==='multi'){
      const items=value.kind==='single'?[value.value]:value.values;
      selections=items.map(item=>({
        value_id:item.custom?0:Number(item.id),original_value_name:item.label.trim(),value_unit:item.custom?unit:'',
      }));
    }else if(value.kind==='text'||value.kind==='number'||value.kind==='date'){
      const original=value.kind==='date'?formatDate(value.value,field.dateFormatType):value.value.trim();
      selections=[{value_id:0,original_value_name:original,value_unit:unit}];
    }
    return selections.length?[{attribute_id:Number(field.providerId),attribute_value_list:selections}]:[];
  });
}

