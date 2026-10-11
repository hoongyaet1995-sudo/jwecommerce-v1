import {it,expect} from 'vitest';
import {TechComparisonSettings,comparisonValue} from '../components/pages/TechLayouts';
import {textRoles} from './inspectorCapabilities';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets} from './imageTargets';
function nodes(node:any):any[]{return !node||typeof node!=='object'?[]:[node,...[node.props?.children].flat(Infinity).flatMap(nodes)];}
it('preserves legacy cells beyond three devices through reordering and deletion',()=>{
 let s:any={id:'s',type:'comparison_matrix',title:'Compare',comparisonPreset:'device-table',items:[...['a','b','c'].map(id=>({id,title:id,subtitle:'device'})),{id:'r',title:'Weight',badge:'A',description:'B',value:'C'}]};
 const controls=()=>nodes(TechComparisonSettings({section:s,mode:'content',onChange:next=>{s=next;}}));
 controls().find(n=>n.type==='button'&&n.props.children==='Add device').props.onClick();expect(imageTargets(s)).toHaveLength(4);
 controls().find(n=>n.props?.['aria-label']==='Specification 1 device 4').props.onChange({target:{value:'D'}});
 controls().filter(n=>n.type==='button'&&n.props['aria-label']==='Move device up')[3].props.onClick();expect(comparisonValue(s.items[4],s.items[2],2)).toBe('D');
 controls().filter(n=>n.type==='button'&&n.props['aria-label']==='Remove device')[0].props.onClick();expect(comparisonValue(s.items[3],s.items[0],0)).toBe('B');expect(comparisonValue(s.items[3],s.items[1],1)).toBe('D');
});
it('exposes service heading copy and typography when shown',()=>{const s:any={type:'feature_cards',featureCardsPreset:'service-highlights',showSectionHeading:true};expect(sectionContentFields(s)).toContain('title');expect(textRoles(s)).toContain('title');expect(sectionContentFields({...s,showSectionHeading:false})).not.toContain('title');});
