// @vitest-environment jsdom
import React,{act,useState} from 'react';import {createRoot} from 'react-dom/client';import {it,expect} from 'vitest';
import {FloatingTextToolbar} from './FloatingTextToolbar';import {TypographySettings} from './TypographySettings';import type {PageSection} from '../../types';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('shares only the selected item color between toolbar and Inspector, leaving shared typography and siblings unchanged',async()=>{
 let latest:PageSection;const seed:PageSection={id:'binding-ui',type:'feature_cards',title:'Cards',isVisible:true,items:[{id:'a',title:'First'},{id:'b',title:'Second'}],typography:{itemTitle:{fontSize:30,color:'#18232b',colorOverride:true}}};
 function Harness(){const [s,set]=useState(seed);latest=s;return <><FloatingTextToolbar section={s} role="itemTitle" itemId="a" position={{left:0,top:0,width:760}} computed={{fontSize:30,fontWeight:600,color:'#18232b'}} onChange={set} onClose={()=>{}}/><TypographySettings section={s} selectedRole="itemTitle" availableRoles={['itemTitle']} colorItemId="a" onSelectRole={()=>{}} onChange={set}/></>}
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);await act(async()=>root.render(<Harness/>));
 const select=(name:string)=>document.querySelector<HTMLSelectElement>(`[aria-label="${name}"]`)!;
 await act(async()=>{select('Toolbar color source').value='light';select('Toolbar color source').dispatchEvent(new Event('change',{bubbles:true}));});
 expect(select('Text color source').value).toBe('light');expect(latest!.textColorBindings).toEqual({a:{itemTitle:{source:'light'}}});
 await act(async()=>{select('Text color source').value='custom';select('Text color source').dispatchEvent(new Event('change',{bubbles:true}));});
 expect(select('Toolbar color source').value).toBe('custom');expect(latest!.typography).toEqual(seed.typography);expect(latest!.items).toEqual(seed.items);
 await act(async()=>{select('Toolbar color source').value='auto';select('Toolbar color source').dispatchEvent(new Event('change',{bubbles:true}));});
 expect(latest!.textColorBindings).toEqual({a:{itemTitle:{source:'auto'}}});await act(async()=>root.unmount());node.remove();
});
