/* One user confirmation starts production; safe recovery is bounded by the server. */
'use strict';
(()=>{
const productionView=data=>({label:data.status==='completed'?(data.scope==='scene_trial'?'查看试生成片段':'查看成片'):['queued','preparing'].includes(data.status)?'正在制作…':'制作已暂停，正在排查',pending:['queued','preparing'].includes(data.status),done:data.status==='completed'});
if(typeof module!=='undefined')module.exports={productionView};
if(typeof document==='undefined')return;
const form=document.querySelector('[data-storyboard-production]');if(!form)return;
const note=form.querySelector('[data-storyboard-production-status]'),result=form.querySelector('[data-storyboard-production-result]'),details=form.querySelector('[data-production-error-details]'),detailText=form.querySelector('[data-production-error-text]');
let run=form.dataset.run,timer=null,busy=false,recoveryAttempted=false,until=Date.now()+30*60*1000;
const start=form.querySelector('button[type=submit]'),scope=form.querySelector('[name=production_scene]'),requestId=crypto.randomUUID();
const needsQuote=()=>form.dataset.blocked==='1'||(scope?.value||'')!==(form.dataset.planScene||'');
function syncConsent(){const reprice=needsQuote();if(result.hidden)start.hidden=false;start.disabled=busy||Boolean(run)||(reprice&&form.dataset.canReprice!=='1');start.textContent=run?'正在制作…':busy?(reprice?'正在核价…':'正在提交…'):reprice?'查看制作费用':'确认并开始制作 · USD '+form.dataset.limit;}
scope?.addEventListener('change',()=>{if((scope.value||'')!==(form.dataset.planScene||'')){if(timer)clearTimeout(timer);run=null;result.hidden=true;start.hidden=false;}syncConsent();});syncConsent();
async function api(action,data){const r=await fetch(form.dataset.api+action,{method:data?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':form.dataset.csrf}:{})},...(data?{body:JSON.stringify({request_id:requestId,version:Number(form.dataset.version),...data})}:{})});const out=await r.json();if(!r.ok){const error=new Error(Object.values(out.errors||{}).flat().join('；')||out.message||'系统正在核对任务，已有结果保留。');error.status=r.status;throw error;}return out;}
async function recover(){
 const recovery=await api('/production-recover',{production_id:run});
 if(recovery.mode==='approval'){
  const q=recovery.quote;const approved=await window.MarketingGenerationBilling.confirm({id:q.id,summary:q.reason,amount:q.additional_limit,previous_actual:q.previous_actual,additional_limit:q.additional_limit,total_limit:q.total_limit});
  if(approved){await api('/billing-confirm',{quote_id:q.id,consent:true});until=Date.now()+30*60*1000;recoveryAttempted=false;return true;}
  await api('/retry-decline',{quote_id:q.id});note.textContent='你未批准新增费用，制作已暂停，已有结果保留。';return false;
 }
 note.textContent=recovery.message||'系统正在继续处理。';return recovery.mode==='recovering'||recovery.mode==='progress';
}
async function poll(navigate=false){if(!run)return;try{
 const data=await api('/productions/'+encodeURIComponent(run));const view=productionView(data);
 note.textContent=view.done?(data.scope==='scene_trial'?'所选分镜已完成，未制作整片。':'新版成片已完成，原版保留。'):view.pending?(data.scope==='scene_trial'?'正在制作所选分镜，进度将自动更新。':'正在制作并更新进度，无需重复点击。'):'制作暂停，系统正在核对，已有结果保留。';
 details.hidden=!data.message;detailText.textContent=data.message||'';
 start.disabled=true;start.textContent=view.label;
 if(view.done&&data.preview_url){start.hidden=true;result.hidden=false;result.href=data.preview_url;result.textContent=view.label;if(navigate)window.location.assign(data.preview_url);return;}
 if(!view.pending&&!recoveryAttempted){recoveryAttempted=true;if(await recover()){timer=setTimeout(()=>poll(navigate),3000);}return;}
 if(view.pending&&Date.now()<until){recoveryAttempted=false;timer=setTimeout(()=>poll(navigate),3000);}
 }catch(e){note.textContent='系统暂时无法读取进度，已有任务保留，不会重新提交生成。';details.hidden=false;detailText.textContent=e.message;}
}
form.addEventListener('submit',async e=>{e.preventDefault();if(busy||run)return;const reprice=needsQuote();if(reprice&&form.dataset.canReprice!=='1')return;busy=true;syncConsent();try{
 if(reprice){const out=await api('/production',{...(scope?.value?{scene_id:scope.value}:{}),request_id:crypto.randomUUID()});window.location.assign(out.preview_url);return;}
 let data;if(form.dataset.billing==='1'){let quoteId=form.dataset.billingQuote;if(!quoteId){const quoted=await api('/billing-quote',{operation:'start-production',payload:{plan_id:form.dataset.plan}});quoteId=quoted.quote.id;form.dataset.billingQuote=quoteId;}data=await api('/billing-confirm',{quote_id:quoteId,consent:true});}else data=await api('/start-production',{plan_id:form.dataset.plan,consent:true});run=data.id;await poll(true);
 }catch(e){note.textContent='制作暂未继续，系统正在核对。';details.hidden=false;detailText.textContent=e.message;if([409,423].includes(e.status)){form.dataset.blocked='1';delete form.dataset.billingQuote;}}
 finally{busy=false;if(!run)syncConsent();}
});
// Legacy scoped upload acceptance remains available where the platform explicitly enables it.
const checkpoint=form.querySelector('[data-upload-checkpoint]'),checkpointNote=form.querySelector('[data-upload-checkpoint-status]');let checkpointRole='start_frame';
checkpoint?.addEventListener('click',async()=>{if(busy)return;busy=true;checkpoint.disabled=true;syncConsent();try{const out=await api('/upload-checkpoint',{plan_id:form.dataset.plan,frame_role:checkpointRole});const c=out.checkpoint;checkpointNote.textContent=c.message+(c.observed_credits!==null?' 本张积分变化：'+Number(c.observed_credits).toFixed(2)+' credits；计入预算 USD '+(c.actual_microusd/1000000).toFixed(2)+'。':'');if(c.status==='measured'&&checkpointRole==='start_frame'){checkpointRole='end_frame';checkpoint.textContent='上传尾帧并核对积分';}else checkpoint.textContent='查看已保存的上传核对结果';}catch(e){checkpointNote.textContent=e.message;}finally{busy=false;checkpoint.disabled=false;syncConsent();}});
if(run)poll();
})();
