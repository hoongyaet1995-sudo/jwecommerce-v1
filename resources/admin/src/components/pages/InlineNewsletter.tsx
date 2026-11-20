import {LayoutDiagramDropdown} from './LayoutDiagramDropdown';
import {SectionEyebrow} from './SectionEyebrow';
import React from 'react';
import type {PageSection} from '../../types';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {MotionItem} from './motion/CmsMotionWrapper';
export function InlineNewsletter({section,email,onEmail,onSubmit,success,error,couponCode,disabled}:{section:PageSection;email:string;onEmail:(value:string)=>void;onSubmit:(event:React.FormEvent)=>void;success:boolean;error:string;couponCode:string|null;disabled:boolean}) {
 return <div data-inline-newsletter className={`@container grid grid-cols-1 @min-[640px]:grid-cols-2 gap-6 items-center ${section.paddingY==='spacious'?'py-20':section.paddingY==='normal'?'py-12 @min-[1024px]:py-16':'py-8'}`}><MotionItem><div style={{textAlign:section.textAlignment}}>{<SectionEyebrow section={section}/>}<h2 data-cms-text="title" className="text-3xl font-serif" style={typographyStyle(section,'title')}>{section.title}</h2>{section.content&&<p data-cms-text="content" className="text-sm mt-2" style={typographyStyle(section,'content')}>{section.content}</p>}</div></MotionItem><MotionItem>{success?<div role="status" className="text-sm"><p>{section.formSuccessMessage??'Thank you for subscribing.'}</p>{couponCode&&<p>{couponCode}</p>}</div>:<form onSubmit={onSubmit} className="flex w-full border border-black/15 bg-white/60"><input aria-label="Newsletter email" type="email" required value={email} onChange={event=>onEmail(event.target.value)} placeholder="Your email address" className="min-w-0 flex-1 bg-transparent px-4 py-3 text-sm focus:outline-none"/><button data-cms-button="primary" data-cms-button-kind="button" data-cms-action="button" type="submit" disabled={disabled} className="px-5 py-3 text-xs font-semibold disabled:opacity-50" style={{backgroundColor:section.customColors?.primaryBtnBg,color:section.customColors?.primaryBtnText}}>{section.buttonText??'Subscribe'}</button></form>}{error&&<p role="alert" className="text-xs text-red-700 mt-2">{error}</p>}</MotionItem></div>;
}
export function NewsletterLayoutSettings({section,onChange}:{section:PageSection;onChange:(section:PageSection)=>void}) {
 if(section.type!=='newsletter_signup')return null;
 return <fieldset><legend className="text-xs font-bold uppercase mb-3">Newsletter layout</legend><LayoutDiagramDropdown label="Newsletter layout" value={section.newsletterPreset??'default'}
 choices={(['default','inline-signup'] as const).map(preset=>({value:preset,label:preset==='default'?'Centered signup':'Inline signup strip',diagram:<span aria-hidden="true" className={`flex gap-2 h-12 p-2 bg-neutral-100 dark:bg-neutral-800 ${preset==='default'?'flex-col':''}`}><span className="flex-1 bg-neutral-400/60"/><span className="flex-1 bg-neutral-400/60"/></span>}))}
 onChange={(preset)=>onChange({...section,newsletterPreset:preset})}/></fieldset>;
}
