import React from 'react';import{createRoot}from'react-dom/client';import'./source.css';
import{Inventory39Provider}from'./context/AdminContext';import{MarketplaceInventoryPage}from'./components/marketplace/MarketplaceInventoryPage';
createRoot(document.getElementById('root')!).render(<Inventory39Provider><main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100"><MarketplaceInventoryPage/></main></Inventory39Provider>);
