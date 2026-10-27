(() => {
 'use strict';
 const root=document.querySelector('[data-workspace-video-poll]'); if(!root)return;
 const targets=JSON.parse(root.dataset.targets), labels=JSON.parse(root.dataset.labels);
 const active=new Set(['queued','upload_pending','uploading','submit_pending','submitting','external_pending','download_pending','downloading','render_pending','rendering']);
 let failures=0, stopped=false;
 const note=text=>document.querySelectorAll('[data-video-poll-note]').forEach(e=>e.textContent=text);
 async function poll(){
  if(stopped)return;
  if(document.hidden){setTimeout(poll,5000);return;}
  try{
   const response=await fetch(root.dataset.url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
   if(!response.ok)throw new Error('status');
   const data=await response.json(); failures=0; let changed=false;
   for(const target of targets){const item=(data[target.kind]||[]).find(c=>c.id===target.id);if(!item)continue;
    document.querySelectorAll('[data-video-state]').forEach(e=>{if(e.dataset.videoState===item.id)e.textContent=labels[item.status]||item.status;});
    if(!active.has(item.status))changed=true;
   }
   if(changed){stopped=true;location.reload();return;}
   note('后台继续处理，正在自动读取进度…');
  }catch(error){failures++;note('暂时无法读取进度，任务记录已保留。可稍后刷新查看。');if(failures>=5){stopped=true;return;}}
  setTimeout(poll,Math.min(3000*Math.max(1,failures),15000));
 }
 setTimeout(poll,2000);
})();
