import React from 'react';
import { renderToString } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import { normalizeAIAutoReplyConfig } from './normalizeAIAutoReply';
import { DEFAULT_AI_AUTO_REPLY_CONFIG } from './defaultAIAutoReply';
import { AIGuardrailsTab } from '../components/ai-auto-reply/AIGuardrailsTab';
import { AISimulatorTab } from '../components/ai-auto-reply/AISimulatorTab';

const state = vi.hoisted(() => ({ config: null as any }));
vi.mock('../context/AdminContext', () => ({ useAdmin: () => ({ aiAutoReplyConfig: state.config, updateAIAutoReplyConfig: vi.fn(), addToast: vi.fn() }) }));

describe('AI Studio configuration compatibility', () => {
  it('keeps a missing personality enable flag disabled and preserves explicit saved choices', () => {
    expect(DEFAULT_AI_AUTO_REPLY_CONFIG.personality.isEnabled).toBe(false);
    expect(normalizeAIAutoReplyConfig({personality:{isEnabled:true}}).personality.isEnabled).toBe(true);
    expect(normalizeAIAutoReplyConfig({personality:{isEnabled:false}}).personality.isEnabled).toBe(false);
  });
  it('retains saved FAQ records when source defaults contain no demo records', () => {
    const items = [{ id: 'saved', category: 'shipping', question: 'Saved question', answer: 'Saved answer', keywords: [], isActive: true, lastUpdatedAt: '2026-10-03' }];
    expect(normalizeAIAutoReplyConfig({ knowledgeScope: { faqKnowledge: { items } } }).knowledgeScope.faqKnowledge.items).toEqual(items);
  });
  it('renders both source tabs using the default configuration', () => {
    state.config = DEFAULT_AI_AUTO_REPLY_CONFIG;
    expect(renderToString(<AIGuardrailsTab />)).toContain('Competitor Brand Neutralizer');
    expect(renderToString(<AISimulatorTab />)).toContain('Active Knowledge Scope Checklist');
  });
  it('preserves legacy switches, lists, responses and threshold units', () => {
    const config = normalizeAIAutoReplyConfig({ guardrails: {
      preventCompetitorMentions: { isEnabled: false, competitorBlacklist: ['Saved brand'], standardRefusalMessage: 'Saved response' },
      strictPriceBargainingGuard: { isEnabled: false, enforcementMode: 'strict_refusal', refusalResponse: 'No bargaining' },
      antiHallucinationRules: { confidenceCutoffThreshold: 0.78, unrelatedQueryResponse: 'Store only' },
      piiAndDataMasking: { maskCreditCardNumbers: false }, customForbiddenWords: [],
    }});
    expect(config.guardrails.competitorMentions).toMatchObject({ blockOrNeutralize: false, blacklistedCompetitors: ['Saved brand'], customCannedResponse: 'Saved response' });
    expect(config.guardrails.priceBargaining).toMatchObject({ blockUnauthorizedDiscounts: false, suggestOfficialVouchersOnly: false, refusalTemplate: 'No bargaining' });
    expect(config.guardrails.antiHallucinationRules.confidenceThresholdCutoff).toBe(78);
    expect(config.guardrails.piiDataMasking.maskCreditCards).toBe(false);
    expect(config.guardrails.profanityAndHarassment.customForbiddenPhrases).toEqual([]);
  });
  it('fills partial nested settings, prefers current fields and survives malformed lists', () => {
    state.config = normalizeAIAutoReplyConfig({ personality: { greetingMessage: { en: 'Saved greeting' } }, guardrails: {
      competitorMentions: { blockOrNeutralize: false, blacklistedCompetitors: 'bad list' },
      preventCompetitorMentions: { isEnabled: true }, profanityAndHarassment: null,
    }});
    expect(state.config.guardrails.competitorMentions.blockOrNeutralize).toBe(false);
    expect(state.config.personality.greetingMessage.en).toBe('Saved greeting');
    expect(renderToString(<AIGuardrailsTab />)).toContain('Competitor Brand Neutralizer');
    expect(renderToString(<AISimulatorTab />)).toContain('Saved greeting');
  });
});
