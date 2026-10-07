import React from 'react';
import type {LucideIcon} from 'lucide-react';

export function AISectionHeader({icon: Icon,title,description}:{icon:LucideIcon;title:string;description?:string}){
 return <div className="flex items-center gap-3 pb-4 mb-4 border-b border-neutral-100 dark:border-neutral-800">
  <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 flex items-center justify-center flex-shrink-0"><Icon className="w-5 h-5" aria-hidden="true"/></div>
  <div><h3 className="text-sm font-semibold text-neutral-900 dark:text-white">{title}</h3>{description&&<p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">{description}</p>}</div>
 </div>;
}
