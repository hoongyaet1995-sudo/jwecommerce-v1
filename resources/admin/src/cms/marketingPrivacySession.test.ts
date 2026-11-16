import {describe,it,expect,vi} from 'vitest';
import {JSDOM} from 'jsdom';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
const script=readFileSync(resolve(process.cwd(),'../../public/marketing-studio/tracking.js'),'utf8');
function page(accepted:boolean|null=null,showPrompt=true){
 const dom=new JSDOM('<!doctype html><html><head></head><body><main><h1>Store</h1><p>Content</p></main><footer>Copyright</footer></body></html>',{url:'http://localhost/shop',runScripts:'outside-only'});
 const w=dom.window;Object.defineProperty(w.crypto,'randomUUID',{value:()=> '11111111-1111-4111-8111-111111111111'});
 w.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','')};w.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open')};
 const fetch=vi.fn().mockResolvedValue({ok:true,json:async()=>({})});w.fetch=fetch;
 const boot=w.document.createElement('script');boot.id='jw-marketing-consent';boot.textContent=JSON.stringify({enabled:true,accepted,showPrompt,csrf:'local-test'});w.document.body.append(boot);w.eval(script);return {w,fetch,close:()=>w.close()};
}
const settle=async()=>{await new Promise(r=>setTimeout(r,0));};
describe('privacy session prompt',()=>{
 it('has no permanent footer entry and does not track an undecided visitor',()=>{const p=page();expect(p.w.document.getElementById('jw-privacy-choices')).toBeNull();expect(p.w.document.querySelector('.jw-privacy-banner')).not.toBeNull();expect(p.fetch).not.toHaveBeenCalled();p.close()});
 it('does not repeat a prompt already claimed by another page in the session',()=>{const p=page(null,false);expect(p.w.document.querySelector('.jw-privacy-banner')).toBeNull();expect(p.fetch).not.toHaveBeenCalled();p.close()});
 it('temporarily closes without sending a consent or tracking request',()=>{const p=page();const b=[...p.w.document.querySelectorAll('button')].find(n=>n.textContent==='Not now');expect(b).toBeDefined();b!.click();expect(p.w.document.querySelector('.jw-privacy-banner')).toBeNull();expect(p.fetch).not.toHaveBeenCalled();p.close()});
 it('accepts before starting page tracking',async()=>{const p=page();[...p.w.document.querySelectorAll('button')].find(n=>n.textContent==='Allow analytics')!.click();await settle();expect(p.fetch.mock.calls.map(c=>c[0])).toEqual(['/marketing/tracking/consent','/marketing/tracking/page']);expect(JSON.parse(p.fetch.mock.calls[0][1].body).accepted).toBe(true);expect(p.w.document.querySelector('.jw-privacy-banner')).toBeNull();p.close()});
 it('declines without starting page tracking',async()=>{const p=page();[...p.w.document.querySelectorAll('button')].find(n=>n.textContent==='Decline')!.click();await settle();expect(p.fetch.mock.calls.map(c=>c[0])).toEqual(['/marketing/tracking/consent']);expect(JSON.parse(p.fetch.mock.calls[0][1].body).accepted).toBe(false);p.close()});
 it('honors prior accept and decline records',()=>{const yes=page(true,false);expect(yes.fetch.mock.calls[0][0]).toBe('/marketing/tracking/page');expect(yes.w.document.querySelector('.jw-privacy-banner')).toBeNull();yes.close();const no=page(false,false);expect(no.fetch).not.toHaveBeenCalled();expect(no.w.document.querySelector('.jw-privacy-banner')).toBeNull();no.close()});
 it('keeps an unsuccessful consent save retryable and does not start tracking',async()=>{const p=page();p.fetch.mockResolvedValueOnce({ok:false});[...p.w.document.querySelectorAll('button')].find(n=>n.textContent==='Allow analytics')!.click();await settle();expect(p.fetch.mock.calls.map(c=>c[0])).toEqual(['/marketing/tracking/consent']);expect(p.w.document.querySelector('.jw-privacy-banner')).not.toBeNull();expect(p.w.document.querySelector('.jw-privacy-dialog')?.hasAttribute('open')).toBe(true);p.close()});
 it('closing the settings dialog also dismisses the session prompt without consenting',()=>{const p=page();[...p.w.document.querySelectorAll('button')].find(n=>n.textContent==='Settings')!.click();p.w.document.querySelector<HTMLButtonElement>('#jw-privacy-cancel')!.click();expect(p.w.document.querySelector('.jw-privacy-banner')).toBeNull();expect(p.fetch).not.toHaveBeenCalled();p.close()});
 it('does not restore the old prompt when navigating back through the browser cache',()=>{const p=page();p.w.dispatchEvent(new p.w.Event('pagehide'));expect(p.w.document.querySelector('.jw-privacy-banner')).toBeNull();expect(p.fetch).not.toHaveBeenCalled();p.close()});
});
