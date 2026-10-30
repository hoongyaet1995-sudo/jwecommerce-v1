import {it,expect} from 'vitest';
import {pageStudioAction} from './pageStudioAction';
it('keeps unanswered and edited briefs behind their own gates',()=>{
 expect(pageStudioAction(2,{token:'',questions:1,images:0})).toBe('update');
 expect(pageStudioAction(2,{token:'valid',questions:1,images:0})).toBe('questions');
 expect(pageStudioAction(2,{token:'valid',questions:0,images:0})).toBe('copy');
});
it('requires image confirmation only for outstanding selected images',()=>{
 expect(pageStudioAction(3,{token:'valid',questions:0,images:1})).toBe('images');
 expect(pageStudioAction(3,{token:'valid',questions:0,images:0})).toBe('review');
 expect(pageStudioAction(4,{token:'valid',questions:0,images:0})).toBe('save');
});
