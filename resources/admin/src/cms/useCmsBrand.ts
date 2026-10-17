import {useEffect,useState} from 'react';
import {isPalette,type BrandPalette} from './pageStyle';
export interface CmsBrandContext {theme:string;main:string;sub:string;palette:BrandPalette}
export function useCmsBrand(enabled=true){
 const [brand,setBrand]=useState<CmsBrandContext>();
 useEffect(()=>{if(!enabled)return;let live=true;const load=()=>{void fetch('/admin/api/cms/brand-context',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>{if(!r.ok)throw new Error('Brand unavailable');return r.json()}).then(r=>{if(live&&isPalette(r.data?.palette))setBrand(r.data)}).catch(()=>{});};load();window.addEventListener('focus',load);return()=>{live=false;window.removeEventListener('focus',load);};},[enabled]);
 return brand;
}
