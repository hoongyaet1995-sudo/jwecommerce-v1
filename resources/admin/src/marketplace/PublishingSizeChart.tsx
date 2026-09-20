import React from 'react';
import type {PublishingSource} from '../integration/marketplacePublishing';
import {MarketplaceImageAttribute} from './MarketplaceImageAttribute';
import type {MarketplaceAttributeValue} from './publishingAttributes/types';
import type {MarketplaceAttributeImageReceipt} from '../integration/marketplaceAttributeImages';

export function PublishingSizeChart({platform,required,source,selectedImageId,onChange,onUploaded}:{
 platform:'shopee'|'lazada';required:boolean;source?:PublishingSource;selectedImageId:number|null;onChange:(imageId:number|null)=>void;onUploaded?:(receipt:MarketplaceAttributeImageReceipt)=>void;
}){
 const selected=source?.images.find(image=>image.id===selectedImageId);
 const value:MarketplaceAttributeValue=selectedImageId&&selected
  ? {kind:'image',imageId:selectedImageId,url:selected.path}
  : {kind:'empty'};
 return <div id="marketplace-size-chart-section" className="sm:col-span-2">
  <MarketplaceImageAttribute
   productId={source?.product_id??0}
   label="Size Chart"
   required={required}
   images={source?.images??[]}
   value={value}
   purpose="size-chart"
   platform={platform}
   onChange={next=>onChange(next.kind==='image'?next.imageId:null)}
   onUploaded={onUploaded}
  />
 </div>;
}
