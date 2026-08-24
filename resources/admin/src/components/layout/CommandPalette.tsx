import React, { useState, useEffect, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { primaryProductImage } from '../../catalogMedia';
import {
  Search,
  Package,
  MenuSquare,
  ShoppingBag,
  Users,
  FolderTree,
  FileCode2,
  Moon,
  Sun,
  X,
  ArrowRight,
  Sparkles,
  LayoutTemplate,
  Settings,
  ShieldCheck,
  Key,
  Share2,
  Truck,
  CreditCard,
  MessageSquareText,
  BarChart3
} from 'lucide-react';
import { NavSection } from '../../types';

export const CommandPalette: React.FC = () => {
  const {
    isCommandPaletteOpen,
    setIsCommandPaletteOpen,
    setCurrentSection,
    products,
    categories,
    orders,
    customers,
    pages,
    staffUsers,
    headerMenus,
    toggleTheme,
    setSelectedOrderId,
    setSelectedCustomerId,
    setSelectedPageId
  } = useAdmin();

  const [query, setQuery] = useState('');
  const previousFocusRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (isCommandPaletteOpen) {
      previousFocusRef.current = document.getElementById('btn-command-palette-trigger');
      setQuery('');
    }
  }, [isCommandPaletteOpen]);

  const closePalette = () => {
    setIsCommandPaletteOpen(false);
    window.requestAnimationFrame(() => previousFocusRef.current?.focus());
  };

  useEffect(() => {
    if (!isCommandPaletteOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      closePalette();
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isCommandPaletteOpen]);

  if (!isCommandPaletteOpen) return null;

  const navigateTo = (section: NavSection, action?: () => void) => {
    setCurrentSection(section);
    if (action) action();
    setIsCommandPaletteOpen(false);
  };

  const cleanQuery = query.toLowerCase().trim();

  // Search matches
  const matchedPages = pages.filter(p => p.title.toLowerCase().includes(cleanQuery) || p.slug.toLowerCase().includes(cleanQuery)).slice(0, 3);
  const matchedStaff = staffUsers.filter(s => s.username.toLowerCase().includes(cleanQuery) || s.position.toLowerCase().includes(cleanQuery) || (s.fullName && s.fullName.toLowerCase().includes(cleanQuery))).slice(0, 3);
  const matchedProducts = products.filter(p => p.title.toLowerCase().includes(cleanQuery) || p.brand.toLowerCase().includes(cleanQuery)).slice(0, 3);
  const matchedCategories = categories.filter(c => c.name.toLowerCase().includes(cleanQuery)).slice(0, 3);
  const matchedOrders = orders.filter(o => o.orderNumber.toLowerCase().includes(cleanQuery) || o.customerName.toLowerCase().includes(cleanQuery)).slice(0, 3);
  const matchedCustomers = customers.filter(c => c.name.toLowerCase().includes(cleanQuery) || c.email.toLowerCase().includes(cleanQuery)).slice(0, 3);
  
  const socialKeywords = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin', 'twitter', 'x', 'whatsapp', 'xiaohongshu', '小红书', 'social', 'link'];
  const matchedSocial = socialKeywords.some(kw => cleanQuery.includes(kw));

  const shippingKeywords = ['shipping', 'courier', 'pos malaysia', 'pos', 'j&t', 'jnt', 'ninja', 'ninjavan', 'flash', 'dhl', 'easyparcel', 'delyva', 'awb', 'waybill', 'routing', 'postcode', 'reconciliation', 'freight', 'dispute'];
  const matchedShipping = shippingKeywords.some(kw => cleanQuery.includes(kw));

  const paymentKeywords = ['payment', 'fpx', 'billplz', 'toyyibpay', 'senangpay', 'stripe', 'paypal', 'tng', 'touch n go', 'grabpay', 'atome', 'bnpl', 'duitnow', 'qr', 'bank transfer', 'cod', 'cash on delivery', 'gateway', 'mdr'];
  const matchedPayment = paymentKeywords.some(kw => cleanQuery.includes(kw));

  const reportKeywords = ['report', 'analytics', 'sales', 'revenue', 'sst', 'tax', 'rfm', 'cohort', 'profit', 'cogs', 'velocity', 'logistics sla', 'conversion', 'roas', 'attribution', '报表', '报告'];
  const matchedReports = reportKeywords.some(kw => cleanQuery.includes(kw));

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-150">
      <div
        id="command-palette-modal"
        className="w-full max-w-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh] animate-in zoom-in-95 duration-150"
      >
        {/* Search Input */}
        <div className="flex items-center gap-3 px-4 py-3.5 border-b border-neutral-200 dark:border-neutral-800">
          <Search className="w-5 h-5 text-neutral-400 flex-shrink-0" />
          <input
            type="text"
            placeholder="Type a command, search products, 3-tier menus, orders, customers..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            autoFocus
            className="flex-1 bg-transparent text-sm text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden"
          />
          <button
            onClick={closePalette}
            className="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 rounded-lg"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Results List */}
        <div className="flex-1 overflow-y-auto p-3 space-y-4">
          {/* Quick Navigation Shortcuts */}
          {!cleanQuery && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Quick Navigation</p>
              <div className="space-y-1">
                <button
                  onClick={() => navigateTo('support')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <MessageSquareText className="w-4 h-4 text-indigo-500" />
                    <span>Customer Support & Omnichannel Inbox (WhatsApp, Live Chat, Triage)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('shipping-malaysia')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Truck className="w-4 h-4 text-rose-500" />
                    <span>Malaysia Shipping Center (Pos MY, Ninja Van, J&T, Flash, DHL, EasyParcel)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('marketplace')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Sparkles className="w-4 h-4 text-indigo-500" />
                    <span>Marketplace Ecosystem (TikTok, Shopee, Lazada OMS & WMS)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('storefront')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <LayoutTemplate className="w-4 h-4 text-emerald-500" />
                    <span>Storefront CMS (Page Builder, Mega Menus)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('catalog')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Package className="w-4 h-4 text-sky-500" />
                    <span>Catalog Hub (Products, Categories, Collections)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('commerce')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <ShoppingBag className="w-4 h-4 text-amber-500" />
                    <span>Orders & CRM Hub (Direct Store Sales & Loyalty)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('reports')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <BarChart3 className="w-4 h-4 text-indigo-500" />
                    <span>Reports & Analytics (Sales, Velocity, RFM, Couriers, SST Tax P&L)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Hub</kbd>
                </button>

                <button
                  onClick={() => navigateTo('settings-users')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <ShieldCheck className="w-4 h-4 text-indigo-500" />
                    <span>User Login Setting (Staff RBAC & Roles)</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Jump</kbd>
                </button>

                <button
                  onClick={() => navigateTo('architecture-spec')}
                  className="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <FileCode2 className="w-4 h-4 text-cyan-500" />
                    <span>Route Specs, JSON Schemas & Master Prompt</span>
                  </div>
                  <kbd className="text-[10px] text-neutral-400 font-mono">Jump</kbd>
                </button>
              </div>
            </div>
          )}

          {/* Matched Staff Users */}
          {matchedStaff.length > 0 && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Staff & Admin Users</p>
              <div className="space-y-1">
                {matchedStaff.map(staff => (
                  <button
                    key={staff.id}
                    onClick={() => navigateTo('settings-users')}
                    className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div className={`w-7 h-7 rounded-full ${staff.avatarColor || 'bg-indigo-600'} text-white flex items-center justify-center font-bold text-xs flex-shrink-0`}>
                        {(staff.username || staff.id || 'AD').slice(0, 2).toUpperCase()}
                      </div>
                      <div className="truncate">
                        <div className="truncate font-semibold text-neutral-900 dark:text-white flex items-center gap-2">
                          <span>{staff.username}</span>
                          <span className="text-[10px] px-2 py-0.2 rounded-full font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            {staff.position}
                          </span>
                        </div>
                        <div className="text-[10px] text-neutral-500">{staff.permissions.length} permissions • {staff.isActive ? 'Active' : 'Disabled'}</div>
                      </div>
                    </div>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Matched Social Media Setting */}
          {cleanQuery && matchedSocial && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Social Media Setting</p>
              <button
                onClick={() => navigateTo('settings-social')}
                className="w-full flex items-center justify-between p-2.5 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/30 hover:bg-indigo-100/60 dark:hover:bg-indigo-900/50 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 border border-indigo-200/50 dark:border-indigo-800/50 transition-colors"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-7 h-7 rounded-lg bg-pink-500/20 text-pink-500 flex items-center justify-center flex-shrink-0">
                    <Share2 className="w-4 h-4" />
                  </div>
                  <div className="truncate">
                    <div className="truncate font-semibold text-neutral-900 dark:text-white">Social Media Links Configuration</div>
                    <div className="text-[10px] text-neutral-500">Facebook, Instagram, TikTok, YouTube, LinkedIn, X, WhatsApp, Xiaohongshu</div>
                  </div>
                </div>
                <kbd className="text-[10px] text-indigo-600 dark:text-indigo-400 font-mono">Manage</kbd>
              </button>
            </div>
          )}

          {/* Matched Shipping */}
          {cleanQuery && matchedShipping && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Shipping & Couriers</p>
              <button
                onClick={() => navigateTo('shipping-malaysia')}
                className="w-full flex items-center justify-between p-2.5 rounded-xl bg-rose-50/50 dark:bg-rose-950/30 hover:bg-rose-100/60 dark:hover:bg-rose-900/50 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 border border-rose-200/50 dark:border-rose-800/50 transition-colors"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-7 h-7 rounded-lg bg-rose-500/20 text-rose-500 flex items-center justify-center flex-shrink-0">
                    <Truck className="w-4 h-4" />
                  </div>
                  <div className="truncate">
                    <div className="truncate font-semibold text-neutral-900 dark:text-white">Shipping & Multi-Courier Logistics Center</div>
                    <div className="text-[10px] text-neutral-500">Pos Malaysia, Ninja Van, J&T, Flash, DHL, EasyParcel, Waybills & Reconciliation</div>
                  </div>
                </div>
                <kbd className="text-[10px] text-rose-600 dark:text-rose-400 font-mono">Open</kbd>
              </button>
            </div>
          )}

          {/* Matched Payment */}
          {cleanQuery && matchedPayment && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Payment & Gateways</p>
              <button
                onClick={() => navigateTo('settings-payment')}
                className="w-full flex items-center justify-between p-2.5 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/30 hover:bg-emerald-100/60 dark:hover:bg-emerald-900/50 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 border border-emerald-200/50 dark:border-emerald-800/50 transition-colors"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
                    <CreditCard className="w-4 h-4" />
                  </div>
                  <div className="truncate">
                    <div className="truncate font-semibold text-neutral-900 dark:text-white">Payment Gateways, FPX & Cash on Delivery (COD)</div>
                    <div className="text-[10px] text-neutral-500">Billplz, ToyyibPay, Stripe, TNG eWallet, Atome, DuitNow QR, Bank Transfer, COD Surcharge</div>
                  </div>
                </div>
                <kbd className="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono">Manage</kbd>
              </button>
            </div>
          )}

          {/* Matched Reports */}
          {cleanQuery && matchedReports && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Reports & Business Intelligence</p>
              <button
                onClick={() => navigateTo('reports')}
                className="w-full flex items-center justify-between p-2.5 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/30 hover:bg-indigo-100/60 dark:hover:bg-indigo-900/50 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 border border-indigo-200/50 dark:border-indigo-800/50 transition-colors"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0">
                    <BarChart3 className="w-4 h-4" />
                  </div>
                  <div className="truncate">
                    <div className="truncate font-semibold text-neutral-900 dark:text-white">E-Commerce Reports & Analytics Center</div>
                    <div className="text-[10px] text-neutral-500">Sales Trends, Product Velocity, RFM Segments, Courier SLAs, Malaysian SST Tax Schedules</div>
                  </div>
                </div>
                <kbd className="text-[10px] text-indigo-600 dark:text-indigo-400 font-mono">Open</kbd>
              </button>
            </div>
          )}

          {/* Matched CMS Pages */}
          {matchedPages.length > 0 && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Design Layout Pages</p>
              <div className="space-y-1">
                {matchedPages.map(page => (
                  <button
                    key={page.id}
                    onClick={() => navigateTo('pages-builder', () => setSelectedPageId(page.id))}
                    className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div className="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center flex-shrink-0">
                        <LayoutTemplate className="w-4 h-4" />
                      </div>
                      <div className="truncate">
                        <div className="truncate font-semibold">{page.title}</div>
                        <div className="text-[10px] text-neutral-500">/pages/{page.slug} • {page.sections.length} sections • {page.status}</div>
                      </div>
                    </div>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Matched Products */}
          {matchedProducts.length > 0 && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Products</p>
              <div className="space-y-1">
                {matchedProducts.map(p => (
                  <button
                    key={p.id}
                    onClick={() => navigateTo('catalog-products')}
                    className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <img src={primaryProductImage(p)} alt={p.title} className="w-7 h-7 rounded-lg object-cover flex-shrink-0" />
                      <div className="truncate">
                        <div className="truncate font-semibold">{p.title}</div>
                        <div className="text-[10px] text-neutral-500">{p.brand} • ${p.basePrice} • {p.variants.length} Variants</div>
                      </div>
                    </div>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Matched Orders */}
          {matchedOrders.length > 0 && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Orders</p>
              <div className="space-y-1">
                {matchedOrders.map(o => (
                  <button
                    key={o.id}
                    onClick={() => navigateTo('orders', () => setSelectedOrderId(o.id))}
                    className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <ShoppingBag className="w-4 h-4 text-indigo-500" />
                      <div>
                        <div className="font-semibold">{o.orderNumber} - {o.customerName}</div>
                        <div className="text-[10px] text-neutral-500">${o.totalAmount.toFixed(2)} • {o.fulfillmentStatus}</div>
                      </div>
                    </div>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Matched Customers */}
          {matchedCustomers.length > 0 && (
            <div>
              <p className="text-[11px] font-semibold uppercase text-neutral-400 px-3 pb-1.5">Customers</p>
              <div className="space-y-1">
                {matchedCustomers.map(c => (
                  <button
                    key={c.id}
                    onClick={() => navigateTo('customers', () => setSelectedCustomerId(c.id))}
                    className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800 text-left text-xs font-medium text-neutral-800 dark:text-neutral-200 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <img src={c.avatar} alt={c.name} className="w-6 h-6 rounded-full object-cover" referrerPolicy="no-referrer" />
                      <div>
                        <div className="font-semibold">{c.name}</div>
                        <div className="text-[10px] text-neutral-500">{c.email} • {c.totalOrders} orders (${c.totalSpent})</div>
                      </div>
                    </div>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer info */}
        <div className="px-4 py-2.5 bg-neutral-50 dark:bg-neutral-950 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-[11px] text-neutral-500">
          <span>Navigate with mouse or keyboard</span>
          <span>Esc to close</span>
        </div>
      </div>
    </div>
  );
};
