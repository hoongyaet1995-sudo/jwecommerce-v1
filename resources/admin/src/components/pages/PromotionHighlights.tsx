import {SectionEyebrow} from './SectionEyebrow';
import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import {resolvePromotionCards,type PromotionOffer} from '../../cms/promotionHighlights';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage} from './ScopedImage';
import {MotionItem} from './motion/CmsMotionWrapper';

export function PromotionHighlights({section,offers,editing=false}:{section:PageSection;offers:PromotionOffer[];editing?:boolean}) {
 const cards=resolvePromotionCards(section,offers);
 if(!cards.length&&!editing)return null;
 const colors=section.customColors;
 return <div data-promotion-highlights data-layout={section.promotionLayout??'side'} className={`@container w-full px-6 @min-[750px]:px-10 ${section.paddingY==='spacious'?'py-16':section.paddingY==='normal'?'py-10':'py-7'}`} style={{color:colors?.titleColor??'#f7f6ef',fontFamily:'Arial, sans-serif'}}>
  <div className={`grid gap-7 items-center ${section.promotionLayout!=='above'?'@min-[750px]:grid-cols-[1fr_2.1fr]':''}`}>
   <MotionItem className={section.promotionLayout==='above'?'max-w-[450px]':''}>
    {<SectionEyebrow section={section}/>}
    <h2 data-cms-text="title" className="font-serif font-normal text-[32px] leading-[1.08] tracking-[-.02em] whitespace-pre-line mb-[18px]" style={{fontFamily:'Georgia, serif',color:colors?.titleColor,textAlign:section.textAlignment,...typographyStyle(section,'title')}}>{section.title}</h2>
    {section.content&&<p data-cms-text="content" className="text-[13px] leading-[1.6] whitespace-pre-line" style={{color:colors?.contentColor??'#f7f6ef',textAlign:section.textAlignment,...typographyStyle(section,'content')}}>{section.content}</p>}
   </MotionItem>
   <div className="grid grid-cols-1 @min-[500px]:grid-cols-2 gap-4">
    {cards.map(({item,offer})=><MotionItem key={`${item.id}-${offer.id}`} data-cms-item={item.id} className="relative isolate flex items-center min-h-[220px] @min-[500px]:min-h-[214px] overflow-hidden bg-[#e9e7df] text-[#282b24]">
     {item.imageUrl&&<ScopedImage section={section} targetId={`item:${item.id}:imageUrl`} src={item.imageUrl} alt={item.title||offer.name} className="absolute inset-0 w-full h-full object-cover -z-20"/>}
     <div aria-hidden="true" className="absolute inset-0 -z-10 pointer-events-none" style={{background:'linear-gradient(90deg,rgba(247,246,240,.96),rgba(247,246,240,.8) 43%,rgba(247,246,240,0) 76%)'}}/>
     <div className="p-5 w-[72%] max-w-full">
      {section.showOfferBadges!==false&&<span data-cms-source className="inline-block px-2 py-1 text-[9px] mb-3" style={{backgroundColor:colors?.primaryBtnBg??'#595e43',color:colors?.primaryBtnText??'#fff'}}>{offer.benefit}</span>}
      <h3 data-cms-item={item.id} data-cms-text="itemTitle" className="font-normal text-[23px] leading-[1.08] mb-2.5 whitespace-pre-line break-words" style={{fontFamily:'Georgia, serif',...typographyStyle(section,'itemTitle')}}>{item.title||offer.name}</h3>
      {(item.description||offer.description)&&<p data-cms-item={item.id} data-cms-text="itemContent" className="text-[11px] leading-[1.45] mb-[13px] whitespace-pre-line text-[#5a5e54]" style={typographyStyle(section,'itemContent')}>{item.description||offer.description}</p>}
      <p data-cms-source className="text-[10px] leading-relaxed text-[#5a5e54] mb-3 break-words">{section.showOfferBadges===false&&<>{offer.benefit} · </>}Use {offer.code}{offer.minimumSpend&&<> · Minimum spend {offer.minimumSpend}</>}{offer.endsAt&&<> · Until {new Date(offer.endsAt).toLocaleDateString('en-MY',{timeZone:'Asia/Kuala_Lumpur',day:'numeric',month:'short',year:'numeric'})}</>}</p>
      {item.buttonText&&item.linkUrl&&<a data-cms-button="primary" data-cms-button-kind="link" data-cms-action="link" data-cms-item={item.id} href={item.linkUrl} onClick={e=>{if(editing)e.preventDefault()}} className="inline-flex items-center gap-[9px] text-[11px] underline underline-offset-4" style={{color:colors?.secondaryBtnText??'#292b24'}}>{item.buttonText}<ArrowRight size={13} aria-hidden="true"/></a>}
     </div>
    </MotionItem>)}
    {!cards.length&&editing&&<p data-cms-source className="col-span-full border border-dashed border-white/40 p-6 text-xs leading-relaxed">No eligible promotions. Choose an active offer in Content. This section is hidden from customers until an eligible offer is available.</p>}
   </div>
  </div>
 </div>;
}
