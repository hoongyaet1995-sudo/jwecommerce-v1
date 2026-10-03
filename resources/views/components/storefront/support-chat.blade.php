<div id="storefront-support-widget">
<style>
#storefront-support-widget{position:fixed;inset:0;z-index:55;pointer-events:none;font-family:inherit;color:#242329}#storefront-support-widget .launcher,#storefront-support-widget .panel{pointer-events:auto}
#storefront-support-widget *{box-sizing:border-box}#storefront-support-widget button,#storefront-support-widget textarea{font:inherit}#storefront-support-widget button{cursor:pointer}#storefront-support-widget button:focus-visible{outline:3px solid #a99aff;outline-offset:3px}#storefront-support-widget svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}#storefront-support-widget .launcher{position:fixed;right:24px;bottom:24px;display:flex;align-items:center;gap:10px;background:#6549e9;color:white;border:0;border-radius:30px;padding:17px 21px;box-shadow:0 8px 30px #4e31bc55;z-index:3;font-weight:600;font-size:14px;transition:transform .2s}#storefront-support-widget .launcher:hover{transform:translateY(-3px)}#storefront-support-widget .launcher.round{width:62px;height:62px;padding:0;justify-content:center}#storefront-support-widget .launcher.round span{display:none}#storefront-support-widget .panel{--bg:#fff;--ink:#242329;--muted:#797480;--line:#eeeaf2;--soft:#f7f5fb;position:fixed;right:24px;bottom:24px;width:390px;height:min(640px,calc(100dvh - 48px));background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:22px;box-shadow:0 20px 80px #20183d33;z-index:5;display:flex;flex-direction:column;overflow:hidden}html.dark #storefront-support-widget .panel{--bg:#17161c;--ink:#f2eff7;--muted:#a6a0b0;--line:#302c38;--soft:#211e29}#storefront-support-widget .panel[hidden],#storefront-support-widget .launcher[hidden]{display:none}#storefront-support-widget .head{display:flex;align-items:center;gap:11px;padding:20px;border-bottom:1px solid var(--line)}#storefront-support-widget .brand{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#eeeaff;color:#6549e9}#storefront-support-widget .bot-identity{min-width:0;flex:1;overflow-wrap:anywhere}#storefront-support-widget .brand{flex-shrink:0}#storefront-support-widget .brand svg{display:block}#storefront-support-widget .chat-language svg{width:15px;height:15px}#storefront-support-widget .chat-language select{font:inherit;font-size:11px;width:65px;border:0;border-radius:6px;background:var(--bg);color:var(--ink);padding:5px 0}#storefront-support-widget .actions{flex-shrink:0}#storefront-support-widget .head strong{font-size:15px}#storefront-support-widget .head small{display:block;font-size:12px;margin-top:5px;color:var(--muted)}#storefront-support-widget .chat-language{display:flex;align-items:center;gap:3px;color:var(--muted)}#storefront-support-widget .chat-language svg{width:15px;height:15px}#storefront-support-widget .chat-language select{font:inherit;font-size:11px;width:65px;border:0;border-radius:6px;background:var(--bg);color:var(--ink);padding:5px 0}#storefront-support-widget .actions{display:flex;margin-left:auto;gap:3px}#storefront-support-widget .icon{display:grid;place-items:center;border:0;border-radius:9px;background:transparent;color:var(--muted);width:34px;height:34px}#storefront-support-widget .icon:hover{background:var(--soft)}#storefront-support-widget .icon svg{width:19px;height:19px}#storefront-support-widget .stream{flex:1;overflow:auto;overscroll-behavior:contain;padding:24px 20px}#storefront-support-widget .intro h1{font-size:26px;letter-spacing:-.7px;line-height:1.2;margin:5px 0 12px}#storefront-support-widget .intro p{font-size:14px;line-height:1.65;color:var(--muted);margin:0 0 22px}#storefront-support-widget .topics{display:grid;gap:8px;margin-bottom:24px}#storefront-support-widget .topic{background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:12px;padding:13px 14px;text-align:left;display:flex;justify-content:space-between;align-items:center;font-size:13px}#storefront-support-widget .topic:hover{border-color:#9c87ef;background:var(--soft)}#storefront-support-widget .topic svg{width:16px;height:16px;color:var(--muted)}#storefront-support-widget .message{max-width:88%;padding:12px 15px;line-height:1.6;font-size:13px;border-radius:16px 16px 16px 4px;background:var(--bg);border:1px solid #e7e1f2;box-shadow:0 2px 8px #24183d08;margin:14px 0 5px;white-space:pre-wrap;overflow-wrap:anywhere}#storefront-support-widget .message.user{margin-left:auto;border-color:transparent;background:#6549e9;color:#fff;border-radius:16px 16px 4px 16px}html.dark #storefront-support-widget .message:not(.user){background:#2b2538;border-color:#443852;box-shadow:0 2px 8px #00000012}#storefront-support-widget .meta{font-size:10px;color:var(--muted);margin:6px 0 15px}#storefront-support-widget .meta.user{text-align:right}#storefront-support-widget .composer{border-top:1px solid var(--line);padding:16px 18px max(15px,env(safe-area-inset-bottom));background:var(--bg)}#storefront-support-widget form{display:flex;align-items:flex-end;gap:8px;border:1px solid var(--line);background:var(--soft);border-radius:14px;padding:9px}#storefront-support-widget textarea{width:100%;resize:none;max-height:100px;height:43px;border:0;background:none;color:var(--ink);outline:none;padding:10px 4px;font-size:14px}#storefront-support-widget .send{border:0;border-radius:10px;background:#6549e9;color:#fff;display:grid;place-items:center;width:38px;height:38px;flex-shrink:0}#storefront-support-widget .send:disabled{opacity:.35;cursor:default}#storefront-support-widget .send svg{width:18px;height:18px}#storefront-support-widget .foot{text-align:center;font-size:10px;color:var(--muted);margin-top:11px}#storefront-support-widget .demo{font-size:12px;line-height:1.5;color:var(--ink);padding:10px 16px;background:var(--soft)}#storefront-support-widget .demo[hidden]{display:none}#storefront-support-widget .composer form:focus-within{border-color:#9c87ef;box-shadow:0 0 0 3px #8067ea18}#storefront-support-widget textarea:focus-visible{outline:none}#storefront-support-widget #sf-support-stream{margin-top:12px;border-top:1px solid var(--line);padding:18px 16px}#storefront-support-widget #sf-support-stream .message:first-child{margin-top:0}
@media(max-width:600px){#storefront-support-widget .launcher{right:18px;bottom:calc(18px + env(safe-area-inset-bottom))}#storefront-support-widget .panel{inset:0;width:100%;height:100dvh;border:0;border-radius:0}#storefront-support-widget .head{padding-top:max(18px,env(safe-area-inset-top))}#storefront-support-widget .stream{padding:26px 20px}#storefront-support-widget .intro h1{font-size:28px}}
@media(prefers-reduced-motion:reduce){#storefront-support-widget .launcher{transition:none}}
#storefront-support-widget .intro h1{font-weight:700}
@media(max-width:600px){#storefront-support-widget .panel{width:100vw}#storefront-support-widget textarea{font-size:16px}}

#storefront-support-widget .faq-question svg{transform:rotate(90deg);transition:transform .18s ease;flex-shrink:0}
#storefront-support-widget .faq-question[aria-expanded="true"] svg{transform:rotate(-90deg)}
#storefront-support-widget .faq-question[aria-expanded="true"]{border-color:#8b75ed;background:var(--soft);color:var(--ink)}
#storefront-support-widget .faq-answer[hidden]{display:none}
#storefront-support-widget .faq-answer{white-space:pre-wrap;line-height:1.65}
#storefront-support-widget .faq-tabs{margin:12px 16px 0;background:var(--soft);border-radius:10px;padding:4px;display:flex;gap:4px;flex-shrink:0}
#storefront-support-widget .faq-tabs button{flex:1;padding:9px 6px;border:0;border-radius:8px;background:transparent;color:var(--muted);font-size:12px;font-weight:600;cursor:pointer}
#storefront-support-widget .faq-tabs button[aria-selected="true"]{background:var(--bg);color:var(--ink);box-shadow:0 1px 4px #0002}
#storefront-support-widget .faq-tabs span{font-size:10px;background:var(--soft);padding:2px 5px;border-radius:8px}
#storefront-support-widget #faq-tools{padding:12px 16px;border-bottom:1px solid var(--line);flex-shrink:0}
#storefront-support-widget .faq-search{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--line);background:var(--soft);border-radius:10px;color:var(--muted)}
#storefront-support-widget .faq-search input{width:100%;background:transparent;border:0;outline:0;color:var(--ink);font:inherit;font-size:12px;min-width:0}
#storefront-support-widget .faq-categories{display:flex;gap:6px;overflow-x:auto;padding:10px 0 3px}
#storefront-support-widget .faq-categories button{flex-shrink:0;white-space:nowrap;border:0;border-radius:7px;padding:6px 9px;background:var(--soft);color:var(--muted);font-size:11px;cursor:pointer}
#storefront-support-widget .faq-categories button[aria-selected="true"]{background:#6549e9;color:#fff}
#storefront-support-widget .stream{padding:16px;min-height:0;background:var(--soft)}
#storefront-support-widget .faq-list{display:flex;flex-direction:column;gap:10px}
#storefront-support-widget .faq-row{width:100%;background:var(--bg);border:1px solid var(--line);border-radius:13px;overflow:hidden;box-shadow:0 1px 2px #00000008}
#storefront-support-widget .faq-question{width:100%;min-height:68px;display:flex;align-items:center;gap:10px;border:0;border-radius:0;background:transparent;color:var(--ink);padding:12px;text-align:left;cursor:pointer;font-size:12px;line-height:1.4}
#storefront-support-widget .faq-question .faq-label{flex:1;min-width:0}
#storefront-support-widget .faq-question .faq-label small{display:block;color:var(--muted);font-size:10px;margin-top:4px}
#storefront-support-widget .faq-question .faq-glyph{background:var(--soft);color:#6549e9;border-radius:8px;width:28px;height:28px;display:grid;place-items:center;flex-shrink:0}
#storefront-support-widget .faq-question svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.5}
#storefront-support-widget .faq-answer{padding:16px;color:var(--muted);font-size:12px;line-height:1.65}
#storefront-support-widget .composer[hidden],#storefront-support-widget #faq-tools[hidden]{display:none}


#storefront-support-widget .faq-empty{display:flex;flex-direction:column;align-items:center;text-align:center;padding:38px 22px 30px;margin:12px 0;background:var(--bg);border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 26px #24232905}
#storefront-support-widget .faq-empty-art{width:104px;height:104px;border-radius:50%;background:var(--soft);display:grid;place-items:center;position:relative;margin-bottom:24px;color:#8067ea}
#storefront-support-widget .faq-empty-art:before{content:'';position:absolute;inset:9px;border:1px dashed #8067ea35;border-radius:50%}
#storefront-support-widget .faq-empty-art svg{width:47px;height:47px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
#storefront-support-widget .faq-empty h2{color:var(--ink);font-size:20px;line-height:1.3;letter-spacing:-.45px;margin:0 0 12px;font-weight:700}
#storefront-support-widget .faq-empty p{color:var(--muted);font-size:13px;line-height:1.7;margin:0;max-width:270px}
#storefront-support-widget .faq-empty-primary{width:100%;margin-top:25px;padding:13px 16px;border:0;border-radius:11px;background:#6549e9;color:#fff;font-size:12px;font-weight:600;cursor:pointer;display:flex;justify-content:center;align-items:center;gap:8px;box-shadow:0 4px 12px #6549e924}
#storefront-support-widget .faq-empty-primary:hover{background:#593dd5}
#storefront-support-widget .faq-empty-secondary{margin-top:14px;background:transparent;border:0;color:var(--muted);font-size:12px;cursor:pointer;padding:7px}
#storefront-support-widget .faq-empty-primary:focus-visible,#storefront-support-widget .faq-empty-secondary:focus-visible{outline:2px solid #8067ea;outline-offset:3px}
@media(max-width:600px){#storefront-support-widget .faq-empty{margin:18px 0;padding:42px 24px 32px}}
#storefront-support-widget button,#storefront-support-widget input,#storefront-support-widget textarea{font-family:inherit}
</style>


<svg style="display:none" aria-hidden="true"><symbol id="sf-support-chat" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2v-10A8.5 8.5 0 0 1 10.5 3H17M7 10h7M7 14h5"/><path d="m20 2 .8 2.2L23 5l-2.2.8L20 8l-.8-2.2L17 5l2.2-.8Z"/></symbol><symbol id="sf-support-bubble" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2v-10A8.5 8.5 0 0 1 10.5 3H13a8 8 0 0 1 8 8.5Z"/><path d="M7 10h10M7 14h6"/></symbol><symbol id="sf-support-care" viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1M4 12H2v6h4v-6Zm16 0h2v6h-4v-6ZM20 18v2h-7"/></symbol><symbol id="sf-support-arrow" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol></svg>

<button class="launcher" id="sf-support-launcher" aria-label="Open support chat" aria-expanded="false" aria-controls="sf-support-panel"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#sf-support-chat"/></svg><span>How can we help?</span></button>
<section class="panel" id="sf-support-panel" role="dialog" aria-label="JW Studio support chat" hidden>
<header class="head"><div class="brand"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#sf-support-chat"/></svg></div><div class="bot-identity"><strong id="sf-bot-name">JW Studio Support</strong><small id="sf-bot-role">Let’s find what you need</small></div><div class="actions"><label class="chat-language"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/></svg><select id="sf-support-language" aria-label="Reply language"><option value="en">English</option><option value="ms">Malay</option><option value="zh">中文</option></select></label><button class="icon" id="sf-support-close" aria-label="Close support chat"><svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div></header>
<div class="faq-tabs" role="tablist" aria-label="Support sections"><button id="sample-chat-tab" role="tab" aria-selected="false">✦ AI Chat</button><button id="sample-faq-tab" role="tab" aria-selected="true">ⓘ FAQ Guide <span id="faq-total"></span></button></div>
<div id="faq-tools"><div class="faq-search"><span aria-hidden="true">⌕</span><input id="faq-search" aria-label="Search FAQ" placeholder="Search FAQ: shipping, returns, payment…" type="search"></div><div class="faq-categories" id="faq-categories" role="tablist" aria-label="FAQ categories"></div></div>
<div class="demo" role="status" aria-live="polite" hidden></div><div class="stream" id="sf-support-stream" role="log" aria-live="polite" hidden></div><div class="stream" id="sf-faq-stream" aria-live="polite"></div><footer class="composer"><form id="sf-support-form"><textarea id="sf-support-input" aria-label="Your message" placeholder="Write your message…" maxlength="2000"></textarea><button class="send" id="sf-support-send" aria-label="Send message" disabled><svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg></button></form><div class="foot">Chat with JW Studio Support</div></footer></section>
<script>
(()=>{const root=document.querySelector("#storefront-support-widget");

const panel=root.querySelector('#sf-support-panel'),launcher=root.querySelector('#sf-support-launcher'),input=root.querySelector('#sf-support-input'),send=root.querySelector('#sf-support-send'),stream=root.querySelector('#sf-support-stream');
let previousOverflow=null;
function syncBackgroundScroll(){const lock=!panel.hidden&&matchMedia('(max-width:600px)').matches;if(lock&&previousOverflow===null){previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden'}else if(!lock&&previousOverflow!==null){document.body.style.overflow=previousOverflow;previousOverflow=null}}
function openChat(){panel.hidden=false;launcher.hidden=true;launcher.setAttribute('aria-expanded','true');syncBackgroundScroll();root.querySelector('#sf-support-close').focus()}
function closeChat(){panel.hidden=true;launcher.hidden=false;launcher.setAttribute('aria-expanded','false');syncBackgroundScroll();launcher.focus()}
window.addEventListener('resize',syncBackgroundScroll);
launcher.onclick=openChat;root.querySelector('#sf-support-close').onclick=closeChat;
const languageSelect=root.querySelector('#sf-support-language');let selectedLanguage=localStorage.getItem('sf-support-language');if(!['en','ms','zh'].includes(selectedLanguage))selectedLanguage=null;languageSelect.value=selectedLanguage||'en';languageSelect.onchange=()=>{selectedLanguage=languageSelect.value;localStorage.setItem('sf-support-language',selectedLanguage)};
let sending=false, loaded=false, timer=null, retry=null, version=0;
const status=root.querySelector('.demo');
async function request(method='GET',data){
 const response=await fetch(method==='GET'?'/support/chat':'/support/chat/messages',{method,credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},body:data?JSON.stringify({...data,language:languageSelect.value}):undefined});
 const payload=await response.json().catch(()=>null);
 if(!response.ok||!payload)throw new Error(payload?.message||'Unable to connect. Please try again.');
 return payload.conversation;
}
function render(conversation){
 const nearBottom=stream.scrollHeight-stream.scrollTop-stream.clientHeight<80;
 const previous=[...stream.querySelectorAll('.message')].at(-1)?.dataset.id;
 stream.querySelectorAll('.message,.meta').forEach(node=>node.remove());
 for(const message of conversation?.messages||[]){
  const user=message.role==='customer',msg=document.createElement('div'),meta=document.createElement('div');
  msg.className='message'+(user?' user':'');msg.dataset.id=message.id;msg.textContent=message.content;
  meta.className='meta'+(user?' user':'');meta.textContent=(user?'You':(message.role==='ai'?root.querySelector('#sf-bot-name').textContent:'Support team'))+' · '+new Date(message.createdAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  stream.append(msg,meta);
 }
 if(conversation?.messages?.length&&(nearBottom||previous!==conversation.messages.at(-1)?.id))stream.scrollTop=stream.scrollHeight;
 status.textContent='';status.hidden=true;
}
async function refresh(){
 const revision=version;
 try{const conversation=await request();loaded=true;if(!sending&&revision===version)render(conversation)}catch(error){status.textContent=error.message;status.hidden=false}
}
async function poll(){if(panel.hidden)return;if(!document.hidden&&!sending)await refresh();if(!panel.hidden)timer=setTimeout(poll,4000)}
const originalOpen=openChat,originalClose=closeChat;
launcher.onclick=()=>{originalOpen();clearTimeout(timer);void poll()};
root.querySelector('#sf-support-close').onclick=()=>{originalClose();clearTimeout(timer)};
async function submit(text){
 text=text.trim();if(!text||sending)return;
 sending=true;send.disabled=true;input.disabled=true;version++;
 if(!retry||retry.content!==text)retry={content:text,id:crypto.randomUUID()};
 try{
  if(!loaded){await request();loaded=true}
  const conversation=await request('POST',{content:text,client_message_id:retry.id});render(conversation);input.value='';retry=null;stream.scrollTop=stream.scrollHeight;
 }catch(error){status.textContent=error.message;status.hidden=false;input.value=text}
 finally{version++;sending=false;input.disabled=false;send.disabled=!input.value.trim()}
}
input.oninput=()=>send.disabled=sending||!input.value.trim();root.querySelector('#sf-support-form').onsubmit=e=>{e.preventDefault();void submit(input.value)};input.onkeydown=e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();void submit(input.value)}};
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)closeChat();if(e.key==='Tab'&&!panel.hidden){const nodes=[...panel.querySelectorAll('button,textarea')].filter(n=>!n.disabled);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus()}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus()}}});

const faqStream=root.querySelector('#sf-faq-stream');let groups=[];
const make=(tag,text,cls)=>{const el=document.createElement(tag);el.textContent=text;if(cls)el.className=cls;return el};
let activeCategory='all',activeTab='faq',expanded=null;
const tools=root.querySelector('#faq-tools'),composer=root.querySelector('.composer'),search=root.querySelector('#faq-search'),categoryBar=root.querySelector('#faq-categories');

function renderCategories(){categoryBar.replaceChildren();[{name:'All Topics'},...groups].forEach((group,index)=>{const id=index===0?'all':group.name;const btn=make('button',group.name);btn.type='button';btn.setAttribute('role','tab');btn.setAttribute('aria-selected',String(id===activeCategory));btn.onclick=()=>{activeCategory=id;expanded=null;renderCategories();renderFaq()};categoryBar.append(btn)})}
function emptyFaq(query){
 const card=make('div','','faq-empty'),art=make('div','','faq-empty-art');art.setAttribute('aria-hidden','true');
 art.innerHTML='<svg viewBox="0 0 48 48"><path d="M9 11h12c3 0 5 2 5 5v24c-2-3-5-4-9-4H9V11Zm30 0H27M39 11v12M15 19h5M15 25h5"/><path d="M30 28h11v10h-5l-5 4v-4h-1V28Z"/></svg>';
 card.append(art,make('h2',query?'Let’s find another answer':activeCategory==='all'?'A little help is always here':'Need help with this topic?'),make('p',query?'No answers match your search. Try a different keyword, or ask our support team.':activeCategory==='all'?'Our FAQ guide is being updated. In the meantime, our support team is here to help you.':'We don’t have an answer here just yet. Ask our support team and we’ll help you find what you need.'));
 const button=make('button',query?'Clear search':'Talk to our team','faq-empty-primary');button.type='button';button.onclick=query?()=>{search.value='';expanded=null;renderFaq();search.focus()}:()=>tab('chat');card.append(button);
 if(query||activeCategory!=='all'){const secondary=make('button',query?'Talk to our team':'Browse all topics','faq-empty-secondary');secondary.type='button';secondary.onclick=query?()=>tab('chat'):()=>{activeCategory='all';search.value='';renderCategories();renderFaq()};card.append(secondary)}
 return card;
}
function renderFaq(){faqStream.replaceChildren();const list=make('div','','faq-list');let total=0;const query=search.value.trim().toLowerCase();groups.forEach((group,gi)=>{if(activeCategory!=='all'&&activeCategory!==group.name)return;group.items.forEach(([question,answer],qi)=>{if(query&&!`${question} ${answer} ${group.name}`.toLowerCase().includes(query))return;total++;const id=`faq-${gi}-${qi}`;const row=make('div','','faq-row'),button=make('button','','faq-question'),label=make('span','','faq-label'),body=make('div',answer,'faq-answer');label.append(make('strong',question),make('small',group.name));button.append(make('span','ⓘ','faq-glyph'),label);const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.innerHTML='<use href="#sf-support-arrow"/>';button.append(svg);button.type='button';button.setAttribute('aria-expanded',String(expanded===id));button.setAttribute('aria-controls',id);body.id=id;body.hidden=expanded!==id;button.onclick=()=>{expanded=expanded===id?null:id;renderFaq()};row.append(button,body);list.append(row)})});if(!total)list.append(emptyFaq(query));faqStream.append(list)}
function tab(value){activeTab=value;root.querySelector('#sample-chat-tab').setAttribute('aria-selected',String(value==='chat'));root.querySelector('#sample-faq-tab').setAttribute('aria-selected',String(value==='faq'));tools.hidden=value!=='faq';composer.hidden=value==='faq';stream.hidden=value==='faq';faqStream.hidden=value!=='faq';status.hidden=value==='faq'||!status.textContent;if(value==='faq'){renderFaq()}else{input.focus()}}
search.oninput=()=>{expanded=null;renderFaq()};root.querySelector('#sample-chat-tab').onclick=()=>tab('chat');root.querySelector('#sample-faq-tab').onclick=()=>tab('faq');

async function loadFAQs(){
 try{const response=await fetch('/support/faq',{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)throw new Error();const data=await response.json();if(!selectedLanguage){const defaultLanguage=data.primaryLanguage==='auto_detect'?((navigator.language||'en').startsWith('zh')?'zh':(navigator.language||'en').startsWith('ms')?'ms':'en'):data.primaryLanguage;languageSelect.value=['en','ms','zh'].includes(defaultLanguage)?defaultLanguage:'en';}if(data.identity){root.querySelector('#sf-bot-name').textContent=data.identity.botName;root.querySelector('#sf-bot-role').textContent=data.identity.roleTitle;root.querySelector('.foot').textContent='Chat with '+data.identity.botName;}groups=data.categories.map(c=>({name:c.label,items:c.items.map(i=>[i.question,i.answer])}));root.querySelector('#faq-total').textContent=groups.reduce((sum,g)=>sum+g.items.length,0);if(activeCategory!=='all'&&!groups.some(g=>g.name===activeCategory))activeCategory='all';renderCategories();if(activeTab==='faq')renderFaq();}
 catch{faqStream.replaceChildren(make('p','Unable to load FAQs. Please try again.'),Object.assign(make('button','Retry','topic'),{onclick:loadFAQs}));}
}
setInterval(()=>{if(!panel.hidden&&activeTab==='faq')void loadFAQs()},5000);
renderCategories();tab('faq');void loadFAQs();launcher.addEventListener('click',loadFAQs);root.querySelector('#sample-faq-tab').addEventListener('click',loadFAQs);

})();
</script>
</div>
