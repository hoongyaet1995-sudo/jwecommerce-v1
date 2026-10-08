import {it,expect} from 'vitest';
import {sectionBorderStyle} from './sectionBorders';
import type {PageSection} from '../types';
const section:PageSection={id:'s',type:'rich_text',title:'Text',isVisible:true};
it('preserves legacy borders until explicitly configured',()=>{expect(sectionBorderStyle(section)).toEqual({});expect(sectionBorderStyle({...section,sectionBorder:'default'})).toEqual({});});
it('overrides both inherited edges and applies safe custom color',()=>{
 expect(sectionBorderStyle({...section,sectionBorder:'none'})).toMatchObject({borderTopWidth:0,borderBottomWidth:0});
 expect(sectionBorderStyle({...section,sectionBorder:'top',sectionBorderColor:'#aabbcc'})).toMatchObject({borderTopWidth:1,borderBottomWidth:0,borderColor:'#aabbcc'});
 expect(sectionBorderStyle({...section,sectionBorder:'bottom'})).toMatchObject({borderTopWidth:0,borderBottomWidth:1});
 expect(sectionBorderStyle({...section,sectionBorder:'both',sectionBorderColor:'invalid'})).toMatchObject({borderTopWidth:1,borderBottomWidth:1,borderColor:'#e5e5e5'});
});
