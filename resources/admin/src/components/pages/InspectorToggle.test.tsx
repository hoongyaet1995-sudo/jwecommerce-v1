// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {InspectorToggle} from './InspectorToggle';
it('exposes an accessible switch and changes its controlled boolean once per click',()=>{
  (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
  const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let changes:boolean[]=[];
  function Harness(){const [checked,setChecked]=useState(false);return <InspectorToggle label="Show dividers" checked={checked} onChange={next=>{changes.push(next);setChecked(next)}}/>}
  act(()=>root.render(<Harness/>));const button=node.querySelector('[role="switch"]') as HTMLButtonElement;
  expect(button.getAttribute('aria-label')).toBe('Show dividers');expect(button.getAttribute('aria-checked')).toBe('false');expect(button.type).toBe('button');
  act(()=>button.click());expect(button.getAttribute('aria-checked')).toBe('true');act(()=>button.click());expect(changes).toEqual([true,false]);
  act(()=>root.render(<InspectorToggle label="Locked option" checked disabled onChange={()=>changes.push(true)}/>));
  expect((node.querySelector('[role="switch"]') as HTMLButtonElement).disabled).toBe(true);
  expect(node.querySelector('input[type="checkbox"],input[type="radio"]')).toBeNull();act(()=>root.unmount());node.remove();
});
