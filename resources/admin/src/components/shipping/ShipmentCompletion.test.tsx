// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {ShipmentCompletion} from './ShipmentCompletion';
it('shows saved arrangements and exactly one next action',()=>{
 const html=renderToStaticMarkup(<ShipmentCompletion orderNumber="JW-1048" customerName="Sarah Lim" method="Own delivery" provider="Store team" onClose={()=>{}}/>);
 expect((html.match(/<button/g)||[]).length).toBe(1);expect(html).not.toContain('Delivered');expect(html).not.toContain('Tracking number');expect(html).toContain('Store team');
});
