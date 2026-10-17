import React,{useEffect,useRef,useState} from 'react';
import {ArrowLeft,ArrowRight,Check,CheckCheck,HelpCircle,X} from 'lucide-react';
import {questionError,type BriefAnswers,type BriefQuestion} from '../../cms/cmsAiGenerator';

export function AiBriefQuestions({questions,answers,onChange,onSubmit,onClose,busy,error}:{questions:BriefQuestion[];answers:BriefAnswers;onChange:(a:BriefAnswers)=>void;onSubmit:()=>void;onClose:()=>void;busy:boolean;error:string}){
 const [index,setIndex]=useState(0),[review,setReview]=useState(false),[validation,setValidation]=useState('');
 const root=useRef<HTMLDivElement>(null);const previousFocus=useRef<HTMLElement|null>(null);
 const q=questions[index];
 useEffect(()=>{previousFocus.current=document.activeElement as HTMLElement;return()=>previousFocus.current?.focus();},[]);
 useEffect(()=>{const el=root.current?.querySelector<HTMLElement>('textarea,select,input,button[data-answer-option]');el?.focus();setValidation('');},[index,review]);
 const next=()=>{const error=questionError(q,answers[q.id]);if(error){setValidation(error);return;}if(index===questions.length-1)setReview(true);else setIndex(index+1);};
 const set=(value:string|string[])=>{onChange({...answers,[q.id]:value});setValidation('');};
 const submit=()=>{for(let i=0;i<questions.length;i++){const error=questionError(questions[i],answers[questions[i].id]);if(error){setIndex(i);setReview(false);setValidation(error);return;}}onSubmit();};
 return <div className="ai-question-scrim" onKeyDown={e=>{
  if(e.key==='Escape'&&!busy){e.stopPropagation();onClose();}
  if(e.key==='Tab'){const list=[...root.current!.querySelectorAll<HTMLElement>('button:not(:disabled),textarea,select,input')].filter(el=>el.getClientRects().length);if(!list.length)return;const first=list[0],last=list[list.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}
 }}><div className="ai-question-dialog" ref={root} role="dialog" aria-modal="true" aria-labelledby="ai-question-heading" aria-describedby="ai-question-description">
 <div className="question-dialog-top"><span className="question-badge"><HelpCircle/> A FEW DETAILS, TOGETHER</span><button type="button" className="icon-button" aria-label="Close questions and keep answers" disabled={busy} onClick={onClose}><X/></button></div>
 <div className="question-progress"><div style={{width:`${(review?questions.length:index+1)/questions.length*100}%`}}/></div>
 {review?<><span className="tiny-label">YOUR ANSWERS</span><h2 id="ai-question-heading">A clearer picture.</h2><p className="lead" id="ai-question-description">Review your answers before we update your design brief.</p><div className="answer-review">{questions.map((item,i)=><button type="button" className="answer-review-row" key={item.id} disabled={busy} onClick={()=>{setIndex(i);setReview(false);}}><span className="outline-num">{String(i+1).padStart(2,'0')}</span><span><strong>{item.question}</strong><small>{Array.isArray(answers[item.id])?(answers[item.id] as string[]).join(', ')||'Skipped':answers[item.id]||'Skipped'}</small></span><span className="muted">Edit</span></button>)}</div></>:<><span className="tiny-label">Question {index+1} of {questions.length} · {q.required?'Required':'Optional'}</span><h2 id="ai-question-heading">{q.question}</h2><p className="lead" id="ai-question-description">{q.help||'Your answer helps shape the page. Use only information you can confirm.'}</p><div className="question-answer" aria-label={`Answer to question ${index+1}`}>
 {q.type==='text'&&<textarea aria-label={q.question} value={typeof answers[q.id]==='string'?answers[q.id] as string:''} onChange={e=>set(e.target.value)} placeholder="Tell us a little more…" maxLength={6000} disabled={busy}/>}
 {q.type==='select'&&<select aria-label={q.question} value={typeof answers[q.id]==='string'?answers[q.id] as string:''} onChange={e=>set(e.target.value)} disabled={busy}><option value="">Choose a direction…</option>{q.options.map(o=><option key={o}>{o}</option>)}</select>}
 {(q.type==='radio'||q.type==='multiple_select')&&<div className="question-options" role="group" aria-label={q.question}>{q.options.map(o=>{const chosen=q.type==='multiple_select'?Array.isArray(answers[q.id])&&answers[q.id].includes(o):answers[q.id]===o;return <button data-answer-option key={o} type="button" className={`question-option ${chosen?'selected':''}`} aria-pressed={chosen} disabled={busy} onClick={()=>{if(q.type==='radio')set(o);else{const list=Array.isArray(answers[q.id])?answers[q.id] as string[]:[];set(chosen?list.filter(v=>v!==o):[...list,o]);}}}><i className={q.type==='radio'?'radio':'answer-checkbox'}>{chosen&&q.type==='multiple_select'?<Check/>:null}</i>{o}</button>;})}</div>}
 {q.type==='multiple_select'&&<p className="note">Choose all that apply.</p>}</div></>}
 {(validation||error)&&<p role="alert" className="ai-error">{validation||error}</p>}
 {busy&&<p role="status" className="note">Updating your brief with these answers…</p>}
 <div className="question-dialog-footer"><button type="button" className="btn" disabled={busy||(!review&&index===0)} onClick={()=>{if(review)setReview(false);else setIndex(index-1);}}><ArrowLeft/>Back</button><div>{!review&&!q.required&&<button type="button" className="quiet" disabled={busy} onClick={()=>{set(q.type==='multiple_select'?[]:'');if(index===questions.length-1)setReview(true);else setIndex(index+1);}}>Skip for now</button>}{review?<button type="button" className="btn primary" disabled={busy} onClick={submit}><CheckCheck/>Update my brief</button>:<button type="button" className="btn primary" disabled={busy} onClick={next}>{index===questions.length-1?'Review answers':'Next question'}<ArrowRight/></button>}</div></div>
 <p className="note question-save-note">Answers are kept while this workspace is open. Closing this dialog won’t discard them.</p>
 </div></div>;
}
