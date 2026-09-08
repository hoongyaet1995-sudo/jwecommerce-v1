// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {InventoryAdjustmentModal} from './components/marketplace/InventoryAdjustmentModal';
it('uses warehouse reservedStock to calculate ATP',()=>{
 const item:any={masterSku:'TEST',productName:'Test',physicalStockStatus:'verified',totalPhysicalStock:25,reservedStock:1,safetyStock:5,warehouseBalances:[{warehouseId:'one',physicalStock:15,reservedStock:1,safetyStock:3,physicalStockStatus:'verified'}]};
 const html=renderToStaticMarkup(<InventoryAdjustmentModal item={item} isOpen onClose={()=>{}} staffUsers={[]} targetWarehouseId="one" onApplyAdjustment={()=>{}} onRecordAuditCount={()=>{}}/>);
 expect(html).not.toContain('NaN');
 expect(html).toContain('21');
});
it('requires an explicit count for unknown physical stock',()=>{
 const item:any={masterSku:'UNKNOWN',productName:'Unknown',physicalStockStatus:'unknown',totalPhysicalStock:null,reservedStock:0,safetyStock:0};
 const html=renderToStaticMarkup(<InventoryAdjustmentModal item={item} isOpen onClose={()=>{}} staffUsers={[]} onApplyAdjustment={()=>{}} onRecordAuditCount={()=>{}}/>);
 const document=new DOMParser().parseFromString(html,'text/html');
 expect(document.querySelector('input[type="number"]')?.getAttribute('value')).toBe('');
 const review=Array.from(document.querySelectorAll('button')).find(button=>button.textContent?.includes('Proceed to Review'));
 expect(review?.disabled).toBe(true);
 expect(html).not.toContain('NaN');
});
it('uses the selected warehouse verification state even when the aggregate SKU is unknown',()=>{
 const item:any={masterSku:'ZERO',productName:'Zero',physicalStockStatus:'unknown',totalPhysicalStock:null,
  warehouseBalances:[{warehouseId:'zero',physicalStock:0,physicalStockStatus:'verified',reservedStock:0,safetyStock:0}]};
 const html=renderToStaticMarkup(<InventoryAdjustmentModal item={item} isOpen targetWarehouseId="zero" onClose={()=>{}} staffUsers={[]} onApplyAdjustment={()=>{}} onRecordAuditCount={()=>{}}/>);
 expect(html).not.toContain('Awaiting Physical Audit');
 expect(html).not.toContain('Physical stock unverified in warehouse');
});
