import {it,expect} from 'vitest';import {sectionAnchorOffset} from './sectionAnchorOffset';
it('keeps contained workflow panels clear of the sticky header',()=>{expect(sectionAnchorOffset({id:'workflow',type:'media_text_split',layoutPreset:'contained-wide-editorial',title:'Workflow'} as any)).toBe(104);});
