document.addEventListener('click',e=>{const b=e.target.closest('[data-speed-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-speed-proposal',{detail:JSON.parse(b.dataset.speedProposal)}));}catch{}}});
'use strict';
(() => {
  document.addEventListener('click',e=>{const b=e.target.closest('[data-overlays-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-overlays-proposal',{detail:JSON.parse(b.dataset.overlaysProposal)}));}catch{}}});
  document.addEventListener('click',e=>{const b=e.target.closest('[data-emphasis-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-emphasis-proposal',{detail:JSON.parse(b.dataset.emphasisProposal)}));}catch{}}});
  document.addEventListener('click',e=>{const b=e.target.closest('[data-framing-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-framing-proposal',{detail:JSON.parse(b.dataset.framingProposal)}));}catch{}}});
  document.addEventListener('click',e=>{const b=e.target.closest('[data-information-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-information-proposal',{detail:JSON.parse(b.dataset.informationProposal)}));}catch{}}});
  document.addEventListener('click',e=>{const b=e.target.closest('[data-packaging-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-packaging-proposal',{detail:JSON.parse(b.dataset.packagingProposal)}));}catch{}}});
  document.addEventListener('click',e=>{const b=e.target.closest('[data-subtitle-proposal]');if(b){try{document.dispatchEvent(new CustomEvent('marketing-subtitle-proposal',{detail:JSON.parse(b.dataset.subtitleProposal)}));}catch{}}});
  const cfg = document.getElementById('ws-chat-config');
  if (!cfg) return;
  let busy = false;
  let pending=false,pollTimer=null,pollFailures=0;
  let unresolvedSend=null;
  const labels = {user:'你',assistant:'AI助手',system:'系统回执'};
  const fields = {offer:'推广内容',goal:'营销目标',audience:'受众',market:'市场',language:'语言',duration_seconds:'时长',pain_point:'核心痛点',differentiator:'卖点',tone:'调性',cta:'行动出口',destination:'目的地'};
  const status = text => document.querySelectorAll('[data-chat-status]').forEach(el => { el.textContent = text; });
  const node = (tag, text, cls) => { const el=document.createElement(tag); el.textContent=text; if(cls)el.className=cls; return el; };
  const lock = value => {
    busy=value||pending;
    document.querySelectorAll('[data-project-chat-form] button,[data-chat-decision],[data-chat-reload],[data-chat-older],[data-chat-reconcile],[data-planning-choice]').forEach(b=>{b.disabled=value||(b.closest('[data-project-chat-form]')&&cfg.dataset.chatReady!=='1');});
    document.querySelectorAll('[data-project-chat-form] button,[data-edit-adopt],[data-edit-reject]').forEach(b=>{b.disabled=busy||(b.hasAttribute('data-edit-adopt')&&(b.dataset.base!==cfg.dataset.film||b.dataset.hash!==cfg.dataset.filmHash))||(b.closest('[data-project-chat-form]')&&cfg.dataset.chatReady!=='1');});
  };
  async function request(url, body) {
    if(!body){const target=new URL(url,location.origin);target.searchParams.set('screen',cfg.dataset.screen);url=target.href;}
    const r=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.dataset.csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
    if(!r.ok){
      const error=await r.json().catch(()=>({}));
      throw new Error(r.status===409?'项目或消息状态已更新，请重新读取；没有重复调用。':r.status===419?'登录状态已过期，请保存输入后重新登录。':Object.values(error.errors||{}).flat()[0]||'暂时无法完成。请重新读取已保存消息；不会自动重发。');
    }
    return r.json();
  }
  function syncPlanning(data){
    if(!['guided','brief'].includes(cfg.dataset.screen)||Number(data.version)<Number(cfg.dataset.version||0))return;
    for(const [key,selector] of [['summary_html','[data-workspace-summary]'],['confirmation_html','[data-planning-confirmation]'],['planning_html','[data-planning-guide]'],['stepper_html','.ws-stepper']]){
      if(!data[key])continue;document.querySelectorAll(selector).forEach(el=>{const t=document.createElement('template');t.innerHTML=data[key];el.replaceWith(t.content);});
    }
    cfg.dataset.version=String(data.version);
  }
  function render(data, prepend=false) {
    if(!prepend)syncPlanning(data);
    if(!prepend)pending=data.messages.some(m=>m.status==='pending');
    if(unresolvedSend&&data.messages.some(m=>m.role==='user'&&m.request_id===unresolvedSend.id))unresolvedSend=null;
    document.querySelectorAll('[data-chat-messages]').forEach(box=>{
      if(!prepend){const preamble=box.querySelector('[data-chat-preamble]')?.cloneNode(true);box.replaceChildren();if(preamble)box.append(preamble);}
      const fragment=document.createDocumentFragment();
      data.messages.forEach(m=>{
        if(prepend&&box.querySelector(`[data-message-id="${CSS.escape(m.id)}"]`))return;
        const article=node('article','','ws-conversation-message'+(m.role==='user'?' is-user':''));
        article.dataset.messageId=m.id;article.dataset.sequence=m.sequence;
        article.append(node('small',labels[m.role]||'系统回执'),node('p',m.role==='assistant'?m.content.replace(/\*\*(.*?)\*\*/gs,'$1'):m.content));
        if(m.context?.overlays_proposal){const b=node('button','查看图片排版方案','btn btn-sm btn-light');b.type='button';b.dataset.overlaysProposal=JSON.stringify(m.context.overlays_proposal);article.append(b);}
        if(m.context?.emphasis_proposal){const b=node('button','查看强调方案','btn btn-sm btn-light');b.type='button';b.dataset.emphasisProposal=JSON.stringify(m.context.emphasis_proposal);article.append(b);}
        if(m.context?.framing_proposal){const b=node('button','查看构图方案','btn btn-sm btn-light');b.type='button';b.dataset.framingProposal=JSON.stringify(m.context.framing_proposal);article.append(b);}
        if(m.context?.information_proposal){const b=node('button','查看信息卡方案','btn btn-sm btn-light');b.type='button';b.dataset.informationProposal=JSON.stringify(m.context.information_proposal);article.append(b);}
        if(m.context?.subtitle_proposal){const b=node('button','查看字幕方案','btn btn-sm btn-light');b.type='button';b.dataset.subtitleProposal=JSON.stringify(m.context.subtitle_proposal);article.append(b);}
        if(m.context?.packaging_proposal){const b=node('button','查看音乐与片尾建议','btn btn-sm btn-light');b.type='button';b.dataset.packagingProposal=JSON.stringify(m.context.packaging_proposal);article.append(b);}
        if(m.status==='pending'){const stage=m.context?.stage;article.append(node('small',stage==='analysis'?`正在分析第${m.context.scene_number}段实际素材…`:stage==='proposal'?'正在整理安全剪辑方案…':'AI is thinking…'));const b=node('button',m.context?.kind==='editing'?'停止等待并核对（至少17分钟后）':'停止等待并核对（至少3分钟后）','btn btn-sm btn-light');b.type='button';b.dataset.chatReconcile='1';article.append(b);}
        if(m.proposal?.length){
          const proposal=node('div','','ws-chat-proposal');proposal.append(node('strong',m.context?.summary_sync==='draft'?'已同步摘要草稿':'待采用的资料修改'));
          m.proposal.forEach(c=>proposal.append(node('p',`${fields[c.field]||c.field}：${c.value}`)));
          proposal.append(node('p',m.context?.summary_sync==='draft'?'本轮已自动同步到摘要草稿；确认整体资料后用于策划，不会启动制作。':'采用后需重新确认资料，相关创意、分镜和报价需重新检查；不授权制作或费用。'));
          if(m.proposal_status==='pending')for(const [action,text] of [['accept','采用资料修改'],['reject','保留原内容']]){
            const b=node('button',text,'btn btn-sm '+(action==='accept'?'ws-primary':'btn-light'));b.type='button';b.dataset.chatDecision=action;b.dataset.message=m.id;proposal.append(b);
          }else proposal.append(node('small',m.context?.summary_sync==='draft'?'已自动同步，仍需确认整体资料':m.proposal_status==='accepted'?'已采用，仍需确认整体资料':'已保留原内容'));
          article.append(proposal);
        }
        if(m.edit_plan){const ep=m.edit_plan,box=node('div','','ws-chat-proposal ws-edit-plan');box.append(node('strong',`${ep.status==='accepted'?'已采用的剪辑方案':ep.status==='pending'?'待采用的剪辑方案':'历史剪辑方案'} · ${Number(ep.duration).toFixed(2)}秒`));ep.scenes.forEach((s,i)=>box.append(node('p',`第${i+1}段 · ${(s.in_frame/24).toFixed(2)}–${(s.out_frame/24).toFixed(2)}秒 / 原${(s.source_frames/24).toFixed(1)}秒\n${s.reason.replace(/(?:依据\s*)?(?:evidence_id\s*)?[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}/giu,'根据本段素材分析')}`)));box.append(node('p','仅同步裁剪现有音画；人物、背景、台词与原声保持。采用后本地生成新预览，原版保留。','small text-secondary'));
          if(ep.status==='pending'){for(const [action,title,url] of [['adopt','采用方案，生成新预览',ep.adopt_url],['reject','保留当前版本',ep.reject_url]]){const b=node('button',title,'btn btn-sm '+(action==='adopt'?'ws-primary':'btn-light'));b.type='button';b.dataset[action==='adopt'?'editAdopt':'editReject']=ep.id;b.dataset.url=url;b.dataset.base=ep.base_film_id;b.dataset.hash=ep.base_sha256;b.disabled=action==='adopt'&&(ep.base_film_id!==cfg.dataset.film||ep.base_sha256!==cfg.dataset.filmHash);box.append(b);}if(ep.base_film_id!==cfg.dataset.film){const a=node('a','切回此方案对应的成片','d-block small mt-2');const u=new URL(location.href);u.searchParams.set('screen','finished');u.searchParams.set('film',ep.base_film_id);a.href=u.href;box.append(a);}}
          else if(ep.film_id){const a=node('a','查看此方案的新预览','btn btn-sm btn-light');const u=new URL(location.href);u.searchParams.set('screen','finished');u.searchParams.set('film',ep.film_id);a.href=u.href;box.append(a);}else box.append(node('small',ep.status==='superseded'?'已被更新方案替代':'已保留当前版本'));article.append(box);
        }
        if(m.context?.handoff_url){const a=node('a','带着需求，返回分镜与素材','btn btn-sm btn-light');a.href=m.context.handoff_url;article.append(a);}
        if(m.context?.recovery_url){const f=node('form','','mt-2');f.method='post';f.action=m.context.recovery_url;f.dataset.editRecovery='1';for(const [name,value] of [['_token',cfg.dataset.csrf],['request_id',crypto.randomUUID()]]){const input=node('input','');input.type='hidden';input.name=name;input.value=value;f.append(input);}const label=node('label','','d-flex gap-2 small');const check=node('input','');check.type='checkbox';check.name='consent';check.value='1';check.required=true;label.append(check,node('span','我确认重新分析；未知的外部请求可能已计费，重新请求可能再次计费。原片和记录保留。'));const b=node('button','重新分析并给建议','btn btn-sm btn-light');b.type='submit';f.append(label,b);article.append(f);}
        fragment.append(article);
      });
      if(prepend)box.prepend(fragment);else{box.append(fragment);box.scrollTop=box.scrollHeight;}
    });
    document.querySelectorAll('[data-chat-older]').forEach(b=>{b.hidden=data.messages.length<50;});
    if(pending&&!pollTimer)pollTimer=setTimeout(poll,2500);
  }
  async function poll(){pollTimer=null;try{const data=await request(cfg.dataset.url);render(data);pollFailures=0;lock(false);if(!pending)status(data.messages.some(m=>m.edit_plan?.status==='pending')?'回复已保存。请审阅方案后再决定采用。':'回复已保存，请查看助手说明。');}catch(e){if(++pollFailures<5)pollTimer=setTimeout(poll,5000);else{pending=false;lock(false);status('暂时无法读取进度，任务记录保留；请重新读取，不要重复发送。');}}}
  function open(form){
    if(form?.dataset.inline==='0')bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('projectConversation')).show();
  }
  async function send(form){
    if(busy)return;
    if(cfg.dataset.chatReady!=='1'){status('AI调用暂未开启；文字保留为输入草稿，未发送、未收费。');return;}
    const input=form.elements.message,content=input.value.trim();if(!content)return;
    const identity=JSON.stringify([content,cfg.dataset.film||'',!!document.querySelector('[data-edit-head]')?.checked]);
    if(unresolvedSend&&unresolvedSend.identity!==identity){status('上一条发送状态待核对，请先重新读取，再发送不同的需求。');return;}
    if(!unresolvedSend)unresolvedSend={id:crypto.randomUUID(),identity};
    open(form);lock(true);status('正在保存消息并检查本项目的回复状态…');
    document.querySelectorAll('[data-chat-messages]').forEach(box=>{box.querySelector('[data-chat-empty]')?.remove();const draft=node('article','','ws-conversation-message is-user');draft.dataset.chatOptimistic='1';draft.append(node('small','你'),node('p',content));const thinking=node('article','','ws-conversation-message');thinking.dataset.chatThinking='1';thinking.setAttribute('role','status');thinking.append(node('small','AI助手'),node('p','AI is thinking…'));box.append(draft,thinking);box.scrollTop=box.scrollHeight;});
    input.value='';
    form.dataset.requestId=unresolvedSend.id;form.dataset.lastContent=content;
    try{
      await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
      const data=await request(cfg.dataset.url,{request_id:form.dataset.requestId,screen:cfg.dataset.screen,message:content,...(cfg.dataset.film?{film_id:cfg.dataset.film,allow_head_trim:!!document.querySelector('[data-edit-head]')?.checked}:{})});
      render(data);unresolvedSend=null;if(input.value.trim()===content)input.value='';const finished=document.getElementById('ws-change');if(finished?.value.trim()===content)finished.value='';delete form.dataset.lastContent;delete form.dataset.requestId;
      status(['guided','brief'].includes(cfg.dataset.screen)?'本轮已保存，右侧摘要已同步；仍需整体确认，未开始制作。':'已保存到本项目对话；未授权制作或发布。');
    }catch(e){status(e.message);document.querySelectorAll('[data-chat-thinking]').forEach(n=>n.remove());document.querySelectorAll('[data-chat-optimistic]').forEach(n=>{n.querySelector('small').textContent='你 · 发送状态待核对';delete n.dataset.chatOptimistic;});if(!input.value)input.value=content;}
    finally{lock(false);}
  }
  const choiceRequests=new Map();
  document.addEventListener('click',async event=>{
    const b=event.target.closest('[data-planning-choice]');if(!b||busy)return;
    if(unresolvedSend){status('上一条消息的结果待核对，请刷新页面查看已保存资料。');return;}
    const key=JSON.stringify([b.dataset.field,b.dataset.value,b.dataset.version]);if(!choiceRequests.has(key))choiceRequests.set(key,crypto.randomUUID());
    lock(true);status('正在保存选择…');
    try{const data=await request(cfg.dataset.url,{request_id:choiceRequests.get(key),screen:cfg.dataset.screen,version:Number(b.dataset.version),choice:{field:b.dataset.field,value:b.dataset.value}});render(data);choiceRequests.delete(key);status('已同步右侧摘要，可以继续回答下一问。');}catch(error){status(error.message);}finally{lock(false);}
  });
  document.querySelectorAll('[data-project-chat-form]').forEach(f=>f.addEventListener('submit',e=>{e.preventDefault();send(f);}));
  document.addEventListener('click',async e=>{
    const b=e.target.closest('[data-chat-reload],[data-chat-older],[data-chat-reconcile],[data-chat-decision]');if(!b||(busy&&!pending))return;
    lock(true);
    try{
      if(b.hasAttribute('data-chat-reconcile')){render(await request(cfg.dataset.url+'/reconcile',{}));status('已核对等待状态，没有重新调用AI。');}
      else if(b.hasAttribute('data-chat-decision')){
        const data=await request(`${cfg.dataset.url}/${b.dataset.message}/decision`,{decision:b.dataset.chatDecision,screen:cfg.dataset.screen});
        render(data);
        if(b.dataset.chatDecision==='accept'){
          if(data.confirmation_html)document.querySelectorAll('[data-planning-confirmation]').forEach(el=>{const temp=document.createElement('template');temp.innerHTML=data.confirmation_html;el.replaceWith(temp.content);});
          if(data.summary_html)document.querySelectorAll('[data-workspace-summary]').forEach(el=>{const temp=document.createElement('template');temp.innerHTML=data.summary_html;const summary=temp.content.querySelector('[data-workspace-summary]');if(summary)el.replaceWith(summary);});
          if(data.stepper_html){const temp=document.createElement('template');temp.innerHTML=data.stepper_html;document.querySelector('.ws-stepper')?.replaceWith(temp.content);}
          status('已采用资料修改。整体资料需重新确认，当前创意、分镜和报价需重新检查；未启动制作。');
        }else status('已保留原内容。');
      }else{
        const older=b.hasAttribute('data-chat-older');
        const seq=Number(b.closest('.ws-project-conversation').querySelector('[data-sequence]')?.dataset.sequence||0);
        render(await request(cfg.dataset.url+(older&&seq?'?before='+seq:'')),older);status('已读取保存记录，没有重新调用AI。');
      }
    }catch(err){status(err.message);}finally{lock(false);}
  });
  document.querySelectorAll('[data-chat-quick]').forEach(b=>b.addEventListener('click',()=>{const scope=b.closest('.ws-editing-assistant')||b.closest('.ws-card')||document;const input=scope.querySelector('[data-project-chat-form] input[name="message"]');if(input){input.value=b.dataset.chatQuick;input.focus();}}));
  const adoptionIds=new Map();
  document.addEventListener('click',async e=>{const b=e.target.closest('[data-edit-adopt],[data-edit-reject]');if(!b||busy||b.disabled)return;const adopting=b.hasAttribute('data-edit-adopt'),id=b.dataset.editAdopt||b.dataset.editReject;if(adopting&&(b.dataset.base!==cfg.dataset.film||b.dataset.hash!==cfg.dataset.filmHash)){status('请切回此方案对应的成片。');return;}lock(true);
   try{if(!adoptionIds.has(id))adoptionIds.set(id,crypto.randomUUID());const data=await request(b.dataset.url,adopting?{request_id:adoptionIds.get(id),base_film_id:cfg.dataset.film,base_sha256:cfg.dataset.filmHash}:{});if(adopting){location.assign(data.url);return;}render(await request(cfg.dataset.url));status('已保留当前版本。');}catch(error){status(error.message);}finally{lock(false);}
  });
  document.querySelector('[data-chat-finished]')?.addEventListener('click',()=>{
    const form=document.querySelector('#projectConversation [data-project-chat-form]');const text=document.getElementById('ws-change').value.trim();if(!text)return;
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('projectConversation')).show();form.elements.message.value=text;send(form);
  });
  let historyBusy=false;
  async function loadHistory(url,version){
    if(historyBusy||busy)return;historyBusy=true;
    const box=document.querySelector('[data-history-content]');
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('conceptHistory')).show();box.textContent='正在读取创意历史…';
    try{
      const response=await fetch(url,{method:version?'POST':'GET',credentials:'same-origin',headers:{Accept:'text/html',...(version?{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.dataset.csrf}:{})},...(version?{body:JSON.stringify({version:Number(version)})}:{})});
      if(!response.ok)throw new Error('项目状态已更新或历史暂不可用，请刷新页面后重开；不会调用AI。');
      box.innerHTML=await response.text();
    }catch(error){box.textContent=error.message;}finally{historyBusy=false;}
  }
  document.addEventListener('click',event=>{
    const button=event.target.closest('[data-concept-history],[data-history-page]');if(!button)return;
    loadHistory(button.dataset.conceptHistory||button.dataset.historyPage,button.dataset.version);
  });
  document.querySelectorAll('[data-creative-action]').forEach(button=>button.addEventListener('click',()=>{
    if(busy)return;
    const form=document.getElementById('ws-creative-form');if(!form)return;
    form.elements.action.value=button.dataset.creativeAction;
    form.elements.index.value=button.dataset.conceptIndex??'';
    form.elements.feedback.value=(button.dataset.feedbackTarget?document.querySelector(button.dataset.feedbackTarget):document.querySelector('[data-project-chat-form] input[name="message"]'))?.value.trim()||'';
    lock(true);document.querySelectorAll('[data-creative-action]').forEach(b=>b.disabled=true);
    button.textContent=button.dataset.creativeAction==='select'?'AI 正在编排导演稿与分镜…':['confirm_director','confirm_storyboard'].includes(button.dataset.creativeAction)?'正在保存确认…':'AI正在准备…';form.requestSubmit();
  }));
  document.querySelectorAll('[data-chat-messages]').forEach(box=>{box.scrollTop=box.scrollHeight;});
  if(cfg.dataset.screen==='finished'){pending=!!document.querySelector('[data-chat-reconcile],[data-planning-choice]');lock(false);if(pending)pollTimer=setTimeout(poll,2500);}
})();
