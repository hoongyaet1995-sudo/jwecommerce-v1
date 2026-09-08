// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {PreviewContext} from './context/AdminContext';
import {InventoryOrderExceptionsDrawer} from './components/marketplace/InventoryOrderExceptionsDrawer';
import {exceptionView} from './readExceptions';
it('renders historical review without claiming exclusion or zero quantity',()=>{
 const row=exceptionView({order_id:12,external_order_id:'TEST-HISTORICAL',platform:'shopee',shop_name:'Test store',error_code:'historical_order',checked_at:'2026-09-07T00:00:00Z'});
 const html=renderToStaticMarkup(<PreviewContext.Provider value={{inventoryExceptionStatus:'1 recorded exception'}}><InventoryOrderExceptionsDrawer isOpen onClose={()=>{}} exceptions={[row as any]}/></PreviewContext.Provider>);
 expect(html).toContain('Requires reconciliation');
 expect(html).toContain('Not available');
 expect(html).toContain('TEST-HISTORICAL');
 expect(html).not.toContain('Excluded from WMS');
 expect(html).not.toContain('Available ATP:');
});
it('renders read failure rather than an empty-success message',()=>{
 const html=renderToStaticMarkup(<PreviewContext.Provider value={{inventoryExceptionStatus:'Exception read failed (503).'}}><InventoryOrderExceptionsDrawer isOpen onClose={()=>{}} exceptions={[]}/></PreviewContext.Provider>);
 expect(html).toContain('Exception read failed (503).');
 expect(html).not.toContain('No recorded order exceptions');
});
