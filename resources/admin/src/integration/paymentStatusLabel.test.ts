import {expect,it} from 'vitest';
import {paymentStatusLabel} from './paymentStatusLabel';
it('does not misrepresent refunded or failed payments as pending',()=>{
 expect(paymentStatusLabel('partially_refunded')).toBe('Partially Refunded');
 expect(paymentStatusLabel('failed')).toBe('Failed');
 expect(paymentStatusLabel('paid')).toBe('Paid');
});
