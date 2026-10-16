import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { CmsPage } from '../../types';
import { createPageTemplateStyle, createPageTemplateSections, type PageTemplateContext } from '../../cms/pageTemplates';
import { createAdminPageRuntimePayload, PageRuntimeProvider } from '../../cms/PageRuntimeContext';
import { SectionRenderer } from './SectionRenderer';

export function PageTemplatePreview({templateId, context}: {templateId:string;context:PageTemplateContext}) {
  const [mobile,setMobile] = useState(false);
  const [body,setBody] = useState<HTMLElement|null>(null);
  const container = useRef<HTMLDivElement>(null);
  const [availableWidth,setAvailableWidth] = useState(800);
  useEffect(()=>{
    const target=container.current;
    if(!target)return;
    const observer=new ResizeObserver(entries=>setAvailableWidth(entries[0].contentRect.width));
    observer.observe(target);
    return ()=>observer.disconnect();
  },[]);
  const frameWidth=mobile?390:1200;
  const scale=Math.min(1,availableWidth/frameWidth);
  const page = useMemo<CmsPage>(() => ({id:'template-preview',title:'Template preview',slug:'template-preview',
    description:'',status:'draft',template:'standard',createdAt:'',updatedAt:'',
    style:createPageTemplateStyle(templateId),
    ...(templateId==='wedding-photography'?{containerWidth:'site' as const}:{}),
    ...(templateId==='everyday-market'?{containerWidth:'wide' as const}:{}),
        sections:createPageTemplateSections(templateId,context,'template-preview'),
  }),[templateId,context]);
  const payload = useMemo(() => createAdminPageRuntimePayload(page,{products:context.products??[],categories:context.categories??[],collections:context.collections??[]},context.currency,context.shippingRates,context.promotionOffers),[page,context]);
  return <div className="min-w-0">
    <div className="flex items-center justify-between gap-3 mb-3"><span className="text-xs text-neutral-500">Actual page sections · draft preview</span><div className="flex gap-2">
      {['Desktop','Mobile'].map(label=><button key={label} type="button" aria-pressed={mobile===(label==='Mobile')} onClick={()=>setMobile(label==='Mobile')} className={`px-3 py-1.5 text-xs rounded-lg border ${mobile===(label==='Mobile')?'border-indigo-500 text-indigo-500':'border-neutral-200 dark:border-neutral-700'}`}>{label}</button>)}
    </div></div>
    <div ref={container} className="bg-neutral-100 dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 overflow-hidden flex justify-center">
      <div style={{width:frameWidth*scale,height:420,position:'relative',flexShrink:0}}>
      <iframe title="Page template preview" className="bg-white border-0" style={{width:frameWidth,height:420/scale,transform:`scale(${scale})`,transformOrigin:'top left'}}
        sandbox="allow-same-origin" srcDoc="<!doctype html><html><head></head><body style='margin:0'></body></html>"
        onLoad={event=>{
          const doc=event.currentTarget.contentDocument;
          if(!doc)return;
          document.querySelectorAll('link[rel="stylesheet"],style').forEach(style=>doc.head.appendChild(style.cloneNode(true)));
          setBody(doc.body);
        }}/></div>
    </div>
    {body&&createPortal(<PageRuntimeProvider payload={payload}><main onClickCapture={event=>{
      if((event.target as HTMLElement).closest('a'))event.preventDefault();
    }}>{page.sections.length?page.sections.map(section=><SectionRenderer key={section.id} section={section} viewport={mobile?'mobile':'desktop'}/>):<p className="p-10 text-center text-neutral-500">An empty canvas, ready for your sections.</p>}</main></PageRuntimeProvider>,body)}
  </div>;
}
