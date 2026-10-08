import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {Manifesto} from './Manifesto';
import {richTextPresetPatch} from '../../cms/richTextPresets';
import type {PageSection} from '../../types';
const section:PageSection={id:'manifesto',type:'rich_text',title:'Every detail has a story.',subtitle:'OUR MANIFESTO',content:'First paragraph.\n\nSecond paragraph.',quote:'Stored legacy quote',isVisible:true};
describe('Manifesto preset',()=>{
  it('renders readable centered paragraphs without the legacy quote card',()=>{
    const html=renderToStaticMarkup(<Manifesto section={section}/>);
    expect(html).toContain('data-editorial-manifesto');
    expect(html).toContain('max-w-[720px]');
    expect(html).toContain('First paragraph.</p>');
    expect(html).toContain('Second paragraph.</p>');
    expect(html).not.toContain('blockquote');
    expect(html).not.toContain('Stored legacy quote');
  });
  it('preserves content, theme and legacy quote while switching presets',()=>{
    const original={...section,backgroundColor:'bg-white',customColors:{titleColor:'#112233'}};
    const patch=richTextPresetPatch(original,'manifesto');
    const next={...original,...patch};
    expect(next.quote).toBe(section.quote);expect(next.content).toBe(section.content);
    expect(next.customColors?.titleColor).toBe('#112233');
    expect(patch.richTextPreset).toBe('manifesto');
    expect(richTextPresetPatch(next,'default')).toEqual({richTextPreset:'default'});
  });
  it('follows the current background for fallback colors and preserves explicit overrides',()=>{
    expect(renderToStaticMarkup(<Manifesto section={{...section,backgroundColor:'bg-white',textColor:'light'}}/>)).toContain('text-neutral-900');
    expect(renderToStaticMarkup(<Manifesto section={{...section,backgroundColor:'bg-neutral-950',textColor:'dark'}}/>)).toContain('text-white');
    expect(renderToStaticMarkup(<Manifesto section={{...section,backgroundColor:'custom',customColors:{customBgColor:'#fff',titleColor:'#112233'}}}/>)).toContain('color:#112233');
  });
});
