import React from 'react';
import {BarChart3,ShoppingBag,Package,Users,Truck,Receipt,Megaphone} from 'lucide-react';
const tabs=[['sales','Sales & revenue',BarChart3],['orders','Orders & enquiries',ShoppingBag],['products','Products & inventory',Package],['customers','Customers',Users],['shipping','Shipping & delivery',Truck],['tax','Payments & reconciliation',Receipt],['marketing','Marketing & conversion',Megaphone]] as const;
export function ReportTabs({active}:{active:string}){
 const shared=new URLSearchParams(Array.from(new URLSearchParams(location.search)).filter(([key])=>['from','to','currency'].includes(key))).toString();
 return <nav aria-label="Report categories" className="flex min-w-0 max-w-full gap-6 border-b border-neutral-200 dark:border-neutral-800 overflow-x-auto">{tabs.map(([key,title,Icon])=><a key={key} href={'/admin/reports/'+key+(shared?'?'+shared:'')} aria-current={active===key?'page':undefined} className={'flex shrink-0 items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 text-xs font-semibold whitespace-nowrap py-3 border-b-2 '+(active===key?'border-indigo-600 text-indigo-600 dark:text-indigo-400':'border-transparent text-neutral-500')}><Icon size={15}/>{title}</a>)}</nav>;
}
