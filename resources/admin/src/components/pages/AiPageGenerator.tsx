import {canGenerateImageTarget} from '../../cms/imageTargets';
import {questionSessionToken} from '../../cms/aiQuestionSession';
import {sectionImageDirection} from '../../cms/aiImageDirection';
import {generateImageBatch} from '../../cms/aiImageBatch';
import {useAiWorkspace} from '../../cms/useAiWorkspace';
import {aiQuality} from '../../cms/aiQuality';
import {AiDraftExit} from './AiDraftExit';
import React,{useEffect,useRef,useState} from 'react';
import type {CmsPage,PageDocument} from '../../types';
import {readAdminBootstrap} from '../../integration/adminBootstrap';
import {pageToAiDocument,pageSourceSignature,reorderSections,type AiBrief,type BriefAnswers} from '../../cms/cmsAiGenerator';
import {AiBriefQuestions} from './AiBriefQuestions';
import {PageStudioScreens as AiGeneratorScreens} from './PageStudioScreens';
import {uploadMediaAsset} from '../../integration/pageData';
import './AiPageGenerator.css';

export type GeneratorMode='create'|'improve'|'section';
export interface GeneratorImage {sectionId:string;target:string;url:string;prompt:string;ratio:string;generate:boolean;requestId?:string;manualPrompt?:boolean;promptStale?:boolean}
export interface GeneratorMetadata {business:string;productCount:number;categoryCount:number;faqCount:number;textReady:boolean;imageReady:boolean;imageModel:string|null;imageModels:string[];textModel?:string;brand?:{palette:import('../../cms/pageStyle').BrandPalette}}
export interface GeneratorState {
 step:number;mode:GeneratorMode;prompt:string;goal:string;style:string;language:string;voice:string;working:PageDocument;
 brief:AiBrief;token:string;questionToken?:string;boundDirection:string;extra:string;confirmed:boolean;answers:BriefAnswers;
 answerHistory:{question:string;answer:string|string[]}[];scope:string;history:PageDocument[];
 images:GeneratorImage[];source:'existing'|'missing'|'custom';imageModel:string;quality:string;count:number;
 generated:boolean;referenceUrl:string;referenceName:string;remoteQuality:import('../../cms/aiQuality').AiQualityIssue[];activity:{id:string;action:string;model:string;usage?:{input_tokens?:number;output_tokens?:number;total_tokens?:number}}[];qualityAcknowledged:boolean;proposal:PageDocument|null;request:string;pending?:{id:string;action:string;document:PageDocument;direction:string;extra:Record<string,unknown>};
}
const defaults=(page:CmsPage,selected:string|null):GeneratorState=>({step:0,mode:'create',prompt:'',goal:'Request a quote',style:'Industrial & confident',language:'English',voice:'Clear and professional',working:pageToAiDocument(page),brief:{summary:'',questions:[],warnings:[],sections:[]},token:'',boundDirection:'',extra:'',confirmed:false,answers:{},answerHistory:[],scope:selected??page.sections[0]?.id??'',history:[],images:[],source:'existing',imageModel:'',quality:'medium',count:2,generated:false,referenceUrl:'',referenceName:'',remoteQuality:[],activity:[],qualityAcknowledged:false,proposal:null,request:''});
const restore=(key:string,page:CmsPage,selected:string|null)=>{try{const data=JSON.parse(sessionStorage.getItem(key)??'null');if(data?.version===4&&data.signature===pageSourceSignature(page)&&data.state?.working?.sections)return {...defaults(page,selected),...data.state} as GeneratorState;}catch{}return defaults(page,selected);};
type Props={page:CmsPage;selected:string|null;onClose:()=>void;onApply:(document:PageDocument,create:boolean,signature:string)=>Promise<void>};

export function AiPageGenerator({page,selected,onClose,onApply}:Props){
 const key=`jw.cms-ai.v4.${readAdminBootstrap().user.email}.${page.id}`;
 const signature=useRef(pageSourceSignature(page));
 const [s,setState]=useState<GeneratorState>(()=>restore(key,page,selected));
 const [busy,setBusy]=useState(''),[error,setError]=useState(''),[notice,setNotice]=useState('');
 const [metadata,setMetadata]=useState<GeneratorMetadata|null>(null);
 const [exitOpen,setExitOpen]=useState(false);const [visualWarnings,setVisualWarnings]=useState<string[]>([]);
 const [questionsOpen,setQuestionsOpen]=useState(false),[questionError,setQuestionError]=useState('');
 const [tab,setTab]=useState<'ai'|'inspector'>('ai'),[preview,setPreview]=useState(false);
 const busyRef=useRef(false),alive=useRef(true),abort=useRef<AbortController|null>(null);
 const latest=useRef(s);latest.current=s;
 const patch=(value:Partial<GeneratorState>)=>{latest.current={...latest.current,...value,...(value.working?{qualityAcknowledged:false,remoteQuality:[]}:{})};setState(latest.current);};
 const proposal=s.proposal,request=s.request,setProposal=(proposal:PageDocument|null)=>patch({proposal}),setRequest=(request:string)=>patch({request});
 const workspace=useAiWorkspace(page.id,signature.current,s,restored=>{latest.current={...defaults(page,selected),...restored};setState(latest.current);});
 useEffect(()=>{patch({qualityAcknowledged:false});},[visualWarnings.join('|')]);
 const target=s.mode==='section'?selected:null;
 useEffect(()=>{alive.current=true;return()=>{alive.current=false;abort.current?.abort();};},[]);
 useEffect(()=>{try{sessionStorage.setItem(key,JSON.stringify({version:4,signature:signature.current,state:s}));}catch{}},[s,key]);
 useEffect(()=>{let active=true;fetch('/admin/api/cms/ai-generator',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(async response=>{const data=await response.json();if(!response.ok)throw new Error(data.message??'AI configuration unavailable.');if(active){setMetadata(data.data);setState(previous=>({...previous,imageModel:previous.imageModel||data.data.imageModel||''}));}}).catch(e=>{if(active)setError(e.message);});return()=>{active=false;};},[]);
 const api=async(action:string,document:PageDocument,direction:string,extra:Record<string,unknown>={})=>{
  let operationId:string|undefined;
  if(['brief','answer','generate','image','refine'].includes(action)){const pending=latest.current.pending;if(pending&&pending.action!==action)throw new Error('Check the previous AI request before starting another.');operationId=pending?.id??crypto.randomUUID();const next={...latest.current,working:document,qualityAcknowledged:false,remoteQuality:[],pending:{id:operationId,action,document,direction,extra}};patch(next);await workspace.flush(next);}
  const controller=new AbortController();abort.current=controller;
  const r=await fetch('/admin/api/cms/ai-generator',{method:'POST',signal:controller.signal,credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':readAdminBootstrap().csrfToken},body:JSON.stringify({action,document,direction,target,sourcePage:page.id,...extra,...(operationId?{operationId}:{})})});
  const p=await r.json().catch(()=>null);if(operationId&&r.status!==409)patch({pending:undefined,...(r.ok?{activity:[...(latest.current.activity??[]).filter(a=>a.id!==operationId),{id:operationId,action,model:action==='image'?String(extra.imageModel??''):metadata?.textModel??'Configured text model',usage:p?.data?.usage}].slice(-30)}:{})});if(!r.ok)throw new Error(Object.values(p?.errors??{}).flat().join(' ')||p?.message||'The request failed. Your page is unchanged.');return p.data;
 };
 const run=async(label:string,action:()=>Promise<void>,question=false)=>{
  if(busyRef.current)return;if(!workspace.ready){setError(workspace.error||'Loading your saved AI workspace…');return;}busyRef.current=true;setBusy(label);setError('');setQuestionError('');
  try{await action();}catch(e){if(alive.current)(question?setQuestionError:setError)(e instanceof Error?e.message:'The request failed.');}finally{busyRef.current=false;if(alive.current)setBusy('');}
 };
 const accept=(data:any,base:PageDocument)=>{setNotice('');const answerHistory=[...s.answerHistory];for(const answer of data.answerHistory??[]){const i=answerHistory.findIndex(a=>a.question===answer.question);if(i>=0)answerHistory[i]=answer;else answerHistory.push(answer);}patch({working:data.document??base,brief:data.brief,token:data.token,questionToken:data.token,boundDirection:data.direction,answerHistory,confirmed:false,generated:false,step:2});};
 const direction=()=>`${s.prompt}\nGoal: ${s.goal}. Direction: ${s.style}. Language: ${s.language}. Voice: ${s.voice}.\nConstraints: ${s.extra}\nUse verified facts; leave unknown commercial details unclaimed.`;
 const prepare=()=>void run('Understanding your direction and planning the page…',async()=>{
  if(!s.prompt.trim())throw new Error('Tell us a little about the page you want to create.');
  if(metadata&&!metadata.textReady)throw new Error('Enable CMS Vibe Page Builder and verify a text model in AI Connect first.');
  const base=s.mode==='create'?{...pageToAiDocument(page),title:'New AI page',slug:`ai-page-${Date.now()}`,sections:[]}:pageToAiDocument(page);
  const data=await api('brief',base,direction(),{mode:s.mode});accept(data,base);patch({answers:{},answerHistory:[],history:[],images:[]});setProposal(null);
 });
 const refreshBrief=()=>void run('Updating your design brief…',async()=>{const data=await api('brief',s.working,`${s.boundDirection}\nMerchant-edited brief: ${s.brief.summary}\nSection purposes: ${JSON.stringify(s.brief.sections)}\nAdditional constraints: ${s.extra}`,{mode:'improve'});accept(data,s.working);});
 const answerQuestions=()=>void run('Updating the brief with your answers…',async()=>{const token=await questionSessionToken(s,async id=>{const response=await fetch('/admin/api/cms/ai-tasks/'+encodeURIComponent(id),{credentials:'same-origin',headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Your answers are kept. Reopen the brief before continuing.');return (await response.json()).data;});patch({questionToken:token});const data=await api('answer',s.working,s.boundDirection,{token,answers:s.answers});accept(data,s.working);patch({answers:{}});setQuestionsOpen(false);setNotice(data.brief.questions.length?'Your brief is updated. A few details still need your input.':'Your answers are included. Review the updated brief before confirming.');},true);
 const update=(working:PageDocument)=>{patch({working,token:'',confirmed:false});setProposal(null);};
 const reorder=(from:number,to:number)=>{if(s.mode==='section')return;update({...s.working,sections:reorderSections(s.working.sections,from,to)});setNotice(`Section moved to position ${to+1}. Update your brief before confirming.`);};
 const confirm=(images:GeneratorImage[])=>{if(!s.token){refreshBrief();return;}if(s.brief.questions.length){setQuestionsOpen(true);return;}if(s.generated){patch({confirmed:true,step:3});return;}void run('Writing your section copy…',async()=>{const data=await api('generate',s.working,s.boundDirection,{token:s.token});const working=data.document as PageDocument;patch({working,confirmed:true,generated:true,step:3,images:images.map(image=>({...image,prompt:image.manualPrompt?image.prompt:sectionImageDirection(working.sections.find(section=>section.id===image.sectionId)!,image.target,metadata?.business??page.title,s.goal,s.style,image.ratio)}))});});};
 const generate=()=>void run('Preparing your page preview…',async()=>{
  if(!s.confirmed||!s.token)throw new Error('Review and confirm your design brief first.');
  const localIssues=aiQuality(s.working,metadata?.brand?.palette);if(localIssues.some(issue=>issue.severity==='block'))throw new Error(localIssues.filter(issue=>issue.severity==='block').map(issue=>issue.message).join(' '));
  const checked=await api('quality',s.working,s.boundDirection);patch({remoteQuality:checked.issues??[]});if(checked.issues?.some((issue:any)=>issue.severity==='block'))throw new Error('Fix the blocked destinations before generating.');
  const selectedImages=s.source==='existing'?[]:s.images.filter(i=>i.generate&&s.working.sections.some(section=>section.id===i.sectionId&&canGenerateImageTarget(section,i.target)));
  if(!s.generated)throw new Error('Return to Design brief and generate your section copy first.');
  const data={document:s.working};
  let next=data.document as PageDocument;let imageState=[...s.images];patch({working:next,generated:true,scope:target??next.sections[0]?.id??'',history:s.generated?s.history:[],step:selectedImages.length?3:4});
  await generateImageBatch(next,imageState,selectedImages,async(image,document,index)=>{
   setBusy(`Generating image ${index+1} of ${selectedImages.length}…`);
   return api('image',document,s.boundDirection,{token:s.token,sectionId:image.sectionId,imageTarget:image.target,imagePrompt:image.prompt,imageModel:s.imageModel,quality:s.quality,ratio:image.ratio,referenceUrl:s.referenceUrl||null,requestId:image.requestId});
  },(document,images)=>patch({working:document,images}),message=>setError(`Your text draft is ready. Image generation failed: ${message}. Successful images are retained. Retry the remaining image from Images.`));
 });
 useEffect(()=>{const pending=s.pending;if(!pending||busy)return;let live=true;let timer:ReturnType<typeof setTimeout>;let attempts=0;
 const check=async()=>{try{const response=await fetch('/admin/api/cms/ai-tasks/'+pending.id,{credentials:'same-origin',headers:{Accept:'application/json'}});if(response.status===404){if(live)setNotice('Your last request was not recorded. Retry the same action to check it safely.');return;}if(!response.ok)throw new Error('Could not check the last AI request.');const task=(await response.json()).data;if(!live)return;
 if(task.status==='completed'){const data=task.response?.data;if(!data?.document)throw new Error('The recovered request has no editable result.');if(['brief','answer'].includes(pending.action)){accept(data,pending.document);patch({pending:undefined,answers:{}});}else if(pending.action==='refine')patch({proposal:data.document,pending:undefined});else{const images=pending.action==='image'?latest.current.images.map(i=>i.sectionId===pending.extra.sectionId&&i.target===pending.extra.imageTarget?{...i,url:data.url,generate:false}:i):latest.current.images;patch({working:data.document,images,generated:true,step:3,pending:undefined});}patch({activity:[...(latest.current.activity??[]).filter(a=>a.id!==pending.id),{id:pending.id,action:pending.action,model:task.model??'Configured model',usage:data.usage}].slice(-30)});setNotice('Recovered your completed AI request. Review the result before applying.');}
 else if(task.status==='failed'){patch({pending:undefined});setError(task.response?.message||'The last AI request failed. Your existing draft is retained. Retry when ready.');}
 else if(++attempts<60){setNotice('Your last AI request is still processing. Checking its saved status; no new AI call is sent.');timer=setTimeout(check,2500);}else setError('The request is still unconfirmed. Reopen later to check its status; do not start a duplicate request.');
 }catch(e){if(live)setError(e instanceof Error?e.message:'Recovery unavailable.');}};void check();return()=>{live=false;clearTimeout(timer);};},[s.pending?.id,busy]);
 const uploadReference=(file:File)=>void run('Uploading your reference to the media library…',async()=>{if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>10*1024*1024)throw new Error('Choose a JPG, PNG or WebP up to 10 MB.');const url=await uploadMediaAsset(readAdminBootstrap(),file);patch({referenceUrl:url,referenceName:file.name,images:s.images.map(i=>({...i,requestId:undefined}))});});
 const refine=()=>void run('Preparing a scoped proposal…',async()=>{if(!request.trim())throw new Error('Describe the change you want to preview.');const data=await api('refine',s.working,`${s.boundDirection}\nRequested change: ${request}`,{target:target??(s.scope||null)});setProposal(data.document);});
 const review=()=>void run('Checking live destinations…',async()=>{const data=await api('quality',s.working,s.boundDirection);patch({remoteQuality:data.issues,qualityAcknowledged:false});setNotice(data.issues.length?'Review the destination findings before applying.':'Live destination checks passed. Destinations are checked again when you apply.');});
 const apply=()=>void run('Saving your editable CMS draft…',async()=>{if(proposal)throw new Error('Apply or discard the proposal first.');const remote=await api('quality',s.working,s.boundDirection);if(JSON.stringify(remote.issues)!==JSON.stringify(s.remoteQuality)){patch({remoteQuality:remote.issues,qualityAcknowledged:false});if(remote.issues.length)throw new Error('Review the destination checks in Quality review before applying.');}const issues=[...aiQuality(s.working,metadata?.brand?.palette),...remote.issues];if(issues.some(i=>i.severity==='block'))throw new Error('Fix the blocking items in Quality review before applying.');if((issues.length||visualWarnings.length)&&!s.qualityAcknowledged)throw new Error('Review and acknowledge the quality warnings before applying.');await workspace.flush({...latest.current,history:[]});await onApply(s.working,s.mode==='create',signature.current);await workspace.discard();sessionStorage.removeItem(key);});
 const changeCandidate=(working:PageDocument)=>{patch({working,history:[...s.history,structuredClone(s.working)].slice(-10),images:s.images.map(image=>{const section=working.sections.find(section=>section.id===image.sectionId);const previous=s.working.sections.find(section=>section.id===image.sectionId);if(!section||JSON.stringify(section)===JSON.stringify(previous))return image;return image.manualPrompt?{...image,promptStale:true}:{...image,prompt:sectionImageDirection(section,image.target,metadata?.business??page.title,s.goal,s.style,image.ratio),requestId:undefined};})});setProposal(null);};
 const discard=()=>{setBusy('Discarding AI workspace…');void workspace.discard().then(()=>{sessionStorage.removeItem(key);onClose();}).catch(e=>setError(e.message)).finally(()=>setBusy(''));};
 const keep=()=>{setBusy('Saving your progress…');void workspace.flush().then(onClose).catch(e=>setError(e.message)).finally(()=>setBusy(''));};
 return <><AiGeneratorScreens page={page} selected={selected} state={s} patch={patch} metadata={metadata} busy={busy||(!workspace.ready&&!workspace.error?'Loading your saved AI workspace…':'')} error={error||workspace.error} notice={notice} onDismiss={()=>setNotice('')} onClose={()=>setExitOpen(true)} prepare={prepare} confirm={confirm} refresh={refreshBrief} reorder={reorder} update={update} generate={generate} proposal={proposal} setProposal={setProposal} request={request} setRequest={setRequest} refine={refine} review={review} apply={apply} changeCandidate={changeCandidate} tab={tab} setTab={setTab} preview={preview} setPreview={setPreview} onQuestions={()=>{setQuestionError('');setQuestionsOpen(true);}} questionsOpen={questionsOpen||exitOpen} visualWarnings={visualWarnings} onVisualQuality={setVisualWarnings} uploadReference={uploadReference}/>{exitOpen&&<div className="cms-ai-generator question-host"><AiDraftExit busy={!!busy} error={error||workspace.error} onBack={()=>setExitOpen(false)} onKeep={keep} onDiscard={discard}/></div>}{questionsOpen&&s.brief.questions.length>0&&<div className="cms-ai-generator question-host"><AiBriefQuestions questions={s.brief.questions} answers={s.answers} onChange={answers=>patch({answers})} onSubmit={answerQuestions} onClose={()=>setQuestionsOpen(false)} busy={!!busy} error={questionError}/></div>}</>;
}
