import React from 'react';
import {Check,ArrowRight,Truck} from 'lucide-react';
import '../commerce/commerce.css';
export function ShipmentCompletion({orderNumber,customerName,method,provider,reference,onClose}:{orderNumber:string;customerName:string;method:string;provider?:string;reference?:string;onClose:()=>void}){
 return <section className="jw-shipment-complete" aria-label="Shipment confirmation" role="status">
  <div className="jw-shipment-complete-mark"><Check size={28} strokeWidth={2}/></div>
  <h2>Your shipment is ready to follow</h2>
  <p className="jw-shipment-complete-intro">The delivery arrangements have been saved for this order.</p>
  <div className="jw-shipment-complete-summary">
   <div className="jw-shipment-complete-summary-heading"><Truck size={18}/><span>Delivery summary</span><span className="jw-shipment-complete-badge">Saved</span></div>
   <dl><div><dt>Order</dt><dd>{orderNumber}</dd></div><div><dt>Customer</dt><dd>{customerName}</dd></div><div><dt>Delivery method</dt><dd>{method}</dd></div>{provider&&<div><dt>{method==='Own delivery'?'Driver':'Courier'}</dt><dd>{provider}</dd></div>}{reference&&<div><dt>{method==='Own delivery'?'Delivery reference':'Tracking number'}</dt><dd>{reference}</dd></div>}</dl>
  </div>
  <div className="jw-shipment-complete-actions"><button className="jw-btn jw-btn-primary" onClick={onClose}>Back to shipments<ArrowRight size={16}/></button><p>You can follow progress and update delivery details from Shipping.</p></div>
 </section>
}
