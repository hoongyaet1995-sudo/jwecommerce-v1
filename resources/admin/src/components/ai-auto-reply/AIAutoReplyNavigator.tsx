import React from 'react';
import { AIChannelControls } from './AIChannelControls';
import { useAdmin } from '../../context/AdminContext';

import {
  Package,
  BookOpen,
  ShieldAlert,
  FolderTree,
  Sparkles,
  ArrowRight,
  Star,
} from 'lucide-react';

export const AIAutoReplyNavigator: React.FC = () => {
  const {setCurrentSection,aiAutoReplyConfig}=useAdmin();
  const metrics={productCount:aiAutoReplyConfig.personality.isEnabled?'Active':'Paused',inStockActiveProductCount:'AI auto-reply',categoryCount:({auto_detect:'Auto detect',en:'English',ms:'Bahasa Melayu',zh:'Chinese'}[aiAutoReplyConfig.personality.primaryLanguage]),nestedCategoryCount:'Multilingual',collectionCount:3,dynamicCollectionCount:'Dedicated pages'};
  const reviews=[1,2,3];const pendingReviewsCount=0;
  const cards=[
   {id:'ai-personality',title:'AI Personality & Tone',shortName:'Identity & Conversation',description:'Define your AI identity, language, tone of voice and human handoff behaviour.',icon:Sparkles,iconBg:'from-indigo-500 to-violet-600',badge:'Persona',badgeColor:'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20',highlights:['Bot identity & role','Tone of voice & languages','Human handoff triggers'],actionLabel:'Manage Personality'},
   {id:'ai-knowledge',title:'AI Knowledge Scope',shortName:'Sources & Answers',description:'Choose the information your AI can use to answer customer questions accurately.',icon:BookOpen,iconBg:'from-teal-500 to-cyan-600',badge:'Knowledge',badgeColor:'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/20',highlights:['FAQ & custom knowledge','Products & order lookup','Promotions & discount rules'],actionLabel:'Manage Knowledge'},
   {id:'ai-guardrails',title:'AI Safety Guardrails',shortName:'Boundaries & Protection',description:'Set response boundaries to protect customer information and keep answers within your business policies.',icon:ShieldAlert,iconBg:'from-rose-500 to-orange-600',badge:'Safety',badgeColor:'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20',highlights:['Anti-hallucination controls','Privacy & competitor boundaries','Pricing & bargaining rules'],actionLabel:'Manage Guardrails'}
  ] as const;
  return (
    <div className="space-y-6">
      {/* Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-neutral-900 via-teal-950 to-neutral-900 border border-neutral-800 p-6 sm:p-8 text-white shadow-lg">
        <div className="relative z-10 flex justify-end mb-4"><AIChannelControls mode="master" /></div>
        <div className="relative z-10 space-y-2 max-w-2xl">
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-500/20 text-teal-300 border border-teal-500/30">
              <Package className="w-3.5 h-3.5 text-teal-400" />
              Agent Studio
            </span>
            <span className="text-xs text-neutral-400">Personality, Knowledge & Safety</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
            AI Auto Reply & Agent Studio
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            Manage how your AI speaks, what it knows, and the boundaries it follows across customer conversations.
          </p>
        </div>

        {/* Quick KPI Bar */}
        <div className="mt-6 pt-6 border-t border-neutral-800 grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">AI Auto-Reply</p>
            <p className="text-xl font-bold text-white mt-0.5">{metrics.productCount}</p>
            <span className="text-[10px] text-emerald-400">{metrics.inStockActiveProductCount} </span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Configuration Areas</p>
            <p className="text-xl font-bold text-white mt-0.5">{reviews.length}</p>
            <span className="text-[10px] text-amber-400">Shared settings</span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">Primary Language</p>
            <p className="text-xl font-bold text-white mt-0.5">{metrics.categoryCount}</p>
            <span className="text-[10px] text-teal-400">{metrics.nestedCategoryCount} </span>
          </div>
          <div className="p-3 rounded-xl bg-white/5 border border-white/5">
            <p className="text-[11px] font-medium text-neutral-400">AI Channels</p>
            <p className="text-xl font-bold text-white mt-0.5">{aiAutoReplyConfig.personality.isEnabled ? Object.entries(aiAutoReplyConfig.aiChannels || {}).filter(([key,on])=>on && key!=='tiktok').length : 0}</p>
            <span className="text-[10px] text-cyan-400">Enabled for automatic replies</span>
          </div>
        </div>
      </div>

      <AIChannelControls mode="channels" />
      {/* Feature Cards Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">AI Modules</h2>
          <span className="text-xs text-neutral-500">Select a section to manage</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {cards.map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.id}
                onClick={() => setCurrentSection(card.id)}
                role="button" tabIndex={0} onKeyDown={e=>{if(e.key==='Enter')setCurrentSection(card.id);}} className="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:border-teal-300 dark:hover:border-teal-700/60 transition-all duration-200 shadow-xs hover:shadow-md cursor-pointer"
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
