import {ShopeeVariationImagesSection} from './publish37/ShopeeVariationImagesSection';
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
 const {resolved}=resolveVariantImages(groups,choices);
 const visible=groups.filter(group=>group.images.length>0);
 if(!visible.length)return null;
 return <ShopeeVariationImagesSection variationGroups={visible.map(group=>({optionName:group.name,optionValue:group.value,candidates:group.images.map(image=>({id:String(image.id),imageUrl:image.path,sku:image.sku,label:image.sku}))}))}
 selectedImages={Object.fromEntries(visible.map(group=>[group.value,group.images.find(image=>image.id===resolved[group.value])?.path??'']))}
 onSelectImage={(value,path)=>{const image=visible.find(group=>group.value===value)?.images.find(image=>image.path===path);if(image)onChange(value,image.id);}}/>;
}
