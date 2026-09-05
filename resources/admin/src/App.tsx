import React, { useState } from 'react';
import { AdminProvider, useAdmin } from './context/AdminContext';
import { AdminSidebar } from './components/layout/AdminSidebar';
import { AdminHeader } from './components/layout/AdminHeader';
import { CommandPalette } from './components/layout/CommandPalette';
import { NotificationDrawer } from './components/layout/NotificationDrawer';
import { ToastContainer } from './components/common/ToastContainer';

// Pages / Sub-components
import { DashboardOverview } from './components/dashboard/DashboardOverview';
import { HeaderMenuAdmin } from './components/menus/HeaderMenuAdmin';
import { FooterMenuAdmin } from './components/menus/FooterMenuAdmin';
import { ProductAdmin } from './components/catalog/ProductAdmin';
import { CategoryAdmin } from './components/catalog/CategoryAdmin';
import { CollectionAdmin } from './components/catalog/CollectionAdmin';
import { OrderAdmin } from './components/orders/OrderAdmin';
import { CustomerAdmin } from './components/customers/CustomerAdmin';
import { DeveloperSpecs } from './components/dev/DeveloperSpecs';
import { PageAdmin } from './components/pages/PageAdmin';
import { ShopSettingsHub } from './components/settings/ShopSettingsHub';
import { MarketplaceHub } from './components/marketplace/MarketplaceHub';
import { MarketplaceAuthPage } from './components/marketplace/MarketplaceAuthPage';
import { MarketplaceOMSPage } from './components/marketplace/MarketplaceOMSPage';
import { MarketplaceInventoryPage } from './components/marketplace/MarketplaceInventoryPage';
import { MarketplaceSyncPage } from './components/marketplace/MarketplaceSyncPage';
import { StorefrontHub } from './components/cms/StorefrontHub';
import { StorefrontHeaderAnnouncementEditor } from './components/cms/StorefrontHeaderAnnouncementEditor';
import { CatalogHub } from './components/catalog/CatalogHub';
import { ProductReviewsAdmin } from './components/catalog/ProductReviewsAdmin';
import { CommerceHub } from './components/orders/CommerceHub';
import { ShippingDashboard } from './components/shipping/ShippingDashboard';
import { CustomerSupportHub } from './components/support/CustomerSupportHub';
import { ReportsHub } from './components/reports/ReportsHub';
import { AIAutoReplyStudioHub } from './components/ai-auto-reply/AIAutoReplyStudioHub';
import { PromotionsHub } from './components/promotions/PromotionsHub';

const AdminLayoutContent: React.FC = () => {
  const { currentSection, isSidebarCollapsed, selectedPageId } = useAdmin();
  const isPageEditorStandalone = currentSection === 'pages-builder' && Boolean(selectedPageId);
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

  const renderActiveSection = () => {
    // Check if currentSection is settings or starts with settings-
    if (currentSection === 'settings' || currentSection.startsWith('settings-')) {
      return <ShopSettingsHub />;
    }

    // Check if currentSection is reports or starts with reports-
    if (currentSection === 'reports' || currentSection.startsWith('reports-')) {
      return <ReportsHub />;
    }

    switch (currentSection) {
      case 'dashboard':
        return <DashboardOverview />;
      case 'promotions':
        return <PromotionsHub />;
      case 'support':
        return <CustomerSupportHub />;
      case 'ai-auto-reply':
        return <AIAutoReplyStudioHub />;
      case 'shipping-malaysia':
        return <ShippingDashboard />;
      case 'marketplace':
        return <MarketplaceHub />;
      case 'marketplace-auth':
        return <MarketplaceAuthPage />;
      case 'marketplace-oms':
        return <MarketplaceOMSPage />;
      case 'marketplace-inventory':
        return <MarketplaceInventoryPage />;
      case 'marketplace-sync':
        return <MarketplaceSyncPage />;
      case 'storefront':
        return <StorefrontHub />;
      case 'storefront-header-announcement':
        return <StorefrontHeaderAnnouncementEditor />;
      case 'pages-builder':
        return <PageAdmin />;
      case 'menu-header':
        return <HeaderMenuAdmin />;
      case 'menu-footer':
        return <FooterMenuAdmin />;
      case 'catalog':
        return <CatalogHub />;
      case 'catalog-products':
        return <ProductAdmin />;
      case 'catalog-reviews':
        return <ProductReviewsAdmin />;
      case 'catalog-categories':
        return <CategoryAdmin />;
      case 'catalog-collections':
        return <CollectionAdmin />;
      case 'commerce':
        return <CommerceHub />;
      case 'orders':
        return <OrderAdmin />;
      case 'customers':
        return <CustomerAdmin />;
      case 'architecture-spec':
        return <DeveloperSpecs />;
      default:
        return <DashboardOverview />;
    }
  };

  return (
    <div className="flex h-screen w-screen overflow-hidden bg-neutral-100 dark:bg-neutral-950 font-sans text-neutral-900 dark:text-neutral-100 antialiased selection:bg-indigo-500 selection:text-white">
      {/* Left Sidebar */}
      {!isPageEditorStandalone && <AdminSidebar
        isOpen={mobileSidebarOpen}
        onCloseMobile={() => setMobileSidebarOpen(false)}
      />}

      {/* Mobile Backdrop Overlay */}
      {!isPageEditorStandalone && mobileSidebarOpen && (
        <div
          onClick={() => setMobileSidebarOpen(false)}
          className="fixed inset-0 z-30 bg-black/60 backdrop-blur-xs lg:hidden transition-opacity"
        />
      )}

      {/* Main Content Area (Offset by 72 on lg screens when not collapsed) */}
      <div className={`flex flex-col flex-1 min-w-0 h-full overflow-hidden transition-all duration-300 ease-in-out ${isPageEditorStandalone ? 'lg:pl-0' : isSidebarCollapsed ? 'lg:pl-0' : 'lg:pl-72'}`}>
        {/* Top Header */}
        {!isPageEditorStandalone && <AdminHeader onToggleMobileSidebar={() => setMobileSidebarOpen(!mobileSidebarOpen)} />}

        {/* Dynamic Route View */}
        <main className={`flex-1 overflow-hidden ${isPageEditorStandalone ? 'p-0 m-0 w-full h-full' : currentSection === 'pages-builder' ? 'overflow-y-auto p-2 sm:p-4' : 'overflow-y-auto p-4 sm:p-6 lg:p-8'} bg-neutral-100/70 dark:bg-neutral-950/70`}>
          <div className={currentSection === 'pages-builder' ? 'w-full h-full' : 'max-w-7xl mx-auto'}>
            {renderActiveSection()}
          </div>
        </main>
      </div>

      {/* Global Utilities & Modals */}
      <CommandPalette />
      <NotificationDrawer />
      <ToastContainer />
    </div>
  );
};

export default function App() {
  return (
    <AdminProvider>
      <AdminLayoutContent />
    </AdminProvider>
  );
}
