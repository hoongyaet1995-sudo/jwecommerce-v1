'use strict';
document.querySelectorAll('[data-single]').forEach(group=>group.addEventListener('click',e=>{const button=e.target.closest('button');if(!button||button.hasAttribute('data-notice'))return;group.querySelectorAll('button').forEach(b=>{b.classList.toggle('selected',b===button);b.setAttribute('aria-pressed',String(b===button));});}));
document.querySelectorAll('[data-multiple] button').forEach(button=>button.addEventListener('click',()=>{button.classList.toggle('selected');button.setAttribute('aria-pressed',String(button.classList.contains('selected')));}));
document.querySelectorAll('[data-notice]').forEach(button=>button.addEventListener('click',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('previewNotice')).show()));
document.querySelectorAll('[data-suggestion]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById('ws-change');input.value=button.dataset.suggestion;input.focus();}));
document.querySelectorAll('[data-source]').forEach(button=>button.addEventListener('click',()=>{document.getElementById('ws-product').classList.toggle('d-none',button.dataset.source==='theme');document.getElementById('ws-theme').classList.toggle('d-none',button.dataset.source!=='theme');}));
document.getElementById('ws-subtitles')?.addEventListener('change',e=>document.getElementById('ws-caption').hidden=!e.target.checked);
document.querySelectorAll('[data-voice]').forEach(button=>button.addEventListener('click',()=>{const muted=button.dataset.voice==='off';document.querySelectorAll('#ws-voices button').forEach(b=>{b.disabled=muted;});}));

// Live form bindings: no fetches, background AI calls or hidden business mutations.
document.querySelectorAll('[data-source]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById('ws-source-kind');if(input)input.value=button.dataset.source;}));
document.querySelectorAll('[data-video-type]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById('ws-video-type');if(input)input.value=button.dataset.videoType;}));
document.querySelector('[data-auto-type]')?.addEventListener('click',()=>{document.getElementById('ws-video-type').value='auto';document.querySelectorAll('[data-video-type]').forEach(b=>b.classList.remove('selected'));});
document.querySelectorAll('[data-duration]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById('ws-duration');if(input)input.value=button.dataset.duration;}));
document.querySelectorAll('[data-voice]').forEach(button=>button.addEventListener('click',()=>{const on=button.dataset.voice==='on';const v=document.getElementById('ws-narration-value');const voice=document.getElementById('ws-voice-value');if(v)v.value=on?'1':'0';if(voice)voice.value=on?'ai':'none';}));
document.querySelectorAll('[data-platform]').forEach(button=>button.addEventListener('click',()=>{const box=document.getElementById('ws-platform-values');if(!box)return;box.replaceChildren();document.querySelectorAll('[data-platform].selected').forEach(b=>{const input=document.createElement('input');input.type='hidden';input.name='platforms[]';input.value=b.dataset.platform;box.appendChild(input);});}));
document.querySelectorAll('[data-goal]').forEach(button=>button.addEventListener('click',()=>{document.getElementById('ws-goal-value').value=button.dataset.goal;}));
const subtitle=document.getElementById('ws-subtitles');if(subtitle)document.getElementById('ws-caption').hidden=!subtitle.checked;
