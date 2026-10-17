import {describe,it,expect} from 'vitest';
import {documentChanges,questionError,reorderSections,type BriefQuestion} from './cmsAiGenerator';
import type {PageDocument} from '../types';
const question=(type:BriefQuestion['type'],required=true):BriefQuestion=>({id:'q',question:'Choose',type,required,help:'',options:['One','Two']});
describe('CMS AI Brief interactions',()=>{
 it('requires meaningful text and accepts optional skips',()=>{expect(questionError(question('text'),'  ')).toBeTruthy();expect(questionError(question('text',false),'')).toBe('');});
 it('validates one answer and multiple answers against actual options',()=>{expect(questionError(question('select'),'Other')).toBeTruthy();expect(questionError(question('radio'),'Two')).toBe('');expect(questionError(question('multiple_select'),['One','Two'])).toBe('');expect(questionError(question('multiple_select'),[])).toBeTruthy();});
 it('moves a section without losing IDs or media references',()=>{const sections=[{id:'a',image:'real-a'},{id:'b',image:'real-b'},{id:'c',image:'real-c'}];const next=reorderSections(sections,0,2);expect(next.map(s=>s.id)).toEqual(['b','c','a']);expect(next[2]).toBe(sections[0]);expect(sections[0].id).toBe('a');});
 it('reports full body and item changes, not just the main heading',()=>{const a:PageDocument={title:'Page',slug:'page',template:'standard',sections:[{id:'a',type:'feature_cards',title:'Heading',content:'Before',isVisible:true,items:[{id:'item',title:'Item',description:'Old'}]}]};const b=structuredClone(a);b.sections[0].content='After';b.sections[0].items![0].description='New';expect(documentChanges(a,b).map(c=>c.field)).toEqual(['content','items.1.description']);});
});
