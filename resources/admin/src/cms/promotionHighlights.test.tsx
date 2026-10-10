import {describe,it,expect} from 'vitest';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {promotionHighlightDefaults,resolvePromotionCards,couponHighlight,isPromotionOffers} from './promotionHighlights';
import {parseDraftImport} from './pageImport';
import {PromotionHighlights} from '../components/pages/PromotionHighlights';
const coupon={id:'1',name:'Lighting edit',description:'Brighten your space',code:'LIGHT20',discountType:'percentage' as const,value:20,minimumSpend:100,usageLimit:null,usageCount:0,isActive:true,startsAt:null,endsAt:null};
describe('Promotion Highlights',()=>{
 it('uses live benefit and excludes scheduled, expired, inactive and exhausted offers',()=>{
  const now=Date.parse('2026-10-09T10:00:00Z');
  expect(couponHighlight(coupon,now).status).toBe('active');
  expect(couponHighlight({...coupon,startsAt:'2026-10-10T00:00:00Z'},now).status).toBe('scheduled');
  expect(couponHighlight({...coupon,endsAt:'2026-10-08T00:00:00Z'},now).status).toBe('expired');
  expect(couponHighlight({...coupon,isActive:false},now).status).toBe('inactive');
  expect(couponHighlight({...coupon,usageLimit:1,usageCount:1},now).status).toBe('exhausted');
  expect(couponHighlight({...coupon,discountType:'fixed_amount',value:25},now).benefit).toBe('RM 25.00 off');
 });
 it('keeps manual order, excludes unavailable selections and rejects duplicates',()=>{
  const s={id:'promo',...promotionHighlightDefaults()};s.promotionSource='manual';s.items![0].sourceId='2';s.items![1].sourceId='1';
  const offers=[couponHighlight(coupon),couponHighlight({...coupon,id:'2'})];
  expect(resolvePromotionCards(s,offers).map(c=>c.offer.id)).toEqual(['2','1']);
  offers[0].status='expired';expect(resolvePromotionCards(s,offers)).toHaveLength(1);
  s.items![1].sourceId='2';expect(resolvePromotionCards(s,offers)).toHaveLength(1);
 });
 it('saves canonical visual overrides and hides empty customer section',()=>{
  const section={id:'promo',...promotionHighlightDefaults()};
  expect(parseDraftImport(JSON.stringify({title:'Home',slug:'home',template:'standard',sections:[section]}),'54').sections[0].promotionLayout).toBe('side');
  expect(renderToStaticMarkup(<PromotionHighlights section={section} offers={[]} />)).toBe('');
  const html=renderToStaticMarkup(<PromotionHighlights section={section} offers={[couponHighlight(coupon)]}/>);
  expect(html).toContain('LIGHT20');expect(html).toContain('20% off');expect(html).toContain('Minimum spend RM 100.00');expect(html).toContain('data-promotion-highlights');
  expect(isPromotionOffers([{...couponHighlight(coupon),unsafe:true}])).toBe(false);
  expect(isPromotionOffers([{...couponHighlight(coupon),endsAt:'invalid'}])).toBe(false);
 });
 it('renders the fourth card and bounds the saved repeatable collection',()=>{
  const section={id:'promo',...promotionHighlightDefaults()};
  section.items=Array.from({length:4},(_,i)=>({...section.items![i%2],id:`slot-${i}`}));
  const offers=Array.from({length:4},(_,i)=>couponHighlight({...coupon,id:String(i+1)}));
  expect(resolvePromotionCards(section,offers)).toHaveLength(4);
  expect(renderToStaticMarkup(<PromotionHighlights section={section} offers={offers}/>)).toContain('data-cms-item="slot-3"');
  section.items=Array.from({length:7},(_,i)=>({...section.items![0],id:`slot-${i}`}));
  expect(()=>parseDraftImport(JSON.stringify({title:'Home',slug:'home',template:'standard',sections:[section]}),'54')).toThrow('six offer cards');
 });
});
