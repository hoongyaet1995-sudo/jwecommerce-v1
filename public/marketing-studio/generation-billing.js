/* A quote displays scope and cost. Only explicit confirmation authorizes paid work. */
'use strict';
window.MarketingGenerationBilling={confirm(quote){return new Promise(resolve=>{
 const dialog=document.createElement('dialog');dialog.className='ws-card';dialog.style.position='fixed';dialog.style.inset='0';dialog.style.margin='auto';dialog.style.maxHeight='90vh';dialog.style.overflow='auto';dialog.style.maxWidth='480px';dialog.style.width='calc(100% - 32px)';
 const title=document.createElement('h3');title.textContent=quote.additional_limit?'是否批准本次收费重试？':'确认本次生成';
 const scope=document.createElement('p');scope.textContent=quote.summary;
 const cost=document.createElement('p');cost.className='ws-cost-row total';cost.textContent=quote.additional_limit?'上次已核算 USD '+quote.previous_actual+'；本次新增上限 USD '+quote.additional_limit+'；批准后总费用上限 USD '+quote.total_limit:'本次费用上限 USD '+quote.amount;
 const note=document.createElement('p');note.className='ws-note';note.textContent='确认后开始执行。原版保留；需要增加费用时会重新请你确认。结果或费用待核对时暂停，不自动重复收费。';
 const actions=document.createElement('div');actions.className='d-flex justify-content-end gap-2 mt-4';
 const cancel=document.createElement('button');cancel.type='button';cancel.className='btn btn-outline-secondary';cancel.textContent=quote.additional_limit?'不批准':'取消';
 const confirm=document.createElement('button');confirm.type='button';confirm.className='btn ws-primary';confirm.textContent=quote.additional_limit?'批准并继续':'确认生成';
 let settled=false;const finish=value=>{if(settled)return;settled=true;dialog.close();dialog.remove();resolve(value);};cancel.onclick=()=>finish(false);confirm.onclick=()=>finish(true);dialog.addEventListener('cancel',event=>{event.preventDefault();finish(false);});
 title.id='generation-billing-title';dialog.setAttribute('aria-labelledby',title.id);actions.append(cancel,confirm);dialog.append(title,scope,cost,note,actions);document.body.append(dialog);dialog.showModal();cancel.focus();
});}};
