document.addEventListener('DOMContentLoaded', () => {
 const root = document.querySelector('[data-material-workspace]');
 if (!root) return;
 const modalEl = root.querySelector('#materialWorkspaceWizard');
 const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
 const maxTask = Number(root.dataset.currentTask);
 const key = `marketing-material-wizard:${root.dataset.project}`;
 let stage = maxTask;
 const focusRepair = () => {
  if (stage !== 0) return;
  try { const position=sessionStorage.getItem(key+':repair-scroll');if(position!==null){sessionStorage.removeItem(key+':repair-scroll');modalEl.querySelector('.modal-body').scrollTop=Number(position);return;} } catch (_) {}
  root.querySelector('[data-repair-workflow]')?.scrollIntoView({block:'start'});
 };
 modalEl.addEventListener('shown.bs.modal',focusRepair);
 const remember = index => { try { sessionStorage.setItem(key, String(index)); } catch (_) {} };
 const show = index => {
  stage = Math.max(0, Math.min(maxTask, index));
  root.querySelectorAll('[data-mw-stage]').forEach(panel => { panel.hidden = Number(panel.dataset.mwStage) !== stage; });
  root.querySelectorAll('[data-mw-footer]').forEach(panel => { panel.hidden = Number(panel.dataset.mwFooter) !== stage; });
  root.querySelectorAll('[data-mw-label]').forEach(label => label.classList.toggle('active', Number(label.dataset.mwLabel) === stage));
  root.querySelector('[data-mw-back]').disabled = stage === 0;
  modalEl.querySelector('.modal-body').scrollTop = 0;
  if (modalEl.classList.contains('show')) focusRepair();
 };
 root.querySelector('[data-mw-start]').addEventListener('click', () => { show(maxTask); modal.show(); });
 root.querySelector('[data-mw-back]').addEventListener('click', () => show(stage - 1));
 root.querySelectorAll('[data-mw-go]').forEach(button => button.addEventListener('click', () => show(Number(button.dataset.mwGo))));
 root.querySelectorAll('[data-mw-scene-go]').forEach(button => button.addEventListener('click', () => {
  const index = Number(button.dataset.mwSceneGo);
  root.querySelectorAll('[data-mw-scene]').forEach(panel => { panel.hidden = Number(panel.dataset.mwScene) !== index; });
  root.querySelectorAll('[data-mw-scene-go]').forEach(item => { const active = Number(item.dataset.mwSceneGo) === index; item.classList.toggle('ws-primary', active); item.classList.toggle('btn-light', !active); });
 }));
 root.querySelector('[data-mw-adjust]')?.addEventListener('click', () => { show(0); root.querySelector('[data-mw-adjust-panel]').hidden = false; modal.show(); });
 root.querySelector('[data-mw-replan]')?.addEventListener('click', () => { show(1); root.querySelector('[data-mw-replan-panel]').hidden = false; modal.show(); });
 root.addEventListener('click', event => {
  const button = event.target.closest('[data-creative-action]'); if (!button) return;
  const action = button.dataset.creativeAction;
  remember(action === 'material_plan' || action === 'confirm_director' ? 1 : 0);
 }, true);
 root.querySelector('[data-mw-transition-review]')?.addEventListener('submit',()=>remember(2));
 root.querySelectorAll('[data-mw-reference-form]').forEach(form => {
  form.addEventListener('submit', () => remember(1));
  const input = form.querySelector('input[type="file"]'); if (!input) return;
  const preview = form.querySelector('[data-mw-file-preview]');
  const status = form.querySelector('[data-mw-reference-status]');
  const drop = form.querySelector('[data-mw-drop]');
  let objectUrl;
  const clear = () => {
   if (objectUrl) URL.revokeObjectURL(objectUrl);
   objectUrl = null; input.value = ''; preview.hidden = true;
   preview.querySelector('img').removeAttribute('src');
  };
  const refresh = () => {
   const file = input.files[0]; if (!file) { clear(); return; }
   if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
    clear(); status.textContent = '请选择10MB以内的JPG、PNG或WebP图片。'; return;
   }
   if (objectUrl) URL.revokeObjectURL(objectUrl);
   objectUrl = URL.createObjectURL(file); preview.querySelector('img').src = objectUrl;
   preview.querySelector('[data-mw-file-name]').textContent = file.name; preview.hidden = false;
   form.querySelectorAll('input[name="source"]').forEach(radio => { radio.checked = false; });
   status.textContent = '将使用这张新上传的参考；点击保存后生效。';
  };
  input.addEventListener('change', refresh);
  form.querySelector('[data-mw-file-clear]').addEventListener('click', () => { clear(); status.textContent = '已移除待上传图片，请选择参考。'; });
  form.querySelectorAll('input[name="source"]').forEach(radio => radio.addEventListener('change', () => {
   clear(); status.textContent = '已选择已有参考；点击保存后生效。';
  }));
  ['dragenter','dragover'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.add('is-dragging'); }));
  ['dragleave','drop'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.remove('is-dragging'); }));
  drop.addEventListener('drop', event => { if (event.dataTransfer.files.length) { input.files = event.dataTransfer.files; refresh(); } });
 });
 show(maxTask);
 try {
  const pending = sessionStorage.getItem(key); sessionStorage.removeItem(key);
  if (pending !== null) { show(Number(pending)); modal.show(); }
 } catch (_) {}
 const imagePanel = root.querySelector('[data-image-workflow]');
 const repairPanel = root.querySelector('[data-repair-workflow]');
 if (repairPanel) {
  let repairBusy = false;
  const repairAction = async button => {
   if (repairBusy) return;
   const op = button.dataset.repairOp;
   const error = repairPanel.querySelector('[data-repair-error]'); error.hidden = true;
   if (['authorize','apply','resume-budget','abandon'].includes(op) && !repairPanel.querySelector(op==='abandon'&&repairPanel.querySelector('[data-repair-cancel-consent]')?'[data-repair-cancel-consent]':'[data-repair-consent]')?.checked) { error.textContent='请先确认上方修正范围、费用或采用说明。'; error.hidden=false; return; }
   repairBusy=true; root.querySelectorAll('[data-repair-op]').forEach(item=>{item.disabled=true;});
   if(op!=='advance'||!repairPanel.querySelector('[data-repair-status]').textContent)repairPanel.querySelector('[data-repair-status]').textContent=op==='plan'?'AI正在评估定向修正范围，不生成图片…':'正在处理当前修正任务，请勿重复提交…';
   try {
    const response=await fetch(repairPanel.dataset.url.replace(/plan$/,op),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('#ws-creative-form input[name="_token"]').value},body:JSON.stringify({version:Number(repairPanel.dataset.version),request_id:repairPanel.dataset.request,run:repairPanel.dataset.run||null,spend_stop_usd:Number(repairPanel.querySelector('[data-repair-budget]')?.value||2),consent:['authorize','apply','resume-budget','abandon'].includes(op)?1:null})});
    const data=await response.json(); if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'修正任务未完成。');
    if(data.run)repairPanel.dataset.run=data.run;
    if(data.status==='running'&&data.next_operation==='advance'){
     const progress=data.progress;
     repairPanel.querySelector('[data-repair-status]').textContent=`${progress.message}… 单帧通过 ${progress.passed}/${progress.total}；已返回用量估计 US$${Number(progress.spent_usd).toFixed(2)}。无需重复点击。`;
     root.querySelectorAll('[data-repair-op]').forEach(item=>{if(item.closest('[data-mw-footer]'))item.textContent='正在修正与复检…';});
     repairBusy=false;
     // Advance only after a confirmed response, never retry a failed/unknown POST.
     setTimeout(()=>repairAction({dataset:{repairOp:'advance'}}),1500);
     return;
    }
    try { sessionStorage.setItem(key+':repair-scroll',String(modalEl.querySelector('.modal-body').scrollTop)); } catch (_) {}
    remember(0); location.reload();
   } catch(failure) {error.textContent=failure.message;error.hidden=false;repairPanel.querySelector('[data-repair-status]').textContent='没有自动重试；重新读取页面可查看已保存任务。';root.querySelectorAll('[data-repair-op]').forEach(item=>{item.disabled=false;});repairBusy=false;}
  };
  root.querySelectorAll('[data-repair-op]').forEach(button=>button.addEventListener('click',()=>repairAction(button)));
  if(repairPanel.dataset.running==='1'){show(0);modal.show();setTimeout(()=>{const button=root.querySelector('[data-repair-op="advance"]');if(button)repairAction(button);},2500);}
 }
 if (imagePanel) {
  let imageBusy = false;
  const imageAction = async button => {
   if (imageBusy) return;
   const op = button.dataset.imageOp;
   const error = imagePanel.querySelector('[data-image-error]'); error.hidden = true;
   if (op === 'authorize' && !imagePanel.querySelector('[data-image-consent]')?.checked) { error.textContent = '请先确认本批素材计划和图片费用。'; error.hidden = false; return; }
   if (op === 'approve-batch' && !imagePanel.querySelector('[data-image-batch-consent]')?.checked) { error.textContent = '请先确认采用整套素材。'; error.hidden = false; return; }
   if (op === 'resume-budget' && !imagePanel.querySelector('[data-image-resume-consent]')?.checked) { error.textContent = '请先确认新的预算停止线。'; error.hidden = false; return; }
   if (op === 'abandon' && !imagePanel.querySelector('[data-image-abandon-consent]')?.checked) { error.textContent = '请先确认停止此批，记录会保留。'; error.hidden = false; return; }
   imageBusy = true; root.querySelectorAll('[data-image-op]').forEach(item => { item.disabled = true; });
   imagePanel.querySelector('[data-image-status]').textContent = op === 'quote' ? '正在核算本批图片…' : '正在处理，请勿重复提交…';
   try {
    const response = await fetch(imagePanel.dataset.url.replace(/quote$/, op), { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('#ws-creative-form input[name="_token"]').value}, body:JSON.stringify({version:Number(imagePanel.dataset.version),request_id:imagePanel.dataset.request,run:imagePanel.dataset.run||null,output:button.dataset.output||null,approve:button.dataset.approve ? Number(button.dataset.approve):null,facts:[...imagePanel.querySelectorAll('[data-fact-review]:checked')].map(item=>item.value),auto_review:imagePanel.querySelector('[data-image-auto]')?.checked?1:0,spend_stop_usd:Number(imagePanel.querySelector('[data-image-budget]')?.value||2),consent:['authorize','facts','approve-batch','resume-budget','abandon'].includes(op)?1:null,feedback:imagePanel.querySelector('[data-image-feedback]')?.value||''}) });
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'请求未完成，请重新读取任务记录。');
    remember(2); location.reload();
   } catch (failure) {
    error.textContent = failure.message; error.hidden = false;
    imagePanel.querySelector('[data-image-status]').textContent = '未自动重试；重新读取页面可查看已保存任务。';
    root.querySelectorAll('[data-image-op]').forEach(item => { item.disabled = false; }); imageBusy = false;
   }
  };
  root.querySelectorAll('[data-image-op]').forEach(button => button.addEventListener('click',()=>imageAction(button)));
  if (imagePanel.dataset.running === '1') { show(2); modal.show(); setTimeout(()=>{const button=root.querySelector('[data-image-op="advance"]');if(button)imageAction(button);},4000); }
 }

});
