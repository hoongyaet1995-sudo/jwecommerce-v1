import React from 'react';
import type {PageSection} from '../../types';
import type {PageRuntimeProduct,PageRuntimePayload} from '../../cms/PageRuntimeContext';
export function BeautyPurchaseAction({product,section,context,editing}:{product:PageRuntimeProduct;section:PageSection;context:PageRuntimePayload['context'];editing:boolean}) {
 const variants=product.commerce?.variants??[];const variant=variants.length===1?variants[0]:undefined;
 const style={backgroundColor:section.customColors?.primaryBtnBg??'#48283e',color:section.customColors?.primaryBtnText??'#ffffff'};
 const cls='block w-full text-center py-2.5 mt-3 text-xs disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2';
 if(product.purchaseMode && product.purchaseMode !== 'buy') return <a href={`/products/${encodeURIComponent(product.slug)}`} className={cls} style={style}>View details</a>;
 if(!variant)return <a href={`/products/${encodeURIComponent(product.slug)}`} onClick={e=>{if(editing)e.preventDefault()}} className={cls} style={style}>Choose options</a>;
 const disabled=editing||context.mode!=='public'||!product.commerce?.canChoose||!variant.enabled||variant.stock<1;
 return <form action="/cart/items" method="post" onSubmit={e=>{if(disabled)e.preventDefault()}}><input type="hidden" name="_token" value={typeof document==='undefined'?'':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??''}/><input type="hidden" name="variant_id" value={variant.id}/><input type="hidden" name="quantity" value="1"/><button type="submit" disabled={disabled} aria-label={`Add ${product.title} to cart`} className={cls} style={style}>{variant.enabled&&variant.stock>0?'Add to cart':'Unavailable'}</button></form>;
}
