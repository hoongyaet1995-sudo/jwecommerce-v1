import type {MessageAttachment} from '../../types/support';
export function uploadSupportMedia(url:string,file:File,kind:'image'|'audio'|'document',onProgress:(percent:number)=>void,signal:AbortSignal):Promise<MessageAttachment>{
 return new Promise((resolve,reject)=>{
  const xhr=new XMLHttpRequest();let settled=false;
  const finish=(error?:Error,attachment?:MessageAttachment)=>{if(settled)return;settled=true;signal.removeEventListener('abort',abort);error?reject(error):resolve(attachment!);};
  const abort=()=>{xhr.abort();finish(new DOMException('Upload cancelled.','AbortError'));};
  if(signal.aborted){finish(new DOMException('Upload cancelled.','AbortError'));return;}
  xhr.open('POST',url);xhr.timeout=120000;xhr.withCredentials=true;xhr.setRequestHeader('Accept','application/json');xhr.setRequestHeader('X-CSRF-TOKEN',document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'');
  xhr.upload.onprogress=e=>{if(e.lengthComputable)onProgress(Math.min(100,Math.round(e.loaded/e.total*100)));};
  xhr.onerror=()=>finish(new Error('Attachment upload failed. Check your connection and retry.'));xhr.ontimeout=()=>finish(new Error('Upload timed out. Retry the upload.'));xhr.onabort=()=>finish(new DOMException('Upload cancelled.','AbortError'));
  xhr.onload=()=>{try{const data=JSON.parse(xhr.responseText);if(xhr.status<200||xhr.status>=300)throw new Error(data.message||'Attachment upload failed.');if(!data.attachment?.id||!data.attachment?.type)throw new Error('Invalid attachment response.');finish(undefined,data.attachment);}catch(e){finish(e instanceof Error?e:new Error('Invalid attachment response.'));}};
  signal.addEventListener('abort',abort,{once:true});const form=new FormData();form.append(kind,file);xhr.send(form);
 });
}
export function mediaSize(bytes?:number):string{return bytes==null?'':bytes<1048576?Math.ceil(bytes/1024)+' KB':(bytes/1048576).toFixed(1)+' MB';}
