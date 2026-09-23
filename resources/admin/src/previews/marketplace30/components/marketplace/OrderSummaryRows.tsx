import React from 'react';
import {ArrowUpRight,Eye,Printer} from 'lucide-react';
import {MarketplaceLogo} from './MarketplaceLogo';
import type {OrderSummary} from '../../../../integration/marketplaceOperations';

// ZIP30 OMS table cells; absent summary fields are disclosed, never synthesized.
export function OrderSummaryRows({orders,onSelect}:{orders:OrderSummary[];onSelect:(id:string)=>void}){
 if(!orders.length)return <tr><td colSpan={8} className="py-16 text-center"><p className="text-xs text-neutral-500 mt-0.5">No fetched orders match these filters.</p></td></tr>;
 return <>{orders.map(order=><tr key={order.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
  <td className="py-4 px-4"><input type="checkbox" disabled className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"/></td>
  <td className="py-4 px-4"><div className="space-y-0.5">
   <button onClick={()=>onSelect(String(order.id))} className="font-bold text-neutral-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1 cursor-pointer font-mono"><span>{order.external_order_id}</span><ArrowUpRight className="w-3 h-3 text-neutral-400"/></button>
   <p className="text-[11px] font-mono text-neutral-500">{order.data_classification==='test'?'[TEST] ':order.data_classification==='operational'?'':'[Unclassified] '}Ref: {order.external_order_id}</p>
  </div></td>
  <td className="py-4 px-4"><MarketplaceLogo platform={order.platform} variant="badge"/><p className="text-[11px] text-neutral-500 mt-1 truncate max-w-[140px]" title={order.shop_name}>{order.shop_name}</p></td>
  <td className="py-4 px-4"><div className="flex items-center gap-2">{order.thumbnail_url && <img src={order.thumbnail_url} alt="" referrerPolicy="no-referrer" loading="lazy" className="w-8 h-8 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800"/>}<div className="min-w-0"><p className="font-semibold text-neutral-900 dark:text-white truncate max-w-[150px]">View order details</p><p className="text-[10px] font-mono text-neutral-500">Items loaded on inspection</p></div></div></td>
  <td className="py-4 px-4"><span className="font-bold text-neutral-900 dark:text-white">{order.currency??'—'} {order.total_amount??'—'}</span></td>
  <td className="py-4 px-4 space-y-1.5"><span className="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-300 dark:border-neutral-700">{order.platform_status.replace(/_/g,' ')}</span></td>
  <td className="py-4 px-4">{order.tracking_number ? <div className="space-y-0.5 font-mono"><p className="font-bold text-neutral-800 dark:text-neutral-200 text-[11px] truncate max-w-[140px]" title={order.tracking_number}>{order.tracking_number}</p><p className="text-[10px] text-neutral-500 truncate max-w-[140px]" title={order.shipping_carrier ?? undefined}>{order.shipping_carrier ?? 'Carrier not provided'}</p></div> : <span className="text-[11px] text-neutral-400 italic">No tracking yet</span>}</td>
  <td className="py-4 px-4 text-right"><div className="inline-flex items-center gap-1.5 justify-end">{order.platform === 'lazada' && <button onClick={()=>onSelect(String(order.id))} className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition-colors cursor-pointer" title="Inspect official AWB availability"><Printer className="w-4 h-4"/></button>}<button onClick={()=>onSelect(String(order.id))} className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer" title="Inspect Order Audit"><Eye className="w-4 h-4"/></button></div></td>
 </tr>)}</>;
}
