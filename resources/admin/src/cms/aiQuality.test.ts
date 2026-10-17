import {it,expect} from 'vitest';
import {aiQuality} from './aiQuality';
import {generatePalette} from './pageStyle';
it('blocks unsafe links but warns for missing editorial images and poor contrast',()=>{
 const doc={title:'QA',slug:'qa',template:'standard' as const,style:{palette:generatePalette('#173247')},sections:[{id:'one',type:'media_text_split' as const,title:'Introduction',isVisible:true,mediaUrl:'',buttonLink:'javascript:alert(1)',surface:'light' as const,typography:{title:{color:'#ffffff'}}}]};
 const issues=aiQuality(doc);expect(issues.some(i=>i.id==='one-link'&&i.severity==='block')).toBe(true);expect(issues.some(i=>i.id==='one-media')).toBe(true);expect(issues.some(i=>i.id==='one-title-contrast')).toBe(true);
 expect(aiQuality({...doc,sections:[{...doc.sections[0],buttonLink:'/shop',mediaUrl:'/real.png',typography:{title:{color:'#111111'}}}]})).toEqual([]);
});
