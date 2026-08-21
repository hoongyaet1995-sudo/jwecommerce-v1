import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Package,
  FolderTree,
  Sparkles,
  ArrowRight,
  Boxes,
  Plus,
  TrendingUp,
  Tag,
  Layers,
  Filter
} from 'lucide-react';

export const CatalogHub: React.FC = () => {
  const { setCurrentSection, products = [], categories = [], collections = [] } = useAdmin();

  const safeProducts = products || [];
  const safeCategories = categories || [];
  const safeCollections = collections || [];

  const inStockCount = safeProducts.filter((p) => p.status === 'active' && p.stock > 0).length;
  const lowStockCount = safeProducts.filter((p) => p.stock > 0 && p.stock <= 5).length;
  const dynamicCollectionsCount = safeCollections.filter((c) => c.type === 'dynamic').length;

  const cards = [
    {
      id: 'catalog-products' as const,
      title: 'Products & Variants',
      shortName: 'SKU Inventory Matrix',
      description: 'Manage master product catalog, multi-attribute variants (colors, sizes), retail & cost pricing, barcode identifiers, and warehouse stock allocations.',
      icon: Package,
      iconBg: 'from-emerald-500 to-teal-600',
      badge: `${safeProducts.length} Master SKUs`,
      badgeColor: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
      highlights: [
        `${inStockCount} In-Stock Active Products`,
        `${lowStockCount} Low Stock Threshold Alerts`,
        'Variant Combination Matrix Generator',
        'Bulk CSV Import / Export',
      ],
      actionLabel: 'Manage Products',
    },
    {
      id: 'catalog-categories' as const,
      title: 'Category Hierarchy',
      shortName: 'Taxonomy & SEO',
      description: 'Create multi-level parent and child category trees, customize SEO slugs, assign icons, and organize category storefront sorting order.',
      icon: FolderTree,
      iconBg: 'from-teal-500 to-cyan-600',
      badge: `${safeCategories.length} Categories`,
      badgeColor: 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/20',
      highlights: [
        'Nested Sub-Category Hierarchy',
        'SEO Meta Title & Keywords',
        'Custom SVG / Lucide Icon Binding',
        'Drag-and-Drop Sort Order',
      ],
      actionLabel: 'Manage Categories',
    },
    {
      id: 'catalog-collections' as const,
      title: 'Smart Collections',
      shortName: 'Dynamic Rules Engine',
      description: 'Build automated product groupings using smart conditional rules (by tag, price range, stock) or manually curated promotional lists.',
      icon: Sparkles,
      iconBg: 'from-cyan-500 to-blue-600',
      badge: `${safeCollections.length} Collections`,
      badgeColor: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-500/20',
      highlights: [
        `${dynamicCollectionsCount} Dynamic Rule Collections`,
        'Condition Matching (AND/OR Logic)',
        'Custom Banner Cover Images',
        'Scheduled Publishing Status',
      ],
      actionLabel: 'Manage Collections',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-neutral-900 via-teal-950 to-neutral-900 border border-neutral-800 p-6 sm:p-8 text-white shadow-lg">
        <div className="relative z-10 space-y-2 max-w-2xl">
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-500/20 text-teal-300 border border-teal-500/30">
              <Package className="w-3.5 h-3.5 text-teal-400" />
              Catalog Central
            </span>
            <span className="text-xs text-neutral-400">Products, Categories & Rules</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
            Catalog & Product Management
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            Central repository for your entire product catalog, nested category taxonomy, and automated smart collection groupings.
          </p>
        </div>

        {/* Quick KPI Bar */}
        <div className="mt-6 pt-6 border-t border-neutral-800 grid grid-cols-3 gap-4">
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Master Products</p>
            <p className="text-xl font-bold text-white mt-0.5">{products.length}</p>
            <span className="text-[10px] text-emerald-400">{inStockCount} active in stock</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Category Taxonomy</p>
            <p className="text-xl font-bold text-white mt-0.5">{categories.length}</p>
            <span className="text-[10px] text-teal-400">Hierarchical structure</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Collections</p>
            <p className="text-xl font-bold text-white mt-0.5">{collections.length}</p>
            <span className="text-[10px] text-cyan-400">{dynamicCollectionsCount} auto-rule powered</span>
          </div>
        </div>
      </div>

      {/* Feature Cards Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">Catalog Modules</h2>
          <span className="text-xs text-neutral-500">Select a section to manage</span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          {cards.map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.id}
                onClick={() => setCurrentSection(card.id)}
                className="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:border-teal-300 dark:hover:border-teal-700/60 transition-all duration-200 shadow-xs hover:shadow-md cursor-pointer"
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
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">
                    {card.title}
                  </h3>

                  <p className="mt-2.5 text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    {card.description}
                  </p>

                  <div className="mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800/80 space-y-1.5">
                    {card.highlights.map((h, i) => (
                      <div key={i} className="flex items-center gap-1.5 text-[11px] text-neutral-600 dark:text-neutral-400">
                        <div className="w-1.5 h-1.5 rounded-full bg-teal-500 shrink-0" />
                        <span className="truncate">{h}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-6 pt-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs font-bold text-teal-600 dark:text-teal-400 group-hover:translate-x-0.5 transition-transform">
                  <span>{card.actionLabel}</span>
                  <div className="w-7 h-7 rounded-lg bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white transition-colors">
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
