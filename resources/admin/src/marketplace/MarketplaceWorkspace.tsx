import React from 'react';
import {useAdmin as useRootAdmin} from '../context/AdminContext';
import {OperationalConnectStore} from './OperationalConnectStore';
import {OperationalMapping} from './OperationalMapping';
import {OperationalOrders} from './OperationalOrders';
import {MarketplaceProvider,useAdmin} from './context/AdminContext';
import {MarketplaceMappingPage} from './components/marketplace/MarketplaceMappingPage';
import {MarketplaceHub} from './components/marketplace/MarketplaceHub';
import {MarketplaceAuthPage} from './components/marketplace/MarketplaceAuthPage';
import {MarketplaceOMSPage} from './components/marketplace/MarketplaceOMSPage';
import {MarketplaceInventoryPage} from './components/marketplace/MarketplaceInventoryPage';
import {MarketplaceSyncPage} from './components/marketplace/MarketplaceSyncPage';
function Content(){
 const {currentSection,loading,error,reload}=useAdmin();
 if(currentSection==='marketplace-auth')return <OperationalConnectStore/>;
 if(currentSection==='marketplace-mapping')return <OperationalMapping/>;
 if(loading)return <p role="status" className="text-sm text-neutral-500">Loading Marketplace settings…</p>;
 if(error)return <div role="alert" className="text-sm text-rose-500">{error}<button className="ml-3 underline" onClick={()=>void reload()}>Retry</button></div>;
 const Page=currentSection==='marketplace-mapping'?MarketplaceMappingPage:currentSection==='marketplace-auth'?MarketplaceAuthPage:currentSection==='marketplace-oms'?MarketplaceOMSPage:currentSection==='marketplace-inventory'?MarketplaceInventoryPage:currentSection==='marketplace-sync'?MarketplaceSyncPage:MarketplaceHub;
 return <><p className="mb-4 text-xs text-neutral-500">Open API not connected. Store configuration is available; remote catalog, orders, publishing and stock synchronization are not enabled.</p><Page/></>;
}
export function MarketplaceWorkspace(){
 const {currentSection}=useRootAdmin();
 if(currentSection==='marketplace-oms')return <OperationalOrders/>;
 if(currentSection==='marketplace-auth')return <OperationalConnectStore/>;
 if(currentSection==='marketplace-mapping')return <OperationalMapping/>;
 return <MarketplaceProvider><Content/></MarketplaceProvider>;
}
