import React from 'react';
import type {LazadaPublishingField} from '../integration/lazadaPublishingFields';

const control='w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500';
export function LazadaPublishingAttributes({fields,onChange}:{fields:LazadaPublishingField[];onChange:(key:string,value:string)=>void}){
 const visible=fields.filter(field=>!field.automatic||!field.complete);
 const render=(field:LazadaPublishingField)=>{
  const id=`lazada-field-${encodeURIComponent(field.key)}`;
  return <div key={field.key} className="space-y-1.5">
   <label htmlFor={id} className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">
    <span>{field.label}{field.sku?` · ${field.sku}`:''}</span>{field.required&&<span className="text-rose-500">*</span>}
   </label>
   {['singleselect','multiselect'].includes(field.type)?<select id={id} className={control} value={field.type==='multiselect'?field.value.split(',').filter(Boolean):field.value}
    multiple={field.type==='multiselect'} onChange={(event:React.ChangeEvent<HTMLSelectElement>)=>onChange(field.key,field.type==='multiselect'?Array.from(event.target.selectedOptions,(option:HTMLOptionElement)=>option.value).join(','):event.target.value)}>
     {field.type==='singleselect'&&<option value="">Select {field.label}</option>}
     {field.options.map(option=><option key={option} value={option}>{option}</option>)}
    </select>:field.type==='richtext'?<textarea id={id} className={control} value={field.value} onChange={event=>onChange(field.key,event.target.value)}/>
    :<input id={id} className={control} type={field.type==='numeric'?'number':'text'} step={field.type==='numeric'?'any':undefined}
     value={field.value} disabled={!field.supported} list={field.options.length?`${id}-options`:undefined}
     onChange={event=>onChange(field.key,event.target.value)}/>}
   {!field.supported&&<p className="text-xs text-rose-500">This required field cannot be completed yet.</p>}
   {field.options.length>0&&['enuminput','multienuminput'].includes(field.type)&&<datalist id={`${id}-options`}>{field.options.map(value=><option key={value} value={value}/>)}</datalist>}
  </div>;
 };
 return <>
  {visible.filter(field=>field.required).map(render)}
  {visible.some(field=>!field.required)&&<details className="space-y-1.5 sm:col-span-2">
   <summary className="text-xs font-bold text-neutral-700 dark:text-neutral-300">Optional attributes</summary>
   <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">{visible.filter(field=>!field.required).map(render)}</div>
  </details>}
 </>;
}
