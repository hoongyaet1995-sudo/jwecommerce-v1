import {AIConversationBoundaryControls} from './AIConversationBoundaryControls';
import {AICustomerMemoryControls} from './AICustomerMemoryControls';
import React, { useState } from 'react';
import {AIAttachmentControls} from './AIAttachmentControls';
import { useAdmin } from '../../context/AdminContext';
import {
  ShieldAlert,
  ShieldCheck,
  Ban,
  DollarSign,
  Lock,
  EyeOff,
  AlertOctagon,
  Plus,
  X,
  FileCode,
  Sliders,
  CheckCircle2,
  Sparkles,
  Info,
  Scale
} from 'lucide-react';

export const AIGuardrailsTab: React.FC = () => {
  const { aiAutoReplyConfig, updateAIAutoReplyConfig, addToast } = useAdmin();
  const guardrails = aiAutoReplyConfig.guardrails;

  const [newCompetitor, setNewCompetitor] = useState('');
  const [newForbiddenWord, setNewForbiddenWord] = useState('');

  const handleUpdate = (section: string, field: string, val: any) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      guardrails: {
        ...prev.guardrails,
        [section]: {
          ...(prev.guardrails as any)[section],
          [field]: val
        }
      }
    }));
  };

  const handleAddCompetitor = () => {
    if (!newCompetitor.trim()) return;
    const name = newCompetitor.trim();
    if (guardrails.competitorMentions.blacklistedCompetitors.includes(name)) return;
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      guardrails: {
        ...prev.guardrails,
        competitorMentions: {
          ...prev.guardrails.competitorMentions,
          blacklistedCompetitors: [...prev.guardrails.competitorMentions.blacklistedCompetitors, name]
        }
      }
    }));
    setNewCompetitor('');
    addToast({ type: 'success', title: 'Competitor Added', message: `Added ${name} to blacklist.` });
  };

  const handleRemoveCompetitor = (name: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      guardrails: {
        ...prev.guardrails,
        competitorMentions: {
          ...prev.guardrails.competitorMentions,
          blacklistedCompetitors: prev.guardrails.competitorMentions.blacklistedCompetitors.filter(c => c !== name)
        }
      }
    }));
  };

  const handleAddForbiddenWord = () => {
    if (!newForbiddenWord.trim()) return;
    const word = newForbiddenWord.trim().toLowerCase();
    if (guardrails.profanityAndHarassment.customForbiddenPhrases.includes(word)) return;
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      guardrails: {
        ...prev.guardrails,
        profanityAndHarassment: {
          ...prev.guardrails.profanityAndHarassment,
          customForbiddenPhrases: [...prev.guardrails.profanityAndHarassment.customForbiddenPhrases, word]
        }
      }
    }));
    setNewForbiddenWord('');
    addToast({ type: 'success', title: 'Phrase Added', message: 'Added to forbidden words filter.' });
  };

  const handleRemoveForbiddenWord = (word: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      guardrails: {
        ...prev.guardrails,
        profanityAndHarassment: {
          ...prev.guardrails.profanityAndHarassment,
          customForbiddenPhrases: prev.guardrails.profanityAndHarassment.customForbiddenPhrases.filter(w => w !== word)
        }
      }
    }));
  };

  return (
    <div id="ai-guardrails-tab-container" className="space-y-6 animate-in fade-in duration-150">
      <AICustomerMemoryControls mode="limits" value={aiAutoReplyConfig.customerMemory} onChange={customerMemory=>updateAIAutoReplyConfig(prev=>({...prev,customerMemory}))}/>
      <AIAttachmentControls mode="limits" value={aiAutoReplyConfig.attachmentAnalysis} onChange={attachmentAnalysis=>updateAIAutoReplyConfig(prev=>({...prev,attachmentAnalysis}))}/>
      <p className="text-xs text-neutral-500">FAQ/product grounding and listed-price boundaries are enforced by the server. Disabled legacy controls marked Not connected are unavailable.</p><fieldset disabled className="space-y-6">
      {/* 1. Competitor Mentions & Neutralizer */}
      <section id="competitor-guardrail-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center justify-center flex-shrink-0">
              <Ban className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                  Competitor Brand Neutralizer & Blacklist
                </h2>
                <span className="px-2 py-0.5 text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 rounded-full">
                  {guardrails.competitorMentions.blacklistedCompetitors.length} Brands · Not connected
                </span>
              </div>
            </div>
          </div>

          <button
            onClick={() => handleUpdate('competitorMentions', 'blockOrNeutralize', !guardrails.competitorMentions.blockOrNeutralize)}
            className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5 ${
              guardrails.competitorMentions.blockOrNeutralize
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
            }`}
          >
            {guardrails.competitorMentions.blockOrNeutralize ? <CheckCircle2 className="w-3.5 h-3.5" /> : <Lock className="w-3.5 h-3.5" />}
            Not connected
          </button>
        </div>

        <div className="space-y-4 pt-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                Competitor Handling Strategy
              </label>
              <select
                value={guardrails.competitorMentions.neutralizeStrategy}
                onChange={e => handleUpdate('competitorMentions', 'neutralizeStrategy', e.target.value)}
                className="w-full text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
              >
                <option value="redirect_to_store_advantages">Redirect & Highlight Our Premium Fabric Advantages</option>
                <option value="neutral_polite_refusal">Polite Neutral Refusal (No Discussion)</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                Standard Canned Response on Competitor Query
              </label>
              <input
                type="text"
                value={guardrails.competitorMentions.customCannedResponse}
                onChange={e => handleUpdate('competitorMentions', 'customCannedResponse', e.target.value)}
                className="w-full text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white"
              />
            </div>
          </div>

          {/* Blacklisted Competitors Tags */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
              Blacklisted Competitor & Marketplace Keywords
            </label>
            <div className="flex flex-wrap items-center gap-2 mb-3">
              {guardrails.competitorMentions.blacklistedCompetitors.map(name => (
                <span
                  key={name}
                  className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700"
                >
                  {name}
                  <button
                    onClick={() => handleRemoveCompetitor(name)}
                    className="hover:text-rose-600 dark:hover:text-rose-400"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              ))}
            </div>
            <div className="flex items-center gap-2 max-w-md">
              <input
                type="text"
                value={newCompetitor}
                onChange={e => setNewCompetitor(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && handleAddCompetitor()}
                placeholder="Add brand e.g. 'Zara', 'Taobao', 'Stussy'"
                className="flex-1 text-xs px-3 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
              />
              <button
                onClick={handleAddCompetitor}
                className="px-3 py-2 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity flex items-center gap-1"
              >
                <Plus className="w-3.5 h-3.5" />
                Add Brand
              </button>
            </div>
          </div>
        </div>
      </section>

      </fieldset>
      <p className="text-xs text-neutral-500">Failure messages and human handoff thresholds are managed in AI Personality &amp; Tone. Identity verification and unauthorized-discount protection are always enforced.</p>
      {/* 2. Price Bargaining & Unauthorized Discount Blocker */}
      <section id="bargaining-guardrail-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center flex-shrink-0">
              <DollarSign className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
                Price Bargaining & Unauthorized Discount Blocker
              </h2>
            </div>
          </div>

          <button
            disabled title="System safety: unauthorized discounts cannot be enabled"
            className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5 ${
              guardrails.priceBargaining.blockUnauthorizedDiscounts
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
            }`}
          >
            {guardrails.priceBargaining.blockUnauthorizedDiscounts ? <CheckCircle2 className="w-3.5 h-3.5" /> : <Lock className="w-3.5 h-3.5" />}
            Always enforced
          </button>
        </div>

        <div className="space-y-4 pt-5">
          <div className="p-4 rounded-xl bg-emerald-50/40 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/60 flex items-start gap-3">
            <input
              type="checkbox"
              id="suggest-official-only"
              checked={guardrails.priceBargaining.suggestOfficialVouchersOnly}
              onChange={e => handleUpdate('priceBargaining', 'suggestOfficialVouchersOnly', e.target.checked)}
              className="mt-0.5 rounded-sm text-emerald-600"
            />
            <label htmlFor="suggest-official-only" className="cursor-pointer">
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Auto-Pivot: Offer Official Active Vouchers Only</span>
            </label>
          </div>

          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
              Firm Pricing Response · English · saved and applied
            </label>
            <textarea
              rows={3}
              value={guardrails.priceBargaining.refusalTemplate}
              onChange={e => handleUpdate('priceBargaining', 'refusalTemplate', e.target.value)}
              className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
            />
            {(['ms','zh'] as const).map(language=><label key={language} className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mt-3">Firm Pricing Response · {language==='ms'?'Malay':'Chinese'} · saved and applied<textarea rows={3} aria-label={`Firm Pricing Response ${language}`} value={guardrails.priceBargaining.refusalMessages?.[language]??''} onChange={e=>handleUpdate('priceBargaining','refusalMessages',{...guardrails.priceBargaining.refusalMessages,[language]:e.target.value})} className="w-full text-xs p-3 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500" /></label>)}
          </div>
        </div>
      </section>

      <AIConversationBoundaryControls/>
      <fieldset disabled className="space-y-6">
      {/* 4. PII Masking & Privacy Safeguards */}
      <section id="pii-privacy-guardrail-card" className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-5 shadow-xs">
        <div className="flex items-center gap-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 flex items-center justify-center flex-shrink-0">
            <EyeOff className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-sm font-semibold text-neutral-900 dark:text-white">
              PII Data Redaction & Privacy Guard (PDPA Compliance)
            </h2>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3.5 pt-5">
          <label className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-2.5 cursor-pointer">
            <input
              type="checkbox"
              checked={guardrails.piiDataMasking.maskCreditCards}
              onChange={e => handleUpdate('piiDataMasking', 'maskCreditCards', e.target.checked)}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <div>
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Credit Card Numbers</span>
            </div>
          </label>

          <label className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-2.5 cursor-pointer">
            <input
              type="checkbox"
              checked={guardrails.piiDataMasking.maskNationalIds}
              onChange={e => handleUpdate('piiDataMasking', 'maskNationalIds', e.target.checked)}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <div>
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">NRIC / Passport IDs</span>
            </div>
          </label>

          <label className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-2.5 cursor-pointer">
            <input
              type="checkbox"
              checked={guardrails.piiDataMasking.maskFullAddresses}
              onChange={e => handleUpdate('piiDataMasking', 'maskFullAddresses', e.target.checked)}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <div>
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Home Addresses</span>
            </div>
          </label>

          <label className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 flex items-start gap-2.5 cursor-pointer">
            <input
              type="checkbox"
              checked={guardrails.piiDataMasking.maskPhoneNumbers}
              onChange={e => handleUpdate('piiDataMasking', 'maskPhoneNumbers', e.target.checked)}
              className="mt-0.5 rounded-sm text-indigo-600"
            />
            <div>
              <span className="text-xs font-bold text-neutral-900 dark:text-white block">Phone Numbers</span>
            </div>
          </label>
        </div>
      </section>

      </fieldset>
    </div>
  );
};
