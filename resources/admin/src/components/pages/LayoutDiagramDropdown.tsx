import React, {useRef, useState} from 'react';
import {ImageChoiceDropdown} from './ImageChoiceDropdown';
import {SectionLayoutPreview} from './SectionLayoutPreview';
import {useLayoutSection} from './SectionLayoutGroup';
import thumbnails from '../../cms/inspectorLayoutThumbnails.json';

type LayoutValue = string | boolean;
type Choice<T extends LayoutValue> = {value:T; label:string; diagram:React.ReactNode; disabled?:boolean};
type Preview = {card:number; layout:string; image:string};
const catalog: Readonly<Record<string, Record<string, Preview>>> = thumbnails;

export function LayoutDiagramDropdown<T extends LayoutValue>({label,value,choices,onChange,disabled}:{
  label:string; value:T; choices:Choice<T>[]; onChange:(value:T)=>void; disabled?:boolean;
}) {
  const section = useLayoutSection();
  const previews = section ? catalog[section.type] : undefined;
  const selected = choices.find(choice=>choice.value===value);
  const preview = selected ? previews?.[String(selected.value)] : undefined;
  const dialog = useRef<HTMLDialogElement>(null);
  const [open,setOpen] = useState(false);
  const [mobile,setMobile] = useState(false);

  return <div className="space-y-2">
    <ImageChoiceDropdown fullWidthOverlay label={label} value={String(value)} unmatchedLabel="Existing layout"
      disabled={disabled} disabledOptions={new Set(choices.filter(choice=>choice.disabled).map(choice=>String(choice.value)))}
      options={choices.map(choice=>[String(choice.value),choice.label])}
      onChange={key=>{
        const choice=choices.find(option=>String(option.value)===key);
        if(choice)onChange(choice.value);
      }}
      icon={index=>{
        const current=previews?.[String(choices[index].value)];
        return current ? <img aria-hidden="true" alt="" loading="lazy" width={56} height={38}
          className="h-[38px] w-14 shrink-0 rounded-md border border-neutral-300 object-cover dark:border-neutral-700"
          src={'/images/cms/layout-thumbnails/'+current.image}/> :
          <span aria-hidden="true" className="relative block h-[38px] w-14 shrink-0 overflow-hidden rounded-md bg-neutral-200 dark:bg-neutral-700">
            <span className="absolute inset-0 block h-[76px] w-28 origin-top-left scale-50 grayscale">{choices[index].diagram}</span>
          </span>;
      }}/>
    {preview && selected && <div>
      <button type="button" className="text-xs underline" onClick={()=>{setMobile(false);dialog.current?.showModal();setOpen(true)}}>Preview default layout</button>
      <dialog ref={dialog} onClose={()=>setOpen(false)} aria-label={selected.label+' default layout'}
        className="fixed inset-0 m-auto max-h-[90vh] w-[min(96vw,1280px)] overflow-auto rounded-xl border border-neutral-400 bg-white p-4 text-neutral-900 backdrop:bg-black/60 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white">
        {open && <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="mr-auto text-base font-semibold">{selected.label}</h2>
            <button type="button" className="rounded border px-2 py-1 aria-pressed:bg-neutral-300 dark:aria-pressed:bg-neutral-700" aria-pressed={!mobile} onClick={()=>setMobile(false)}>Desktop</button>
            <button type="button" className="rounded border px-2 py-1 aria-pressed:bg-neutral-300 dark:aria-pressed:bg-neutral-700" aria-pressed={mobile} onClick={()=>setMobile(true)}>Mobile</button>
            <button type="button" className="rounded border px-2 py-1" onClick={()=>dialog.current?.close()}>Close preview</button>
          </div>
          <SectionLayoutPreview card={preview.card} layout={preview.layout} title={selected.label} full mobile={mobile}/>
        </div>}
      </dialog>
    </div>}
  </div>;
}
