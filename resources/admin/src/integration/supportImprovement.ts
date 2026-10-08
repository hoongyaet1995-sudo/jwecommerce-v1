export type ReviewStatus = 'Needs review'|'Published'|'Dismissed';
export type ImprovementDraft={id:number;question:string;originalQuestion:string;answer:string;category:string;channel:string;status:ReviewStatus;version:number;conversationId:string|null;sourceMessageId:number|null;staffAnswer:string;publishedFaqId:string|null;createdAt:string;reviewedAt:string|null};
export type ImprovementQuestion={sourceMessageId:number;conversationId:string;question:string;channel:string;resolved:boolean;reason:string;count?:number;createdAt:string;failure?:boolean};
export type ImprovementOverview={counts:{received:number;answered:number;improve:number;handoff:number;failures:number};questions:ImprovementQuestion[];failures:ImprovementQuestion[];limited:boolean;activatedAt:string;from:string};
export type DraftList={drafts:ImprovementDraft[];categories:{id:string;label:string}[];pending:number;limited:boolean;nextCursor?:number|null};
async function request<T>(path:string,method='GET',body?:unknown):Promise<T>{
 const response=await fetch('/admin/api/support/improvement'+path,{method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||''}:{})},body:body?JSON.stringify(body):undefined});
 const data=await response.json().catch(()=>null);if(!response.ok||!data)throw new Error(data?.message||'Unable to load support improvements. Please retry.');return data;
}
export const supportImprovement={
 overview:(days:number,channel:string)=>request<ImprovementOverview>(`?days=${days}&channel=${encodeURIComponent(channel)}`),
 drafts:(before?:number)=>request<DraftList>('/drafts'+(before?`?before=${before}`:'')),
 create:(sourceMessageId:number)=>request<{draft:ImprovementDraft}>('/drafts','POST',{sourceMessageId}),
 save:(d:ImprovementDraft)=>request<{draft:ImprovementDraft}>(`/drafts/${d.id}`,'PATCH',{version:d.version,question:d.question,answer:d.answer,category:d.category}),
 review:(d:ImprovementDraft,publish:boolean)=>request<{draft:ImprovementDraft;faq?:{version:number;scope:unknown}}>(`/drafts/${d.id}/${publish?'publish':'dismiss'}`,'POST',{version:d.version,...(publish?{policyConfirmed:true}:{})}),
};
