(()=>{'use strict';
window.JWAudienceTransfer={create({base,onState,onBusy,canContinue=true}){
 let task=null,timer=null,controller=null,sequence=0,leaving=false;
 const stop=()=>{sequence++;clearTimeout(timer);controller?.abort();};
 async function request(id,checkpoint){controller=new AbortController();const r=await fetch(base+'/tasks/'+id+(checkpoint?'/continue':''),{method:checkpoint?'POST':'GET',credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(checkpoint?{body:JSON.stringify({checkpoint})}:{})});let d;try{d=await r.json();}catch{d={};}if(!r.ok){const e=Error('Unable to continue. Your progress is saved.');e.status=r.status;throw e;}return d;}
 function fail(e){if(leaving||e.name==='AbortError')return;clearTimeout(timer);onBusy(![401,419,403,410].includes(e.status));onState(task,e.status===401||e.status===419?'signin':e.status===403?'access':e.status===410?'expired':'interrupted');}
 function settle(t,mode){task=t;onState(t,t.state==='queued'&&!canContinue?'access':mode);const active=t.state==='queued'&&canContinue;onBusy(active);if(active&&!leaving&&!document.hidden)timer=setTimeout(advance,400);}
 async function advance(){if(leaving||document.hidden||!task||task.state!=='queued')return;const run=sequence;try{const t=await request(task.id,task.checkpoint);if(run===sequence)settle(t,'processing');}catch(e){if(run===sequence)fail(e);}}
 async function watch(id){stop();leaving=false;const run=sequence;onBusy(true);try{const t=await request(id);if(run===sequence)settle(t,t.state==='queued'?'resuming':'ready');}catch(e){if(run===sequence){task=task||{id,state:'queued'};fail(e);}}}
 const pause=()=>{leaving=true;stop();};
 const resume=()=>task?watch(task.id):Promise.resolve();
 window.addEventListener('pagehide',pause);document.addEventListener('visibilitychange',()=>{if(document.hidden)pause();else if(task?.state==='queued')resume();});window.addEventListener('pageshow',e=>{if(e.persisted&&task?.state==='queued')resume();});
 return {watch,resume};
}};
})();
