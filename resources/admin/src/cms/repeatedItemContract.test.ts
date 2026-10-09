import {describe,it,expect} from 'vitest';
import {repeatedItemFields} from './repeatedItemContract';
import type {PageSection} from '../types';
const section=(type:string,patch:object={})=>({id:'qa',type,title:'Test',...patch}) as PageSection;
describe('renderer-backed repeated item capabilities',()=>{
 it('location cards consistently expose icons, never unused badges or images',()=>{const fields=repeatedItemFields(section('store_locator',{contactPreset:'location'}));expect(fields).toEqual(['title','description','icon']);});
 it('keeps legacy locator badges and links and editorial column text only',()=>{expect(repeatedItemFields(section('store_locator'))).toContain('badge');expect(repeatedItemFields(section('feature_cards',{featureCardsPreset:'editorial-columns'}))).toEqual(['title','description']);});
 it('no-item sections have no item controls; feature cards retain badge and Lucide',()=>{expect(repeatedItemFields(section('rich_text'))).toEqual([]);expect(repeatedItemFields(section('feature_cards'))).toEqual(['title','description','icon','badge']);});
});
