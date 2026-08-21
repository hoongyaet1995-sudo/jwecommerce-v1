import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  LayoutTemplate,
  MenuSquare,
  PanelBottom,
  ArrowRight,
  Sparkles,
  Layers,
  Eye,
  CheckCircle2,
  SlidersHorizontal,
  Compass
} from 'lucide-react';

export const StorefrontHub: React.FC = () => {
  const { setCurrentSection, pages = [], headerMenus = [], footerMenus = [] } = useAdmin();

  const publishedPages = (pages || []).filter((p) => p.status === 'published').length;
  const headerL1Count = (headerMenus || []).length;
  const footerL1Count = (footerMenus || []).length;

  const cards = [
    {
      id: 'pages-builder' as const,
      title: 'Design Layout Pages (CMS)',
      shortName: 'Visual Block Builder',
      description: 'Drag-and-drop page builder for homepages, marketing landing pages, category hubs, and campaign banners with customizable block components.',
      icon: LayoutTemplate,
      iconBg: 'from-blue-500 to-indigo-600',
      badge: `${pages.length} Pages (${publishedPages} Live)`,
      badgeColor: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
      highlights: [
        'Live Preview Simulator (Mobile/Tablet/Desktop)',
        'Custom Hero Banners & Slider Blocks',
        'Featured Product & Category Carousels',
        'SEO Metadata & Slug Controls',
      ],
      actionLabel: 'Open Page Builder',
    },
    {
      id: 'menu-header' as const,
      title: 'Header Mega Menu',
      shortName: '3-Tier Navigation Tree',
      description: 'Configure desktop mega menus and mobile navigation trees with 3-tier deep category links, promotional badges, and featured images.',
      icon: MenuSquare,
      iconBg: 'from-indigo-500 to-violet-600',
      badge: `${headerL1Count} Top-Level Items`,
      badgeColor: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20',
      highlights: [
        '3-Tier Deep Nested Navigation',
        'Custom URL & Category Link Mapping',
        'Promo Badges (HOT, NEW, SALE)',
        'Highlight & Dropdown Styles',
      ],
      actionLabel: 'Edit Header Menu',
    },
    {
      id: 'menu-footer' as const,
      title: 'Footer Navigation',
      shortName: '2-Tier Footer Columns',
      description: 'Organize structured footer column groups with customer care links, policies, social media channels, and copyright information.',
      icon: PanelBottom,
      iconBg: 'from-purple-500 to-pink-600',
      badge: `${footerL1Count} Column Sections`,
      badgeColor: 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20',
      highlights: [
        '2-Tier Column Hierarchy',
        'External & Internal Target Links',
        'Policy & Compliance Badges',
        'Responsive Mobile Accordion Preview',
      ],
      actionLabel: 'Edit Footer Menu',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-neutral-900 via-purple-950 to-neutral-900 border border-neutral-800 p-6 sm:p-8 text-white shadow-lg">
        <div className="relative z-10 space-y-2 max-w-2xl">
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
              <Sparkles className="w-3.5 h-3.5 text-purple-400" />
              Storefront CMS
            </span>
            <span className="text-xs text-neutral-400">Visual Layout & Menus</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
            Navigation & Storefront CMS
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            Customize storefront layout pages, header mega menus, and footer navigation columns to deliver a frictionless shopping experience.
          </p>
        </div>

        {/* Quick KPI Bar */}
        <div className="mt-6 pt-6 border-t border-neutral-800 grid grid-cols-3 gap-4">
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Total CMS Pages</p>
            <p className="text-xl font-bold text-white mt-0.5">{pages.length}</p>
            <span className="text-[10px] text-emerald-400">{publishedPages} Published live</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Header Menu Structure</p>
            <p className="text-xl font-bold text-white mt-0.5">3-Tier</p>
            <span className="text-[10px] text-indigo-400">{headerL1Count} primary navigation pillars</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Footer Columns</p>
            <p className="text-xl font-bold text-white mt-0.5">{footerL1Count} Columns</p>
            <span className="text-[10px] text-purple-400">Structured layout</span>
          </div>
        </div>
      </div>

      {/* Feature Cards Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">Storefront Modules</h2>
          <span className="text-xs text-neutral-500">Select a module to edit layout</span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          {cards.map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.id}
                onClick={() => setCurrentSection(card.id)}
                className="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:border-purple-300 dark:hover:border-purple-700/60 transition-all duration-200 shadow-xs hover:shadow-md cursor-pointer"
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
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                    {card.title}
                  </h3>

                  <p className="mt-2.5 text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    {card.description}
                  </p>

                  <div className="mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800/80 space-y-1.5">
                    {card.highlights.map((h, i) => (
                      <div key={i} className="flex items-center gap-1.5 text-[11px] text-neutral-600 dark:text-neutral-400">
                        <div className="w-1.5 h-1.5 rounded-full bg-purple-500 shrink-0" />
                        <span className="truncate">{h}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-6 pt-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs font-bold text-purple-600 dark:text-purple-400 group-hover:translate-x-0.5 transition-transform">
                  <span>{card.actionLabel}</span>
                  <div className="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition-colors">
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
