import React,{useEffect,useLayoutEffect,useState} from 'react';
import type {PageSection} from '../../types';
import type {TextRole} from '../../cms/inspectorCapabilities';
import {targetRole,headingSelection,type HeadingSelection} from '../../cms/textToolbar';
import {FloatingTextToolbar} from './FloatingTextToolbar';

type Target={sectionId:string;role:TextRole;itemId?:string;element:HTMLElement;selection?:HeadingSelection};
export type RenderedText={sectionId:string;role:TextRole;itemId?:string;fontSize:number;fontWeight:number;color:string};
type Props={canvasRef:React.RefObject<HTMLDivElement|null>;sections:PageSection[];activeSectionId:string|null;pageId:string;onChange:(s:PageSection)=>void;onRendered?:(value:RenderedText|null)=>void;onSelect:(id:string,role:TextRole,itemId?:string)=>void};
/** Builder-only adapter. Content, persistence and style ownership stay in the existing Passport. */
export function BuilderTextToolbar({canvasRef,sections,activeSectionId,pageId,onChange,onSelect,onRendered}:Props){
 const [target,setTarget]=useState<Target|null>(null);
 const [placement,setPlacement]=useState<{left:number;top:number;width:number;computed:{fontSize:number;fontWeight:number;color:string}}|null>(null);
 const section=target?sections.find(s=>s.id===target.sectionId):undefined;
 useEffect(()=>{setTarget(null)},[pageId]);
 useEffect(()=>{if(target&&target.sectionId!==activeSectionId)setTarget(null)},[activeSectionId,target?.sectionId]);
 useEffect(()=>{const canvas=canvasRef.current;if(!canvas)return;
  const open=(event:MouseEvent)=>{const node=event.target;if(!(node instanceof HTMLElement)||node.closest('[data-cms-source],form,input,textarea,select,[contenteditable="true"]'))return;
   const root=node.closest<HTMLElement>('section[id^="section-"]');const s=sections.find(s=>root?.id===`section-${s.id}`);if(!s)return;
   const role=targetRole(s,node);if(!role)return;const element=node.closest<HTMLElement>('[data-cms-text]')!;
   event.preventDefault();const selection=headingSelection(s,element,window.getSelection());const itemId=element.closest<HTMLElement>('[data-cms-item]')?.dataset.cmsItem;setTarget({sectionId:s.id,role,itemId,element,selection});if(itemId)onSelect(s.id,role,itemId);else onSelect(s.id,role);
  };
  const close=()=>setTarget(null);const key=(e:KeyboardEvent)=>{if(e.key==='Escape')close()};
  canvas.addEventListener('contextmenu',open);canvas.addEventListener('pointerdown',close);window.addEventListener('keydown',key);
  return()=>{canvas.removeEventListener('contextmenu',open);canvas.removeEventListener('pointerdown',close);window.removeEventListener('keydown',key)};
 },[canvasRef,sections,onSelect]);
 useLayoutEffect(()=>{
  const position=()=>{const canvas=canvasRef.current,e=target?.element;if(!e?.isConnected||!canvas||!section){setPlacement(null);return;}
   const r=e.getBoundingClientRect(),bounds=canvas.getBoundingClientRect();if(r.bottom<bounds.top||r.top>Math.min(bounds.bottom,innerHeight)){setPlacement(null);return;}
   const width=Math.min(760,innerWidth-24,Math.max(240,Math.min(bounds.width,e.closest<HTMLElement>('section[id^="section-"]')?.getBoundingClientRect().width??bounds.width)-24));const left=Math.max(12,Math.min(r.left,innerWidth-width-12));
   const top=r.top-56>bounds.top?r.top-56:Math.min(innerHeight-64,r.bottom+10);const style=getComputedStyle(e);const rgb=style.color.match(/\d+/g);
   const color=rgb?'#'+rgb.slice(0,3).map(n=>Math.min(255,Number(n)).toString(16).padStart(2,'0')).join(''):'#18181b';
   setPlacement({left,top:Math.max(8,top),width,computed:{fontSize:parseFloat(style.fontSize)||36,fontWeight:parseInt(style.fontWeight)||400,color}});
  };position();const observer=typeof ResizeObserver==='undefined'?undefined:new ResizeObserver(position);
  if(canvasRef.current)observer?.observe(canvasRef.current);if(target?.element){observer?.observe(target.element);const region=target.element.closest('section[id^="section-"]');if(region)observer?.observe(region);}
  window.addEventListener('scroll',position,true);window.addEventListener('resize',position);
  return()=>{observer?.disconnect();window.removeEventListener('scroll',position,true);window.removeEventListener('resize',position)};
 },[target,section,canvasRef]);
 useEffect(()=>{onRendered?.(target&&placement?{sectionId:target.sectionId,role:target.role,itemId:target.itemId,...placement.computed}:null)},[target,placement]);
 if(!target||!section||!placement||!targetRole(section,target.element))return null;
 return <FloatingTextToolbar section={section} role={target.role} itemId={target.itemId} position={placement} computed={placement.computed} selection={target.selection} onChange={onChange} onClose={()=>setTarget(null)}/>;
}
