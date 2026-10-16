import {enquiryRequest,type EnquiryList} from './EnquiryAdmin';
import { useAdminCurrency } from '../../context/useAdminCurrency';
import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  ShoppingBag,
  Users,
  ArrowRight,
  TrendingUp,
  CreditCard,
  Truck,
  ShieldCheck,
  UserCheck,
  Award,
  DollarSign
} from 'lucide-react';

export const CommerceHub: React.FC = () => {
  const [enquiryUnavailable,setEnquiryUnavailable]=React.useState(false);
  const [enquiryCounts,setEnquiryCounts]=React.useState<EnquiryList['counts']|null>(null);
  React.useEffect(()=>{let active=true;enquiryRequest<EnquiryList>('/admin/api/enquiries').then(data=>{if(active)setEnquiryCounts(data.counts);}).catch(()=>{if(active)setEnquiryUnavailable(true);});return()=>{active=false;};},[]);
  const { money, currencyCode } = useAdminCurrency();
  const { setCurrentSection, orders = [], customers = [] } = useAdmin();

  const safeOrders = orders || [];
  const safeCustomers = customers || [];

  const pendingFulfillment = safeOrders.filter(
    (o) => o.fulfillmentStatus === 'processing' || o.fulfillmentStatus === 'unfulfilled'
  ).length;
  const vipCustomersCount = safeCustomers.filter((c) => c.tags?.includes('VIP') || c.totalSpent > 1000).length;
  const totalRevenue = safeOrders.reduce((sum, o) => sum + (o.paymentStatus === 'paid' ? o.totalAmount : 0), 0);

  const cards = [
    {
      id: 'orders' as const,
      title: 'Orders & Fulfillment',
      shortName: 'Store Order Stream',
      description: 'Manage direct web store orders, review customer delivery notes, process tracking numbers, and manage invoices and refunds.',
      icon: ShoppingBag,
      iconBg: 'from-amber-500 to-orange-600',
      badge: pendingFulfillment > 0 ? `${pendingFulfillment} Processing` : 'All Shipped',
      badgeColor: pendingFulfillment > 0 ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
      highlights: [
        `${safeOrders.length} Direct Store Orders`,
        `${money(totalRevenue)} Total Revenue`,
        'Courier Tracking & Shipping Labels',
        'Payment Verification & Tax Records',
      ],
      actionLabel: 'Manage Store Orders',
    },
    {
      id: 'customers' as const,
      title: 'Customer Directory',
      shortName: 'CRM & Loyalty',
      description: 'Track customer profiles, spending history, loyalty reward point balances, delivery address book, and VIP tag segments.',
      icon: Users,
      iconBg: 'from-orange-500 to-rose-600',
      badge: `${safeCustomers.length} Profiles`,
      badgeColor: 'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/20',
      highlights: [
        `${vipCustomersCount} VIP Tier Accounts`,
        'Lifetime Value (LTV) Metrics',
        'Reward Points & Earning History',
        'Multi-Address Verification Book',
      ],
      actionLabel: 'Manage Customers',
    },
    {
      id: 'enquiries' as const, title: 'Customer Enquiries', shortName: 'Enquiries & Follow-up',
      description: 'Review product and service requests, assign a teammate and follow up.',
      icon: ShoppingBag, iconBg: 'from-amber-500 to-orange-600',
      badge: enquiryCounts ? `${enquiryCounts.new} New` : enquiryUnavailable ? 'Temporarily unavailable' : 'Loading enquiries',
      badgeColor: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',
      highlights: ['Customer requirements & selected options', 'Owner, status & internal notes', enquiryCounts ? `${enquiryCounts.all} Total Enquiries` : 'Enquiry records'], actionLabel: 'Manage Enquiries',
    },

  ];

  return (
    <div className="space-y-6">
      {/* Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-neutral-900 via-amber-950 to-neutral-900 border border-neutral-800 p-6 sm:p-8 text-white shadow-lg">
        <div className="relative z-10 space-y-2 max-w-2xl">
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
              <ShoppingBag className="w-3.5 h-3.5 text-amber-400" />
              Direct Commerce
            </span>
            <span className="text-xs text-neutral-400">Web Store Transactions & CRM</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
            Orders & Customer Commerce
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            Process web orders, handle courier fulfillments, manage payments, and nurture customer relationships with VIP tags and reward points.
          </p>
        </div>

        {/* Quick KPI Bar */}
        <div className="mt-6 pt-6 border-t border-neutral-800 grid grid-cols-3 gap-4">
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Total Store Orders</p>
            <p className="text-xl font-bold text-white mt-0.5">{orders.length}</p>
            <span className="text-[10px] text-amber-400">{pendingFulfillment} awaiting fulfillment</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Customer Base</p>
            <p className="text-xl font-bold text-white mt-0.5">{customers.length}</p>
            <span className="text-[10px] text-orange-400">{vipCustomersCount} high-value VIPs</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Direct Store Sales</p>
            <p className="text-xl font-bold text-white mt-0.5">{money(totalRevenue)}</p>
            <span className="text-[10px] text-emerald-400">Paid volume</span>
          </div>
        </div>
      </div>

      {/* Feature Cards Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">Commerce Modules</h2>
          <span className="text-xs text-neutral-500">Select a section to manage</span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          {cards.map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.id}
                onClick={() => setCurrentSection(card.id)}
                className="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:border-amber-300 dark:hover:border-amber-700/60 transition-all duration-200 shadow-xs hover:shadow-md cursor-pointer"
              >
                <div>
                  <div className="flex items-start justify-between gap-3">
                    <div
                      className={`w-11 h-11 rounded-xl bg-gradient-to-tr ${card.iconBg} flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform`}
                    >
                      <Icon className="w-5 h-5" />
                    </div>
                    <span className={`text-[11px] font-semibold px-2 py-0.5 rounded-full border ${card.badgeColor}`}>
                      {card.badge}
                    </span>
                  </div>

                  <span className="inline-block mt-3 text-[10px] uppercase tracking-wider font-semibold text-neutral-400">
                    {card.shortName}
                  </span>
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                    {card.title}
                  </h3>

                  <p className="mt-2.5 text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    {card.description}
                  </p>

                  <div className="mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800/80 space-y-1.5">
                    {card.highlights.map((h, i) => (
                      <div key={i} className="flex items-center gap-1.5 text-[11px] text-neutral-600 dark:text-neutral-400">
                        <div className="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" />
                        <span className="truncate">{h}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-6 pt-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition-transform">
                  <span>{card.actionLabel}</span>
                  <div className="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-colors">
                    <ArrowRight className="w-4 h-4" />
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
