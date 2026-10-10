import {describe,it,expect} from 'vitest';
import {textRoles,typographyStyle,isTypography,inspectorGroups} from './inspectorCapabilities';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
describe('unified Inspector capabilities',()=>{
 it('accounts for every global preset without discarding controls',()=>{expect(SECTION_TEMPLATES).toHaveLength(68);expect(inspectorGroups).toHaveLength(27);expect(inspectorGroups.every(group=>['content','design','advanced'].includes(group.tab))).toBe(true)});
 it('keeps hidden headings out of typography controls',()=>{expect(textRoles({id:'x',type:'chapter_block',title:'Principles',chapterPreset:'principles',subtitle:'Our principles',items:[{id:'a',title:'Title',description:'Body'}],isVisible:true})).toEqual(['eyebrow','itemTitle','itemContent'])});
 it('leaves old presets intact and bounds new overrides',()=>{expect(typographyStyle({id:'x',type:'rich_text',title:'X',isVisible:true},'title')).toEqual({});expect(isTypography({title:{fontSize:999}})).toBe(false);expect(isTypography({title:{fontSize:48,fontFamily:'serif',fontWeight:700}})).toBe(true);expect(isTypography({title:{unknown:'x'}})).toBe(false)});
});
