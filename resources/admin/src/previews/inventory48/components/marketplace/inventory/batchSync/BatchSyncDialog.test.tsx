// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot, type Root} from 'react-dom/client';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {BatchSyncDialog} from './BatchSyncDialog';
import type {BatchSyncState, Review, ReviewTarget, BatchDetail, BatchItem} from './types';

const target=(rowKey:string,variantId:number,quantity:number|null,disposition:'valid'|'skipped'='valid',reasonCode:string|null=null):ReviewTarget=>({
  schemaVersion:1,rowKey,variantId,platform:'shopee',storeId:1,facilityId:rowKey.endsWith('2')?2:1,
  mode:'shared_pool',configurationVersion:1,balanceId:1,balanceVersion:1,quantity,
  listingId:1,providerShopId:'shop',externalProductId:'product',externalVariantId:'variant',providerLocationId:'loc',
  topologyFingerprint:'topology',policyFingerprint:'policy',providerAddressKey:rowKey,disposition,reasonCode,
  display:{sku:variantId===1?'JWSTORE1PS5-500GB':variantId===2?'ZERO-001':'UNCOUNTED-001',name:variantId===1?'JW Sandbox PS5':variantId===2?'ZERO-001':'Uncounted item',image:'',store:'OpenSANDBOX',facility:rowKey.endsWith('2')?'Secondary warehouse':'Main warehouse'},
});
const review:Review={id:'review',actorId:1,kind:'initial',batchId:null,variantIds:[1,2,3],itemIds:[],mode:'shared_pool',configurationVersion:1,
  targets:[target('one-1',1,60),target('one-2',1,60),target('zero-1',2,0),target('zero-2',2,0),target('skip-1',3,null,'skipped','first_count_required')],
  digest:'digest',expiresAt:'2026-10-01T10:00:00Z',counts:{skus:3,validDestinations:4,skippedRows:1}};
const reviewState:BatchSyncState={kind:'review',generation:1,review,frozenVariantIds:[1,2,3],reviewRequestKey:'key'};
const item=(id:string,status:BatchItem['status'],can_retry=false,can_check=false):BatchItem=>({id,row_key:id,display:{sku:id,name:`Item ${id}`,image:'',store:'OpenSANDBOX',facility:'Main warehouse'},
  current_attempt:{id:`attempt-${id}`,generation:1,task_id:`task-${id}`,snapshot_token:`token-${id}`,target_quantity:0,observed_quantity:null,phase:status,checked_at:null,check_count:0,exhausted_at:null,diagnostics:{}},
  history:[],status,reason_code:null,message:null,can_retry,can_check});
const batch:BatchDetail={id:'batch',mode:'shared_pool',revision:1,created_at:'2026-10-01T10:00:00Z',actor:{},selection:{},aggregate:'needs_attention',
  counts:{skus:3,destinations:{queued:0,confirming:1,confirmed:1,failed:1,skipped:1}},
  items:[item('confirmed','confirmed'),item('failed','failed',true),item('uncertain','confirming',false,true),item('skipped','skipped')],next_poll_ms:3000};
const callbacks=()=>({onStart:vi.fn(),onClose:vi.fn(),onReviewAgain:vi.fn(),onReviewFailed:vi.fn(),onCheck:vi.fn()});
let host:HTMLDivElement,root:Root;
beforeEach(()=>{(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;host=document.createElement('div');document.body.append(host);root=createRoot(host)});
afterEach(async()=>{await act(async()=>root.unmount());host.remove()});
async function render(state:BatchSyncState,props=callbacks()){await act(async()=>root.render(<BatchSyncDialog state={state} {...props}/>));return props}
function click(text:string){const button=[...document.querySelectorAll('button')].find(x=>x.textContent?.includes(text)||x.getAttribute('aria-label')===text);expect(button).toBeTruthy();button!.click()}

describe('BatchSyncDialog',()=>{
  it('renders immutable shared targets including counted zero and an uncounted skip',async()=>{
    await render(reviewState);
    expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    expect(document.body.textContent).toContain('JW Sandbox PS5');
    expect(document.body.textContent).toContain('Secondary warehouse');
    expect(document.body.textContent).toContain('Awaiting first count');
    expect(host.querySelector('.bs-skipped .bs-current strong')?.textContent).toBe('Awaiting first count');
    expect(document.querySelectorAll('[data-target-quantity="0"]')).toHaveLength(2);
    expect(document.body.textContent).toContain('4Destination updates ready');
    expect(document.querySelector('input')).toBeNull();
    expect(document.querySelector('[role="checkbox"]')).toBeNull();
    expect([...host.querySelectorAll('.bs-marker')].every(marker=>marker.tagName==='SPAN'&&!marker.hasAttribute('tabindex'))).toBe(true);
  });
  it('never submits from Close or Escape before Start and restores focus',async()=>{
    const trigger=document.createElement('button');document.body.append(trigger);trigger.focus();
    const props=await render(reviewState);
    await act(async()=>click('Close'));
    expect(props.onClose).toHaveBeenCalledOnce();expect(props.onStart).not.toHaveBeenCalled();
    await act(async()=>root.render(<BatchSyncDialog state={{kind:'idle',generation:2}} {...props}/>));
    expect(document.activeElement).toBe(trigger);
    await render(reviewState,props);
    await act(async()=>document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})));
    expect(props.onClose).toHaveBeenCalledTimes(2);expect(props.onStart).not.toHaveBeenCalled();trigger.remove();
  });
  it('disables Start when no destinations are valid',async()=>{
    const empty={...review,targets:[target('skip-1',3,null,'skipped','first_count_required')],counts:{skus:1,validDestinations:0,skippedRows:1}};
    const props=await render({...reviewState,review:empty});
    const start=[...document.querySelectorAll('button')].find(x=>x.textContent?.includes('Start sync'));
    expect(start?.disabled).toBe(true);expect(props.onStart).not.toHaveBeenCalled();
  });
  it('closing a submitted batch reports background continuation without cancellation',async()=>{
    const props=await render({kind:'batch',generation:2,batchId:'batch',batch});
    expect(document.body.textContent).toContain('continues in the background');
    await act(async()=>click('Close'));
    expect(props.onClose).toHaveBeenCalledOnce();expect(props.onStart).not.toHaveBeenCalled();
  });
  it('offers retry only for eligible failed rows and Check for uncertain rows',async()=>{
    const props=await render({kind:'batch',generation:2,batchId:'batch',batch});
    expect(document.body.textContent).not.toContain('All updates confirmed');
    await act(async()=>click('Review failed items'));
    expect(props.onReviewFailed).toHaveBeenCalledWith(['failed']);
    await act(async()=>click('Check confirmation'));
    expect(props.onCheck).toHaveBeenCalledWith('uncertain',{task_id:'task-uncertain',operation_key:expect.any(String),snapshot_token:'token-uncertain'});
  });
  it('renders malicious long Unicode display and error strings as text',async()=>{
    const hostile='<script>window.__bad=1</script>⚡'.repeat(30);
    const unsafe={...review,targets:[{...target('one-1',1,60),display:{...target('one-1',1,60).display,name:hostile}}]};
    await render({...reviewState,review:unsafe});
    expect(document.body.textContent).toContain(hostile);expect(document.querySelector('script')).toBeNull();
    await render({kind:'error',generation:2,message:hostile});
    expect(document.body.textContent).toContain(hostile);expect(document.querySelector('script')).toBeNull();
  });
  it('shows known skipped stock without relabelling it as uncounted',async()=>{
    await render({...reviewState,review:{...review,targets:[target('paused-1',1,60,'skipped','store_paused')],counts:{skus:1,validDestinations:0,skippedRows:1}}});
    expect(host.querySelector('.bs-current strong')?.textContent).toBe('60');
    expect(host.textContent).toContain('Store or warehouse is paused');
  });
  it('keeps Independent quantities per destination without a shared total',async()=>{
    const independent={...review,mode:'independent_stores' as const,targets:[{...target('one-1',1,17),mode:'independent_stores' as const},{...target('one-2',1,23),mode:'independent_stores' as const}],counts:{skus:1,validDestinations:2,skippedRows:0}};
    await render({...reviewState,review:independent});
    expect(host.textContent).toContain('Independent stock mode');
    expect(host.textContent).not.toContain('same shared quantity');
    expect(host.querySelector('.bs-current strong')?.textContent).toBe('Per destination');
    expect(host.querySelectorAll('[data-target-quantity="17"]')).toHaveLength(1);
    expect(host.querySelectorAll('[data-target-quantity="23"]')).toHaveLength(1);
  });
  it('wraps keyboard focus in both directions',async()=>{
    await render(reviewState);
    const controls=[...host.querySelectorAll<HTMLButtonElement>('button')];
    controls[controls.length-1].focus();
    document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));
    expect(document.activeElement).toBe(controls[0]);
    document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',shiftKey:true,bubbles:true,cancelable:true}));
    expect(document.activeElement).toBe(controls[controls.length-1]);
  });
  it('uses Start as the only review submission action',async()=>{
    const props=await render(reviewState);
    expect(props.onStart).not.toHaveBeenCalled();
    await act(async()=>click('Start sync (4 destinations)'));
    expect(props.onStart).toHaveBeenCalledOnce();
  });
  it('does not offer Check on confirmed or skipped rows even with an eligibility flag',async()=>{
    await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,items:[item('confirmed','confirmed',false,true),item('skipped','skipped',false,true)]}});
    expect(host.textContent).not.toContain('Check confirmation');
  });
  it('keeps submitted and unknown outcomes closable without submitting again',async()=>{
    const props=callbacks();
    for(const kind of ['submitting','unknown'] as const){
      await render({kind,generation:2,reviewId:review.id,review,operationKey:'preserved-key',retry:false,scope:{kind:'initial',variantIds:[1,2,3]}},props);
      expect(host.textContent).not.toContain('Start sync');
      await act(async()=>click('Close'));
    }
    expect(props.onClose).toHaveBeenCalledTimes(2);
    expect(props.onStart).not.toHaveBeenCalled();
  });
  it('offers Check for an uncertain failed row without adding it to retry',async()=>{
    const props=await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,items:[item('failed-uncertain','failed',false,true)]}});
    expect(host.textContent).not.toContain('Review failed items');
    await act(async()=>click('Check confirmation'));
    expect(props.onCheck).toHaveBeenCalledWith('failed-uncertain',expect.objectContaining({task_id:'task-failed-uncertain'}));
  });
  it('never claims all-success when completed results include a skip',async()=>{
    await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,aggregate:'completed_with_issues',counts:{skus:2,destinations:{queued:0,confirming:0,confirmed:1,failed:0,skipped:1}},items:[item('confirmed','confirmed'),item('skipped','skipped')]}});
    expect(host.textContent).toContain('Completed with issues');
    expect(host.textContent).not.toContain('All updates confirmed');
  });
  it('renders recovery without the optional review snapshot and never invents quantities',async()=>{
    for(const kind of ['submitting','unknown'] as const){
      await render({kind,generation:2,reviewId:'saved-review',operationKey:'saved-key',retry:true,scope:{kind:'retry',batchId:'batch',itemIds:['item']}});
      expect(host.querySelector('.bs-current')).toBeNull();
      expect(host.textContent).not.toContain('Start sync');
      expect(host.textContent).toContain(kind==='unknown'?'submission outcome is still unknown':'Submitting the reviewed destinations');
    }
  });
  it('preserves unknown Independent stock without diagnosing First Count',async()=>{
    await render({...reviewState,review:{...review,mode:'independent_stores',targets:[{...target('unknown-1',1,null,'skipped','unknown_quantity'),mode:'independent_stores'}],counts:{skus:1,validDestinations:0,skippedRows:1}}});
    expect(host.querySelector('.bs-current strong')?.textContent).toBe('Unknown');
    expect(host.textContent).not.toContain('Awaiting first count');
  });
  it('discloses mixed destination eligibility, known skipped quantities and server skipped counts',async()=>{
    await render({...reviewState,review:{...review,mode:'independent_stores',targets:[{...target('mixed-1',1,17),mode:'independent_stores'},{...target('mixed-2',1,23,'skipped','store_paused'),mode:'independent_stores'}],counts:{skus:1,validDestinations:1,skippedRows:1}}});
    expect(host.querySelector('[data-target-quantity="23"]')?.textContent).toBe('23');
    expect(host.textContent).toContain('Store or warehouse is paused');
    expect(host.textContent).toContain('Partially eligible');
    expect(host.textContent).not.toContain('Store and warehouse settings verified.');
    expect([...host.querySelectorAll('.bs-summary-line')].find(row=>row.textContent?.includes('Skipped destinations'))?.textContent).toBe('1Skipped destinations');
  });
  it('distinguishes exhausted confirmation using original observation facts without retry',async()=>{
    const exhausted={...item('uncertain','confirming',false,true),current_attempt:{...item('uncertain','confirming').current_attempt!,target_quantity:60,observed_quantity:59,check_count:4,checked_at:'2026-10-01T09:00:00Z',exhausted_at:'2026-10-01T09:00:00Z'}};
    const props=await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,items:[exhausted]}});
    expect(host.textContent).toContain('Confirming - Needs review');
    expect(host.textContent).toContain('Observed quantity: 59');
    expect(host.textContent).toContain('Checks: 4');
    expect(host.querySelector('time')?.getAttribute('datetime')).toBe('2026-10-01T09:00:00Z');
    expect(host.textContent).not.toContain('Review failed items');
    await act(async()=>click('Check confirmation'));
    expect(props.onCheck).toHaveBeenCalledWith('uncertain',expect.objectContaining({task_id:'task-uncertain'}));
  });
  it('groups reviews by stable variant identity even when display SKUs match',async()=>{
    await render({...reviewState,review:{...review,targets:[{...target('101-1',101,60),display:{...target('101-1',101,60).display,sku:'DUPLICATE',name:'Product 101'}},{...target('202-1',202,0),display:{...target('202-1',202,0).display,sku:'DUPLICATE',name:'Product 202'}}],counts:{skus:2,validDestinations:2,skippedRows:0}}});
    expect(host.querySelectorAll('.bs-sku-card')).toHaveLength(2);
    expect(host.textContent).toContain('Product 101');expect(host.textContent).toContain('Product 202');
    expect([...host.querySelectorAll('.bs-current strong')].map(el=>el.textContent)).toEqual(['60','0']);
  });
  it('never merges result identities solely because display SKU strings match',async()=>{
    const first=item('a','confirmed'),second=item('b','confirmed');
    second.display={...second.display,sku:first.display.sku};
    await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,items:[first,second]}});
    expect(host.querySelectorAll('.bs-sku-card')).toHaveLength(2);
    expect(host.textContent).toContain('Item a');expect(host.textContent).toContain('Item b');
  });
  it('groups result destinations only by positive canonical variant IDs when projected',async()=>{
    const first={...item('a','confirmed'),variant_id:101},second={...item('b','confirmed'),variant_id:101},third={...item('c','confirmed'),variant_id:202};
    second.display={...first.display};third.display={...third.display,sku:first.display.sku};
    await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,items:[first,second,third]}});
    expect(host.querySelectorAll('.bs-sku-card')).toHaveLength(2);
    expect(host.querySelectorAll('.bs-sku-card')[0].querySelectorAll('.bs-result')).toHaveLength(2);
    expect(host.textContent).toContain('Item c');
  });
  it('falls back to separate item identities for invalid projected variant IDs',async()=>{
    const first={...item('a','confirmed'),variant_id:'101'},second={...item('b','confirmed'),variant_id:'101'},third={...item('c','confirmed'),variant_id:0};
    second.display={...second.display,sku:first.display.sku};third.display={...third.display,sku:first.display.sku};
    await render({kind:'batch',generation:2,batchId:'batch',batch:{...batch,items:[first,second,third]}});
    expect(host.querySelectorAll('.bs-sku-card')).toHaveLength(3);
  });
  it('announces changed destination counts without repeated polling announcements',async()=>{
    const props=await render({kind:'batch',generation:2,batchId:'batch',batch});
    const region=host.querySelector('[role="status"][aria-live="polite"]');
    expect(region).toBeTruthy();expect(region?.textContent).toContain('1 confirmed');
    const changed={...batch,revision:2,counts:{...batch.counts,destinations:{...batch.counts.destinations,confirmed:2,confirming:0}}};
    await render({kind:'batch',generation:2,batchId:'batch',batch:changed},props);
    expect(region?.textContent).toContain('2 confirmed');
    const mutations:MutationRecord[]=[];const observer=new MutationObserver(records=>mutations.push(...records));
    observer.observe(region!,{childList:true,subtree:true,characterData:true});
    await render({kind:'batch',generation:2,batchId:'batch',batch:{...changed,revision:3}},props);
    observer.disconnect();expect(mutations).toHaveLength(0);
  });
});
