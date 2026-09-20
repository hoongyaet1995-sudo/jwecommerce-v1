import React,{useMemo,useState} from 'react';
import {Check,ChevronDown,Plus,X} from 'lucide-react';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';
import {MarketplaceImageAttribute,type MarketplaceImageCandidate} from './MarketplaceImageAttribute';
import type {MarketplaceAttributeImageReceipt} from '../integration/marketplaceAttributeImages';

const controlClass='w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white placeholder-neutral-400 focus:ring-1 focus:ring-indigo-500';

export interface MarketplaceAttributeControlProps {
 field:MarketplaceAttributeField;
 value:MarketplaceAttributeValue;
 unit?:string;
 error?:string;
 onChange:(value:MarketplaceAttributeValue)=>void;
 onUnitChange?:(unit:string)=>void;
 supplemental?:React.ReactNode;
 imageContext?:{productId:number;images:MarketplaceImageCandidate[];platform:'shopee'|'lazada';onUploaded?:(receipt:MarketplaceAttributeImageReceipt)=>void};
}

const selectedItems=(value:MarketplaceAttributeValue)=>value.kind==='multi'?value.values:[];

export function MarketplaceAttributeControl({field,value,unit='',error,onChange,onUnitChange,supplemental,imageContext}:MarketplaceAttributeControlProps){
 const [open,setOpen]=useState(false);
 const [search,setSearch]=useState('');
 const [customMode,setCustomMode]=useState(false);
 const [customValue,setCustomValue]=useState('');
 const filtered=useMemo(()=>field.options.filter(option=>option.label.toLocaleLowerCase().includes(search.toLocaleLowerCase())),[field.options,search]);
 const invalid=Boolean(error);
 const describedBy=invalid?`${field.key}-error`:undefined;

 if(!field.supported)return <div data-unsupported="true" className="p-3 rounded-xl border border-rose-300 dark:border-rose-900 bg-rose-50/70 dark:bg-rose-950/20">
  <p className="text-xs font-semibold text-rose-700 dark:text-rose-300">{error??field.unsupportedReason??`${field.label} cannot be completed yet.`}</p>
 </div>;

 const scalar=value.kind==='text'||value.kind==='number'||value.kind==='date'?value.value:'';
 const selectOne=(id:string,label:string,custom=false)=>{onChange({kind:'single',value:{id:custom?null:id,label,custom}});setOpen(false);};
 const toggleMulti=(id:string|null,label:string,custom:boolean)=>{
  const current=selectedItems(value);
  const identity=`${custom?'custom':id}:${label.toLocaleLowerCase()}`;
  const exists=current.some(item=>`${item.custom?'custom':item.id}:${item.label.toLocaleLowerCase()}`===identity);
  onChange({kind:'multi',values:exists?current.filter(item=>`${item.custom?'custom':item.id}:${item.label.toLocaleLowerCase()}`!==identity):[...current,{id,label,custom}]});
 };
 const input=(type:'text'|'number'|'date',step?:string)=><input type={type} step={step} aria-label={field.label} aria-describedby={describedBy} aria-invalid={invalid} value={scalar} onChange={event=>onChange(type==='number'?{kind:'number',value:event.target.value}:type==='date'?{kind:'date',value:event.target.value}:{kind:'text',value:event.target.value})} className={controlClass}/>;

 return <div className="space-y-1.5 relative">
  {field.control==='text'&&input('text')}
  {field.control==='textarea'&&<textarea aria-label={field.label} aria-describedby={describedBy} aria-invalid={invalid} value={scalar} onChange={event=>onChange({kind:'text',value:event.target.value})} className={`${controlClass} min-h-20 resize-y`}/>}
  {(field.control==='integer'||field.control==='decimal')&&<div className="flex items-center gap-2">{input('number',field.control==='integer'?'1':'any')}{field.units.length>0&&<select aria-label={`${field.label} unit`} value={unit} onChange={event=>onUnitChange?.(event.target.value)} className="px-2.5 py-2 bg-neutral-100 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-800 dark:text-neutral-200 font-semibold focus:ring-1 focus:ring-indigo-500 shrink-0"><option value="">Select unit</option>{field.units.map(item=><option key={item} value={item}>{item}</option>)}</select>}</div>}
  {field.control==='date'&&input(field.dateFormatType===1?'text':'date')}
  {field.control==='single'&&field.options.length===2&&!field.allowCustom&&!field.supportSearch&&<div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">{field.options.map(option=>{const checked=value.kind==='single'&&value.value.id===option.id;return <label key={option.id} className={`p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-2.5 ${checked?'border-indigo-600 bg-white dark:bg-neutral-800 shadow-xs':'border-neutral-200 dark:border-neutral-700 bg-white/50 dark:bg-neutral-900/40 opacity-70'}`}><input type="radio" name={field.key} checked={checked} onChange={()=>selectOne(option.id,option.label)} className="mt-0.5 text-indigo-600"/><span className="text-xs font-bold text-neutral-900 dark:text-white">{option.label}</span></label>;})}</div>}
  {field.control==='single'&&(field.options.length!==2||field.allowCustom||field.supportSearch)&&<div className="space-y-1.5 relative">
   {field.allowCustom&&<button type="button" onClick={()=>setCustomMode(mode=>!mode)} className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{customMode?'← Pick standard option':'+ Custom value'}</button>}
   {customMode?<input type="text" aria-label={`Custom ${field.label}`} value={value.kind==='single'&&value.value.custom?value.value.label:''} onChange={event=>selectOne('',event.target.value,true)} className={controlClass}/>:<>
    <button type="button" aria-label={`Select ${field.label}`} onClick={()=>setOpen(state=>!state)} className={`${controlClass} flex items-center justify-between`}><span>{value.kind==='single'?value.value.label:`Select ${field.label}...`}</span><ChevronDown className="w-3.5 h-3.5 text-neutral-400"/></button>
    {open&&<div className="absolute z-40 top-full mt-1.5 w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-2xl overflow-hidden"><input aria-label={`Filter ${field.label}`} value={search} onChange={event=>setSearch(event.target.value)} placeholder="Filter options..." className={`${controlClass} rounded-none border-0 border-b`}/><div className="max-h-48 overflow-y-auto">{filtered.map(option=><button type="button" key={option.id} onClick={()=>selectOne(option.id,option.label)} className="w-full px-3 py-2 text-left text-xs flex items-center justify-between hover:bg-neutral-50 dark:hover:bg-neutral-800"><span>{option.label}</span>{value.kind==='single'&&value.value.id===option.id&&<Check className="w-3.5 h-3.5 text-indigo-500"/>}</button>)}</div></div>}
   </>}
  </div>}
  {field.control==='multi'&&<div className="space-y-1.5 relative">
   <div role="button" tabIndex={0} aria-label={`Choose ${field.label}`} onClick={()=>setOpen(state=>!state)} onKeyDown={event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();setOpen(state=>!state);}}} className={`${controlClass} min-h-[38px] flex flex-wrap items-center gap-1.5 text-left cursor-pointer`}>{selectedItems(value).length?selectedItems(value).map(item=><span key={`${item.custom?'custom':item.id}:${item.label}`} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-[11px] font-medium">{item.label}<X className="w-2.5 h-2.5"/></span>):<span className="text-neutral-400">Choose {field.label}...</span>}<ChevronDown className="w-3.5 h-3.5 ml-auto text-neutral-400"/></div>
   {open&&<div className="absolute z-40 top-full mt-1.5 w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-2xl overflow-hidden"><input aria-label={`Filter ${field.label}`} value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search or filter options..." className={`${controlClass} rounded-none border-0 border-b`}/><div className="max-h-48 overflow-y-auto p-1">{filtered.map(option=>{const checked=selectedItems(value).some(item=>!item.custom&&item.id===option.id);return <label key={option.id} onClick={event=>{event.preventDefault();toggleMulti(option.id,option.label,false);}} className="w-full px-2.5 py-1.5 rounded-lg flex items-center gap-2.5 text-xs text-left hover:bg-neutral-50 dark:hover:bg-neutral-800 cursor-pointer"><input type="checkbox" checked={checked} readOnly className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"/>{option.label}</label>;})}</div>{field.allowCustom&&<div className="p-2 border-t border-neutral-200 dark:border-neutral-800 flex gap-1.5"><input aria-label={`Custom ${field.label}`} value={customValue} onChange={event=>setCustomValue(event.target.value)} className={controlClass}/><button type="button" disabled={!customValue.trim()||selectedItems(value).length>=field.maxValues} onClick={()=>{toggleMulti(null,customValue.trim(),true);setCustomValue('');}} className="px-2.5 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold disabled:opacity-50"><Plus className="w-3 h-3"/></button></div>}</div>}
  </div>}
  {field.control==='image'&&(imageContext?<MarketplaceImageAttribute productId={imageContext.productId} images={imageContext.images} platform={imageContext.platform} label={field.label} required={field.required} purpose={field.purpose} value={value} onChange={onChange} onUploaded={imageContext.onUploaded}/>:<div data-unsupported="true" className="p-3 rounded-xl border border-rose-300 dark:border-rose-900 bg-rose-50/70 dark:bg-rose-950/20 text-xs text-rose-700 dark:text-rose-300">Product media is not available for this image attribute.</div>)}
  {supplemental}
  {field.help&&<p className="text-[10px] text-neutral-400">{field.help}</p>}
  {error&&<p id={`${field.key}-error`} className="text-[10px] text-rose-500">{error}</p>}
 </div>;
}
