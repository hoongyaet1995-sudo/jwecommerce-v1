import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {SECTION_TEMPLATES} from './AddSectionModal';
import {ValuesList} from './ValuesList';
describe('standalone Values List integration',()=>{
  it('offers an independent reusable section with readable defaults and only numbered content',()=>{
    const template=SECTION_TEMPLATES.find(entry=>entry.type==='values_list');
    expect(template).toBeDefined();
    expect(template!.defaultData.items).toHaveLength(3);
    expect(template!.defaultData.customColors?.titleColor).toBe('#171717');
    expect(template!.defaultData).not.toHaveProperty('featureCardsPreset');
    const html=renderToStaticMarkup(<ValuesList section={{...template!.defaultData,id:'new-values'}}/>);
    expect(html).toContain('01');expect(html).toContain('03');expect(html).not.toContain('<svg');
  });
});
