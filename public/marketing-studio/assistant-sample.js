'use strict';
(() => {
  const root=document.querySelector('[data-assistant-sample]');if(!root)return;
  const mode=root.dataset.mode,log=root.querySelector('[data-sample-log]'),form=root.querySelector('[data-sample-form]');
  const feedback=root.querySelector('[data-sample-feedback]');
  const key='jw-marketing-assistant-sample:'+new URL(location.href).searchParams.get('project');
  const labels={goal:'目的',audience:'受众',pain:'核心痛点',proof:'特点与依据',creative:'第二个创意的表达'};
  const initial={messages:[],values:{},visited:[]};let state;
  try{state=JSON.parse(sessionStorage.getItem(key))||structuredClone(initial);if(!Array.isArray(state.messages)||!state.values||!Array.isArray(state.visited))throw new Error();}catch{state=structuredClone(initial);}
  const baseline=new Map(Array.from(root.querySelectorAll('[data-sample-field]')).map(e=>[e.dataset.sampleField,e.textContent]));
  function save(){try{sessionStorage.setItem(key,JSON.stringify(state));}catch{feedback.textContent='当前浏览器未允许保存样本会话；本页仍可演示，不影响真实项目。';}}
  function el(tag,text,cls){const n=document.createElement(tag);if(text)n.textContent=text;if(cls)n.className=cls;return n;}
  function append(role,text,proposal=null){state.messages.push({id:crypto.randomUUID(),role,text,proposal,scope:mode,status:proposal?'pending':null});save();render();}
  function render(){
    log.replaceChildren();
    state.messages.forEach(m=>{
      const bubble=el('article',null,'ws-sample-message'+(m.role==='user'?' is-user':''));bubble.append(el('small',m.role==='user'?'你':m.role==='system'?'样本提示':'AI助手 · 演示'),el('div',m.text));
      if(m.proposal){
        const card=el('div',null,'ws-sample-proposal');card.append(el('strong','建议这样整理'));
        const dl=document.createElement('dl');Object.entries(m.proposal).forEach(([field,value])=>{dl.append(el('dt',labels[field]),el('dd',value));});card.append(dl);
        card.append(el('p',!(m.scope==='concepts'||m.proposal.creative)?'这是待你采用的建议，不是已核实的公司事实。':'只调整第二个方案的表达；Hook和其他两个方案保留，不制作视频。','ws-note'));
        if(m.status==='pending')for(const [action,text] of [['accept','采用这组建议'],['reject','先不采用']]){
          const b=el('button',text,'btn '+(action==='accept'?'ws-primary':'btn-light'));b.type='button';b.dataset.sampleDecision=action;b.dataset.proposal=m.id;card.append(b);
        }else card.append(el('small',m.status==='accepted'?'已在样本中采用':m.status==='rejected'?'未采用，原内容保留':'已有新的讨论建议'));
        bubble.append(card);
      }
      log.append(bubble);
    });
    root.querySelectorAll('[data-sample-status]').forEach(n=>{const carried=['offer','platform','tone'].includes(n.dataset.sampleStatus);n.textContent=carried?'已带入':n.dataset.sampleStatus==='creative'?'待选择':'待补充';n.classList.toggle('ws-pending',!carried);});
    for(const [field,value] of Object.entries(state.values)){
      root.querySelectorAll(`[data-sample-field="${field}"]`).forEach(n=>{n.textContent=value;});
      root.querySelectorAll(`[data-sample-status="${field}"]`).forEach(n=>{n.textContent='已采用';n.classList.remove('ws-pending');});
    }
    const progress=root.querySelector('[data-sample-progress]');if(progress)progress.textContent=`已整理 ${3+['goal','audience','pain','proof'].filter(k=>state.values[k]).length}/7`;
    const pending=[...state.messages].reverse().find(m=>m.status==='pending');
    if(pending)Object.keys(pending.proposal).forEach(field=>root.querySelectorAll(`[data-sample-status="${field}"]`).forEach(n=>{n.textContent='有新建议';n.classList.add('ws-pending');}));
    log.scrollTop=log.scrollHeight;
  }
  function propose(text,values){state.messages.forEach(m=>{if(m.status==='pending')m.status='superseded';});append('assistant',text,values);}
  function say(text){
    append('user',text);
    if(/影响|费用|收费/.test(text)){
      append('assistant',mode==='guided'?'这里仅讨论策划资料。采用建议后可以检查摘要；未来制作费用会单独确认，不会因为聊天开始制作。':'这次建议只调整第二个创意的叙述口吻，不改Hook，也不动其他方案。选定并修改分镜后，才会检查素材与制作费用。');return;
    }
    if(mode==='concepts'){
      if(/第二|亲切|聊天|建议|正式/.test(text))propose('我理解为：保留第二个方案介绍货柜选择的方向，改成老板与客户自然聊天的口吻。先保留原Hook。你可以采用，或继续告诉我想怎么调。',{creative:'像老板与客户自然聊天，先了解对方用途，再介绍适合考虑的货柜方向；原Hook保留。'});
      else append('assistant','你想调整故事表达、开场Hook，还是先比较三个方向？这个样本可先试“第二个故事更亲切”，不会执行制作。');
      return;
    }
    if(/特点|卖点|封闭|坚固/.test(text)){
      append('assistant','商品特点需要回到商家的真实资料核实。我们可以先保留“通用封闭式货柜”这一已带入的描述；库存、交期、独家优势和成功案例不能自行补写。你有哪些可以确认的资料？');return;
    }
    if(/第一次|初次/.test(text))propose('明白，先聚焦第一次选购货柜的人。下面把受众和可能的困惑一起整理；这是待确认的需求假设，采用后再补真实特点。',{audience:'第一次选购货柜的企业客户',pain:'不清楚不同货柜如何匹配自己的实际用途'});
    else if(/建议|不知道|决定|询价/.test(text))propose('根据这个货柜示例，我建议以“获得询价”为主要目标。先让有需求的人理解用途，再以咨询结束；视频主体不是教人填写询价表。受众和痛点可以先这样假设，你觉得符合实际吗？',{goal:'获得询价 · CTA：联系我们咨询合适方案',audience:'第一次选购货柜的企业客户',pain:'不清楚哪种货柜适合自己的储物或货物用途'});
    else append('assistant','这句话我先作为讨论保留，不直接写进摘要。你是在补充客户需求，还是希望我提供建议？可以直接说“请给我建议”，或说明你最想调整哪一点。');
  }
  form.addEventListener('submit',e=>{e.preventDefault();const input=form.elements.message,text=input.value.trim();if(!text)return;input.value='';say(text);});
  root.addEventListener('click',e=>{
    const b=e.target.closest('button');if(!b)return;
    if(b.dataset.sampleSay)say(b.dataset.sampleSay);
    if(b.hasAttribute('data-sample-open'))bootstrap.Offcanvas.getOrCreateInstance(root.querySelector('#assistantSampleDrawer')).show();
    if(b.dataset.sampleDecision){
      const m=state.messages.find(x=>x.id===b.dataset.proposal);if(!m||m.status!=='pending')return;
      m.status=b.dataset.sampleDecision==='accept'?'accepted':'rejected';
      if(m.status==='accepted'){
        Object.assign(state.values,m.proposal);append('user','采用这组建议');
        append('assistant',!m.proposal.creative?'已在样本摘要中整理好。接下来我们补充可核实的商品特点；如果不确定，我会说明哪些能建议、哪些必须由你确认。':'已在样本中调整第二个创意的表达，其他方案和原Hook保留。要继续比较，还是讨论选定后的分镜？');
      }else append('user','先不采用，我再想一想');
      save();render();
    }
    if(b.hasAttribute('data-sample-review')){
      const missing=['goal','audience','pain','proof'].filter(k=>!state.values[k]);
      append('assistant',missing.length?'还有待补充的内容：'+missing.map(k=>labels[k]).join('、')+'。我们可以继续讨论；这个样本不会跳到真实制作。':'样本资料已齐备。正式流程会在这里统一确认摘要；本样本不保存、不进入制作。');
    }
    if(b.hasAttribute('data-sample-reset')){
      state=structuredClone(initial);for(const [field,value]of baseline)root.querySelectorAll(`[data-sample-field="${field}"]`).forEach(n=>{n.textContent=value;});
      root.querySelectorAll('[data-sample-status]').forEach(n=>{n.textContent=['offer','platform','tone'].includes(n.dataset.sampleStatus)?'已带入':'待补充';});
      start();
    }
  });
  function start(){
    if(!state.visited.includes(mode)){
      state.visited.push(mode);
      append('assistant',mode==='guided'?'商品资料和制作偏好已经整理好。我们会一起补齐右侧摘要；不确定时可以直接让我建议。你希望这支视频主要带来品牌认识，还是吸引有需求的人来询价？':'继续这个项目的讨论。前面确认的目标和受众会保留；现在可以比较创意，或用日常语言调整其中一个方案。');
    }else render();save();
  }
  root.querySelector('#assistantSampleDrawer')?.addEventListener('shown.bs.offcanvas',()=>{log.scrollTop=log.scrollHeight;});
  start();
})();
