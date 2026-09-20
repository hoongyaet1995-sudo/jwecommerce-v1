import React,{lazy,Suspense,useEffect,useRef} from 'react';
const InventoryRuntime=lazy(()=>import('../previews/inventory39/Inventory39Runtime').then(module=>({default:module.Inventory39Runtime})));
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
import {decodeInventoryIntake,INVENTORY_INTAKE_STORAGE_KEY} from './inventoryIntake';
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
 const {currentSection,setCurrentSection}=useRootAdmin();
 const intakeRef=useRef<ReturnType<typeof decodeInventoryIntake>>(null);
 if(currentSection==='marketplace-inventory'&&!intakeRef.current)
  intakeRef.current=decodeInventoryIntake(sessionStorage.getItem(INVENTORY_INTAKE_STORAGE_KEY));
 useEffect(()=>{
  if(currentSection==='marketplace-inventory'&&intakeRef.current)sessionStorage.removeItem(INVENTORY_INTAKE_STORAGE_KEY);
  if(currentSection!=='marketplace-inventory')intakeRef.current=null;
 },[currentSection]);
 if(currentSection==='marketplace-inventory'){
  return <Suspense fallback={<p role="status" className="text-sm text-neutral-500">Loading inventory…</p>}>
   <InventoryRuntime onNavigate={setCurrentSection}/>
  </Suspense>;
 }
 if(currentSection==='marketplace-oms')return <OperationalOrders/>;
 if(currentSection==='marketplace-auth')return <OperationalConnectStore/>;
 if(currentSection==='marketplace-mapping')return <OperationalMapping/>;
 return <MarketplaceProvider><Content/></MarketplaceProvider>;
}
