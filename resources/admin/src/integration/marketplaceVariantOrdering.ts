type ProductOptionDefinition={name?:unknown;values?:unknown};
type VariantWithOptions={sku:string;options?:unknown};

function definitions(options:unknown):{name:string;values:string[]}[]{
 if(!Array.isArray(options))return [];
 const names=new Set<string>();
 return options.flatMap(option=>{
  if(!option||typeof option!=='object')return [];
  const {name,values}=option as ProductOptionDefinition;
  if(typeof name!=='string'||!name.trim()||names.has(name))return [];
  names.add(name);
  return [{name,values:Array.isArray(values)?values.filter((value):value is string=>typeof value==='string'):[]}];
 });
}

function variantValue(variant:VariantWithOptions,name:string):string{
 const options=variant.options;
 if(!options||typeof options!=='object'||Array.isArray(options))return '';
 const value=(options as Record<string,unknown>)[name];
 return typeof value==='string'?value:'';
}

/** Orders variants by the product's declared option dimensions and values without mutating their source array. */
export function orderedPublishingVariants<T extends VariantWithOptions>(options:unknown,variants:T[]):T[]{
 const dimensions=definitions(options);
 if(!dimensions.length)return [...variants].sort((left,right)=>left.sku.localeCompare(right.sku));
 return [...variants].sort((left,right)=>{
  for(const dimension of dimensions){
   const leftValue=variantValue(left,dimension.name);const rightValue=variantValue(right,dimension.name);
   const leftIndex=dimension.values.indexOf(leftValue);const rightIndex=dimension.values.indexOf(rightValue);
   const leftRank=leftIndex===-1?dimension.values.length:leftIndex;
   const rightRank=rightIndex===-1?dimension.values.length:rightIndex;
   if(leftRank!==rightRank)return leftRank-rightRank;
   if(leftIndex===-1&&rightIndex===-1&&leftValue!==rightValue)return leftValue.localeCompare(rightValue);
  }
  return left.sku.localeCompare(right.sku);
 });
}
