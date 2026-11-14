import React from 'react';
import type {ConnectedSection} from '../../cms/connectedFamilies';
import {shopNavigationHref} from '../../cms/shopNavigation';
export const detail=(section:ConnectedSection,slot:string)=>section.items?.find(item=>item.layoutGroup==='details'&&item.layoutSlot===slot);
export const group=(section:ConnectedSection,id:string)=>(section.items??[]).filter(item=>item.layoutGroup===id);
export const nodeId=(section:ConnectedSection,id:string)=>`${section.id}-native-${id}`;
export function action(event:React.MouseEvent,destination:unknown,editing:boolean){const href=shopNavigationHref(typeof destination==='string'?destination:'');if(editing||!href){event.preventDefault();return;}if(href==='#storefront-support-widget'){const launcher=document.getElementById('sf-support-launcher');if(launcher){event.preventDefault();launcher.click();}}}
/** Inline decoration belongs to the selected visual. Authored copy remains the only text owner. */
export function AuthoredInline({value,original,children}:{value:unknown;original:string;children?:React.ReactNode}){
 const copy=typeof value==='number'?String(value):typeof value==='string'?value:'';
 if(!copy)return null;
 if(copy===original)return <>{children}</>;
 const weights:number[]=[];
 const inspect=(node:React.ReactNode):void=>{if(typeof node==='string'||typeof node==='number'){weights.push(String(node).trim().split(/\s+/).filter(Boolean).length);return;}if(!React.isValidElement<{children?:React.ReactNode;'aria-hidden'?:string|boolean}>(node)||node.props['aria-hidden']===true||node.props['aria-hidden']==='true')return;React.Children.forEach(node.props.children,inspect);};
 React.Children.forEach(children,inspect);
 const total=weights.reduce((sum,value)=>sum+value,0),words=copy.match(/\S+\s*/g)??[];let index=0,used=0,weight=0;
 const update=(node:React.ReactNode):React.ReactNode=>{
  if(typeof node==='string'||typeof node==='number'){weight+=weights[index++]??0;const end=index===weights.length?words.length:Math.round(words.length*weight/Math.max(1,total)),text=words.slice(used,end).join('');used=end;return text||null;}
  if(!React.isValidElement<{children?:React.ReactNode;'aria-hidden'?:string|boolean}>(node))return node;
  if(node.props['aria-hidden']===true||node.props['aria-hidden']==='true')return node;
  const next=React.Children.map(node.props.children,update);if(['em','mark','strong'].includes(typeof node.type==='string'?node.type:'')&&!React.Children.toArray(next).length)return null;return React.cloneElement(node,undefined,next);
 };
 return <>{React.Children.map(children,update)}</>;
}
