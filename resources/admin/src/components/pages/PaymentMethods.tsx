import React from 'react';
import type {PageSection} from '../../types';
import {PaymentLogo} from '../common/PaymentLogo';
import {usePaymentInformation} from '../../cms/paymentInformation';
import {ShippingHeading} from './ShippingSections';
import {typographyStyle} from '../../cms/inspectorCapabilities';
export function PaymentMethods({section}:{section:PageSection}){
 const {facts,failed,editing}=usePaymentInformation(section.id),list=section.paymentLayout==='list';
 const labels={available:'Available',sandbox:'Sandbox',setup_required:'Setup required',integration_pending:'Integration pending',unavailable:'Unavailable for this currency'};
 return <div data-payment-methods className={section.paddingY==='compact'?'py-8':section.paddingY==='spacious'?'py-20':'py-12 @min-[1024px]:py-16'}><ShippingHeading section={section}/>
 {facts?.methods.length?<div className={list?'border-t border-neutral-200':'grid grid-cols-1 @min-[640px]:grid-cols-2 @min-[1024px]:grid-cols-3 gap-5'}>{facts.methods.map(method=><article key={method.id} className={list?'py-7 border-b border-neutral-200 grid grid-cols-1 @min-[640px]:grid-cols-[140px_1fr] gap-5 items-center':'border border-neutral-200 rounded-xl p-6'}><div className="w-28 h-12 flex items-center"><PaymentLogo type={method.id} size="lg"/></div><div><h3 data-cms-text="itemTitle" className="text-lg font-bold mt-3" style={{color:section.customColors?.titleColor,...typographyStyle(section,'itemTitle')}}>{method.title}</h3><p data-cms-text="itemContent" className="mt-3 text-sm leading-relaxed" style={{color:section.customColors?.contentColor,...typographyStyle(section,'itemContent')}}>{method.description}</p>{editing&&method.status!=='available'&&<span className="inline-block mt-4 text-[11px] rounded-full bg-neutral-100 text-neutral-600 px-3 py-1">{labels[method.status]}</span>}</div></article>)}</div>:<p role="status" className="text-sm text-neutral-500 py-6 border-y border-neutral-200">{failed?'Payment information is unavailable.':!facts&&editing?'Loading payment methods…':'Payment options are confirmed at checkout. Contact our team if you need help.'}</p>}
 {section.paymentNote&&<p style={{color:section.customColors?.contentColor,...typographyStyle(section,'content')}} className="mt-5 text-xs leading-relaxed">{section.paymentNote}</p>}</div>;
}
