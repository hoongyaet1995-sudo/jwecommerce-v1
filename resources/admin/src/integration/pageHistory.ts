import type {PageDocument,PageDraftPayload} from '../types';
import {readAdminBootstrap} from './adminBootstrap';
export interface PageRevision {id:string;version:number;kind:string;name:string|null;actor:string;createdAt:string;restoredFrom:number|null;restoredFromVersion:number|null;isCurrent:boolean;isLive:boolean;hash:string;summary:string}
export class HistoryError extends Error {constructor(message:string,public status:number){super(message);}}
export async function historyRequest<T>(page:string,suffix='',method='GET',body?:unknown):Promise<T>{
 const b=readAdminBootstrap();const response=await fetch(`/admin/api/pages/${encodeURIComponent(page)}/history${suffix}`,{method,credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':b.csrfToken},...(body?{body:JSON.stringify(body)}:{})});const result=await response.json().catch(()=>null);
 if(!response.ok)throw new HistoryError(Object.values(result?.errors??{}).flat().join(' ')||result?.message||'History could not be loaded. Your page is unchanged.',response.status);return result;
}
export const revisionDocument=(page:string,id:string)=>historyRequest<{data:{id:string;document:PageDocument}}>(page,'/'+id);
export const restoreRevision=(page:string,id:string,lockVersion:number)=>historyRequest<{data:PageDraftPayload}>(page,'/'+id+'/restore','POST',{lockVersion});
