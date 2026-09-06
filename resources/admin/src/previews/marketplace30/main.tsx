import React from 'react';
import {createRoot} from 'react-dom/client';
import {AdminProvider, useAdmin} from './context/AdminContext';
import {MarketplaceMappingPage} from './components/marketplace/MarketplaceMappingPage';
import {MarketplaceHub} from './components/marketplace/MarketplaceHub';
import {MarketplaceAuthPage} from './components/marketplace/MarketplaceAuthPage';
import {MarketplaceInventoryPage} from './components/marketplace/MarketplaceInventoryPage';
import {MarketplaceOMSPage} from './components/marketplace/MarketplaceOMSPage';
import {MarketplaceSyncPage} from './components/marketplace/MarketplaceSyncPage';
import {ToastContainer} from './components/common/ToastContainer';
import './source.css';

function Preview(){
  const {currentSection} = useAdmin();
  const pages = {
    'marketplace': MarketplaceHub,
    'marketplace-mapping': MarketplaceMappingPage,
    'marketplace-auth': MarketplaceAuthPage,
    'marketplace-inventory': MarketplaceInventoryPage,
    'marketplace-oms': MarketplaceOMSPage,
    'marketplace-sync': MarketplaceSyncPage,
  };
  const Page = pages[currentSection as keyof typeof pages] || MarketplaceMappingPage;
  return <>
    <aside style={{padding:8,background:'#fff3cd',color:'#332701',font:'12px sans-serif'}}>LOCAL ISOLATED DEMO — simulated data only. No real store, inventory or product changes. Official Admin navigation is unchanged.</aside>
    <main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 p-4 md:p-8"><Page /></main>
    <ToastContainer />
  </>;
}
createRoot(document.getElementById('root')!).render(<AdminProvider><Preview /></AdminProvider>);
