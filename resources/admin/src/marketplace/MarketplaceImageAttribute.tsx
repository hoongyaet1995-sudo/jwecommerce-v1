import React,{useMemo,useRef,useState} from 'react';
import {Check,Eye,Image as ImageIcon,LoaderCircle,Upload,X} from 'lucide-react';
import {uploadMarketplaceAttributeImage,type MarketplaceAttributeImageReceipt} from '../integration/marketplaceAttributeImages';
import type {MarketplaceAttributeValue} from './publishingAttributes/types';

export interface MarketplaceImageCandidate {id:number;path:string;alt?:string}
export interface MarketplaceImageAttributeProps {
 productId:number;
 label:string;
 required:boolean;
 images:MarketplaceImageCandidate[];
 value:MarketplaceAttributeValue;
 onChange:(value:MarketplaceAttributeValue)=>void;
 purpose?:'size-chart';
 platform?:'shopee'|'lazada';
 upload?:(productId:number,file:File)=>Promise<MarketplaceAttributeImageReceipt>;
}

export function MarketplaceImageAttribute({productId,label,required,images,value,onChange,purpose,platform,upload=uploadMarketplaceAttributeImage}:MarketplaceImageAttributeProps){
 const fileInput=useRef<HTMLInputElement>(null);
 const [added,setAdded]=useState<MarketplaceImageCandidate[]>([]);
 const [uploading,setUploading]=useState(false);
 const [error,setError]=useState<string>();
 const [preview,setPreview]=useState<MarketplaceImageCandidate>();
 const candidates=useMemo(()=>{
  const byId=new Map<number,MarketplaceImageCandidate>();
  [...images,...added].forEach(image=>byId.set(image.id,image));
  return [...byId.values()];
 },[images,added]);
 const selectedId=value.kind==='image'?value.imageId:null;
 const copy=purpose==='size-chart'
  ? required?'Required by this marketplace category.':'Choose a saved JW product image when this listing needs a size guide.'
  : required?'Required by this marketplace category.':'Choose an existing JW product image or upload a new one.';
 const chooseFile=async(file?:File)=>{
  if(!file)return;
  setUploading(true);setError(undefined);
  try{
   const receipt=await upload(productId,file);
   setAdded(current=>[...current,{id:receipt.imageId,path:receipt.url,alt:receipt.alt}]);
   onChange({kind:'image',imageId:receipt.imageId,url:receipt.url});
  }catch(reason){setError(reason instanceof Error?reason.message:'Unable to upload image.');}
  finally{setUploading(false);if(fileInput.current)fileInput.current.value='';}
 };
 return <div className="space-y-4">
  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80">
   <div><div className="flex items-center gap-2"><span className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5"><ImageIcon className="w-4 h-4 text-indigo-600 dark:text-indigo-400"/>{label}</span><span className={`text-[11px] font-bold px-2 py-0.5 rounded-full border ${selectedId?'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800':'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'}`}>{selectedId?'Selected':required?'Required':'Optional'}</span></div><p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">{copy}</p></div>
   <button type="button" disabled={uploading} onClick={()=>fileInput.current?.click()} className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold disabled:opacity-60 shrink-0">{uploading?<LoaderCircle className="w-3.5 h-3.5 animate-spin"/>:<Upload className="w-3.5 h-3.5"/>}{uploading?'Uploading…':selectedId?'Replace / upload':'Upload image'}</button>
   <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={event=>void chooseFile(event.target.files?.[0])}/>
  </div>
  {platform&&<p className="text-[10px] text-neutral-400">The selected managed image will be prepared securely for {platform==='shopee'?'Shopee':'Lazada'} when the listing is submitted.</p>}
  {candidates.length>0&&<div className="grid grid-cols-2 md:grid-cols-4 gap-3.5">
   {!required&&<button type="button" aria-label={`Remove ${label}`} onClick={()=>onChange({kind:'empty'})} className={`p-3.5 rounded-xl border transition-all min-h-28 ${selectedId===null?'border-indigo-600/70 dark:border-indigo-500/70 bg-indigo-50/20 dark:bg-indigo-950/15':'border-neutral-200 dark:border-neutral-700/80 bg-white dark:bg-neutral-900/70'}`}><span className="text-xs font-bold text-neutral-900 dark:text-white">No image</span></button>}
   {candidates.map(image=>{const selected=image.id===selectedId;return <div key={image.id} className={`relative p-2 rounded-xl border transition-all ${selected?'border-indigo-600 dark:border-indigo-500 bg-white dark:bg-neutral-800 ring-2 ring-indigo-500/30 shadow-xs':'border-neutral-200 dark:border-neutral-700 bg-neutral-50/60 dark:bg-neutral-800/40 hover:border-neutral-300 dark:hover:border-neutral-600'}`}>
    <button data-marketplace-image={image.id} data-size-chart-image={purpose==='size-chart'?image.id:undefined} type="button" onClick={()=>onChange({kind:'image',imageId:image.id,url:image.path})} className="relative aspect-square w-full rounded-lg bg-neutral-100 dark:bg-neutral-900/80 p-1 flex items-center justify-center overflow-hidden border border-neutral-200/60 dark:border-neutral-700/60"><img src={image.path} alt={image.alt??`${label} candidate`} className="w-full h-full object-contain"/>{selected&&<span className="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-xs"><Check className="w-3 h-3"/></span>}</button>
    <button type="button" aria-label={`Preview ${label} image ${image.id}`} onClick={()=>setPreview(image)} className="absolute bottom-7 right-3 p-1 rounded-md bg-black/60 hover:bg-black/80 text-white"><Eye className="w-3 h-3"/></button><span className="mt-1.5 block text-[10px] font-mono text-neutral-500 dark:text-neutral-400 truncate">JW image #{image.id}</span>
   </div>;})}
  </div>}
  {error&&<p role="alert" className="text-[10px] text-rose-500">{error}</p>}
  {purpose==='size-chart'&&selectedId&&<p className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-800 dark:text-emerald-300">Size chart image selected for this store listing.</p>}
  {preview&&<div className="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs" onClick={()=>setPreview(undefined)}><div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl p-4 max-w-md w-full space-y-3 shadow-2xl" onClick={event=>event.stopPropagation()}><div className="flex items-center justify-between"><span className="text-xs font-bold text-neutral-900 dark:text-white">{label} Preview</span><button type="button" onClick={()=>setPreview(undefined)} className="p-1 rounded-lg text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"><X className="w-4 h-4"/></button></div><img src={preview.path} alt={`${label} preview`} className="w-full max-h-[65vh] object-contain rounded-xl bg-neutral-100 dark:bg-neutral-950"/></div></div>}
 </div>;
}
