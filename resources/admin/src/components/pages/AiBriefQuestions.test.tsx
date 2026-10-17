// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot,type Root} from 'react-dom/client';
import {afterEach,describe,expect,it,vi} from 'vitest';
import {AiBriefQuestions} from './AiBriefQuestions';
import type {BriefAnswers,BriefQuestion} from '../../cms/cmsAiGenerator';
(globalThis as typeof globalThis & {IS_REACT_ACT_ENVIRONMENT:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
let root:Root;let host:HTMLDivElement;
afterEach(()=>{if(root)act(()=>root.unmount());host?.remove();});
const qs:BriefQuestion[]=[
 {id:'name',type:'text',question:'Business name?',help:'Use your brand name.',required:true,options:[]},
 {id:'style',type:'select',question:'Which direction?',help:'',required:true,options:['Editorial','Clean']},
 {id:'show',type:'radio',question:'Show products?',help:'',required:true,options:['Yes','No']},
 {id:'audience',type:'multiple_select',question:'Who should we reach?',help:'',required:true,options:['Business','Consumer']},
];
function button(name:string){const el=[...host.querySelectorAll('button')].find(el=>el.textContent?.trim()===name);if(!el)throw new Error(`Button missing: ${name}`);act(()=>el.click());}
describe('AI Brief question wizard',()=>{
 it('renders every answer type, retains previous answers and submits after review',()=>{
  const submit=vi.fn();let last:BriefAnswers={};
  function Harness(){const [answers,setAnswers]=useState<BriefAnswers>({});last=answers;return <AiBriefQuestions questions={qs} answers={answers} onChange={setAnswers} onSubmit={submit} onClose={()=>{}} busy={false} error=""/>;}
  host=document.createElement('div');document.body.append(host);root=createRoot(host);act(()=>root.render(<Harness/>));
  button('Next question');expect(host.textContent).toContain('Please answer this question.');
  const textarea=host.querySelector('textarea')!;act(()=>{Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value')!.set!.call(textarea,'JTG');textarea.dispatchEvent(new Event('input',{bubbles:true}));});
  button('Next question');expect(host.textContent).toContain('Which direction?');
  act(()=>{const select=host.querySelector('select')!;select.value='Clean';select.dispatchEvent(new Event('change',{bubbles:true}));});
  button('Next question');button('Yes');button('Next question');button('Business');button('Consumer');button('Review answers');
  expect(host.textContent).toContain('A clearer picture.');expect(host.textContent).toContain('JTG');expect(host.textContent).toContain('Business, Consumer');
  button('Back');expect(host.querySelectorAll('[aria-pressed="true"]').length).toBe(2);button('Review answers');button('Update my brief');
  expect(submit).toHaveBeenCalledTimes(1);expect(last).toEqual({name:'JTG',style:'Clean',show:'Yes',audience:['Business','Consumer']});
 });
});
