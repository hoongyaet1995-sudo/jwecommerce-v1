import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderWorkflow} from './websiteBuilderWorkflow';
import {RoutineSteps} from '../components/pages/BeautyLayouts';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {textRoles} from './inspectorCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';

describe('Website illustrated workflow',()=>{
 it('keeps images/title/description independent and hides manual numbering, links and icons',()=>{
  const s=websiteBuilderWorkflow('f01',['/shape.webp','/edit.webp','/publish.webp']);
  expect(s.type).toBe('media_gallery');expect(s.galleryPreset).toBe('corporate-illustrated-steps');
  expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');
  expect(sectionContentFields(s)).toEqual(['title','content']);
  expect(repeatedItemFields(s)).toEqual(['title','description','imageUrl']);
  expect(textRoles(s)).toEqual(['title','content','itemTitle','itemContent']);
  expect(imageTargets(s).map(t=>t.url)).toEqual(['/shape.webp','/edit.webp','/publish.webp']);expect(supportsMediaWidth(s)).toBe(false);
 });
 it('regenerates numbering/connectors after add, reorder and delete; never adds a trailing connector',()=>{
  const s=websiteBuilderWorkflow('f01',['/shape.webp','/edit.webp','/publish.webp']);
  s.items!.push({id:'fourth',title:'Fourth step',description:'Editable',imageUrl:'/four.webp',value:'99',linkUrl:'/old'});
  let html=renderToStaticMarkup(<RoutineSteps section={s}/>);
  expect(html.match(/data-step-number=/g)).toHaveLength(4);expect(html.match(/data-step-connector=/g)).toHaveLength(3);
  expect(html).toContain('data-step-number="4"');expect(html).not.toContain('<a ');expect(html).not.toContain('>99<');
  s.items=[s.items![3],s.items![0],s.items![1]];html=renderToStaticMarkup(<RoutineSteps section={s}/>);
  expect(html.match(/data-step-connector=/g)).toHaveLength(2);expect(html.indexOf('Fourth step')).toBeLessThan(html.indexOf('Shape your page'));
  expect(html).not.toContain('data-step-number="4"');
 });
 it('supports sparse editing and stacks long workflows without disconnecting wrapped rows',()=>{
  const sparse={id:'empty',type:'media_gallery' as const,galleryPreset:'corporate-illustrated-steps' as const,title:'Workflow',isVisible:true,items:[]};
  expect(repeatedItemFields(sparse)).toEqual(['title','description','imageUrl']);expect(renderToStaticMarkup(<RoutineSteps section={sparse}/>)).not.toContain('data-step-connector=');
  const a=websiteBuilderWorkflow('a'),b=websiteBuilderWorkflow('b');a.items![0].title='Changed';expect(b.items![0].title).toBe('Shape your page');
  b.items!.push({id:'4',title:'Four'},{id:'5',title:'Five'});expect(renderToStaticMarkup(<RoutineSteps section={b}/>)).toContain('data-stacked="true"');
 });
});
