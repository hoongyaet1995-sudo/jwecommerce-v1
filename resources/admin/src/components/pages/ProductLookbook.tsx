import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import type {PageRuntimePayload,PageRuntimeProduct} from '../../cms/PageRuntimeContext';
import {EditorialProductGrid} from './EditorialProductGrid';
import {ScopedImage} from './ScopedImage';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {shopNavigationHref} from '../../cms/shopNavigation';

/** Catalog facts and authored editorial cards have separate, editable owners. */
export function ProductLookbook({section,products,context,editing=false}:{section:PageSection;products:PageRuntimeProduct[];context:PageRuntimePayload['context'];editing?:boolean}){
 return <div data-product-lookbook className="@container grid grid-cols-1 @min-[900px]:grid-cols-2 gap-6 items-stretch">
  <EditorialProductGrid section={section} products={products} context={context} editing={editing}/>
  <div className="grid gap-4 py-8">{(section.items??[]).map(item=>{const href=shopNavigationHref(item.linkUrl??'');return <article key={item.id} data-cms-item={item.id} className="relative min-h-[300px] overflow-hidden bg-neutral-200 flex items-center">
   {item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title} className="absolute inset-0 w-full h-full object-cover"/>}
   <div className="relative p-8 max-w-[60%]"><h3 data-cms-item={item.id} data-cms-text="lead" className="text-3xl font-bold leading-tight whitespace-pre-line" style={typographyStyle(section,'lead')}>{item.title}</h3>{item.description&&<p data-cms-item={item.id} data-cms-text="itemContent" className="mt-3 text-sm whitespace-pre-line" style={typographyStyle(section,'itemContent')}>{item.description}</p>}{item.buttonText&&href&&<a data-cms-button="primary" data-cms-button-kind="link" data-cms-item={item.id} data-cms-action="button" href={href} onClick={e=>{if(editing)e.preventDefault()}} className="inline-flex items-center gap-2 text-xs mt-5 py-2">{item.buttonText}<ArrowRight size={14}/></a>}</div>
  </article>})}{editing&&!section.items?.length&&<p className="text-sm text-neutral-500">Add an editorial card in Content.</p>}</div>
 </div>;
}
