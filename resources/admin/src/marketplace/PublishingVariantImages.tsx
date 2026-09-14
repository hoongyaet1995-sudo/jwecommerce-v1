import React from 'react';
import type {PublishingSource} from '../integration/marketplacePublishing';
export function variantImageGroups(source?:PublishingSource) {
  const tier=source?.options?.[0];
  if(!tier || !source || source.variants.length<2)return [];
  return tier.values.map(value=>{
    const images=new Map<string,{id:number;path:string;sku:string}>();
    for(const v of source.variants){
      const options=v.options as Record<string,string>|undefined;
      const path=v.option_image||v.image_url;
      if(options?.[tier.name]===value && path && !images.has(path))images.set(path,{id:v.id,path,sku:v.sku});
    }
    return {name:tier.name,value,images:[...images.values()]};
  });
}
export function resolveVariantImages(groups:ReturnType<typeof variantImageGroups>,choices:Record<string,number>) {
  const resolved:Record<string,number>={};
  const missing:string[]=[];
  for(const group of groups){
    if(group.images.length===1)resolved[group.value]=group.images[0].id;
    else if(group.images.length>1){
      if(group.images.some(image=>image.id===choices[group.value]))resolved[group.value]=choices[group.value];
      else missing.push(group.value);
    }
  }
  return {resolved,missing};
}
export function PublishingVariantImages({groups,choices,onChange}:{groups:ReturnType<typeof variantImageGroups>;choices:Record<string,number>;onChange:(value:string,id:number)=>void}) {
  const {resolved,missing}=resolveVariantImages(groups,choices);
  if(!groups.some(group=>group.images.length))return null;
  return <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-3">
    <div className="text-xs font-bold text-neutral-900 dark:text-white">Shopee variation images</div>
    <p className="text-xs text-neutral-500">Choose one image per {groups[0].name}. Used for this store listing only; JW product images are unchanged.</p>
    {groups.filter(group=>group.images.length>0).map(group=><div key={group.value} className="space-y-2">
      <div className="text-xs font-bold">{group.name}: {group.value} {group.images.length===1 ? '(Automatically selected)' : '(Choose one)'}</div>
      <div className="flex flex-wrap gap-2">{group.images.map(image=><button type="button" key={image.id} aria-label={`${group.value}: ${image.sku}`} aria-pressed={resolved[group.value]===image.id} onClick={()=>onChange(group.value,image.id)} className={`p-2 rounded-xl border text-xs ${resolved[group.value]===image.id?'border-indigo-600 bg-indigo-500/10':'border-neutral-300 dark:border-neutral-700'}`}>
        <img src={image.path} alt={image.sku} className="w-20 h-20 object-contain rounded-lg" />
        <span>{resolved[group.value]===image.id?'✓ ':''}{image.sku}</span>
      </button>)}</div>
    </div>)}
    {missing.length>0 && <p role="alert" className="text-xs text-amber-600">Select an image for: {missing.join(', ')} before publishing.</p>}
  </div>;
}
