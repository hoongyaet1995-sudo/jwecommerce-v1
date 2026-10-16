import React, {useState} from 'react';
import type {PageSection} from '../../types';

const layoutTypes = new Set(['hero_banner','testimonials_slider','comparison_matrix','newsletter_signup','hero_slider','announcement_bar','media_text_split','rich_text','product_listing','media_gallery','feature_cards','values_list','chapter_block','faq_accordion','cta_strip','policy_content','payment_methods','shop_navigation','promotion_highlights']);
export function hasSectionLayout(section:PageSection) {
  return layoutTypes.has(section.type)||(section.type==='store_locator'&&section.contactPreset==='location');
}
export function SectionLayoutGroup({section,children}:{section:PageSection;children:React.ReactNode}) {
  // UI preference only: never serialize disclosure state into the page document.
  const key='jw-cms-section-layout:'+section.type;
  const read=()=>{try{return localStorage.getItem(key)!=='closed'}catch{return true}};
  const [open,setOpen]=useState(read);
  return <details key={section.type} className="cms-settings-group cms-section-layout" open={open} onToggle={event=>{const next=event.currentTarget.open;setOpen(next);try{localStorage.setItem(key,next?'open':'closed')}catch{}}}>
    <summary><span>Section layout<small className="block text-[11px] font-normal text-neutral-500 capitalize">{section.type.replace(/_/g,' ')}</small></span></summary>
    <div className="cms-settings-group-content space-y-4">{children}</div>
  </details>;
}
