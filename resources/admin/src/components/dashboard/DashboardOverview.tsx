import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import { primaryProductImage } from '../../catalogMedia';
import {
  TrendingUp,
  ShoppingBag,
  Users,
  DollarSign,
  Package,
  AlertTriangle,
  ArrowUpRight,
  ArrowDownRight,
  CheckCircle2,
  Clock,
  Sparkles,
  Layers,
  ChevronRight
} from 'lucide-react';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  BarChart,
  Bar
} from 'recharts';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import { fetchDashboardData, type DashboardData } from '../../integration/dashboardData';

const SALES_CHART_DATA = [
  { day: 'Mon', revenue: 14200, orders: 110 },
  { day: 'Tue', revenue: 18900, orders: 145 },
  { day: 'Wed', revenue: 16400, orders: 130 },
  { day: 'Thu', revenue: 22800, orders: 180 },
  { day: 'Fri', revenue: 29500, orders: 235 },
  { day: 'Sat', revenue: 34100, orders: 290 },
  { day: 'Sun', revenue: 28300, orders: 215 },
];

const CATEGORY_DISTRIBUTION = [
  { name: 'Electronics', count: 42, fill: '#6366f1' },
  { name: 'Audio & HiFi', count: 18, fill: '#06b6d4' },
  { name: 'Apparel', count: 29, fill: '#10b981' },
  { name: 'Bags & Gear', count: 15, fill: '#f59e0b' },
  { name: 'Wearables', count: 14, fill: '#ec4899' },
];

export const DashboardOverview: React.FC = () => {
  const { products, orders, customers, setCurrentSection, setSelectedOrderId, theme } = useAdmin();
  const bootstrap = React.useMemo(() => readAdminBootstrap(), []);
  const [dashboardData, setDashboardData] = React.useState<DashboardData | null>(null);

  React.useEffect(() => {
    let active = true;

    void fetchDashboardData(bootstrap)
      .then((data) => {
        if (active && data) setDashboardData(data);
      })
      .catch((error: unknown) => console.error('Unable to load dashboard data.', error));

    return () => {
      active = false;
    };
  }, [bootstrap]);

  const totalRevenue = dashboardData
    ? dashboardData.metrics.sales_today / 100
    : orders.reduce((acc, o) => acc + (o.paymentGatewayStatus === 'paid' ? o.totalAmount : 0), 0) + 164280;
  const totalOrders = dashboardData?.metrics.orders ?? 1280;
  const activeCustomers = dashboardData?.metrics.customers ?? 4812;
  const averageOrderValue = dashboardData && dashboardData.metrics.orders > 0
    ? dashboardData.metrics.sales_today / dashboardData.metrics.orders / 100
    : 143.9;
  const salesChartData = dashboardData
    ? dashboardData.revenueTrend.map((point) => ({ day: point.label, revenue: point.total / 100, orders: 0 }))
    : SALES_CHART_DATA;
  const categoryDistribution = dashboardData
    ? dashboardData.categoryCounts.map((category, index) => ({
      name: category.name,
      count: category.productsCount,
      fill: ['#6366f1', '#06b6d4', '#10b981', '#f59e0b'][index % 4],
    }))
    : CATEGORY_DISTRIBUTION;
  const recentOrders = dashboardData
    ? dashboardData.recentOrders.map((order) => ({
      id: String(order.id),
      orderNumber: order.number,
      customerName: order.customerName,
      itemCount: order.itemCount,
      totalAmount: order.total / 100,
      fulfillmentStatus: order.status,
    }))
    : orders.map((order) => ({
      id: order.id,
      orderNumber: order.orderNumber,
      customerName: order.customerName,
      itemCount: order.items.length,
      totalAmount: order.totalAmount,
      fulfillmentStatus: order.fulfillmentStatus,
    }));
  const pendingOrderCount = dashboardData
    ? dashboardData.metrics.pending_orders
    : orders.filter(o => o.fulfillmentStatus === 'processing' || o.fulfillmentStatus === 'unfulfilled').length;
  const lowStockProducts = dashboardData
    ? dashboardData.lowStockVariants.map((variant) => ({
      productTitle: variant.product?.title ?? '',
      variantTitle: variant.title,
      sku: variant.sku,
      stock: variant.stock,
      image: variant.image,
    }))
    : products.flatMap(p =>
      p.variants.filter(v => v.inventoryQuantity < 30).map(v => ({
        productTitle: p.title,
        variantTitle: v.title,
        sku: v.sku,
        stock: v.inventoryQuantity,
        image: v.image || primaryProductImage(p)
      }))
    );

  const tooltipBg = theme === 'dark' ? '#171717' : '#ffffff';
  const tooltipBorder = theme === 'dark' ? '#262626' : '#e5e7eb';
  const tooltipText = theme === 'dark' ? '#ffffff' : '#111827';

  return (
    <div className="space-y-6 pb-12">
      {/* Welcome Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-neutral-900 dark:from-indigo-900/40 dark:via-purple-900/30 dark:to-neutral-900 border border-indigo-500/20 shadow-xl text-white">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
              Operations Center
            </span>
            <span className="text-xs text-indigo-200/80 dark:text-neutral-400">August 2026 Snapshot</span>
          </div>
          <h2 className="text-xl sm:text-2xl font-black text-white tracking-tight mt-1">
            Universal Ecommerce Intelligence
          </h2>
          <p className="text-xs text-indigo-100/80 dark:text-neutral-300 mt-1 max-w-xl">
            Live telemetry for 3-tier storefront navigation, Cartesian SKU variant matrices, and omni-channel order fulfillment.
          </p>
        </div>

        <div className="flex items-center gap-2 flex-shrink-0">
          <button
            onClick={() => setCurrentSection('menu-header')}
            className="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md flex items-center gap-1.5"
          >
            <Sparkles className="w-3.5 h-3.5" />
            <span>Manage Mega Menu</span>
          </button>
          <button
            onClick={() => setCurrentSection('catalog-products')}
            className="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold backdrop-blur-md border border-white/10 transition-all flex items-center gap-1.5"
          >
            <Package className="w-3.5 h-3.5" />
            <span>SKU Matrix</span>
          </button>
        </div>
      </div>

      {/* 4 Key Metrics */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Metric 1 */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Total Net Sales</span>
            <div className="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <DollarSign className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
              ${totalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
            <span className="flex items-center text-xs font-bold text-emerald-600 dark:text-emerald-400">
              <ArrowUpRight className="w-3.5 h-3.5" /> +14.8%
            </span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">vs. previous 7-day period</p>
        </div>

        {/* Metric 2 */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Total Orders</span>
            <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <ShoppingBag className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
              {totalOrders.toLocaleString('en-US')}
            </span>
            <span className="flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400">
              <ArrowUpRight className="w-3.5 h-3.5" /> +9.2%
            </span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">98.4% fulfillment success</p>
        </div>

        {/* Metric 3 */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Active Customers</span>
            <div className="w-8 h-8 rounded-xl bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
              <Users className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
              {activeCustomers.toLocaleString('en-US')}
            </span>
            <span className="flex items-center text-xs font-bold text-cyan-600 dark:text-cyan-400">
              <ArrowUpRight className="w-3.5 h-3.5" /> +22.4%
            </span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">34% repeat purchase rate</p>
        </div>

        {/* Metric 4 */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Avg. Order Value (AOV)</span>
            <div className="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center">
              <TrendingUp className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
              ${averageOrderValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
            <span className="flex items-center text-xs font-bold text-emerald-600 dark:text-emerald-400">
              <ArrowUpRight className="w-3.5 h-3.5" /> +$8.40
            </span>
          </div>
          <p className="text-[11px] text-neutral-400 mt-1">Across 18 global currencies</p>
        </div>
      </div>

      {/* Visual Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Revenue Trend Chart */}
        <div className="lg:col-span-2 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm flex flex-col">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Revenue & Velocity Trend</h3>
              <p className="text-[11px] text-neutral-400">7-day gross sales performance</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="flex items-center gap-1.5 text-xs text-indigo-500 font-medium">
                <span className="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> Daily Revenue ($)
              </span>
            </div>
          </div>

          <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={salesChartData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.4} />
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0.0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#33333320" vertical={false} />
                <XAxis dataKey="day" stroke="#888888" fontSize={11} tickLine={false} />
                <YAxis stroke="#888888" fontSize={11} tickLine={false} tickFormatter={(v) => `$${v / 1000}k`} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: tooltipBg,
                    borderColor: tooltipBorder,
                    borderRadius: '12px',
                    color: tooltipText,
                    fontSize: '12px',
                    boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                  }}
                  formatter={(val: number) => [`$${val.toLocaleString()}`, 'Revenue']}
                />
                <Area type="monotone" dataKey="revenue" stroke="#6366f1" strokeWidth={3} fillOpacity={1} fill="url(#colorRevenue)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Catalog Categories Breakdown */}
        <div className="p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Catalog Taxonomies</h3>
                <p className="text-[11px] text-neutral-400">Products per category branch</p>
              </div>
              <button
                onClick={() => setCurrentSection('catalog-categories')}
                className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
              >
                Tree View
              </button>
            </div>

            <div className="h-44 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={categoryDistribution} layout="vertical" margin={{ top: 5, right: 15, left: 10, bottom: 5 }}>
                  <XAxis type="number" hide />
                  <YAxis dataKey="name" type="category" stroke="#888888" fontSize={11} tickLine={false} width={80} />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: tooltipBg,
                      borderColor: tooltipBorder,
                      borderRadius: '12px',
                      color: tooltipText,
                      fontSize: '12px',
                      boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                    }}
                  />
                  <Bar dataKey="count" radius={[0, 8, 8, 0]} fill="#6366f1" barSize={14} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs">
            <span className="text-neutral-400">Total active SKUs</span>
            <span className="font-bold text-neutral-900 dark:text-white">
              {products.reduce((acc, p) => acc + p.variants.length, 0)} SKUs in Matrix
            </span>
          </div>
        </div>
      </div>

      {/* Bottom Section: Urgent Low Stock Alerts & Recent Orders */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Low Stock Alerts */}
        <div className="p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <AlertTriangle className="w-4 h-4" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Low Stock Warning</h3>
                <p className="text-[11px] text-neutral-400">Variants below threshold of 30 units</p>
              </div>
            </div>
            <button
              onClick={() => setCurrentSection('catalog-products')}
              className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
            >
              Restock Matrix
            </button>
          </div>

          <div className="space-y-2.5">
            {lowStockProducts.slice(0, 3).map((item, idx) => (
              <div
                key={idx}
                className="flex items-center justify-between p-3 rounded-2xl border border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <img src={item.image} alt={item.productTitle} className="w-9 h-9 rounded-xl object-cover flex-shrink-0" />
                  <div className="truncate">
                    <p className="text-xs font-bold text-neutral-900 dark:text-white truncate">{item.productTitle}</p>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400">{item.variantTitle} • SKU: <span className="font-mono">{item.sku}</span></p>
                  </div>
                </div>
                <div className="text-right flex-shrink-0">
                  <span className="text-xs font-bold text-rose-500 px-2 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/50 border border-rose-500/20">
                    {item.stock} left
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Live Orders Processing Queue */}
        <div className="p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <ShoppingBag className="w-4 h-4" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Recent Orders Stream</h3>
                <p className="text-[11px] text-neutral-400">{pendingOrderCount} orders needing action</p>
              </div>
            </div>
            <button
              onClick={() => setCurrentSection('orders')}
              className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
            >
              View All ({totalOrders})
            </button>
          </div>

          <div className="space-y-2.5">
            {recentOrders.slice(0, 3).map((o) => (
              <div
                key={o.id}
                onClick={() => {
                  setCurrentSection('orders');
                  setSelectedOrderId(o.id);
                }}
                className="group flex items-center justify-between p-3 rounded-2xl border border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30 hover:border-indigo-300 dark:hover:border-indigo-700 cursor-pointer transition-all"
              >
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-mono font-bold text-xs">
                    #
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-neutral-900 dark:text-white">{o.orderNumber}</span>
                      <span className="text-[10px] text-neutral-400">• {o.customerName}</span>
                    </div>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400">{o.itemCount} item(s) • ${o.totalAmount.toFixed(2)}</p>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <span
                    className={`text-[10px] font-semibold px-2 py-0.5 rounded-full capitalize ${
                      o.fulfillmentStatus === 'processing'
                        ? 'bg-amber-100 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800'
                        : o.fulfillmentStatus === 'shipped'
                        ? 'bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-800'
                        : 'bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800'
                    }`}
                  >
                    {o.fulfillmentStatus}
                  </span>
                  <ChevronRight className="w-4 h-4 text-neutral-400 group-hover:translate-x-0.5 transition-transform" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
