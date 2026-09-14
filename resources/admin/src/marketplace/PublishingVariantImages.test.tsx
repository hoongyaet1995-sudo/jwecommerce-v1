// @vitest-environment jsdom
import React from 'react';
import {describe,it,expect,vi} from 'vitest';
import {act} from 'react';
import {createRoot} from 'react-dom/client';
import {variantImageGroups,resolveVariantImages,PublishingVariantImages} from './PublishingVariantImages';
import type {PublishingSource} from '../integration/marketplacePublishing';
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
const source={options:[{name:'Color',values:['Black','Pink']}],variants:[
{id:1,sku:'B-S',options:{Color:'Black'},image_url:'/one.jpg'},
{id:2,sku:'B-M',options:{Color:'Black'},image_url:'/two.jpg'},
{id:3,sku:'P-S',options:{Color:'Pink'},image_url:'/pink.jpg'},
{id:4,sku:'P-M',options:{Color:'Pink'},image_url:'/pink.jpg'}]} as PublishingSource;
describe('publishing variation image selection',()=>{
 it('automatically resolves identical images and requires explicit conflict choice',()=>{
  const groups=variantImageGroups(source);
  expect(resolveVariantImages(groups,{})).toEqual({resolved:{Pink:3},missing:['Black']});
  expect(resolveVariantImages(groups,{Black:2})).toEqual({resolved:{Black:2,Pink:3},missing:[]});
  expect(resolveVariantImages(groups,{Black:3}).missing).toEqual(['Black']);
 });
 it('allows selecting a thumbnail and reflects the saved selection',async()=>{
  const onChange=vi.fn();const groups=variantImageGroups(source);
  const container=document.createElement('div');const root=createRoot(container);
  await act(async()=>root.render(<PublishingVariantImages groups={groups} choices={{}} onChange={onChange}/>));
  await act(async()=>(container.querySelector('[aria-label="Black: B-M"]') as HTMLButtonElement).click());
  expect(onChange).toHaveBeenCalledWith('Black',2);
  await act(async()=>root.render(<PublishingVariantImages groups={groups} choices={{Black:2}} onChange={onChange}/>));
  expect(container.querySelector('[aria-label="Black: B-M"]')?.getAttribute('aria-pressed')).toBe('true');
  expect(container.querySelector('[role="alert"]')).toBeNull();
  await act(async()=>root.unmount());
 });
});
