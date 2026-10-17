import type {PageDocument} from '../types';
import type {GeneratorImage} from '../components/pages/AiPageGenerator';

/** Sequential checkpoints retain successful assets. Unknown failures stop before another paid call. */
export async function generateImageBatch(document:PageDocument,images:GeneratorImage[],selected:GeneratorImage[],
 generate:(image:GeneratorImage,document:PageDocument,index:number)=>Promise<{document:PageDocument;url:string}>,
 checkpoint:(document:PageDocument,images:GeneratorImage[])=>void,failed:(message:string)=>void){
 let current=document,state=[...images];
 for(const [index,image] of selected.entries()){
  // Success flags survive refresh and are authoritative even if a stale selection was captured.
  const latest=state.find(i=>i.sectionId===image.sectionId&&i.target===image.target);
  if(!latest?.generate)continue;
  const request={...latest,requestId:latest.requestId??crypto.randomUUID()};
  state=state.map(i=>i===latest?request:i);checkpoint(current,state);
  try{
   const result=await generate(request,current,index);current=result.document;
   state=state.map(i=>i===request?{...i,url:result.url,generate:false}:i);checkpoint(current,state);
  }catch(error){failed(error instanceof Error?error.message:'Provider error');break;}
 }
}
