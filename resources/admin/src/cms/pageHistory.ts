import type {PageDocument} from '../types';
export interface HistoryChange {section:string;field:string;before:string;after:string}
const text=(v:unknown)=>v==null?'Not set':typeof v==='string'?v:JSON.stringify(v);
export function historyChanges(before:PageDocument,after:PageDocument):HistoryChange[]{
 const changes:HistoryChange[]=[];
 for(const field of new Set([...Object.keys(before),...Object.keys(after)])){
  if(field==='sections')continue;
  if(JSON.stringify((before as any)[field])!==JSON.stringify((after as any)[field]))changes.push({section:'Page settings',field,before:text((before as any)[field]),after:text((after as any)[field])});
 }
 const current=new Map(after.sections.map(s=>[s.id,s]));
 before.sections.forEach((s,index)=>{const next=current.get(s.id);if(!next){changes.push({section:s.title||s.type,field:'Section removed',before:s.title||s.type,after:'Removed'});return;}
  if(after.sections.findIndex(a=>a.id===s.id)!==index)changes.push({section:s.title||s.type,field:'Position',before:String(index+1),after:String(after.sections.findIndex(a=>a.id===s.id)+1)});
  for(const field of new Set([...Object.keys(s),...Object.keys(next)])){if(field==='id')continue;if(JSON.stringify((s as any)[field])!==JSON.stringify((next as any)[field]))changes.push({section:s.title||s.type,field,before:text((s as any)[field]),after:text((next as any)[field])});}
 });
 for(const s of after.sections)if(!before.sections.some(b=>b.id===s.id))changes.push({section:s.title||s.type,field:'Section added',before:'Not present',after:s.title||s.type});
 return changes;
}
