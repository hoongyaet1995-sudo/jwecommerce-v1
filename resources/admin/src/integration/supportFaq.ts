import type { AIKnowledgeScopeConfig } from '../types/aiAutoReply';
export async function supportFaqRequest(body?: { version: number; personality:{toneOfVoice:string;customToneInstructions?:string;maxOutputTokens:number}; autoReplyEnabled:boolean; primaryLanguage:'auto_detect'|'en'|'ms'|'zh'; identity: {botName: string; roleTitle: string}; faq: AIKnowledgeScopeConfig['faqKnowledge'] }) {
  const response=await fetch('/admin/api/support/faq',{method:body?'PUT':'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||''}:{})},body:body?JSON.stringify(body):undefined});
  const data=await response.json().catch(()=>null);
  if(!response.ok||!data)throw new Error(data?.message||'Unable to publish FAQs. Retry.');
  return data as {personality?:{toneOfVoice:import('../types/aiAutoReply').AIToneOfVoice;customToneInstructions?:string;maxOutputTokens:number}|null;autoReplyEnabled?:boolean;primaryLanguage?:'auto_detect'|'en'|'ms'|'zh';identity?:{botName:string;roleTitle:string}|null;version:number;faq:AIKnowledgeScopeConfig['faqKnowledge']|null};
}
