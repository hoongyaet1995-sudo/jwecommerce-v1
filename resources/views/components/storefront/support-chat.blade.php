<div id="storefront-support-widget">
<style>
#storefront-support-widget{position:fixed;inset:0;z-index:55;pointer-events:none;font-family:Inter,Arial,sans-serif;color:#242329}#storefront-support-widget .launcher,#storefront-support-widget .panel{pointer-events:auto}
#storefront-support-widget *{box-sizing:border-box}#storefront-support-widget button,#storefront-support-widget textarea{font:inherit}#storefront-support-widget button{cursor:pointer}#storefront-support-widget button:focus-visible,#storefront-support-widget textarea:focus-visible{outline:3px solid #a99aff;outline-offset:3px}#storefront-support-widget svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}#storefront-support-widget .launcher{position:fixed;right:24px;bottom:24px;display:flex;align-items:center;gap:10px;background:#6549e9;color:white;border:0;border-radius:30px;padding:17px 21px;box-shadow:0 8px 30px #4e31bc55;z-index:3;font-weight:600;font-size:14px;transition:transform .2s}#storefront-support-widget .launcher:hover{transform:translateY(-3px)}#storefront-support-widget .launcher.round{width:62px;height:62px;padding:0;justify-content:center}#storefront-support-widget .launcher.round span{display:none}#storefront-support-widget .panel{--bg:#fff;--ink:#242329;--muted:#797480;--line:#eeeaf2;--soft:#f7f5fb;position:fixed;right:24px;bottom:24px;width:390px;height:min(640px,calc(100dvh - 48px));background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:22px;box-shadow:0 20px 80px #20183d33;z-index:5;display:flex;flex-direction:column;overflow:hidden}#storefront-support-widget .panel.dark{--bg:#17161c;--ink:#f2eff7;--muted:#a6a0b0;--line:#302c38;--soft:#211e29}#storefront-support-widget .panel[hidden],#storefront-support-widget .launcher[hidden]{display:none}#storefront-support-widget .head{display:flex;align-items:center;gap:11px;padding:20px;border-bottom:1px solid var(--line)}#storefront-support-widget .brand{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#eeeaff;color:#6549e9}#storefront-support-widget .head strong{font-size:15px}#storefront-support-widget .head small{display:block;font-size:12px;margin-top:5px;color:var(--muted)}#storefront-support-widget .actions{display:flex;margin-left:auto;gap:3px}#storefront-support-widget .icon{display:grid;place-items:center;border:0;border-radius:9px;background:transparent;color:var(--muted);width:34px;height:34px}#storefront-support-widget .icon:hover{background:var(--soft)}#storefront-support-widget .icon svg{width:19px;height:19px}#storefront-support-widget .stream{flex:1;overflow:auto;overscroll-behavior:contain;padding:24px 20px}#storefront-support-widget .intro h1{font-size:26px;letter-spacing:-.7px;line-height:1.2;margin:5px 0 12px}#storefront-support-widget .intro p{font-size:14px;line-height:1.65;color:var(--muted);margin:0 0 22px}#storefront-support-widget .topics{display:grid;gap:8px;margin-bottom:24px}#storefront-support-widget .topic{background:var(--bg);color:var(--ink);border:1px solid var(--line);border-radius:12px;padding:13px 14px;text-align:left;display:flex;justify-content:space-between;align-items:center;font-size:13px}#storefront-support-widget .topic:hover{border-color:#9c87ef;background:var(--soft)}#storefront-support-widget .topic svg{width:16px;height:16px;color:var(--muted)}#storefront-support-widget .message{max-width:88%;padding:12px 15px;line-height:1.6;font-size:13px;border-radius:16px 16px 16px 4px;background:var(--soft);margin:14px 0 5px;white-space:pre-wrap;overflow-wrap:anywhere}#storefront-support-widget .message.user{margin-left:auto;background:#6549e9;color:#fff;border-radius:16px 16px 4px 16px}#storefront-support-widget .meta{font-size:10px;color:var(--muted);margin:6px 0 15px}#storefront-support-widget .meta.user{text-align:right}#storefront-support-widget .composer{border-top:1px solid var(--line);padding:16px 18px max(15px,env(safe-area-inset-bottom));background:var(--bg)}#storefront-support-widget form{display:flex;align-items:flex-end;gap:8px;border:1px solid var(--line);background:var(--soft);border-radius:14px;padding:9px}#storefront-support-widget textarea{width:100%;resize:none;max-height:100px;height:43px;border:0;background:none;color:var(--ink);outline:none;padding:10px 4px;font-size:14px}#storefront-support-widget .send{border:0;border-radius:10px;background:#6549e9;color:#fff;display:grid;place-items:center;width:38px;height:38px;flex-shrink:0}#storefront-support-widget .send:disabled{opacity:.35;cursor:default}#storefront-support-widget .send svg{width:18px;height:18px}#storefront-support-widget .foot{text-align:center;font-size:10px;color:var(--muted);margin-top:11px}#storefront-support-widget .demo{font-size:10px;letter-spacing:.2px;color:var(--muted);text-align:center;padding:10px;background:var(--soft)}
@media(max-width:600px){#storefront-support-widget .launcher{right:18px;bottom:calc(18px + env(safe-area-inset-bottom))}#storefront-support-widget .panel{inset:0;width:100%;height:100dvh;border:0;border-radius:0}#storefront-support-widget .head{padding-top:max(18px,env(safe-area-inset-top))}#storefront-support-widget .stream{padding:26px 20px}#storefront-support-widget .intro h1{font-size:28px}}
@media(prefers-reduced-motion:reduce){#storefront-support-widget .launcher{transition:none}}
#storefront-support-widget .intro h1{font-weight:700}
@media(max-width:600px){#storefront-support-widget .panel{width:100vw}#storefront-support-widget textarea{font-size:16px}}

</style>


<svg style="display:none" aria-hidden="true"><symbol id="sf-support-chat" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2v-10A8.5 8.5 0 0 1 10.5 3H17M7 10h7M7 14h5"/><path d="m20 2 .8 2.2L23 5l-2.2.8L20 8l-.8-2.2L17 5l2.2-.8Z"/></symbol><symbol id="sf-support-bubble" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2v-10A8.5 8.5 0 0 1 10.5 3H13a8 8 0 0 1 8 8.5Z"/><path d="M7 10h10M7 14h6"/></symbol><symbol id="sf-support-care" viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1M4 12H2v6h4v-6Zm16 0h2v6h-4v-6ZM20 18v2h-7"/></symbol><symbol id="sf-support-arrow" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol></svg>

<button class="launcher" id="sf-support-launcher" aria-label="Open support chat" aria-expanded="false" aria-controls="sf-support-panel"><svg><use href="#sf-support-chat"/></svg><span>How can we help?</span></button>
<section class="panel" id="sf-support-panel" role="dialog" aria-label="JW Studio support chat" hidden>
<header class="head"><div class="brand"><svg><use href="#sf-support-chat"/></svg></div><div><strong>JW Studio Support</strong><small>Let’s find what you need</small></div><div class="actions"><button class="icon" id="sf-support-theme" aria-label="Switch chat theme"><svg viewBox="0 0 24 24"><path d="M20 15A8 8 0 0 1 9 4a8 8 0 1 0 11 11Z"/></svg></button><button class="icon" id="sf-support-close" aria-label="Close support chat"><svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div></header>
<div class="demo" role="status" aria-live="polite">Messages go to our support team</div>
<div class="stream" id="sf-support-stream" role="log" aria-live="polite"><div class="intro"><h1>A little help,<br>right here.</h1><p>Have a question? Start with a topic below or leave us a message.</p></div><div class="topics"><button class="topic">Help with my order<svg><use href="#sf-support-arrow"/></svg></button><button class="topic">Delivery & returns<svg><use href="#sf-support-arrow"/></svg></button><button class="topic">Product questions<svg><use href="#sf-support-arrow"/></svg></button><button class="topic">Talk to the support team<svg><use href="#sf-support-arrow"/></svg></button></div></div>
<footer class="composer"><form id="sf-support-form"><textarea id="sf-support-input" aria-label="Your message" placeholder="Write your message…" maxlength="2000"></textarea><button class="send" id="sf-support-send" aria-label="Send message" disabled><svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg></button></form><div class="foot">Chat with JW Studio Support</div></footer></section>
<script>
(()=>{const root=document.querySelector("#storefront-support-widget");

const panel=root.querySelector('#sf-support-panel'),launcher=root.querySelector('#sf-support-launcher'),input=root.querySelector('#sf-support-input'),send=root.querySelector('#sf-support-send'),stream=root.querySelector('#sf-support-stream');
let previousOverflow=null;
function syncBackgroundScroll(){const lock=!panel.hidden&&matchMedia('(max-width:600px)').matches;if(lock&&previousOverflow===null){previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden'}else if(!lock&&previousOverflow!==null){document.body.style.overflow=previousOverflow;previousOverflow=null}}
function openChat(){panel.hidden=false;launcher.hidden=true;launcher.setAttribute('aria-expanded','true');syncBackgroundScroll();root.querySelector('#sf-support-close').focus()}
function closeChat(){panel.hidden=true;launcher.hidden=false;launcher.setAttribute('aria-expanded','false');syncBackgroundScroll();launcher.focus()}
window.addEventListener('resize',syncBackgroundScroll);
launcher.onclick=openChat;root.querySelector('#sf-support-close').onclick=closeChat;root.querySelector('#sf-support-theme').onclick=()=>panel.classList.toggle('dark');
let sending=false, loaded=false, timer=null, retry=null, version=0;
const status=root.querySelector('.demo');
async function request(method='GET',data){
 const response=await fetch(method==='GET'?'/support/chat':'/support/chat/messages',{method,credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},body:data?JSON.stringify(data):undefined});
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
  meta.className='meta'+(user?' user':'');meta.textContent=(user?'You':'Support team')+' · '+new Date(message.createdAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  stream.append(msg,meta);
 }
 if(conversation?.messages?.length&&(nearBottom||previous!==conversation.messages.at(-1)?.id))stream.scrollTop=stream.scrollHeight;
 status.textContent=conversation?.status==='resolved'?'Conversation resolved · Send a message to reopen':'Messages go to our support team';
}
async function refresh(){
 const revision=version;
 try{const conversation=await request();loaded=true;if(!sending&&revision===version)render(conversation)}catch(error){status.textContent=error.message}
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
 }catch(error){status.textContent=error.message;input.value=text}
 finally{version++;sending=false;input.disabled=false;send.disabled=!input.value.trim()}
}
input.oninput=()=>send.disabled=sending||!input.value.trim();root.querySelector('#sf-support-form').onsubmit=e=>{e.preventDefault();void submit(input.value)};input.onkeydown=e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();void submit(input.value)}};root.querySelectorAll('.topic').forEach(b=>b.onclick=()=>{void submit(b.textContent)});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)closeChat();if(e.key==='Tab'&&!panel.hidden){const nodes=[...panel.querySelectorAll('button,textarea')].filter(n=>!n.disabled);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus()}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus()}}});

})();
</script>
</div>
