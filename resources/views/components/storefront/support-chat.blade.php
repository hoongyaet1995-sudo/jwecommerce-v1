@php
 $supportReceipt=request()->attributes->get('supportEnquiryContext')??(request()->routeIs('enquiries.receipt')?request()->route('enquiry'):null);
 $supportEnquiryContext=$supportReceipt?($supportReceipt->support_conversation_id && session('support_enquiry_id')===$supportReceipt->id?$supportReceipt->id:null):session('support_enquiry_id');
@endphp
<div id="storefront-support-widget" data-enquiry-context="{{ $supportEnquiryContext }}" data-support-generation="{{ session('support_generation','') }}">
<style>
#storefront-support-widget #sf-image-preview[hidden]{display:none!important}

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
#storefront-support-widget .message-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;white-space:normal}#storefront-support-widget .message-action{display:inline-flex;align-items:center;min-height:36px;padding:7px 12px;border:1px solid #9c87ef;border-radius:10px;color:var(--ink);background:var(--soft);font:inherit;font-weight:600;text-decoration:none}#storefront-support-widget .message-action:hover{border-color:#6549e9;color:#8067ea}#storefront-support-widget .message-action:focus-visible{outline:3px solid #a99aff;outline-offset:3px}
#storefront-support-widget .product-cards{width:88%;display:grid;gap:12px;margin:8px 0 0;white-space:normal}#storefront-support-widget .product-card{padding:6px;border:1px solid var(--line);border-radius:16px;background:var(--bg);overflow:hidden;box-shadow:0 3px 12px #0000000b}html.dark #storefront-support-widget .product-card{border-color:#443852;background:#1d1925}#storefront-support-widget .product-card-image{position:relative;width:100%;height:200px;border-radius:11px;background:#eeeef0;overflow:hidden;display:grid;place-items:center}#storefront-support-widget .product-card-image img{position:absolute;width:100%;height:100%;object-fit:contain;display:block}#storefront-support-widget .product-image-placeholder{font-size:52px;color:#aaa4b6}#storefront-support-widget .product-image-placeholder[hidden]{display:none}#storefront-support-widget .product-card-info{padding:13px 9px 8px}#storefront-support-widget .product-card h3{margin:0;font-size:16px;font-weight:700;line-height:1.35;overflow-wrap:anywhere;color:var(--ink)}#storefront-support-widget .product-card-price{font-size:18px;font-weight:700;margin-top:9px;line-height:1.3;color:var(--ink)}#storefront-support-widget .product-card-footer{border-top:1px solid var(--line);padding-top:12px;margin-top:13px}#storefront-support-widget .product-card .message-action{width:100%;justify-content:center;min-height:40px;background:transparent;color:#8067ea;border-color:#9c87ef;font-size:13px}html.dark #storefront-support-widget .product-card .message-action{color:#b99aff}#storefront-support-widget .product-card .message-action:hover{background:var(--soft)}
#storefront-support-widget .message.enter{animation:sf-message-in .22s ease-out both}
#storefront-support-widget .thinking{display:flex;align-items:center;gap:9px;color:var(--muted);width:fit-content}
#storefront-support-widget .thinking-dots{display:flex;gap:4px}
#storefront-support-widget .thinking-dots i{width:5px;height:5px;border-radius:50%;background:#9c87ef;animation:sf-thinking 1.2s ease-in-out infinite}
#storefront-support-widget .thinking-dots i:nth-child(2){animation-delay:.16s}
#storefront-support-widget .thinking-dots i:nth-child(3){animation-delay:.32s}
@keyframes sf-message-in{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}
@keyframes sf-thinking{0%,80%,100%{opacity:.35;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}
@media(prefers-reduced-motion:reduce){#storefront-support-widget .message.enter,#storefront-support-widget .thinking-dots i{animation:none}}
#storefront-support-widget .conversation-status{margin:22px auto 8px;padding:11px 14px;max-width:92%;border:1px solid var(--line);border-radius:12px;background:var(--soft);color:var(--muted);font-size:12px;line-height:1.6;text-align:center;white-space:normal}
 #storefront-support-widget form.contact-form{display:flex;flex-direction:column;align-items:stretch;gap:12px;width:100%;max-width:100%;padding:18px;margin:14px 0;background:var(--soft);border:1px solid var(--line);border-radius:16px;color:var(--ink);font-size:13px;line-height:1.5;white-space:normal;overflow-wrap:normal}
 #storefront-support-widget .contact-form input{width:100%;min-width:0;height:42px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);padding:10px 12px;font:inherit}
 #storefront-support-widget .contact-form input:focus{outline:2px solid #9c87ef;outline-offset:1px}
 #storefront-support-widget .contact-form small{color:var(--muted);font-size:11px;line-height:1.6}
 #storefront-support-widget .contact-form button[type=submit]{width:100%;padding:11px;background:#6549e9;color:white;border:0;border-radius:10px;font-weight:600}
</style>


<svg style="display:none" aria-hidden="true"><symbol id="sf-support-chat" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2v-10A8.5 8.5 0 0 1 10.5 3H17M7 10h7M7 14h5"/><path d="m20 2 .8 2.2L23 5l-2.2.8L20 8l-.8-2.2L17 5l2.2-.8Z"/></symbol><symbol id="sf-support-bubble" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2v-10A8.5 8.5 0 0 1 10.5 3H13a8 8 0 0 1 8 8.5Z"/><path d="M7 10h10M7 14h6"/></symbol><symbol id="sf-support-care" viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1M4 12H2v6h4v-6Zm16 0h2v6h-4v-6ZM20 18v2h-7"/></symbol><symbol id="sf-support-arrow" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol></svg>

<button class="launcher" id="sf-support-launcher" aria-label="Open support chat" aria-expanded="false" aria-controls="sf-support-panel"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#sf-support-chat"/></svg><span>How can we help?</span></button>
<section class="panel" id="sf-support-panel" role="dialog" aria-label="JW Studio support chat" hidden>
<header class="head"><div class="brand"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#sf-support-chat"/></svg></div><div class="bot-identity"><strong id="sf-bot-name">JW Studio Support</strong><small id="sf-bot-role">Let’s find what you need</small></div><div class="actions"><label class="chat-language"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/></svg><select id="sf-support-language" aria-label="Reply language"><option value="en">English</option><option value="ms">Malay</option><option value="zh">中文</option></select></label><button class="icon" id="sf-support-close" aria-label="Close support chat"><svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div></header>
<div class="faq-tabs" role="tablist" aria-label="Support sections"><button id="sample-chat-tab" role="tab" aria-selected="false">✦ AI Chat</button><button id="sample-faq-tab" role="tab" aria-selected="true">ⓘ FAQ Guide <span id="faq-total"></span></button></div>
<div id="faq-tools"><div class="faq-search"><span aria-hidden="true">⌕</span><input id="faq-search" aria-label="Search FAQ" placeholder="Search FAQ: shipping, returns, payment…" type="search"></div><div class="faq-categories" id="faq-categories" role="tablist" aria-label="FAQ categories"></div></div>
<div class="demo" role="status" aria-live="polite" hidden></div><div class="stream" id="sf-support-stream" role="log" aria-live="polite" hidden></div><div class="stream" id="sf-faq-stream" aria-live="polite"></div><footer class="composer"><div id="sf-image-status" role="status" hidden></div><div id="sf-image-preview" hidden></div><button id="sf-document-attach" type="button" class="icon" aria-label="Attach PDF" title="Attach PDF"><svg><use href="#sf-support-care"/></svg></button><input id="sf-document-file" type="file" accept="application/pdf,.pdf" hidden><input id="sf-image-file" type="file" accept="image/jpeg,image/png,image/webp" hidden><form id="sf-support-form"><button type="button" class="icon" id="sf-image-attach" aria-label="Attach image"><svg viewBox="0 0 24 24"><path d="m21 12-8 8a6 6 0 0 1-8-8l9-9a4 4 0 0 1 6 6l-9 9a2 2 0 0 1-3-3l8-8"/></svg></button><textarea id="sf-support-input" aria-label="Your message" placeholder="Write your message…" maxlength="2000"></textarea><button class="send" id="sf-support-send" aria-label="Send message" disabled><svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg></button></form><div class="foot">Chat with JW Studio Support</div></footer></section>
<script>
(()=>{const root=document.querySelector("#storefront-support-widget");
let enquiryContext=root.dataset.enquiryContext||null;
let generationContext=root.dataset.supportGeneration||'';
const chatUrl=path=>{const url=new URL(path,location.origin);if(enquiryContext)url.searchParams.set("enquiry_id",enquiryContext);url.searchParams.set('support_generation',generationContext);return url.pathname+url.search;};

const panel=root.querySelector('#sf-support-panel'),launcher=root.querySelector('#sf-support-launcher'),input=root.querySelector('#sf-support-input'),send=root.querySelector('#sf-support-send'),stream=root.querySelector('#sf-support-stream');
let previousOverflow=null;
function latest(){requestAnimationFrame(()=>{if(!panel.hidden&&!stream.hidden)stream.scrollTop=stream.querySelector('.meta')?stream.scrollHeight:0})}
const seenMessages=new Set();
function syncBackgroundScroll(){const lock=!panel.hidden&&matchMedia('(max-width:600px)').matches;if(lock&&previousOverflow===null){previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden'}else if(!lock&&previousOverflow!==null){document.body.style.overflow=previousOverflow;previousOverflow=null}}
function openChat(){panel.hidden=false;tab('chat');launcher.hidden=true;launcher.setAttribute('aria-expanded','true');syncBackgroundScroll();latest();root.querySelector('#sf-support-close').focus()}
function closeChat(){panel.hidden=true;launcher.hidden=false;launcher.setAttribute('aria-expanded','false');syncBackgroundScroll();launcher.focus()}
window.addEventListener('resize',syncBackgroundScroll);
launcher.onclick=openChat;root.querySelector('#sf-support-close').onclick=closeChat;
const languageSelect=root.querySelector('#sf-support-language');let selectedLanguage=localStorage.getItem('sf-support-language');if(!['en','ms','zh'].includes(selectedLanguage))selectedLanguage=null;languageSelect.value=selectedLanguage||'en';languageSelect.onchange=()=>{selectedLanguage=languageSelect.value;localStorage.setItem('sf-support-language',selectedLanguage);renderGreeting()};
let imageDraft=null,uploadingImage=false,uploadXhr=null,failedFile=null;
let sending=false, loaded=false, timer=null, retry=null, version=0;
let greetings=null,hasMessages=false;
function renderGreeting(){stream.querySelectorAll('[data-greeting]').forEach(node=>node.remove());if(hasMessages||!greetings)return;const text=greetings[languageSelect.value]||greetings.en;if(!text)return;const bubble=document.createElement('div');bubble.className='message';bubble.dataset.greeting='true';bubble.textContent=text;stream.prepend(bubble);}
const status=root.querySelector('.demo');
const mediaStatus=root.querySelector('#sf-image-status');
const imageFile=root.querySelector('#sf-image-file'),imagePreview=root.querySelector('#sf-image-preview'),imageAttach=root.querySelector('#sf-image-attach');
const documentAttach=root.querySelector('#sf-document-attach'),documentFile=root.querySelector('#sf-document-file');
function updateSend(){documentAttach.disabled=sending||uploadingImage;send.disabled=sending||uploadingImage||(!input.value.trim()&&!imageDraft);imageAttach.disabled=sending||uploadingImage;}
function removeImage(){if(sending||uploadingImage)return;imageDraft=null;imagePreview.replaceChildren();imagePreview.hidden=true;updateSend();}
function fileSize(bytes){return bytes<1048576?Math.ceil(bytes/1024)+' KB':(bytes/1048576).toFixed(1)+' MB';}
function showImagePreview(){imagePreview.replaceChildren();imagePreview.hidden=!imageDraft;if(!imageDraft)return;
 const visual=document.createElement(imageDraft.type==='document'?'span':'img');if(imageDraft.type==='document')visual.textContent='PDF';else{visual.src=imageDraft.url;visual.alt='Attachment preview';visual.style.cssText='width:54px;height:54px;object-fit:cover;border-radius:8px';}
 const name=document.createElement('span');name.textContent=imageDraft.name+' - '+fileSize(imageDraft.sizeBytes||0);name.style.cssText='font-size:11px;overflow:hidden;text-overflow:ellipsis';const button=document.createElement('button');button.type='button';button.className='icon';button.textContent='X';button.setAttribute('aria-label',imageDraft.type==='document'?'Remove document':'Remove image');button.onclick=removeImage;imagePreview.style.cssText='display:flex;gap:10px;align-items:center;margin-bottom:8px';imagePreview.append(visual,name,button);
}
function uploadState(text,action,label){mediaStatus.replaceChildren();mediaStatus.hidden=false;const span=document.createElement('span');span.textContent=text;mediaStatus.append(span);if(action){const b=document.createElement('button');b.type='button';b.className='message-action';b.textContent=label;b.onclick=action;mediaStatus.append(b);}}
async function uploadImage(file){
 if(sending||uploadingImage||currentStatus==='resolved')return;
 const pdf=file.type==='application/pdf'||file.name.toLowerCase().endsWith('.pdf');
 if(pdf?file.size>10485760:(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5242880)){uploadState(pdf?'Use a PDF up to 10 MB.':'Choose a JPEG, PNG or WebP image up to 5 MB.');return;}
 uploadingImage=true;failedFile=file;version++;updateSend();const xhr=new XMLHttpRequest();uploadXhr=xhr;
 const cancel=()=>xhr.abort();uploadState('Uploading attachment 0%',cancel,'Cancel upload');
 try{const a=await new Promise((resolve,reject)=>{
  xhr.open('POST',chatUrl('/support/chat/attachments'));xhr.timeout=120000;xhr.withCredentials=true;xhr.setRequestHeader('Accept','application/json');xhr.setRequestHeader('X-CSRF-TOKEN',document.querySelector('meta[name="csrf-token"]').content);
  xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.min(100,Math.round(e.loaded/e.total*100));uploadState(p===100?'Processing attachment...':'Uploading attachment '+p+'%',cancel,'Cancel upload');}};
  xhr.onerror=()=>reject(new Error('Upload failed. Check your connection.'));xhr.ontimeout=()=>reject(new Error('Upload timed out.'));xhr.onabort=()=>reject(new Error('Upload cancelled.'));
  xhr.onload=()=>{try{const d=JSON.parse(xhr.responseText);if(xhr.status<200||xhr.status>=300)throw new Error(d.message||'Upload failed.');if(!d.attachment?.id)throw new Error('Invalid upload response.');resolve(d.attachment);}catch(e){reject(e);}};
  const form=new FormData();form.append(pdf?'document':'image',file);xhr.send(form);
 });imageDraft={...a,url:chatUrl(a.url)};failedFile=null;showImagePreview();mediaStatus.hidden=true;}
 catch(e){uploadState(e.message,()=>{if(failedFile)void uploadImage(failedFile);},'Retry upload');}
 finally{if(uploadXhr===xhr)uploadXhr=null;uploadingImage=false;version++;updateSend();}
}
documentAttach.onclick=()=>documentFile.click();documentFile.onchange=()=>{const f=documentFile.files[0];if(f)void uploadImage(f);documentFile.value='';};
window.addEventListener('pagehide',()=>uploadXhr?.abort());
imageAttach.onclick=()=>imageFile.click();imageFile.onchange=()=>{const file=imageFile.files[0];if(file)void uploadImage(file);imageFile.value='';};
input.addEventListener('paste',e=>{const file=Array.from(e.clipboardData.items).find(i=>['image/jpeg','image/png','image/webp'].includes(i.type))?.getAsFile();if(file){e.preventDefault();void uploadImage(file);}});
async function request(method='GET',data){
 const response=await fetch(chatUrl(method==='GET'?'/support/chat':'/support/chat/messages'),{method,credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}:{})},body:data?JSON.stringify({...data,language:languageSelect.value}):undefined});
 const payload=await response.json().catch(()=>null);
 if(!response.ok||!payload)throw new Error(payload?.message||'Unable to connect. Please try again.');
 return payload.conversation;
}
async function resolvedAction(action){
 if(sending)return;sending=true;version++;
 try{const response=await fetch(chatUrl('/support/chat/resolved'),{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({action})});const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to update conversation.');if(action==='new'){enquiryContext=null;generationContext=data.generation||'';}retry=null;input.value='';render(data.conversation);latest();}catch(error){status.textContent=error.message;status.hidden=false;}finally{sending=false;version++;}
}
function render(conversation){
 if(stream.querySelector('[data-contact-form]')&&!sending)return;
 requiresContact=conversation?.requiresContact!==false;hasMessages=Boolean(conversation?.messages?.length);
 const oldTop=stream.scrollTop;
 const nearBottom=stream.scrollHeight-stream.scrollTop-stream.clientHeight<80;
 const previous=[...stream.querySelectorAll('.message')].at(-1)?.dataset.id;
 stream.querySelectorAll('.message,.meta,.product-cards,.conversation-status').forEach(node=>node.remove());
 for(const message of conversation?.messages||[]){
  const user=message.role==='customer',msg=document.createElement('div'),meta=document.createElement('div');
  msg.className='message'+(user?' user':'');msg.dataset.id=message.id;msg.textContent=message.content;for(const a of message.attachments||[]){const box=document.createElement('div');if(a.type==='document'&&a.status==='ready'&&a.url){const link=document.createElement('a');link.href=chatUrl(a.url+'?download=1');link.className='message-action';link.setAttribute('aria-label','Download document '+a.name);link.textContent=a.name+' - '+fileSize(a.sizeBytes||0)+' - Download';box.append(link);}else if(a.status==='pending'){box.textContent='Downloading attachment…';}else if(!a.url||a.status==='failed'){box.textContent=a.error||'Image unavailable.';}else{const link=document.createElement('a');link.href=chatUrl(a.url);link.target='_blank';link.rel='noopener';link.setAttribute('aria-label','Open image '+a.name);const img=document.createElement('img');img.src=chatUrl(a.url);img.alt=a.name;img.loading='lazy';img.style.cssText='max-width:100%;max-height:220px;border-radius:8px;object-fit:contain';img.onerror=()=>{box.textContent='Image unavailable.';};link.append(img);box.append(link);}msg.prepend(box);}if(loaded&& !seenMessages.has(message.id))msg.classList.add('enter');seenMessages.add(message.id);
  const cards=document.createElement('div');cards.className='product-cards';
  if(!user&&message.actions?.length){for(const action of message.actions){if(action.type==='request_human'&&conversation?.status==='open'&&message.id===conversation.messages.filter(m=>m.role==='ai').at(-1)?.id){const button=document.createElement('button');button.type='button';button.className='message-action';button.dataset.requestHuman='true';button.textContent={en:'Talk to human →',ms:'Bercakap dengan ejen →',zh:'转人工客服 →'}[languageSelect.value]||'Talk to human →';button.disabled=sending;button.onclick=()=>{if(!sending)void submit({en:'I would like to talk to a human agent.',ms:'Saya ingin bercakap dengan ejen manusia.',zh:'我想转人工客服。'}[languageSelect.value],true)};const row=document.createElement('div');row.className='message-actions';row.append(button);msg.append(row);continue;}if(action.type==='view_quotation'&&typeof action.url==='string'&&/^\/quotations\/[0-9a-f-]{36}$/.test(action.url)){const link=document.createElement('a');link.className='message-action';link.href=action.url;link.textContent='View quotation →';cards.append(link);continue;}if(action.type==='view_order_tracking'&&action.url==='/order-tracking'){const link=document.createElement('a');link.className='message-action';link.href=action.url;link.textContent='Order Tracking →';cards.append(link);continue;}if(['view_category','view_collection'].includes(action.type)&&typeof action.url==='string'&&/^\/(categories|collections)\/[^/]+$/.test(action.url)){const link=document.createElement('a');link.className='message-action';link.href=action.url;link.textContent=action.label+': '+action.title+' →';cards.append(link);continue;}if(action.type!=='view_product'||typeof action.url!=='string'||!action.url.startsWith('/products/')||action.url.startsWith('//'))continue;
   const card=document.createElement('article');card.className='product-card';
   const imageBox=document.createElement('div');imageBox.className='product-card-image';
   const fallback=document.createElement('span');fallback.className='product-image-placeholder';fallback.textContent='◇';fallback.setAttribute('aria-label','Product image unavailable');imageBox.append(fallback);
   if(typeof action.image==='string'&&(/^(https?:\/\/|\/(?!\/))/.test(action.image))){const image=document.createElement('img');image.src=action.image;image.alt=action.title;image.loading='lazy';image.onload=()=>fallback.hidden=true;image.onerror=()=>image.remove();imageBox.append(image)}
   const info=document.createElement('div');info.className='product-card-info';const title=document.createElement('h3');title.textContent=action.title;info.append(title);
   if(action.price){const price=document.createElement('div');price.className='product-card-price';price.textContent=action.price;info.append(price)}
   const footer=document.createElement('div');footer.className='product-card-footer';const link=document.createElement('a');link.className='message-action';link.href=action.url;link.textContent=action.label+' →';link.setAttribute('aria-label',action.label+': '+action.title);footer.append(link);info.append(footer);card.append(imageBox,info);cards.append(card);
  }}
  meta.className='meta'+(user?' user':'');meta.textContent=(user?'You':(message.role==='ai'?root.querySelector('#sf-bot-name').textContent:'Support team'))+' · '+new Date(message.createdAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  stream.append(msg);if(cards.childElementCount)stream.append(cards);stream.append(meta);
 }
 renderGreeting();
 if(conversation?.messages?.length&&(nearBottom||previous!==conversation.messages.at(-1)?.id))latest();else stream.scrollTop=oldTop;
 currentStatus=conversation?.status||'open'; input.disabled=currentStatus==='resolved'; root.querySelector('#sf-support-form').hidden=currentStatus==='resolved'; if(currentStatus==='resolved'){const copy={en:['✓ This support conversation has ended.','Issue not resolved','Ask a new question'],ms:['✓ Perbualan ini telah ditutup.','Masalah belum selesai','Tanya soalan baharu'],zh:['✓ 此次客服会话已结束','问题还没解决','咨询新问题']}[languageSelect.value]||['✓ This support conversation has ended.','Issue not resolved','Ask a new question'];const card=document.createElement('div');card.className='conversation-status';card.setAttribute('role','status');const label=document.createElement('div');label.textContent=copy[0];card.append(label);const actions=document.createElement('div');actions.className='message-actions';['reopen','new'].forEach((action,i)=>{const button=document.createElement('button');button.type='button';button.className='message-action';button.textContent=copy[i+1];button.onclick=()=>void resolvedAction(action);actions.append(button)});card.append(actions);stream.append(card);if(nearBottom)latest();}  const notices={en:{waiting_human:'Your request is waiting for our support team.',human_mode:'A support agent is handling this conversation.'},ms:{waiting_human:'Permintaan anda sedang menunggu khidmat pelanggan.',human_mode:'Ejen khidmat pelanggan sedang mengendalikan perbualan ini.'},zh:{waiting_human:'已提交请求，正在等待客服接手。',human_mode:'客服已接手本次对话。'}};const notice=(currentStatus==='waiting_human'?conversation?.waitingNotice?.[languageSelect.value]:null)||notices[languageSelect.value]?.[currentStatus]||'';status.textContent='';status.hidden=true;if(notice){const row=document.createElement('div');row.className='conversation-status';row.setAttribute('role','status');row.textContent=notice;stream.append(row);if(nearBottom)latest();}
}
async function refresh(){
 const revision=version;
 try{const conversation=await request();loaded=true;if(!sending&&revision===version)render(conversation)}catch(error){status.textContent=error.message;status.hidden=false}
}
async function poll(){if(panel.hidden)return;if(!document.hidden&&!sending)await refresh();if(!panel.hidden)timer=setTimeout(poll,4000)}
const originalOpen=openChat,originalClose=closeChat;
launcher.onclick=()=>{originalOpen();clearTimeout(timer);void poll()};
root.querySelector('#sf-support-close').onclick=()=>{originalClose();clearTimeout(timer)};
let currentStatus='open',requiresContact=true,contactDraft=null;
async function submit(text,humanRequest=false){
 if(humanRequest&&requiresContact&&!contactDraft){
  if(stream.querySelector('[data-contact-form]'))return;
  const form=document.createElement('form');form.className='contact-form';form.dataset.contactForm='true';
  const title=document.createElement('div');title.textContent={en:'How can our team contact you?',ms:'Bagaimana kami boleh menghubungi anda?',zh:'请留下联系方式，方便客服协助。'}[languageSelect.value];form.append(title);
  for(const [name,label,type,required] of [['name','Name','text',true],['email','Email','email',true],['phone','Telephone (optional)','tel',false]]){const field=document.createElement('input');field.name=name;field.placeholder=label;field.setAttribute('aria-label',label);field.type=type;field.required=required;field.maxLength=name==='phone'?30:name==='name'?100:255;field.className='message-action';field.style.cssText='display:block;width:100%;box-sizing:border-box;margin-top:8px';form.append(field);}
  const note=document.createElement('small');note.textContent='Used for this support request. This does not create a login account.';form.append(note);
  const button=document.createElement('button');button.type='submit';button.className='message-action';button.textContent='Request human support';form.append(button);
  const cancel=document.createElement('button');cancel.type='button';cancel.className='message-action';cancel.textContent={en:'Cancel',ms:'Batal',zh:'取消'}[languageSelect.value]||'Cancel';cancel.onclick=()=>{if(sending)return;contactDraft=null;form.remove();void refresh();};form.append(cancel);
  form.onsubmit=e=>{e.preventDefault();if(sending)return;contactDraft=Object.fromEntries(new FormData(form));void submit(text,true);};stream.append(form);latest();return;
 }
 text=text.trim();if((!text&&!imageDraft)||sending||uploadingImage||currentStatus==='resolved')return;const attachment=imageDraft;
 sending=true;send.disabled=true;input.disabled=true;version++;stream.querySelectorAll('[data-request-human]').forEach(button=>button.disabled=true);
 stream.querySelectorAll('[data-greeting]').forEach(n=>n.remove());
 const pending=document.createElement('div');pending.className='message user enter';pending.dataset.pending='true';pending.textContent=text||(attachment?'Sending image…':'');
 const thinking=document.createElement('div');thinking.className='message thinking enter';thinking.dataset.pending='true';thinking.setAttribute('role','status');
 const dots=document.createElement('span');dots.className='thinking-dots';dots.setAttribute('aria-hidden','true');for(let i=0;i<3;i++)dots.append(document.createElement('i'));
 const label=document.createElement('span');label.textContent={en:'AI is thinking…',ms:'AI sedang berfikir…',zh:'AI 正在思考…'}[languageSelect.value]||'AI is thinking…';thinking.append(dots,label);
 stream.append(pending);if(text&&!humanRequest&&currentStatus==='open')stream.append(thinking);input.value='';status.hidden=true;latest();
 if(!retry||retry.content!==text||retry.attachmentId!==attachment?.id)retry={content:text,attachmentId:attachment?.id,id:crypto.randomUUID()};
 try{
  if(!loaded){await request();loaded=true}
  const conversation=await request('POST',{content:text,attachment_id:attachment?.id,client_message_id:retry.id,...(humanRequest?{request_human:true,...(contactDraft?{contact:contactDraft}:{})}:{})});render(conversation);stream.querySelectorAll('[data-contact-form]').forEach(n=>n.remove());input.value='';imageDraft=null;imagePreview.replaceChildren();imagePreview.hidden=true;retry=null;contactDraft=null;stream.scrollTop=stream.scrollHeight;
 }catch(error){stream.querySelectorAll('[data-pending]').forEach(n=>n.remove());status.textContent=error.message;status.hidden=false;input.value=text;renderGreeting()}
 finally{stream.querySelectorAll('[data-pending]').forEach(n=>n.remove());version++;sending=false;stream.querySelectorAll('[data-request-human]').forEach(button=>button.disabled=false);input.disabled=false;updateSend()}
}
input.oninput=updateSend;root.querySelector('#sf-support-form').onsubmit=e=>{e.preventDefault();void submit(input.value)};input.onkeydown=e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();void submit(input.value)}};
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)closeChat();if(e.key==='Tab'&&!panel.hidden){const nodes=[...panel.querySelectorAll('button,textarea,a[href],select')].filter(n=>!n.disabled);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus()}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus()}}});

const faqStream=root.querySelector('#sf-faq-stream');let groups=[];
const make=(tag,text,cls)=>{const el=document.createElement(tag);el.textContent=text;if(cls)el.className=cls;return el};
let activeCategory='all',activeTab='chat',expanded=null;
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
function tab(value){activeTab=value;root.querySelector('#sample-chat-tab').setAttribute('aria-selected',String(value==='chat'));root.querySelector('#sample-faq-tab').setAttribute('aria-selected',String(value==='faq'));tools.hidden=value!=='faq';composer.hidden=value==='faq';stream.hidden=value==='faq';faqStream.hidden=value!=='faq';status.hidden=value==='faq'||!status.textContent;if(value==='faq'){renderFaq()}else{latest();input.focus()}}
search.oninput=()=>{expanded=null;renderFaq()};root.querySelector('#sample-chat-tab').onclick=()=>tab('chat');root.querySelector('#sample-faq-tab').onclick=()=>tab('faq');

async function loadFAQs(){
 try{const response=await fetch('/support/faq',{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)throw new Error();const data=await response.json();greetings=data.greetingMessage;renderGreeting();if(!selectedLanguage){const defaultLanguage=data.primaryLanguage==='auto_detect'?((navigator.language||'en').startsWith('zh')?'zh':(navigator.language||'en').startsWith('ms')?'ms':'en'):data.primaryLanguage;languageSelect.value=['en','ms','zh'].includes(defaultLanguage)?defaultLanguage:'en';}if(data.identity){root.querySelector('#sf-bot-name').textContent=data.identity.botName;root.querySelector('#sf-bot-role').textContent=data.identity.roleTitle;root.querySelector('.foot').textContent='Chat with '+data.identity.botName;}renderGreeting();groups=data.categories.map(c=>({name:c.label,items:c.items.map(i=>[i.question,i.answer])}));root.querySelector('#faq-total').textContent=groups.reduce((sum,g)=>sum+g.items.length,0);if(activeCategory!=='all'&&!groups.some(g=>g.name===activeCategory))activeCategory='all';renderCategories();if(activeTab==='faq')renderFaq();}
 catch{faqStream.replaceChildren(make('p','Unable to load FAQs. Please try again.'),Object.assign(make('button','Retry','topic'),{onclick:loadFAQs}));}
}
setInterval(()=>{if(!panel.hidden&&activeTab==='faq')void loadFAQs()},5000);
renderCategories();tab('chat');void loadFAQs();launcher.addEventListener('click',loadFAQs);root.querySelector('#sample-faq-tab').addEventListener('click',loadFAQs);
if(document.querySelector('[data-enquiry-support-open]'))launcher.click();

})();
</script>
</div>

