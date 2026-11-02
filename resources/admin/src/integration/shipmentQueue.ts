import type { ShipmentParcel } from '../types/shipping';

export const visibleShipmentParcels = (parcels: ShipmentParcel[]): ShipmentParcel[] => parcels.filter((parcel) => !(
  parcel.source !== 'pending'
  && parcels.some(candidate => (
    candidate.source === 'pending'
    && candidate.orderId === parcel.orderId
    && candidate.capabilities.resume
    && (
      candidate.activeCorrection?.sourceShipmentId === parcel.id
      || candidate.easyParcelRecovery?.recoveryId === parcel.easyParcelRecovery?.recoveryId
    )
  ))
));

// Preparation contains orders, never historical shipment rows.
export const preparationParcels = (parcels: ShipmentParcel[]): ShipmentParcel[] =>
  parcels.filter(parcel => parcel.source === 'pending' && parcel.orderStatus === 'processing');
export const deliveryParcels = (parcels: ShipmentParcel[]): ShipmentParcel[] =>
  parcels.filter(parcel => parcel.source !== 'pending');
