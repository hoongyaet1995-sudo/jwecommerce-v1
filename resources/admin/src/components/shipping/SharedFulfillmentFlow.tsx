import React,{useState} from 'react';
import {EasyParcelOAuthModal} from './EasyParcelOAuthModal';
import {useAdmin} from '../../context/AdminContext';
import {ShipmentParcel} from '../../types/shipping';
import {ShipmentPreparationPage} from './ShipmentPreparationPage';
export function SharedFulfillmentFlow({parcel,onClose}:{parcel:ShipmentParcel;onClose:()=>void}){
 const {orders=[],easyParcelProvider,quoteEasyParcelShipment,bookEasyParcelShipment,recordExternalCourierDispatch,recordManualDeliveryDispatch,courierConfigs=[],pingCourierAPI,disconnectEasyParcel,saveEasyParcelSender}=useAdmin();
 const [connecting,setConnecting]=useState(false);
 const courier=courierConfigs.find(c=>c.courierCode==='easyparcel');
 const order=orders.find(o=>o.id===parcel.orderId);
 return <><ShipmentPreparationPage key={parcel.id} parcel={parcel} address={order?.shippingAddress} connected={easyParcelProvider?.connectionState==='connected'} onConnect={()=>setConnecting(true)} onClose={onClose} onQuote={quoteEasyParcelShipment} onBook={bookEasyParcelShipment} onExternal={recordExternalCourierDispatch} onManual={recordManualDeliveryDispatch}/>{connecting&&<EasyParcelOAuthModal isOpen provider={easyParcelProvider??null} webhookCallbackUrl={window.__JW_ADMIN__?.easyParcelWebhookCallbackUrl??''} onClose={()=>setConnecting(false)} onAuthorize={environment=>{if(courier)void pingCourierAPI(courier.id,environment)}} onDisconnect={disconnectEasyParcel} onSaveSenderProfile={saveEasyParcelSender}/>}</>;
}
