import React,{useEffect,useRef} from 'react';
import {createPortal} from 'react-dom';
export function OrderDialogOverlay({children,onClose,busy=false}:{children:React.ReactNode;onClose:()=>void;busy?:boolean}){
 const ref=useRef<HTMLDivElement>(null);
 useEffect(()=>{const previous=document.activeElement as HTMLElement|null;ref.current?.querySelector<HTMLElement>('button,input,select,textarea')?.focus();return()=>previous?.focus()},[]);
 const overlay=<div ref={ref} className="jw-commerce jw-order-overlay" onKeyDown={e=>{if(e.key==='Escape'&&!busy){e.preventDefault();onClose()}if(e.key==='Tab'){const list=Array.from(ref.current?.querySelectorAll<HTMLElement>('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),a[href]')??[]);const first=list[0],last=list.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus()}}}}>{children}</div>;
 return typeof document === 'undefined' ? overlay : createPortal(overlay,document.body);
}
