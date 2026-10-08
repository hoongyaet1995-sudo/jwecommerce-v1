import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {ValuesList} from './ValuesList';
import type {PageSection} from '../../types';
const section:PageSection={id:'values',type:'feature_cards',title:'Stored heading',subtitle:'OUR VALUES',content:'Stored intro',isVisible:true,items:[{id:'a',title:'Thoughtful choices',description:'Description one',icon:'Sparkles',badge:'Legacy badge'},{id:'b',title:'Clear information',description:'Description two',icon:'ShieldCheck'}]};
describe('Values List',()=>{
  it('renders ordered numbered rows without legacy icons, badges or hidden headings',()=>{
    const html=renderToStaticMarkup(<ValuesList section={section}/>);
    expect(html).toContain('data-values-list');expect(html).toContain('01');expect(html).toContain('02');
    expect(html).toContain('Thoughtful choices');expect(html).toContain('Description two');
    expect(html).not.toContain('Stored heading');expect(html).not.toContain('Stored intro');expect(html).not.toContain('Legacy badge');expect(html).not.toContain('<svg');
  });
  it('supports a visible heading and configurable dividers, spacing and number color',()=>{
    expect(renderToStaticMarkup(<ValuesList section={{...section,valuesShowHeading:true}}/>)).toContain('Stored heading');
    const html=renderToStaticMarkup(<ValuesList section={{...section,valuesShowDividers:false,valuesRowSpacing:'compact',valuesNumberColor:'#bbbbbb'}}/>);
    expect(html).not.toContain('border-b');expect(html).toContain('py-4');expect(html).toContain('color:#bbbbbb');
  });
});
