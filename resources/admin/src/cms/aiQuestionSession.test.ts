import {describe,it,expect,vi} from 'vitest';
import {questionSessionToken} from './aiQuestionSession';
describe('question sessions survive brief edits',()=>{
 it('uses the question token after generation confirmation is invalidated',async()=>{
  const load=vi.fn();
  expect(await questionSessionToken({token:'',questionToken:'question-session',brief:{questions:[]},activity:[]},load)).toBe('question-session');
  expect(load).not.toHaveBeenCalled();
 });
 it('recovers legacy edited drafts from the existing completed operation without generating',async()=>{
  const questions=[{id:'direction',question:'Which direction?'}];
  const load=vi.fn().mockResolvedValue({status:'completed',response:{data:{token:'saved-session',brief:{questions}}}});
  expect(await questionSessionToken({token:'',brief:{questions},activity:[{id:'operation',action:'brief'}]},load)).toBe('saved-session');
  expect(load).toHaveBeenCalledExactlyOnceWith('operation');
 });
 it('does not attach answers to a different question set',async()=>{
  await expect(questionSessionToken({token:'',brief:{questions:[{id:'new'}]},activity:[{id:'operation',action:'brief'}]},async()=>({status:'completed',response:{data:{token:'old',brief:{questions:[{id:'old'}]}}}}))).rejects.toThrow('answers are kept');
 });
});
