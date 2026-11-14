// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionLayoutControls} from './SectionLayoutControls';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('retains old values across supported/fixed/supported layouts and resets one field only',async()=>{
 const host=document.createElement('div'),root=createRoot(host),onChange=vi.fn();
 const stored:any={id:'same',type:'shop_navigation',title:'Keep copy',isVisible:true,shopNavigationPreset:'collection-banners',showSectionHeading:true,paddingY:'spacious',textAlignment:'right',items:[{id:'item',title:'Keep item'}]};
 const show=async(section:any)=>act(async()=>root.render(<SectionLayoutControls section={section} onChange={onChange}/>));
 try{
  await show(stored);expect(host.querySelectorAll('[data-layout-spacing],[data-layout-alignment]')).toHaveLength(2);
  await show({...stored,shopNavigationPreset:'market-hero'});expect(host.querySelectorAll('[data-layout-spacing],[data-layout-alignment]')).toHaveLength(0);
  await show(stored);expect(onChange).not.toHaveBeenCalled();expect(host.querySelector('[aria-label="Text alignment right"]')?.getAttribute('aria-pressed')).toBe('true');
  await act(async()=>(host.querySelector('[aria-label="Follow page spacing"]') as HTMLButtonElement).click());
  const resetSpacing={...stored};delete resetSpacing.paddingY;expect(onChange.mock.lastCall?.[0]).toEqual(resetSpacing);
  await act(async()=>(host.querySelector('[aria-label="Use layout alignment"]') as HTMLButtonElement).click());
  const resetAlignment={...stored};delete resetAlignment.textAlignment;expect(onChange.mock.lastCall?.[0]).toEqual(resetAlignment);
 }finally{await act(async()=>root.unmount());}
});
