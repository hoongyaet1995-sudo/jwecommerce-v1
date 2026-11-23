import React from 'react';
import type {PageSection} from '../../types';
import {rebaseTitleRanges,setTitleRange} from '../../cms/titleEmphasisRanges';
const toolbarButtonClass='rounded-lg border border-neutral-200 px-2.5 py-1.5 text-xs hover:bg-neutral-100 disabled:opacity-40 dark:border-neutral-700 dark:hover:bg-neutral-800';
const inputClass='w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900';

export function SectionTitleEditor({section,onChange}:{section:PageSection;onChange:(value:PageSection)=>void}) {
 const titleInput=React.useRef<HTMLTextAreaElement>(null);const [selection,setSelection]=React.useState({start:0,end:0});
 const patch=(change:Partial<PageSection>)=>onChange({...section,...change});
 return <div className="space-y-2"><label className="block space-y-1 text-xs font-medium"><span>Title</span><textarea ref={titleInput} data-inspector-field="title" aria-label="Title" className={inputClass} value={section.title} rows={3} onSelect={event=>setSelection({start:event.currentTarget.selectionStart,end:event.currentTarget.selectionEnd})} onChange={event=>patch({title:event.target.value,...(section.titleEmphasis?{titleEmphasis:rebaseTitleRanges(section.title,event.target.value,section.titleEmphasis)}:{})})}/></label><div className="flex gap-2 text-xs"><button type="button" className={toolbarButtonClass} disabled={selection.end<=selection.start} onClick={()=>patch({titleEmphasis:setTitleRange(section.titleEmphasis??[],selection.start,selection.end,true,section.title.length)})}>Emphasis</button><button type="button" className={toolbarButtonClass} disabled={selection.end<=selection.start} onClick={()=>patch({titleEmphasis:setTitleRange(section.titleEmphasis??[],selection.start,selection.end,false,section.title.length)})}>Clear</button><button type="button" className={toolbarButtonClass} onClick={()=>patch({titleEmphasis:undefined})}>Restore layout style</button></div></div>;
}
