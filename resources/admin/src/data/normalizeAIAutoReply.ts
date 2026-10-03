import { DEFAULT_AI_AUTO_REPLY_CONFIG } from './defaultAIAutoReply';
import type { AIAutoReplyMasterConfig } from '../types/aiAutoReply';

type RecordValue = Record<string, any>;
const object = (value: unknown): RecordValue => value && typeof value === 'object' && !Array.isArray(value) ? value as RecordValue : {};

// Preserve existing settings; absent or malformed known fields use source defaults.
function merge(defaults: any, saved: any): any {
  if (Array.isArray(defaults) && defaults.length === 0) return Array.isArray(saved) ? [...saved] : [];
  if (Array.isArray(defaults)) return Array.isArray(saved) && saved.every(item => typeof item === 'string') && defaults.every(item => typeof item === 'string') ? [...saved] : Array.isArray(saved) && !defaults.every(item => typeof item === 'string') ? saved : [...defaults];
  if (defaults && typeof defaults === 'object') return { ...object(saved), ...Object.fromEntries(Object.entries(defaults).map(([key, value]) => [key, merge(value, object(saved)[key])])) };
  return typeof defaults === typeof saved && (typeof saved !== 'number' || Number.isFinite(saved)) ? saved : defaults;
}

export function normalizeAIAutoReplyConfig(value: unknown): AIAutoReplyMasterConfig {
  const saved = object(value);
  const g = object(saved.guardrails);
  const competitor = object(g.preventCompetitorMentions);
  const price = object(g.strictPriceBargainingGuard);
  const anti = object(g.antiHallucinationRules);
  const pii = object(g.piiAndDataMasking);
  const toxic = object(g.toxicAndProfanityFilter);
  const mapped = {
    ...saved,
    guardrails: {
      ...g,
      competitorMentions: { blockOrNeutralize: competitor.isEnabled, blacklistedCompetitors: competitor.competitorBlacklist, customCannedResponse: competitor.standardRefusalMessage, ...object(g.competitorMentions) },
      priceBargaining: { blockUnauthorizedDiscounts: price.isEnabled, suggestOfficialVouchersOnly: price.enforcementMode === undefined ? undefined : price.enforcementMode === 'redirect_to_public_promotions', refusalTemplate: price.refusalResponse, ...object(g.priceBargaining) },
      antiHallucinationRules: { refuseNonEcommerceQueries: anti.isEnabled, confidenceThresholdCutoff: typeof anti.confidenceCutoffThreshold === 'number' ? anti.confidenceCutoffThreshold * 100 : undefined, nonEcommerceRefusalMessage: anti.unrelatedQueryResponse, ...anti },
      piiDataMasking: { maskCreditCards: pii.maskCreditCardNumbers, maskNationalIds: pii.maskNationalIdOrIC, maskPhoneNumbers: pii.maskCustomerPersonalPhoneInLogs, ...object(g.piiDataMasking) },
      profanityAndHarassment: { profanityFilterLevel: toxic.isEnabled === undefined ? undefined : toxic.isEnabled ? 'strict' : 'lenient', customForbiddenPhrases: g.customForbiddenWords, ...object(g.profanityAndHarassment) },
    },
  };
  return merge(DEFAULT_AI_AUTO_REPLY_CONFIG, mapped);
}

