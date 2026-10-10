import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {CreatePromotionModal} from '../components/promotions/CreatePromotionModal';
import {promotionStatus} from './promotionStatus';
describe('optional promotion schedule',()=>{
 it('keeps empty schedules active and respects dates and quotas',()=>{
 const p={isActive:true,startsAt:null,endsAt:null,usageLimit:null,usageCount:0};
 expect(promotionStatus(p)).toBe('active');
 expect(promotionStatus({...p,endsAt:'2020-01-01T00:00:00Z'})).toBe('expired');
 expect(promotionStatus({...p,startsAt:'2099-01-01T00:00:00Z'})).toBe('scheduled');
 expect(promotionStatus({...p,usageLimit:1,usageCount:1})).toBe('exhausted');
 });
 it('exposes empty optional start and end inputs',()=>{
 const html=renderToStaticMarkup(<CreatePromotionModal isOpen onClose={()=>{}} onSave={()=>{}}/>);
 expect(html).toContain('aria-label="Start date"');expect(html).toContain('aria-label="End date"');expect(html).not.toContain('2026-09-30');
 });
});
