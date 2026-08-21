import React, { useState } from 'react';
import {
  ResponsiveContainer,
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  PieChart,
  Pie,
  Cell
} from 'recharts';
import {
  TrendingUp,
  ArrowUpRight,
  ArrowDownRight,
  CreditCard,
  Layers,
  MapPin,
  HelpCircle,
  Download,
  Filter
} from 'lucide-react';
import {
  EXECUTIVE_OVERVIEW_METRICS,
  DAILY_SALES_TREND,
  CHANNEL_PERFORMANCE_LIST,
  REGIONAL_SALES_DATA
} from '../../data/reportsData';
import { ReportChannelFilter } from '../../types/reports';

export const SalesReportView: React.FC<{
  channelFilter: ReportChannelFilter;
  onExport: (reportName: string) => void;
}> = ({ channelFilter, onExport }) => {
  const [metricMode, setMetricMode] = useState<'gmv' | 'net' | 'profit' | 'orders'>('gmv');

  // Filter daily trends if specific channel selected
  const filteredDailyData = DAILY_SALES_TREND.map(d => {
    let activeMetric = d.gmv;
    if (metricMode === 'net') activeMetric = d.netSales;
    if (metricMode === 'profit') activeMetric = d.grossProfit;
    if (metricMode === 'orders') activeMetric = d.ordersCount;

    if (channelFilter === 'web_direct') {
      return { ...d, displayVal: d.webSales };
    } else if (channelFilter === 'tiktok_shop') {
      return { ...d, displayVal: d.tiktokSales };
    } else if (channelFilter === 'shopee') {
      return { ...d, displayVal: d.shopeeSales };
    } else if (channelFilter === 'lazada') {
      return { ...d, displayVal: d.lazadaSales };
    }
    return { ...d, displayVal: activeMetric };
  });

  return (
    <div className="space-y-6">
      {/* 1. Key Metrics Cards Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {EXECUTIVE_OVERVIEW_METRICS.slice(0, 6).map(metric => (
          <div
            key={metric.id}
            id={`metric-card-${metric.id}`}
            className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-800/80 transition-all"
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                {metric.label}
              </span>
              <span
                className={`inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold ${
                  metric.trend === 'up'
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                    : metric.trend === 'down'
                    ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                    : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700'
                }`}
              >
                {metric.trend === 'up' && <ArrowUpRight className="w-3 h-3" />}
                {metric.trend === 'down' && <ArrowDownRight className="w-3 h-3" />}
                {metric.changePercent > 0 ? `+${metric.changePercent}%` : `${metric.changePercent}%`}
              </span>
            </div>

            <div className="mt-3 flex items-baseline gap-2">
              <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 tracking-tight font-mono">
                {metric.value}
              </span>
            </div>

            <div className="mt-2 text-xs text-neutral-500 dark:text-neutral-400 flex items-center justify-between">
              <span>{metric.subtext}</span>
            </div>

            <div className="mt-1 text-[11px] text-neutral-400 dark:text-neutral-500">
              {metric.comparisonPeriod}
            </div>
          </div>
        ))}
      </div>

      {/* 2. Main Sales Time-Series Chart */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                Revenue & Sales Trajectory
              </h3>
              <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-mono">
                MYR (RM)
              </span>
            </div>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              Daily gross sales, net revenue, and gross profit trend lines with promotional spikes.
            </p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            <div className="flex rounded-xl p-1 bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs font-semibold">
              <button
                id="btn-metric-gmv"
                onClick={() => setMetricMode('gmv')}
                className={`px-3 py-1 rounded-lg transition-all ${
                  metricMode === 'gmv'
                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-2xs font-bold'
                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-200'
                }`}
              >
                GMV
              </button>
              <button
                id="btn-metric-net"
                onClick={() => setMetricMode('net')}
                className={`px-3 py-1 rounded-lg transition-all ${
                  metricMode === 'net'
                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-2xs font-bold'
                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-200'
                }`}
              >
                Net Sales
              </button>
              <button
                id="btn-metric-profit"
                onClick={() => setMetricMode('profit')}
                className={`px-3 py-1 rounded-lg transition-all ${
                  metricMode === 'profit'
                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-2xs font-bold'
                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-200'
                }`}
              >
                Gross Profit
              </button>
              <button
                id="btn-metric-orders"
                onClick={() => setMetricMode('orders')}
                className={`px-3 py-1 rounded-lg transition-all ${
                  metricMode === 'orders'
                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-2xs font-bold'
                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-200'
                }`}
              >
                Orders Qty
              </button>
            </div>

            <button
              id="btn-export-sales-chart"
              onClick={() => onExport('Sales-Trend-Report')}
              className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div className="h-72 mt-6">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={filteredDailyData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id="colorGmv" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#6366f1" stopOpacity={0.4} />
                  <stop offset="95%" stopColor="#6366f1" stopOpacity={0.0} />
                </linearGradient>
                <linearGradient id="colorProfit" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#10b981" stopOpacity={0.4} />
                  <stop offset="95%" stopColor="#10b981" stopOpacity={0.0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#88888820" vertical={false} />
              <XAxis
                dataKey="dayName"
                tick={{ fontSize: 11, fill: '#888888' }}
                axisLine={false}
                tickLine={false}
              />
              <YAxis
                tick={{ fontSize: 11, fill: '#888888' }}
                axisLine={false}
                tickLine={false}
                tickFormatter={val => (metricMode === 'orders' ? `${val}` : `RM${val >= 1000 ? `${(val / 1000).toFixed(1)}k` : val}`)}
              />
              <Tooltip
                contentStyle={{
                  backgroundColor: '#171717',
                  border: '1px solid #333',
                  borderRadius: '12px',
                  fontSize: '12px',
                  color: '#fff'
                }}
                formatter={(val: any) => [
                  metricMode === 'orders' ? `${val} Orders` : `RM ${Number(val).toLocaleString()}`,
                  metricMode.toUpperCase()
                ]}
              />
              <Legend wrapperStyle={{ fontSize: '12px', paddingTop: '10px' }} />
              <Area
                type="monotone"
                dataKey="displayVal"
                name={metricMode === 'orders' ? 'Order Count' : 'Gross Value (RM)'}
                stroke="#6366f1"
                strokeWidth={2.5}
                fillOpacity={1}
                fill="url(#colorGmv)"
              />
              {metricMode === 'gmv' && channelFilter === 'all' && (
                <Area
                  type="monotone"
                  dataKey="grossProfit"
                  name="Gross Profit (RM)"
                  stroke="#10b981"
                  strokeWidth={2}
                  fillOpacity={1}
                  fill="url(#colorProfit)"
                />
              )}
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* 3. Multi-Channel Revenue Breakdown & Regional Share */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left: Omnichannel Sales Performance Table & Donut */}
        <div className="lg:col-span-7 p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <div>
              <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                Omnichannel Channel Contribution
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Revenue, AOV, and conversion distribution across storefronts and marketplaces.
              </p>
            </div>
            <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
              5 Connected Channels
            </span>
          </div>

          <div className="overflow-x-auto mt-4">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500">
                  <th className="py-2.5 px-3 font-semibold">Channel</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Orders</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Gross GMV</th>
                  <th className="py-2.5 px-3 font-semibold text-right">AOV</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Conv. Rate</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Share %</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {CHANNEL_PERFORMANCE_LIST.map(ch => (
                  <tr key={ch.channelKey} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                    <td className="py-3 px-3 font-medium text-neutral-800 dark:text-neutral-200 flex items-center gap-2">
                      <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: ch.color }} />
                      <span>{ch.channel}</span>
                    </td>
                    <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                      {ch.orders.toLocaleString()}
                    </td>
                    <td className="py-3 px-3 text-right font-mono font-bold text-neutral-900 dark:text-neutral-100">
                      RM {ch.gmv.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                      RM {ch.aov.toFixed(2)}
                    </td>
                    <td className="py-3 px-3 text-right font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                      {ch.conversionRate}%
                    </td>
                    <td className="py-3 px-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <span className="font-mono text-xs font-bold text-neutral-700 dark:text-neutral-300">
                          {ch.sharePercent}%
                        </span>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Right: Regional Sales Breakdown (Malaysia Peninsular vs East) */}
        <div className="lg:col-span-5 p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                  <MapPin className="w-4 h-4 text-rose-500" />
                  <span>Malaysia State Breakdown</span>
                </h3>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  Regional order distribution & top courier SLA.
                </p>
              </div>
            </div>

            <div className="space-y-3.5 mt-4">
              {REGIONAL_SALES_DATA.map(reg => (
                <div key={reg.state} className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs font-semibold">
                    <span className="text-neutral-800 dark:text-neutral-200">{reg.state}</span>
                    <span className="font-mono text-neutral-900 dark:text-neutral-100">
                      RM {reg.gmv.toLocaleString()} ({reg.sharePercent}%)
                    </span>
                  </div>
                  <div className="w-full h-2 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                    <div
                      className="h-full rounded-full bg-indigo-600 dark:bg-indigo-500 transition-all duration-500"
                      style={{ width: `${reg.sharePercent * 1.8}%` }}
                    />
                  </div>
                  <div className="flex items-center justify-between text-[11px] text-neutral-400 dark:text-neutral-500">
                    <span>Top: {reg.topCourier}</span>
                    <span>Avg {reg.avgDeliveryDays} Days SLA</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
