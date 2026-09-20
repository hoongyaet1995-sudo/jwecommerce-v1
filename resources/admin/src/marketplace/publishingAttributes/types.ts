export type MarketplaceAttributeValue =
  | {kind:'empty'}
  | {kind:'text';value:string}
  | {kind:'number';value:string}
  | {kind:'date';value:string}
  | {kind:'single';value:{id:string|null;label:string;custom:boolean}}
  | {kind:'multi';values:{id:string|null;label:string;custom:boolean}[]}
  | {kind:'image';imageId:number;url:string};

export interface MarketplaceAttributeOption {
  id:string;
  label:string;
}

export interface MarketplaceAttributeField {
  key:string;
  provider:'lazada'|'shopee';
  scope:'product'|'variant';
  providerName:string;
  providerId:string;
  sku?:string;
  label:string;
  control:'text'|'textarea'|'integer'|'decimal'|'date'|'single'|'multi'|'image';
  required:boolean;
  options:MarketplaceAttributeOption[];
  allowCustom:boolean;
  maxValues:number;
  units:string[];
  help?:string;
  supported:boolean;
  unsupportedReason?:string;
  purpose?:'size-chart';
}

export const emptyMarketplaceAttributeValue=():MarketplaceAttributeValue=>({kind:'empty'});

