// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {VisualChoiceSelect} from './VisualChoiceSelect';
import {CtaStripSettings} from './CtaStripSettings';
it('preserves fragment option values, selection and disabled geometry',()=>{
  const node=document.createElement('div');const root=createRoot(node);const change=vi.fn();
  act(()=>root.render(<VisualChoiceSelect aria-label="Layout" value="story" onChange={change}><option value="classic">Classic</option><><option value="story">Story</option><option value="cards" disabled>Cards</option></></VisualChoiceSelect>));
  expect(node.querySelectorAll('select')).toHaveLength(0);expect(node.querySelectorAll('button')).toHaveLength(3);
  expect(node.querySelector('[data-choice-value="story"]')!.getAttribute('aria-pressed')).toBe('true');
  act(()=>(node.querySelector('[data-choice-value="classic"]') as HTMLButtonElement).click());expect(change.mock.calls[0][0].target.value).toBe('classic');
  expect((node.querySelector('[data-choice-value="cards"]') as HTMLButtonElement).disabled).toBe(true);
  act(()=>root.unmount());
});
it('only offers valid heights for CTA image banners while retaining layout choices',()=>{
  const node=document.createElement('div');const root=createRoot(node);
  act(()=>root.render(<CtaStripSettings section={{id:'cta',type:'cta_strip',title:'Keep',isVisible:true,ctaPreset:'image-banner'}} onChange={()=>{}}/>));
  expect([...node.querySelectorAll('[aria-label="Banner height"] button')].map(b=>b.getAttribute('data-choice-value'))).toEqual(['compact','normal','spacious']);
  expect(node.querySelector('[aria-label="Layout preset"] [data-choice-value="help-panel"]')).not.toBeNull();act(()=>root.unmount());
});
