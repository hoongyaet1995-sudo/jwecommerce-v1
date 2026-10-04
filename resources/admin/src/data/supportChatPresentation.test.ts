// @vitest-environment jsdom
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {it,expect,vi,afterEach} from 'vitest';
const blade=readFileSync(resolve(process.cwd(),'../../resources/views/components/storefront/support-chat.blade.php'),'utf8');
afterEach(()=>{vi.restoreAllMocks();vi.unstubAllGlobals();document.body.innerHTML=''});
it('shows messages immediately, replaces thinking, restores failures and scrolls when reopening',async()=>{
 document.body.innerHTML='<meta name="csrf-token" content="test">'+blade.replace(/<script>[\s\S]*?<\/script>/,'');
 vi.stubGlobal('matchMedia',()=>({matches:false}));vi.stubGlobal('requestAnimationFrame',(fn:any)=>{fn();return 1});vi.stubGlobal('setInterval',()=>1);vi.stubGlobal('setTimeout',()=>1);
 const stream=document.querySelector<HTMLElement>('#sf-support-stream')!;Object.defineProperty(stream,'scrollHeight',{get:()=>900});Object.defineProperty(stream,'clientHeight',{get:()=>200});
 let finish:any;let fail=false;const postIds:string[]=[];
 vi.stubGlobal('fetch',vi.fn((url:string,options:any)=>{
 if(url==='/support/faq')return Promise.resolve({ok:true,json:async()=>({categories:[],greetingMessage:{en:'Welcome'},primaryLanguage:'en'})});
 if(options?.method==='POST'){postIds.push(JSON.parse(options.body).client_message_id);return new Promise((resolve)=>{finish=()=>resolve({ok:!fail,json:async()=>fail?{message:'Network failed'}:{conversation:{messages:[{id:'1',role:'customer',content:'Hello',createdAt:new Date().toISOString()},{id:'2',role:'ai',content:'Reply',createdAt:new Date().toISOString()}]}}})})}
 return Promise.resolve({ok:true,json:async()=>({conversation:{messages:[{id:'old',role:'ai',content:'Old',createdAt:new Date().toISOString()}]}})});
 }));
 new Function(blade.match(/<script>([\s\S]*?)<\/script>/)![1])();
 document.querySelector<HTMLButtonElement>('#sf-support-launcher')!.click();await vi.waitFor(()=>expect(stream.textContent).toContain('Old'));
 document.querySelector<HTMLButtonElement>('#sample-chat-tab')!.click();expect(stream.scrollTop).toBe(900);
 const input=document.querySelector<HTMLTextAreaElement>('#sf-support-input')!;input.value='Hello';document.querySelector('#sf-support-form')!.dispatchEvent(new Event('submit',{cancelable:true}));
 expect(stream.querySelector('[data-pending].user')?.textContent).toBe('Hello');expect(stream.textContent).toContain('AI is thinking');expect(input.value).toBe('');expect(stream.scrollTop).toBe(900);
 finish();await vi.waitFor(()=>expect(stream.textContent).toContain('Reply'));expect(stream.querySelectorAll('[data-pending]')).toHaveLength(0);expect([...stream.querySelectorAll('.message')].filter(n=>n.textContent==='Hello')).toHaveLength(1);
 fail=true;input.value='Retry me';document.querySelector('#sf-support-form')!.dispatchEvent(new Event('submit',{cancelable:true}));finish();await vi.waitFor(()=>expect(input.disabled).toBe(false));expect(input.value).toBe('Retry me');expect(stream.querySelectorAll('[data-pending]')).toHaveLength(0);
 document.querySelector('#sf-support-form')!.dispatchEvent(new Event('submit',{cancelable:true}));expect(postIds[1]).toBe(postIds[2]);finish();await vi.waitFor(()=>expect(input.disabled).toBe(false));
 stream.scrollTop=0;document.querySelector<HTMLButtonElement>('#sf-support-close')!.click();document.querySelector<HTMLButtonElement>('#sf-support-launcher')!.click();expect(stream.scrollTop).toBe(900);
});

it('renders escalation only in the AI bubble and submits a human request without a thinking bubble',async()=>{
 document.body.innerHTML='<meta name="csrf-token" content="test">'+blade.replace(/<script>[\s\S]*?<\/script>/,'');
 vi.stubGlobal('matchMedia',()=>({matches:false}));vi.stubGlobal('requestAnimationFrame',(fn:any)=>{fn();return 1});vi.stubGlobal('setTimeout',()=>1);
 let finish:any;let posted:any;
 const response={status:'open',messages:[{id:'1',role:'ai',content:'Need help?',actions:[{type:'request_human'}],createdAt:new Date().toISOString()}]};
 vi.stubGlobal('fetch',vi.fn((url:string,options:any)=>{
  if(url==='/support/faq')return Promise.resolve({ok:true,json:async()=>({categories:[],primaryLanguage:'en'})});
  if(options?.method==='POST'){posted=JSON.parse(options.body);return new Promise(resolve=>{finish=()=>resolve({ok:true,json:async()=>({conversation:{status:'waiting_human',messages:response.messages}})})})}
  return Promise.resolve({ok:true,json:async()=>({conversation:response})});
 }));
 new Function(blade.match(/<script>([\s\S]*?)<\/script>/)![1])();
 document.querySelector<HTMLButtonElement>('#sf-support-launcher')!.click();
 await vi.waitFor(()=>expect(document.querySelector('[data-request-human]')).not.toBeNull());
 expect(document.querySelector('.composer [data-request-human]')).toBeNull();expect(document.querySelector('#sf-request-human')).toBeNull();
 expect(document.querySelector('.message [data-request-human]')).not.toBeNull();
 document.querySelector<HTMLButtonElement>('[data-request-human]')!.click();
 const form=document.querySelector<HTMLFormElement>('[data-contact-form]')!;expect(form.classList.contains('contact-form')).toBe(true);expect(posted).toBeUndefined();form.querySelector<HTMLInputElement>('[name=name]')!.value='Visitor';form.querySelector<HTMLInputElement>('[name=email]')!.value='visitor@example.test';document.querySelector<HTMLButtonElement>('#sf-support-close')!.click();document.querySelector<HTMLButtonElement>('#sf-support-launcher')!.click();await Promise.resolve();await Promise.resolve();expect(document.querySelector('[data-contact-form]')).toBe(form);expect(form.querySelector<HTMLInputElement>('[name=name]')!.value).toBe('Visitor');form.dispatchEvent(new Event('submit',{cancelable:true}));expect(posted.contact.name).toBe('Visitor');expect(posted.content).toContain('human agent');expect(document.querySelector('.thinking')).toBeNull();
 finish();await vi.waitFor(()=>expect(document.querySelector('[data-request-human]')).toBeNull());
 expect(document.querySelector('.conversation-status')!.textContent).toContain('waiting');
});

