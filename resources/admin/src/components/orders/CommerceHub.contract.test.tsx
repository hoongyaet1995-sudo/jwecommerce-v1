import React from 'react';
import {renderToString} from 'react-dom/server';
import {expect,it,vi} from 'vitest';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:vi.fn(),customers:[],orders:[{paymentGatewayStatus:'paid',totalAmount:120},{paymentGatewayStatus:'pending',totalAmount:900},{paymentGatewayStatus:'failed',totalAmount:300}]})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({money:(amount:number)=>`MYR ${amount}`,currencyCode:'MYR'})}));
vi.mock('./EnquiryAdmin',()=>({enquiryRequest:vi.fn()}));
import {CommerceHub} from './CommerceHub';
it('counts only confirmed paid orders in the existing commerce summary',()=>{
 const html=renderToString(<CommerceHub/>);
 expect(html).toContain('MYR 120 Total Revenue');
 expect(html).not.toContain('MYR 1320 Total Revenue');
});
