import React,{useEffect,useState} from 'react';

import {Coins,SlidersHorizontal,Clock} from 'lucide-react';

import {readAdminBootstrap} from '../../integration/adminBootstrap';

import './PromotionWorkflow.css';

export function RewardsSettings(){const [d,D]=useState<any>(null),[error,E]=useState(''),[message,M]=useState(''),[busy,B]=useState(false);const boot=readAdminBootstrap();

useEffect(()=>{fetch('/admin/api/rewards/settings',{headers:{Accept:'application/json'}}).then(r=>{if(!r.ok)throw Error('Unable to load rewards settings.');return r.json()}).then(j=>D(j.data)).catch(e=>E(e.message))},[]);

async function save(e:React.FormEvent){e.preventDefault();B(true);E('');M('');try{const r=await fetch('/admin/api/rewards/settings',{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':boot.csrfToken},body:JSON.stringify(d)});const j=await r.json();if(!r.ok)throw Error(j.errors?Object.values(j.errors).flat().join(' '):'Unable to save.');D(j.data);M('Rewards settings saved.')}catch(e){E((e as Error).message)}finally{B(false)}}

const field=(key:string,label:string)=><label>{label}<input required type="number" min={key==='minimum_coins'?0:1} max={key==='max_discount_percent'?100:1000000} value={d[key]} onChange={e=>D({...d,[key]:Number(e.target.value)})}/></label>;

return <div className="promotion-workflow"><nav><a href="#overview">Promotions</a> / <a href="#rewards">Coins cashback</a> / Settings</nav><div className="pw-heading"><div><h1>Rewards settings</h1><p>Set how customers use their rewards.</p></div><a href="#rewards"><button>Back</button></a></div>{error&&<p role="alert" className="pw-error">{error}</p>}{message&&<p role="status">{message}</p>}{d&&<form onSubmit={save}><div className="pw-editor"><div><section className="pw-panel"><h2><Coins size={17}/>Coin value</h2>{field('coins_per_unit','Coins worth 1 store currency unit')}<p>Currency follows your store.</p></section><section className="pw-panel"><h2><SlidersHorizontal size={17}/>Redemption</h2><div className="pw-fields">{field('max_discount_percent','Maximum order discount (%)')}{field('minimum_coins','Minimum coins to redeem')}</div><p>Shipping and taxes are excluded.</p></section><section className="pw-panel"><h2><Clock size={17}/>Expiry</h2><label>Coins expire<select value={d.expiry_months??''} onChange={e=>D({...d,expiry_months:e.target.value?Number(e.target.value):null})}><option value="6">6 months after earning</option><option value="12">12 months after earning</option><option value="">Never</option></select></label></section></div><div><section className="pw-panel"><h2>Availability</h2><label className="pw-toggle">Enable rewards<input type="checkbox" role="switch" checked={!!d.enabled} onChange={e=>D({...d,enabled:e.target.checked})}/></label><p>Customers earn coins on confirmed payments and use available coins at checkout.</p></section><section className="pw-panel"><h2><Coins size={17}/>Customer preview</h2><strong>{d.coins_per_unit} coins = 1.00</strong><p>Use up to {d.max_discount_percent}% towards eligible merchandise.</p></section></div></div><div className="pw-actions"><a href="#rewards"><button type="button">Cancel</button></a><button className="pw-primary" disabled={busy}>{busy?'Saving…':'Save settings'}</button></div></form>}</div>}
