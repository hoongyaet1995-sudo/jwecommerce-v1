document.addEventListener('DOMContentLoaded', () => {
 const root = document.querySelector('[data-material-review-sample]');
 if (!root) return;
 const modalEl = root.querySelector('#materialReviewSample');
 const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
 const stages = [...root.querySelectorAll('[data-mr-stage]')];
 const next = root.querySelector('[data-mr-next]');
 const back = root.querySelector('[data-mr-back]');
 let stage = 0, completed = 0, previewUrl = null, hasFile = false;
 const pageLabels = ['审阅镜头与口播', '补充必要真实参考', '核对素材制备计划'];
 const pageCopy = ['按段看清画面与表达，确认后再检查需要哪些参考资料。','只收集分镜确实需要的真实依据，其余内容由AI准备。','参考与制备范围已在样本中检查，图片生成仍需单独确认费用。'];
 const update = () => {
  stages.forEach((panel, i) => { panel.hidden = i !== stage; });
  root.querySelectorAll('[data-mr-stage-label]').forEach((label, i) => label.classList.toggle('active', i === stage));
  back.disabled = stage === 0;
  next.innerHTML = ['确认镜头，检查素材 <i class="bi bi-arrow-right"></i>', '确认参考，查看制备计划 <i class="bi bi-arrow-right"></i>', '完成本次准备（演示） <i class="bi bi-check2"></i>'][stage];
  root.querySelector('[data-mr-error]').hidden = true;
  modalEl.querySelector('.modal-body').scrollTop = 0;
  root.querySelectorAll('[data-mr-check]').forEach((row, i) => {
   row.classList.toggle('done', i < completed); row.classList.toggle('current', i === completed);
   row.querySelector(':scope > i').className = `bi ${i < completed ? 'bi-check2' : i === completed ? 'bi-arrow-right' : 'bi-lock'}`;
  });
  root.querySelector('[data-mr-current-title]').textContent = pageLabels[completed];
  root.querySelector('[data-mr-current-copy]').textContent = pageCopy[completed];
  root.querySelector('[data-mr-start]').innerHTML = `${completed ? '继续审阅与准备' : '开始审阅与准备'} <i class="bi bi-arrow-right"></i>`;
 };
 root.querySelector('[data-mr-start]').addEventListener('click', () => { stage = completed; update(); modal.show(); });
 next.addEventListener('click', () => {
  if (stage === 1 && !hasFile && !root.querySelector('[data-mr-no-rear]').checked) { root.querySelector('[data-mr-error]').hidden = false; return; }
  if (stage === 2) { completed = 2; update(); modal.hide(); root.querySelector('[data-mr-current-title]').textContent = '等待图片制备费用确认'; root.querySelector('[data-mr-start]').innerHTML = '查看素材制备计划 <i class="bi bi-arrow-right"></i>'; return; }
  stage += 1; completed = Math.max(completed, stage); update();
 });
 back.addEventListener('click', () => { stage -= 1; update(); });
 root.querySelectorAll('[data-mr-scene-go]').forEach(button => button.addEventListener('click', () => {
  const index = Number(button.dataset.mrSceneGo);
  root.querySelectorAll('[data-mr-scene]').forEach(panel => { panel.hidden = Number(panel.dataset.mrScene) !== index; });
  root.querySelectorAll('[data-mr-scene-go]').forEach(item => { const active = Number(item.dataset.mrSceneGo) === index; item.classList.toggle('ws-primary', active); item.classList.toggle('btn-light', !active); });
 }));
 const openAdjustment = redesign => {
  stage = 0; update(); root.querySelector('[data-mr-adjust]').hidden = false;
  root.querySelector('#mr-adjustment').placeholder = redesign ? '演示：正式重新设计前会说明文字AI调用；本页不会调用。' : '例如：开场直接讲用途，语气亲切一点…'; modal.show();
 };
 root.querySelector('[data-mr-edit]').addEventListener('click', () => openAdjustment(false));
 root.querySelector('[data-mr-redesign]').addEventListener('click', () => openAdjustment(true));
 const preview = file => {
  if (!file || !['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) { root.querySelector('[data-mr-file-label]').textContent = '请选择10MB以内的JPG、PNG或WebP'; return; }
  if (previewUrl) URL.revokeObjectURL(previewUrl);
  previewUrl = URL.createObjectURL(file); hasFile = true;
  const image = root.querySelector('[data-mr-file-preview]'); image.src = previewUrl; image.hidden = false;
  root.querySelector('[data-mr-file-label]').textContent = file.name; root.querySelector('[data-mr-no-rear]').checked = false;
 };
 root.querySelector('#mr-reference-image').addEventListener('change', event => preview(event.target.files[0]));
 const drop = root.querySelector('.mr-upload');
 drop.addEventListener('dragover', event => event.preventDefault());
 drop.addEventListener('drop', event => { event.preventDefault(); preview(event.dataTransfer.files[0]); });
 root.querySelector('[data-mr-question]').addEventListener('click', () => {
  const answer = root.querySelector('[data-mr-answer]'); answer.hidden = false;
  answer.textContent = '演示回复：只有镜头需要真实老板或商品不可见细节时才需要你提供参考；虚拟人物与虚构场景由AI准备。正式助手将结合项目内容回应。';
 });
 window.addEventListener('beforeunload', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
 update();
});
