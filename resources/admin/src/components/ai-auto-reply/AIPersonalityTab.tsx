import {AIKeywordInput, AISettingToggle} from './AISettingInputs';
import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Bot,
  Sparkles,
  Sliders,
  Languages,
  MessageSquare,
  Moon,
  AlertTriangle,
  UserCheck,
  Plus,
  X,
  Volume2,
  HelpCircle,
  Clock,
  ShieldAlert
} from 'lucide-react';
import { AIToneOfVoice, AIPrimaryLanguage } from '../../types/aiAutoReply';

const TONE_PRESETS: { id: AIToneOfVoice; label: string; desc: string; sample: string; badge: string }[] = [
  {
    id: 'luxury_professional',
    label: 'Luxury & Professional',
    desc: 'Sophisticated, refined, polite and authoritative. Ideal for high-end designer apparel.',
    sample: '"Good afternoon. It is our pleasure to assist with your JW Studio luxury curation today."',
    badge: 'Recommended for JW Studio'
  },
  {
    id: 'friendly_helpful',
    label: 'Warm & Friendly',
    desc: 'Approachable, cheerful, empathetic and solution-focused.',
    sample: '"Hi there! 😊 I\'d love to help you find the perfect size or track your package right away!"',
    badge: 'High Engagement'
  },
  {
    id: 'energetic_streetwear',
    label: 'Streetwear & Trendsetter',
    desc: 'Trendy, energetic, youth-culture attuned with streetwear vernacular.',
    sample: '"Yo! Looking for that oversized drop-shoulder boxy fit? Let\'s lock in your size, fam."',
    badge: 'Youth & Hype'
  },
  {
    id: 'concise_efficient',
    label: 'Concise & Fast-Track',
    desc: 'Ultra-direct, zero fluff, bullet-pointed answers for rapid mobile resolution.',
    sample: '"Order #JW-1002 status: In Transit with Pos Laju (Tracking: ER82910291MY). ETA: Tomorrow by 4 PM."',
    badge: 'Mobile Optimized'
  },
  {
    id: 'formal_corporate',
    label: 'Formal Corporate',
    desc: 'Strictly formal, structured corporate tone with conventional business phrasing.',
    sample: '"Thank you for contacting JW Studio. Please find the relevant policy documentation outlined below."',
    badge: 'B2B Wholesale'
  }
];

export const AIPersonalityTab: React.FC = () => {
  const { aiAutoReplyConfig, updateAIAutoReplyConfig, addToast } = useAdmin();
  const personality = aiAutoReplyConfig.personality;

  const [activeLangTab, setActiveLangTab] = useState<'en' | 'ms' | 'zh'>('en');
  const [newKeyword, setNewKeyword] = useState('');
  const [newSupportedLang, setNewSupportedLang] = useState('');

  const handleUpdate = (field: string, val: any) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      personality: {
        ...prev.personality,
        [field]: val
      }
    }));
  };

  const handleGreetingChange = (lang: 'en' | 'ms' | 'zh', text: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      personality: {
        ...prev.personality,
        greetingMessage: {
          ...prev.personality.greetingMessage,
          [lang]: text
        }
      }
    }));
  };

  const handleOfflineChange = (lang: 'en' | 'ms' | 'zh', text: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      personality: {
        ...prev.personality,
        offlineMessage: {
          ...prev.personality.offlineMessage,
          [lang]: text
        }
      }
    }));
  };

  const handleHandoffChange = (lang: 'en' | 'ms' | 'zh', text: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      personality: {
        ...prev.personality,
        fallbackHandoffMessage: {
          ...prev.personality.fallbackHandoffMessage,
          [lang]: text
        }
      }
    }));
  };

  const handleAddKeyword = () => {
    if (!newKeyword.trim()) return;
    if (personality.humanHandoffTriggers.urgentKeywords.includes(newKeyword.trim().toLowerCase())) return;
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      personality: {
        ...prev.personality,
        humanHandoffTriggers: {
          ...prev.personality.humanHandoffTriggers,
          urgentKeywords: [...prev.personality.humanHandoffTriggers.urgentKeywords, newKeyword.trim().toLowerCase()]
        }
      }
    }));
    setNewKeyword('');
    addToast({ type: 'success', title: 'Keyword Added', message: 'Added to human assistance suggestions.' });
  };

  const handleRemoveKeyword = (kw: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      personality: {
        ...prev.personality,
        humanHandoffTriggers: {
          ...prev.personality.humanHandoffTriggers,
          urgentKeywords: prev.personality.humanHandoffTriggers.urgentKeywords.filter(k => k !== kw)
        }
      }
    }));
  };

  return (
    <div id="ai-personality-tab-container" className="space-y-6 animate-in fade-in duration-150">
      {/* 1. Bot Profile & Identity */}
      <section id="ai-bot-identity-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center flex-shrink-0">
              <Bot className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                AI Bot Identity & Profile
              </h2>
            </div>
          </div>
          <span className="px-2.5 py-1 text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-full flex items-center gap-1.5">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
            Active Persona
          </span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-5 pt-5">
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
              Bot Display Name
            </label>
            <input
              type="text"
              value={personality.botName}
              onChange={e => handleUpdate('botName', e.target.value)}
              className="w-full text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
              placeholder="e.g. JW AI Concierge & Stylist"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
              Official Role Title
            </label>
            <input
              type="text"
              value={personality.roleTitle}
              onChange={e => handleUpdate('roleTitle', e.target.value)}
              className="w-full text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
              placeholder="e.g. E-Commerce Stylist & Customer Care Specialist"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
              Primary Language Setting
            </label>
            <select
              value={personality.primaryLanguage}
              onChange={e => handleUpdate('primaryLanguage', e.target.value as AIPrimaryLanguage)}
              className="w-full text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            >
              <option value="auto_detect">Auto Detect Customer Language (Recommended)</option>
              <option value="en">Always Default to English</option>
              <option value="ms">Bahasa Melayu</option>
              <option value="zh">中文 (Chinese)</option>
            </select>
          </div>
        </div>

        {/* Supported Languages Tags */}
        <div className="mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800">
          <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
            Supported Dialects & Multilingual Matrix
          </label>
          <div className="flex flex-wrap items-center gap-2">
            {personality.supportedLanguages.map(lang => (
              <span
                key={lang}
                className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700"
              >
                <Languages className="w-3.5 h-3.5 text-indigo-500" />
                {lang}
              </span>
            ))}
          </div>
        </div>
      </section>

      {/* 2. Tone of Voice & Brand Style */}
      <section id="ai-tone-of-voice-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex items-center gap-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 border border-violet-200 dark:border-violet-800 flex items-center justify-center flex-shrink-0">
            <Volume2 className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
              AI Tone of Voice & Conversational Style
            </h2>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5 pt-5">
          {TONE_PRESETS.map(preset => {
            const isSelected = personality.toneOfVoice === preset.id;
            return (
              <div
                key={preset.id}
                onClick={() => handleUpdate('toneOfVoice', preset.id)}
                className={`cursor-pointer rounded-xl p-4 border transition-all text-left relative flex flex-col justify-between ${
                  isSelected
                    ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-1 ring-indigo-500 dark:border-indigo-500'
                    : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-800/40'
                }`}
              >
                <div>
                  <div className="flex items-center justify-between gap-2 mb-1.5">
                    <span className="text-xs font-bold text-neutral-900 dark:text-white">{preset.label}</span>
                    <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${
                      isSelected
                        ? 'bg-indigo-600 text-white'
                        : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300'
                    }`}>
                      {preset.badge}
                    </span>
                  </div>
                </div>
                <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg p-2.5 text-[11px] italic text-neutral-700 dark:text-neutral-300">
                  {preset.sample}
                </div>
              </div>
            );
          })}
        </div>

        {/* Custom Tone Extra Instructions */}
        <div className="mt-5 pt-4 border-t border-neutral-100 dark:border-neutral-800">
          <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
            Additional Custom Tone Nuances
          </label>
          <textarea
            rows={2}
            maxLength={2000} value={personality.customToneInstructions || ''}
            onChange={e => handleUpdate('customToneInstructions', e.target.value)}
            placeholder="e.g. Always mention that 280GSM heavy cotton feels premium and drop-shoulder fits true to size. Use respectful Malaysian English phrasing."
            className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
          />
        </div>
      </section>

      {/* 3. Core System Prompt & Brand Mission */}
      <section id="ai-system-prompt-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center justify-center flex-shrink-0">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                Brand Mission & Master System Instructions
              </h2>
            </div>
          </div>
          <div className="text-[11px] font-mono text-neutral-400">
            {personality.brandMissionPrompt.length} characters
          </div>
        </div>

        <div className="pt-4 space-y-3">
          <textarea
            rows={7}
            maxLength={4000} value={personality.brandMissionPrompt}
            onChange={e => handleUpdate('brandMissionPrompt', e.target.value)}
            className="w-full font-mono text-xs p-3.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500 leading-relaxed"
          />

          {/* Model Sampling Parameters */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-3">
            <div>
              <div className="flex items-center justify-between text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                <span>Creativity Temperature</span>
                <span className="font-mono text-indigo-600 dark:text-indigo-400">{personality.creativityTemperature.toFixed(2)}</span>
              </div>
              <input
                type="range"
                min="0.0"
                max="1.0"
                step="0.05"
                value={personality.creativityTemperature}
                onChange={e => handleUpdate('creativityTemperature', parseFloat(e.target.value))}
                className="w-full accent-indigo-600"
              />
            </div>

            <div>
              <div className="flex items-center justify-between text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                <span>Max Output Tokens per Turn</span>
                <span className="font-mono text-indigo-600 dark:text-indigo-400">{personality.maxOutputTokens} tokens</span>
              </div>
              <input
                type="range"
                min="150"
                max="600"
                step="20"
                value={personality.maxOutputTokens}
                onChange={e => handleUpdate('maxOutputTokens', parseInt(e.target.value))}
                className="w-full accent-indigo-600"
              />
            </div>
          </div>
        </div>
      </section>

      <fieldset className="space-y-6">
      {/* 4. Automated Greeting & Offline Auto-Responders */}
      <section id="ai-multilingual-messages-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 flex items-center justify-center flex-shrink-0">
              <MessageSquare className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                Multilingual Greeting & System Fallbacks
              </h2>
            </div>
          </div>

          {/* Language Switcher Tabs */}
          <div className="flex items-center p-1 bg-neutral-100 dark:bg-neutral-800 rounded-lg">
            <button
              onClick={() => setActiveLangTab('en')}
              className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors ${
                activeLangTab === 'en'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              English
            </button>
            <button
              onClick={() => setActiveLangTab('ms')}
              className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors ${
                activeLangTab === 'ms'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              Bahasa Melayu
            </button>
            <button
              onClick={() => setActiveLangTab('zh')}
              className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors ${
                activeLangTab === 'zh'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              中文 (Chinese)
            </button>
          </div>
        </div>

        <div className="space-y-4 pt-5">
          {/* Greeting */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
              <span>👋 Welcome Greeting Message ({activeLangTab.toUpperCase()})</span>
            </label>
            <textarea
              rows={2}
              value={personality.greetingMessage[activeLangTab]}
              onChange={e => handleGreetingChange(activeLangTab, e.target.value)}
              className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            />
          </div>

          <AIKeywordInput label="Greeting phrases" values={personality.greetingAliases??[]} onChange={values=>handleUpdate('greetingAliases',values)} maxLength={80} />
          <p className="text-xs text-neutral-500 mb-4">Optional hints for unusual expressions. Common greetings and mixed questions are understood automatically.</p>
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">No Confirmed Answer ({activeLangTab.toUpperCase()})</label>

            <textarea rows={2} maxLength={2000} value={personality.unknownAnswerMessage[activeLangTab]} onChange={e => handleUpdate('unknownAnswerMessage', {...personality.unknownAnswerMessage, [activeLangTab]:e.target.value})} className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500" />
          </div>
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">AI Service Unavailable ({activeLangTab.toUpperCase()})</label>
            <textarea rows={2} maxLength={2000} value={personality.serviceUnavailableMessage[activeLangTab]} onChange={e => handleUpdate('serviceUnavailableMessage', {...personality.serviceUnavailableMessage, [activeLangTab]:e.target.value})} className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500" />
          </div>
          {/* Offline Notice */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
              <span>🌙 After-Hours / Offline Message · Not connected ({activeLangTab.toUpperCase()})</span>
            </label>
            <textarea
              rows={2}
              disabled
              value={personality.offlineMessage[activeLangTab]}
              onChange={e => handleOfflineChange(activeLangTab, e.target.value)}
              className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            />
          </div>

          {/* Handoff Message */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
              <span>🤝 Waiting for Human Support Notice ({activeLangTab.toUpperCase()})</span>
            </label>
            <textarea
              rows={2}
              value={personality.fallbackHandoffMessage[activeLangTab]}
              onChange={e => handleHandoffChange(activeLangTab, e.target.value)}
              className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            />
          </div>
        </div>
      </section>

      </fieldset><fieldset className="space-y-6">
      {/* 5. Human Agent Escalation Triggers */}
      <section id="ai-escalation-triggers-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex items-center gap-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 flex items-center justify-center flex-shrink-0">
            <UserCheck className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
              Human Agent Escalation & Fallback Rules
            </h2>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-5">
          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <AISettingToggle
              id="trigger-frustration"
              checked={personality.humanHandoffTriggers.onFrustrationDetected}
              onChange={e => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  personality: {
                    ...prev.personality,
                    humanHandoffTriggers: {
                      ...prev.personality.humanHandoffTriggers,
                      onFrustrationDetected: e.target.checked
                    }
                  }
                }));
              }}
              className="mt-0.5 rounded-sm text-indigo-600" />
            <label htmlFor="trigger-frustration" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Customer Says the Issue Is Not Resolved</span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <AISettingToggle
              id="trigger-refund"
              checked={personality.humanHandoffTriggers.onRefundOrDispute}
              onChange={e => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  personality: {
                    ...prev.personality,
                    humanHandoffTriggers: {
                      ...prev.personality.humanHandoffTriggers,
                      onRefundOrDispute: e.target.checked
                    }
                  }
                }));
              }}
              className="mt-0.5 rounded-sm text-indigo-600" />
            <label htmlFor="trigger-refund" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Offer Human Support for Disputes / Paid Order Changes</span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <AISettingToggle
              id="trigger-explicit"
              checked={personality.humanHandoffTriggers.onExplicitRequest}
              onChange={e => {
                updateAIAutoReplyConfig(prev => ({
                  ...prev,
                  personality: {
                    ...prev.personality,
                    humanHandoffTriggers: {
                      ...prev.personality.humanHandoffTriggers,
                      onExplicitRequest: e.target.checked
                    }
                  }
                }));
              }}
              className="mt-0.5 rounded-sm text-indigo-600" />
            <label htmlFor="trigger-explicit" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Offer Confirmation When Customer Requests a Human</span>
            </label>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <div className="flex-1">
              <div className="flex items-center justify-between text-xs font-bold text-neutral-900 dark:text-white mb-1">
                <span>Consecutive Failure Threshold</span>
                <span className="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{personality.humanHandoffTriggers.consecutiveFailureCount} failed turns</span>
              </div>
              <input type="range" aria-label="Consecutive failure threshold" min={0} max={5} value={personality.humanHandoffTriggers.consecutiveFailureCount} onChange={e => updateAIAutoReplyConfig(prev => ({...prev,personality:{...prev.personality,humanHandoffTriggers:{...prev.personality.humanHandoffTriggers,consecutiveFailureCount:Number(e.target.value)}}}))} className="w-full accent-indigo-600" />
              <span className="text-xs text-neutral-500">0 disables this trigger.</span>
            </div>
          </div>
          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-3">
            <AISettingToggle id="trigger-unavailable" checked={personality.humanHandoffTriggers.onServiceUnavailable} onChange={e=>updateAIAutoReplyConfig(prev=>({...prev,personality:{...prev.personality,humanHandoffTriggers:{...prev.personality.humanHandoffTriggers,onServiceUnavailable:e.target.checked}}}))} className="mt-0.5 rounded-sm text-indigo-600" />
            <label htmlFor="trigger-unavailable" className="cursor-pointer"><span className="text-xs font-bold text-neutral-900 dark:text-white block">Offer Human Support When AI Is Unavailable</span></label>
          </div>
        </div>

        {/* Urgent Keywords List */}
        <div className="mt-5 pt-4 border-t border-neutral-100 dark:border-neutral-800">
          <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
            Additional Keywords (Offer Human Support)
          </label>
          <div className="flex flex-wrap items-center gap-2 mb-3">
            {personality.humanHandoffTriggers.urgentKeywords.map(kw => (
              <span
                key={kw}
                className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
              >
                {kw}
                <button
                  onClick={() => handleRemoveKeyword(kw)}
                  className="hover:text-rose-900 dark:hover:text-white"
                >
                  <X className="w-3 h-3" />
                </button>
              </span>
            ))}
          </div>
          <div className="flex items-center gap-2 max-w-md">
            <input
              type="text"
              value={newKeyword}
              onChange={e => setNewKeyword(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && handleAddKeyword()}
              placeholder="Add keyword e.g. 'urgent', 'lawyer', 'bank charge'"
              className="flex-1 text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            />
            <button
              onClick={handleAddKeyword}
              className="px-3 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1"
            >
              <Plus className="w-3.5 h-3.5" />
              Add
            </button>
          </div>
        </div>
      </section>
      </fieldset>
    </div>
  );
};
