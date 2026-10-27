import test from 'node:test';
import assert from 'node:assert/strict';
import {audiencePresentation,viewerPages} from './viewer-copy.mjs';
test('only explicit storyboard copy is displayable, never legacy instructions',()=>{
 const s={number:5,frames:120,subtitle:'Share your use and delivery details',viewer_copy:{text:'Share your use and delivery details',source:'document.scenes.4.subtitle'}};
 const p=audiencePresentation(s,40);assert.equal(p.heading+p.body,s.subtitle);
 assert.throws(()=>audiencePresentation({...s,viewer_copy:undefined},40));
 assert.throws(()=>audiencePresentation({...s,viewer_copy:{...s.viewer_copy,text:'Insert a product image'}},40));
});
test('all scenes preserve every character over display pages, including long words and CJK',()=>{
 for(const text of ['Equipment. Materials. General goods.','X'.repeat(180),'这是保持完整的观众文字。'.repeat(20),('ABCDEFGHIJKLM ').repeat(12)]){
  const pages=viewerPages(text);assert.equal(pages.join(''),text);
  for(let number=1;number<=6;number++){
   const s={number,frames:pages.length*24,subtitle:text,viewer_copy:{text,source:`document.scenes.${number-1}.subtitle`}};
   const shown=pages.map((_,i)=>{const p=audiencePresentation(s,i*24);return number===1?p.lines.join('')+p.heading:number===2?p.lines[0]+p.heading:number===5?p.heading+p.body:number===6?p.heading:p.heading+p.lines.join('');}).join('');
   assert.equal(shown,text);
  }
 }
});
