import React from 'react';
import {createRoot} from 'react-dom/client';
import '../inventory39/source.css';
import {Inventory39Provider} from '../inventory39/context/AdminContext';
import {MarketplaceInventoryPage} from '../inventory39/components/marketplace/MarketplaceInventoryPage';
const navigate=(section:string) => {
  const routes:Record<string,string>={'marketplace-mapping':'/admin/marketplace/mapping','marketplace-auth':'/admin/marketplace/auth'};
  if(routes[section]) window.location.assign(routes[section]);
};
createRoot(document.getElementById('root')!).render(<Inventory39Provider onNavigate={navigate}><main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100"><MarketplaceInventoryPage recoveryPreview/></main></Inventory39Provider>);
