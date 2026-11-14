import {it,expect} from 'vitest';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {resolveStyledSection,presetStyle,generatePalette} from './pageStyle';
it('preserves every untouched registered legacy section resolution',()=>{
 const file=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/controls-integration/pre-resolved.json');
 const cases=SECTION_TEMPLATES.flatMap((t,i)=>[undefined,presetStyle('warm'),{palette:generatePalette('#173247'),typography:{title:{fontSize:50}}}].map((style,j)=>({name:t.title,style:j,resolved:resolveStyledSection({...structuredClone(t.defaultData),id:'legacy-'+i,isVisible:true},style)})));
 const actual=JSON.parse(JSON.stringify(cases));if(process.env.CAPTURE_CONTROLS_BASELINE==='1')writeFileSync(file,JSON.stringify(actual,null,2));
 expect(actual).toEqual(JSON.parse(readFileSync(file,'utf8')));
});
