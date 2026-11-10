import React from 'react';
import type {PageSection,SectionItem} from '../../types';
import {SectionIcon} from './IconPicker';
import {typographyStyle} from '../../cms/inspectorCapabilities';

function Node({s,item}:{s:PageSection;item:SectionItem}){
 return <><SectionIcon name={item.icon}/><strong data-cms-text="itemTitle" data-cms-item={item.id} style={{color:s.customColors?.titleColor,...typographyStyle(s,'itemTitle')}}>{item.title}</strong>{item.description&&<small data-cms-text="itemContent" data-cms-item={item.id} style={{color:s.customColors?.contentColor,...typographyStyle(s,'itemContent')}}>{item.description}</small>}</>;
}
export function CorporateWorkflow({section:s}:{section:PageSection}){
 const steps=s.items?.filter(i=>i.category==='workflow')??[],channels=s.items?.filter(i=>i.category==='channel')??[],captions=s.items?.filter(i=>i.category==='caption')??[];
 const support=s.layoutPreset==='corporate-support';
 if(!steps.length&&!captions.length&&(!support||!channels.length))return null;
 return <div className={support?'corp-support-overlay':'corp-workflow-wrap'} data-corporate-workflow={support?'network':'linear'}>
  {support?<div className="corp-network" role="group" aria-label="Customer channels and handling workflow">
   {channels.length>0&&<div className="corp-network-channels">{channels.map(item=><div key={item.id} data-cms-item={item.id}><Node s={s} item={item}/></div>)}</div>}
   {channels.length>0&&steps.length>0&&<span className="corp-network-bridge" aria-hidden="true">→</span>}
   <div className="corp-network-steps">{steps.map(item=><div key={item.id} data-cms-item={item.id}><Node s={s} item={item}/></div>)}</div>
  </div>:steps.length>0&&<div className="corp-workflow" role="group" aria-label="Content workflow">{steps.map(item=><div key={item.id} data-cms-item={item.id}><Node s={s} item={item}/></div>)}</div>}
  {captions.map(item=><p className="corp-workflow-caption" key={item.id} data-cms-item={item.id}><Node s={s} item={item}/></p>)}
 </div>;
}
