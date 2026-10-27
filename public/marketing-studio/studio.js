(() => {
    const body=document.body, sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebar-overlay'),toggle=document.getElementById('sidebar-toggle');
    const close=()=>{sidebar?.classList.remove('show');overlay?.classList.remove('show');toggle?.setAttribute('aria-expanded','false');};
    toggle?.addEventListener('click',()=>{const open=!sidebar.classList.contains('show');sidebar.classList.toggle('show',open);overlay?.classList.toggle('show',open);toggle.setAttribute('aria-expanded',String(open));});
    overlay?.addEventListener('click',close);document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
    document.getElementById('desktop-sidebar-toggle')?.addEventListener('click',()=>{body.classList.toggle('sidebar-minimized');});
    window.addEventListener('resize',()=>{if(innerWidth>=1200)close();});
    document.getElementById('catalog-source')?.addEventListener('change',e=>{const image=document.getElementById('catalog-preview'),path=e.target.selectedOptions[0]?.dataset.preview; image.hidden=!path; if(path)image.src=path;else image.removeAttribute('src');});
    document.querySelectorAll('form[method="post"]').forEach(form=>form.addEventListener('submit',()=>{form.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>{b.disabled=true;});}));
})();
