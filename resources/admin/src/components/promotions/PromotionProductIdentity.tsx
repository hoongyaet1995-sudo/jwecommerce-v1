import React from 'react';
import {Package} from 'lucide-react';
export function PromotionProductIdentity({name,image}:{name:string;image?:string|null}){
 return <span className="pw-product-identity">{image?<img src={image} alt="" loading="lazy" onError={e=>{e.currentTarget.style.display='none';}}/>:<span className="pw-product-placeholder"><Package size={18}/></span>}<strong>{name}</strong></span>;
}
