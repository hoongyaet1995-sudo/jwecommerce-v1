// @vitest-environment jsdom
import React,{act,useRef,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {BuilderTextToolbar} from './BuilderTextToolbar';
import type {PageSection} from '../../types';
(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
it('opens only on registered right-click, writes shared fields and preserves heading ranges',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let latest:PageSection;const select=vi.fn();
 function Harness(){const ref=useRef<HTMLDivElement>(null);const [s,set]=useState({id:'qa',type:'hero_banner',title:'Test heading',isVisible:true} as PageSection);latest=s;return <><div ref={ref}><section id="section-qa"><h1 data-cms-text="title">Test heading</h1><form><p data-cms-text="content">Operational</p></form></section></div><BuilderTextToolbar canvasRef={ref} sections={[s]} activeSectionId="qa" pageId="draft" onChange={set} onSelect={select}/></>}
 await act(async()=>root.render(<Harness/>));const heading=host.querySelector('h1')!;
 await act(async()=>heading.click());expect(document.querySelector('[role="toolbar"]')).toBeNull();
 const range=document.createRange();range.setStart(heading.firstChild!,5);range.setEnd(heading.firstChild!,12);window.getSelection()!.removeAllRanges();window.getSelection()!.addRange(range);
 await act(async()=>heading.dispatchEvent(new MouseEvent('contextmenu',{bubbles:true,cancelable:true})));
 expect(select).toHaveBeenCalledWith('qa','title');expect(document.querySelector('[role="toolbar"]')).not.toBeNull();
 await act(async()=>document.querySelector<HTMLButtonElement>('[aria-label="Emphasize selection"]')!.click());expect(latest!.titleEmphasis).toEqual([{start:5,end:12}]);expect(latest!.title).toBe('Test heading');
 await act(async()=>document.querySelector<HTMLButtonElement>('[aria-label="Toolbar alignment right"]')!.click());expect(latest!.textAlignment).toBe('right');expect(latest!.titleEmphasis).toEqual([{start:5,end:12}]);
 await act(async()=>document.querySelector<HTMLButtonElement>('[aria-label="Close text toolbar"]')!.click());
 await act(async()=>host.querySelector('p')!.dispatchEvent(new MouseEvent('contextmenu',{bubbles:true,cancelable:true})));expect(document.querySelector('[role="toolbar"]')).toBeNull();
 await act(async()=>root.unmount());host.remove();
});
