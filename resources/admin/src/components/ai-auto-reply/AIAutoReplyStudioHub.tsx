import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Bot,
  Sparkles,
  BookOpen,
  ShieldAlert,
  PlayCircle,
  Save,
  RotateCcw,
  CheckCircle2,
  Lock,
  Power,
  Layers,
  HelpCircle,
  Info
} from 'lucide-react';
import { AIPersonalityTab } from './AIPersonalityTab';
import { AIKnowledgeScopeTab } from './AIKnowledgeScopeTab';
import { AIGuardrailsTab } from './AIGuardrailsTab';
import { AISimulatorTab } from './AISimulatorTab';

type AIStudioTab = 'personality' | 'knowledge' | 'guardrails' | 'simulator';

export const AIAutoReplyStudioHub: React.FC = () => {
  const { aiAutoReplyConfig, saveAIAutoReplyConfig, resetAIAutoReplyToDefaults, addToast } = useAdmin();
  const [activeTab, setActiveTab] = useState<AIStudioTab>('personality');

  const isMasterEnabled = aiAutoReplyConfig.personality.isEnabled;

  const handleToggleMaster = () => {
    saveAIAutoReplyConfig({
      ...aiAutoReplyConfig,
      personality: {
        ...aiAutoReplyConfig.personality,
        isEnabled: !isMasterEnabled
      }
    });
  };

  const handleSaveAll = () => {
    saveAIAutoReplyConfig(aiAutoReplyConfig);
  };

  return (
    <div id="ai-auto-reply-studio-hub" className="space-y-6 max-w-7xl mx-auto pb-16">
      {/* Header Banner */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl p-6 shadow-xs">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 shadow-md">
              <Bot className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                  AI Auto Reply & Storefront Agent Studio
                </h1>
                <span
                  className={`px-2.5 py-0.5 rounded-full text-xs font-bold flex items-center gap-1.5 ${
                    isMasterEnabled
                      ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                      : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border border-neutral-300 dark:border-neutral-700'
                  }`}
                >
                  <span
                    className={`w-2 h-2 rounded-full ${
                      isMasterEnabled ? 'bg-emerald-500 animate-pulse' : 'bg-neutral-400'
                    }`}
                  />
                  {isMasterEnabled ? 'AI Auto-Reply Active' : 'AI Offline (Human Only)'}
                </span>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={handleToggleMaster}
              className={`px-4 py-2 text-xs font-bold rounded-xl border transition-all flex items-center gap-2 shadow-xs ${
                isMasterEnabled
                  ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800 hover:bg-rose-100'
                  : 'bg-emerald-600 text-white hover:bg-emerald-700 border-transparent'
              }`}
            >
              <Power className="w-4 h-4" />
              {isMasterEnabled ? 'Pause AI Auto-Reply' : 'Enable AI Auto-Reply'}
            </button>

            <button
              onClick={resetAIAutoReplyToDefaults}
              className="px-3.5 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl transition-colors flex items-center gap-1.5"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              Reset Defaults
            </button>

            <button
              onClick={handleSaveAll}
              className="px-4 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-bold rounded-xl hover:opacity-90 transition-opacity flex items-center gap-1.5 shadow-sm"
            >
              <Save className="w-4 h-4" />
              Save Configuration
            </button>
          </div>
        </div>

        {/* Tab Navigation Navigation Bar */}
        <div className="flex items-center gap-2 mt-6 pt-5 border-t border-neutral-100 dark:border-neutral-800 overflow-x-auto">
          {[
            {
              id: 'personality' as AIStudioTab,
              label: 'AI Personality & Tone',
              sub: 'Persona, tone, prompts & languages',
              icon: Sparkles,
              color: 'text-indigo-600 dark:text-indigo-400'
            },
            {
              id: 'knowledge' as AIStudioTab,
              label: 'AI Knowledge Scope',
              sub: 'FAQ, Orders, Products & Vouchers',
              icon: BookOpen,
              color: 'text-blue-600 dark:text-blue-400'
            },
            {
              id: 'guardrails' as AIStudioTab,
              label: 'AI Safety Guardrails',
              sub: 'Competitors, Bargaining & Anti-Hallucination',
              icon: ShieldAlert,
              color: 'text-rose-600 dark:text-rose-400'
            },
            {
              id: 'simulator' as AIStudioTab,
              label: 'Interactive Simulator · Not connected',
              sub: 'Live preview test & policy inspector',
              icon: PlayCircle,
              color: 'text-emerald-600 dark:text-emerald-400'
            }
          ].map(tab => {
            const isActive = activeTab === tab.id;
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                disabled={tab.id === 'simulator'}
                onClick={() => setActiveTab(tab.id)}
                className={`px-4 py-2.5 rounded-xl border transition-all text-left flex items-center gap-3 whitespace-nowrap ${
                  isActive
                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 border-neutral-900 dark:border-white shadow-xs'
                    : 'bg-neutral-50/80 dark:bg-neutral-800/60 text-neutral-600 dark:text-neutral-400 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
                }`}
              >
                <Icon className={`w-4 h-4 ${isActive ? 'text-white dark:text-neutral-900' : tab.color}`} />
                <div>
                  <div className="text-xs font-bold">{tab.label}</div>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Main Tab View Routing */}
      <div className="transition-all">
        {activeTab === 'personality' && <AIPersonalityTab />}
        {activeTab === 'knowledge' && <AIKnowledgeScopeTab />}
        {activeTab === 'guardrails' && <AIGuardrailsTab />}
        {activeTab === 'simulator' && <AISimulatorTab />}
      </div>
    </div>
  );
};
