export type SearchKind='product'|'category'|'collection'|'page';
export interface SearchMetadata {title:string;rawTitle?:string;description:string;source:string;version:number;titleLocked:boolean;descriptionLocked:boolean;excluded:boolean;hash:string;topics:string[]}
export interface SearchTask {id:number;status:string;error:string|null;model:string|null;usage:Record<string,number>|null;before:SearchMetadata;result:{title:string;description:string;topics:string[]}|null}
export interface SearchEntity {metadata:SearchMetadata;facts:{name:string;description:string};origin:string;url:string;task:SearchTask|null}
export async function searchRequest<T>(path:string,method='GET',body?:unknown):Promise<T>{
 const response=await fetch('/admin/api/search'+path,{method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(body===undefined?{}:{'Content-Type':'application/json'}),...(['GET','HEAD'].includes(method.toUpperCase())?{}:{'X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content??''})},body:body===undefined?undefined:JSON.stringify(body)});
 const data=await response.json().catch(()=>null);if(!response.ok){throw new Error(data?.message??'Search settings could not be loaded. Reload and try again.')}return data;
}

const publicationTokens=new Map<string,string>();
export function savePublicationToken(id:string,token:string){publicationTokens.set(id,token)}
export function publicationToken(path:string){const match=path.match(/\/pages\/(\d+)\/(?:publish|homepage)$/);return match?publicationTokens.get(match[1]):undefined}
