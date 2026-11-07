/* Manual-only Automation workspace. No timer schedules or background jobs. */
(()=>{'use strict';
const boot=window.MarketingAutomationBoot;if(!boot)return;
const form=document.getElementById('automation-form'),status=document.getElementById('automation-status'),progress=document.getElementById('automation-progress');
let current={...boot.form},selected=structuredClone(boot.labels),busy=false,searchVersion=0,searchAbort=null;
const runKey='marketing-automation-run:'+current.id, draftKey='marketing-automation-edit:'+current.id;
let activeRun=null,operation='',sourcePool=null;
const message=(s)=>{status.textContent=s;};
const typeOptions=window.FreeBriefCore?.types||[];
function choiceCard(name,description,icon){const button=document.createElement('button'),symbol=document.createElement('i'),body=document.createElement('span'),title=document.createElement('strong'),detail=document.createElement('small');button.type='button';button.className='content-type';symbol.className='bi bi-'+icon;symbol.setAttribute('aria-hidden','true');title.textContent=name;detail.textContent=description;body.append(title,detail);button.append(symbol,body);return button;}
for(const item of typeOptions){const button=choiceCard(item.name,item.description,item.icon);button.dataset.postType=item.id;button.onclick=()=>{if(busy)return;form.elements.type.value=item.id;remember();};document.getElementById('automation-types')?.append(button);}
for(const [value,name,description,icon]of [['single','Single image','One complete image with matching post copy.','image'],['multiple','Multiple images','AI chooses 2–10 images to tell the full story without repetition.','images']]){const button=choiceCard(name,description,icon);button.dataset.imageMode=value;button.onclick=()=>{if(busy)return;form.elements.image_mode.value=value;remember();};document.getElementById('automation-images')?.append(button);}
async function request(path,data){const r=await fetch(boot.base+path,{method:data?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':boot.csrf}:{})},...(data?{body:JSON.stringify(data)}:{})});let j;try{j=await r.json();}catch{throw Error('The request could not be confirmed. Reload and open the saved generation before retrying.');}if(!r.ok)throw Error(Object.values(j.errors||{}).flat().join(' ')||j.message||'Request failed.');return j;}
function values(){const d=new FormData(form);return {...current,schedule_enabled:form.elements.schedule_enabled?form.elements.schedule_enabled.value==='1':(current.schedule_enabled??false),source_modes:sourcePool?.modes||current.source_modes||Object.fromEntries(Object.entries(selected).map(([k,v])=>[k,v.length?'items':'none'])),source_scope:d.get('source_scope')||current.source_scope||'selected',market:d.get('market')||current.market||'Malaysia',occasions:form.elements.occasions?d.has('occasions'):(current.occasions??true),live_trends:form.elements.live_trends?d.has('live_trends'):(current.live_trends??false),name:d.get('name'),prompt:d.get('prompt'),type:d.get('type'),image_mode:d.get('image_mode'),cta_url:d.get('cta_url'),platforms:d.getAll('platforms'),language:d.get('language'),tone:d.get('tone'),theme:d.get('theme'),days:d.getAll('days').map(Number),time:d.get('time'),timezone:'Asia/Kuala_Lumpur',sources:Object.fromEntries(Object.entries(selected).map(([k,v])=>[k,v.map(x=>x.id)]))};}
function remember(){try{sessionStorage.setItem(draftKey,JSON.stringify({version:current.version,values:values(),selected}));}catch{}purpose();}
function purpose(){
 const v=values(),type=typeOptions.find(t=>t.id===v.type);sourcePool?.sync(v.source_scope);
 document.querySelectorAll('[data-scope]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.scope===v.source_scope)));
 if(document.getElementById('all-sources-panel'))document.getElementById('all-sources-panel').hidden=v.source_scope!=='all';
 if(document.getElementById('selected-sources-panel'))document.getElementById('selected-sources-panel').hidden=v.source_scope!=='selected';
 if(document.getElementById('occasion-status'))document.getElementById('occasion-status').textContent=v.occasions?'Automatic':'Off';
 if(document.getElementById('trend-status'))document.getElementById('trend-status').textContent=v.live_trends?'Optional · On':'Optional · Off';
 document.getElementById('post-purpose').textContent=type?.note||'';
 for(const button of document.querySelectorAll('[data-post-type]'))button.setAttribute('aria-pressed',String(button.dataset.postType===v.type));
 for(const button of document.querySelectorAll('[data-image-mode]'))button.setAttribute('aria-pressed',String(button.dataset.imageMode===v.image_mode));
 const hex=document.getElementById('automation-theme-hex');if(hex)hex.textContent=v.theme;
 const summary=document.getElementById('automation-summary');if(!summary)return;const directionOpen=!!summary.querySelector('details[open]');summary.replaceChildren();
 const sourceNames=sourcePool?sourcePool.description():(v.source_scope==='all'?['All eligible resources']:Object.values(selected).flat().map(x=>x.label));
 const format=document.getElementById('automation-format');if(format)format.textContent=(type?.name||v.type)+' \u00b7 '+(v.image_mode==='multiple'?'AI chooses 2\u201310 images':'Single image');
 const field=(label)=>{const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;summary.append(dt,dd);return dd;};
 field('Source pool').textContent=sourceNames.length?sourceNames.join(' \u00b7 '):'Choose your sources';
 const direction=field('Creative direction'),prompt=(v.prompt||'').trim();
 if(!prompt)direction.textContent='Add your creative direction';else{const excerpt=document.createElement('p');excerpt.className='summary-direction';excerpt.textContent=prompt;direction.append(excerpt);if(prompt.length>60){const details=document.createElement('details'),toggle=document.createElement('summary'),full=document.createElement('p');details.className='summary-full-direction';details.open=directionOpen;toggle.textContent='View full direction';full.textContent=v.prompt;details.append(toggle,full);direction.append(details);}}
 const channels=field('Platforms & language');channels.className='summary-channels';
 for(const platform of v.platforms){const chip=document.createElement('span'),symbol=document.createElement('i');chip.className='summary-platform';chip.setAttribute('role','img');chip.setAttribute('aria-label',platform);chip.title=platform;symbol.className='bi bi-'+platform.toLowerCase();symbol.setAttribute('aria-hidden','true');chip.append(symbol);channels.append(chip);}
 const language=document.createElement('span');language.textContent=(v.platforms.length?'\u00b7 ':'No platforms selected \u00b7 ')+(v.language||'Not selected');channels.append(language);
 const destination=field('CTA destination'),url=(v.cta_url||'').trim();
 if(!url){destination.textContent='Not set';destination.classList.add('text-muted');}else{try{const target=new URL(url);if(target.protocol!=='https:'||target.username||target.password)throw Error('Invalid destination');const link=document.createElement('a');link.href=target.href;link.target='_blank';link.rel='noopener noreferrer';link.title=target.href;const path=target.pathname==='/'?'':target.pathname;link.textContent=target.hostname+(path.length>40?path.slice(0,37)+'\u2026':path);destination.append(link);}catch{destination.textContent='Enter a valid HTTPS URL';destination.classList.add('text-warning');}}

}
function renderSelected(){const box=document.getElementById('source-selected');if(!box)return;box.replaceChildren();for(const [kind,items]of Object.entries(selected))for(const item of items){const row=document.createElement('div');row.className='automation-source-row';const text=document.createElement('span');text.textContent=kind+' · '+item.label;const remove=document.createElement('button');remove.type='button';remove.className='btn-crm-secondary';remove.textContent='Remove';remove.onclick=()=>{if(busy)return;selected[kind]=selected[kind].filter(x=>x.id!==item.id);renderSelected();remember();};row.append(text,remove);box.append(row);}}
try{const saved=JSON.parse(sessionStorage.getItem(draftKey)||'null');if(saved&&saved.version===current.version){selected=saved.selected;current.source_modes=saved.values.source_modes??current.source_modes;for(const [k,v]of Object.entries(saved.values)){const el=form.elements[k];if(!el)continue;if(['platforms','days'].includes(k)||(k==='image_mode'&&el.type==='radio')){for(const input of form.querySelectorAll('[name="'+k+'"]'))input.checked=Array.isArray(v)?v.map(String).includes(input.value):input.value===v;}else if(['occasions','live_trends'].includes(k))el.checked=!!v;else if(k==='schedule_enabled')el.value=v?'1':'0';else el.value=v;}}}catch{}
document.querySelectorAll('[data-scope]').forEach(b=>b.onclick=()=>{if(busy)return;form.elements.source_scope.value=b.dataset.scope;searchAbort?.abort();remember();});
if(window.MarketingSourcePool&&document.getElementById('source-type-rows'))sourcePool=new window.MarketingSourcePool({base:boot.base,csrf:boot.csrf,selected,modes:current.source_modes,isBusy:()=>busy,onChange:()=>{selected=sourcePool.selected;current.source_modes={...sourcePool.modes};remember();}});
renderSelected();purpose();form.addEventListener('input',remember);form.addEventListener('change',remember);
async function search(){const v=++searchVersion;searchAbort?.abort();const q=document.getElementById('source-search').value.trim(),kind=document.getElementById('source-kind').value,box=document.getElementById('source-results');box.replaceChildren();if(!q)return;searchAbort=new AbortController();box.textContent='Searching…';try{const r=await fetch(boot.base+'/sources?kind='+encodeURIComponent(kind)+'&q='+encodeURIComponent(q),{headers:{Accept:'application/json'},signal:searchAbort.signal,credentials:'same-origin'});const j=await r.json();if(v!==searchVersion)return;if(!r.ok)throw Error(j.message||'Search failed.');box.replaceChildren();for(const item of j.items){const b=document.createElement('button');b.type='button';b.className='btn btn-light text-start w-100 mb-1';b.textContent=item.label;b.disabled=selected[kind].some(x=>x.id===item.id);b.onclick=()=>{if(busy)return;if(!selected[kind].some(x=>x.id===item.id))selected[kind].push(item);renderSelected();remember();b.disabled=true;};box.append(b);}if(!j.items.length)box.textContent='No matching sources.';if(j.more){const hint=document.createElement('p');hint.className='small text-muted';hint.textContent='More results available. Refine your keyword.';box.append(hint);}}catch(e){if(e.name!=='AbortError'&&v===searchVersion)box.textContent=e.message;}}
if(document.getElementById('source-search')){let debounce;document.getElementById('source-search').oninput=()=>{clearTimeout(debounce);if(!document.getElementById('source-search').value.trim())search();else debounce=setTimeout(search,300);};document.getElementById('source-kind').onchange=()=>{document.getElementById('source-search').value='';search();};}
function lock(on,kind='inspect'){busy=on;operation=on?kind:'';for(const el of form.querySelectorAll('input,textarea,select,button'))el.disabled=on;for(const id of ['automation-save','automation-generate'])document.getElementById(id).disabled=on;refreshAction();}
async function save(data){if(!form.reportValidity())throw Error('Complete the required settings.');const result=await request('/ai-posts',data);current={...data,version:result.version};history.replaceState(null,'',result.edit_url);try{sessionStorage.removeItem(draftKey);}catch{}return result;}
function refreshAction(){
 const button=document.getElementById('automation-generate');delete button.dataset.reviewUrl;
 if(busy){button.textContent=operation==='generate'?'Generating…':operation==='save'||operation==='settings'?'Saving…':'Checking…';return;}
 if(activeRun?.status==='completed'){button.textContent='Generate Post';}
 else if(activeRun?.can_save)button.textContent='Finish saving';
 else if(activeRun?.can_step)button.textContent='Continue generation';
 else if(activeRun&&activeRun.status.endsWith('_requesting'))button.textContent='View progress';
 else if(activeRun)button.textContent='View issue';
 else button.textContent=sessionStorage.getItem(runKey)?'View progress':'Generate Post';
}
function recordDraft(run){
 if(!run.review_url)return;
 let box=document.getElementById('automation-history');
 if(!box){box=document.createElement('section');box.id='automation-history';box.className='brief-card';const h=document.createElement('h2');h.className='h6';h.textContent='Recent generations';box.append(h);(document.querySelector('.brief-summary')||progress.parentElement).append(box);}
 box.hidden=false;
 let row=[...box.querySelectorAll('[data-history-run]')].find(x=>x.dataset.historyRun===run.id);
 if(!row){row=document.createElement('div');row.dataset.historyRun=run.id;row.className='border-bottom py-3';box.insertBefore(row,box.children[1]||null);}
 row.replaceChildren();const time=document.createElement('span');time.className='field-note';time.textContent='Drafts ready';const link=document.createElement('a');link.className='btn-crm-secondary mt-2 w-100 justify-content-center';link.href=run.review_url;link.textContent='View drafts';row.append(time,link);
}
function renderRun(run){
 if(run.status==='completed'){recordDraft(run);sessionStorage.removeItem(runKey);}
 activeRun=run;refreshAction();progress.replaceChildren();const text=document.createElement('p');text.textContent=run.status==='completed'?'Drafts are ready for review.':run.status.replaceAll('_',' ')+(run.total_images?' · '+run.completed_images+'/'+run.total_images+' images':'');progress.append(text);
 if(run.status!=='completed'&&!run.can_step&&!run.can_save&&!run.status.endsWith('_requesting')){
  const details=document.createElement('details'),summary=document.createElement('summary'),warning=document.createElement('p');details.id='automation-issue';summary.textContent='Generation details';warning.className='field-note mt-2';warning.textContent='This request result could not be confirmed. It may already have been charged. No automatic retry was sent. Keep this record for review. Run: '+run.id+'. '+(run.error||'');details.append(summary,warning);progress.append(details);
 }
}
function pendingRequest(){const raw=sessionStorage.getItem(runKey);if(!raw)return null;try{return JSON.parse(raw);}catch{return {id:raw};}}
async function inspectRun(id){const run=await request('/runs/'+encodeURIComponent(id));renderRun(run);message(run.status==='completed'?'Ready. Nothing has been published.':run.status.endsWith('_requesting')?'Checking the existing request only. Nothing was resent.':run.can_step?'Generation can be continued using the button below.':'Generation needs review. Nothing was resent.');return run;}

async function continueRun(id){let run=await request('/runs/'+id);renderRun(run);while(run.can_step){run=await request('/runs/'+id+'/step',{});renderRun(run);}if(run.status.endsWith('_requesting'))message('A provider request may still be running. Reopen this generation to check; it will not be resent.');else message(run.status==='completed'?'Ready. Nothing has been published.':'Generation paused.');}
form.onsubmit=async e=>{e.preventDefault();if(busy)return;try{const data=values();if(!form.reportValidity())return;lock(true,'settings');await save(data);if(activeRun?.status==='status_unconfirmed'&&!pendingRequest()){activeRun=null;progress.replaceChildren();}refreshAction();message('Automation saved. No generation was started.');}catch(e){message(e.message);}finally{lock(false);}};
document.getElementById('automation-generate').onclick=async()=>{
 if(busy)return;
 if(activeRun?.status==='completed'){activeRun=null;progress.replaceChildren();}
 try{
  const pending=pendingRequest();
  if(pending){lock(true,activeRun?.can_save?'save':activeRun?.can_step?'generate':'inspect');const id=pending.id||pending;
   if(activeRun?.can_save){const run=await request('/runs/'+encodeURIComponent(id)+'/step',{});renderRun(run);message(run.status==='completed'?'Ready. Nothing has been published.':'Local saving needs review. No automatic retry was sent.');}
   else if(activeRun?.can_step){await continueRun(id);}
   else {await inspectRun(id);const details=document.getElementById('automation-issue');if(details)details.open=true;}
   return;
  }
  if(activeRun){const details=document.getElementById('automation-issue');if(details)details.open=true;message('Review this issue and save corrected settings before generating. Nothing was sent.');return;}
  const data=values();if(!form.reportValidity())return;lock(true,'generate');document.getElementById('automation-generate').textContent='Generating…';await save(data);const id=crypto.randomUUID();sessionStorage.setItem(runKey,JSON.stringify({id,version:current.version}));message('Selecting source content and generating drafts…');const run=await request('/ai-posts/'+current.id+'/generate',{id,version:current.version});renderRun(run);await continueRun(id);
 }catch(e){message(e.message+' Existing generation state is retained.');activeRun=activeRun||{id:pendingRequest()?.id||'',status:'status_unconfirmed',can_step:false,error:'result_unconfirmed'};renderRun(activeRun);}
 finally{lock(false);refreshAction();}
};
for(const b of document.querySelectorAll('[data-resume-run]'))b.onclick=async()=>{if(busy)return;try{lock(true);const run=await inspectRun(b.dataset.resumeRun);if(run.status!=='completed')sessionStorage.setItem(runKey,JSON.stringify({id:run.id}));}catch(e){message(e.message);}finally{lock(false);refreshAction();}};
refreshAction();const initial=pendingRequest();if(initial){inspectRun(initial.id||initial).catch(e=>{renderRun({id:initial.id||initial,status:'status_unconfirmed',can_step:false,error:'result_unconfirmed'});message(e.message+' Nothing was resent.');});}
})();
