import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {OrderSummaryRows} from './OrderSummaryRows';
it('renders real identity and unknown fields without fabricated payment or item facts',()=>{
 const html=renderToStaticMarkup(<table><tbody><OrderSummaryRows onSelect={()=>{}} orders={[{id:1,store_id:2,external_order_id:'REAL-SN',platform:'shopee',shop_name:'Shop',platform_status:'READY_TO_SHIP',currency:'MYR',total_amount:null,platform_updated_at:1,fetched_at:''}]}/></tbody></table>);
 expect(html).toContain('REAL-SN');expect(html).toContain('MYR —');expect(html).toContain('READY TO SHIP');
 expect(html).not.toContain('AWB Printed');expect(html).not.toContain('0 unit');expect(html).not.toContain('Paid');
});
