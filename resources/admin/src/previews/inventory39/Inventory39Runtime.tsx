import React from 'react';
import {Inventory39Provider} from './context/AdminContext';
import {MarketplaceInventoryPage} from './components/marketplace/MarketplaceInventoryPage';

export function Inventory39Runtime({onNavigate}:{onNavigate?:(section:any)=>void}){
  return <Inventory39Provider onNavigate={onNavigate}><MarketplaceInventoryPage/></Inventory39Provider>;
}
