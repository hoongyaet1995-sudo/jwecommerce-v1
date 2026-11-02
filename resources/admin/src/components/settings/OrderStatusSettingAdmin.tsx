import React, {useEffect, useState} from 'react';
import {Clock, Truck, Pencil, X} from 'lucide-react';
import {useAdmin} from '../../context/AdminContext';
import {OrderDialogOverlay} from '../orders/OrderDialogOverlay';

type Status = {id:string;code:string;name:string;description:string;ordersCount:number};
export const OrderStatusSettingAdmin: React.FC<{scope?:'order'|'shipment'}> = ({scope='order'}) => {
 const {laravelBootstrap,reloadOrderStatuses}=useAdmin();
 const [rows,setRows]=useState<Status[]>([]),[error,setError]=useState(''),[loading,setLoading]=useState(true),[editing,setEditing]=useState<Status|null>(null),[busy,setBusy]=useState(false);
 const title=scope==='order'?'Order status':'Shipment status';
 useEffect(()=>{let active=true;setLoading(true);setError('');fetch(`/admin/api/commerce-statuses/${scope}`,{credentials:'same-origin',headers:{Accept:'application/json'}}).then(async r=>{if(!r.ok)throw new Error('Status settings could not be loaded.');return r.json();}).then(p=>{if(active)setRows(p.data);}).catch(e=>{if(active)setError(e.message);}).finally(()=>{if(active)setLoading(false);});return()=>{active=false;};},[scope]);
 return <div className="space-y-6">
  <nav className="text-sm text-neutral-500">Settings / {title}</nav>
  <div><h1 className="text-2xl font-bold text-neutral-900 dark:text-white">{title}</h1><p className="mt-2 text-sm text-neutral-500">{scope==='order'?'Manage the names used to follow order progress.':'Manage the names used to follow preparation and delivery.'}</p></div>
  {error&&<p role="alert" className="rounded-xl border border-rose-200 p-4 text-rose-600">{error}</p>}
  <section className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
   <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center gap-3">{scope==='order'?<Clock size={20}/>:<Truck size={20}/>}<h2 className="font-semibold">{title}</h2></div>
   <div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead className="bg-neutral-50 dark:bg-neutral-950 text-xs uppercase text-neutral-500"><tr><th className="px-6 py-4">Status</th><th className="px-6 py-4">Description</th><th className="px-6 py-4">{scope==='order'?'Orders':'Shipments'}</th><th className="px-6 py-4">Actions</th></tr></thead><tbody>
   {loading?<tr><td colSpan={4} className="p-6">Loading statuses…</td></tr>:rows.map(row=><tr key={row.code} className="border-t border-neutral-100 dark:border-neutral-800"><td className="px-6 py-5 font-semibold">{row.name}</td><td className="px-6 py-5 text-neutral-500">{row.description}</td><td className="px-6 py-5">{row.ordersCount}</td><td className="px-6 py-5"><button aria-label={`Edit ${row.name}`} onClick={()=>{setError('');setEditing({...row});}} className="inline-flex items-center gap-2 rounded-lg border border-neutral-200 dark:border-neutral-700 px-3 py-2"><Pencil size={14}/>Edit</button></td></tr>)}
   </tbody></table></div>
  </section>
  {editing&&<OrderDialogOverlay busy={busy} onClose={()=>setEditing(null)}><section className="jw-order-dialog" role="dialog" aria-modal="true" aria-label="Edit status"><header><h2>Edit status</h2><button aria-label="Close" disabled={busy} onClick={()=>setEditing(null)}><X size={18}/></button></header><form onSubmit={async e=>{e.preventDefault();setBusy(true);setError('');try{const r=await fetch(`/admin/api/commerce-statuses/${scope}/${editing.code}`,{method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':laravelBootstrap.csrfToken},body:JSON.stringify({name:editing.name,description:editing.description})});const payload=await r.json();if(!r.ok)throw new Error(payload.message||'The status could not be saved.');setRows(payload.data);if(scope==='order') await reloadOrderStatuses();setEditing(null);}catch(e){setError(e instanceof Error?e.message:'The status could not be saved.');}finally{setBusy(false);}}}><label>Name<input required maxLength={100} value={editing.name} onChange={e=>setEditing({...editing,name:e.target.value})}/></label><label>Description<textarea maxLength={500} value={editing.description} onChange={e=>setEditing({...editing,description:e.target.value})}/></label><p>Status rules are managed automatically.</p>{error&&<p role="alert">{error}</p>}<footer><button type="button" className="jw-btn" disabled={busy} onClick={()=>setEditing(null)}>Cancel</button><button className="jw-btn jw-btn-primary" disabled={busy}>Save changes</button></footer></form></section></OrderDialogOverlay>}
 </div>;
};
