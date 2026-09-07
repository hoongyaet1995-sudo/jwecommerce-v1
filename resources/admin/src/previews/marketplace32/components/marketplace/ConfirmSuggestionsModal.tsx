import React, { useState } from 'react';
import { 
  X, 
  Sparkles, 
  CheckCircle2, 
  ArrowRight, 
  ShieldCheck, 
  AlertCircle,
  HelpCircle
} from 'lucide-react';
import { MarketplaceStoreProduct, MarketplaceStoreVariant } from '../../types';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceLogo } from './MarketplaceLogo';

interface ConfirmSuggestionsModalProps {
  isOpen: boolean;
  onClose: () => void;
  productsToAccept: MarketplaceStoreProduct[];
  onConfirmed?: () => void;
}

interface CandidateItem {
  storeProductId: string;
  storeVariantId: string;
  storeProductTitle: string;
  storeVariantTitle: string;
  channelSku: string;
  channelPrice: number;
  platform: string;
  shopName: string;
  suggestedJwProductId: string;
  suggestedJwVariantId: string;
  suggestedJwSku: string;
  suggestedJwVariantTitle: string;
  suggestedJwProductName: string;
  confidenceScore: number;
  matchReason: string;
}

export const ConfirmSuggestionsModal: React.FC<ConfirmSuggestionsModalProps> = ({
  isOpen,
  onClose,
  productsToAccept,
  onConfirmed
}) => {
  const { mapStoreVariantToJwSku, addToast } = useAdmin();

  // Extract all candidates with suggestedMatch
  const candidates: CandidateItem[] = React.useMemo(() => {
    const list: CandidateItem[] = [];
    productsToAccept.forEach(prod => {
      prod.variants.forEach(variant => {
        if (variant.mappingStatus === 'unmapped' && variant.suggestedMatch) {
          list.push({
            storeProductId: prod.id,
            storeVariantId: variant.id,
            storeProductTitle: prod.title,
            storeVariantTitle: variant.title,
            channelSku: variant.channelSellerSku,
            channelPrice: variant.price,
            platform: prod.platform,
            shopName: prod.shopName,
            suggestedJwProductId: variant.suggestedMatch.productId,
            suggestedJwVariantId: variant.suggestedMatch.variantId,
            suggestedJwSku: variant.suggestedMatch.sku,
            suggestedJwVariantTitle: variant.suggestedMatch.variantTitle,
            suggestedJwProductName: variant.suggestedMatch.productName,
            confidenceScore: variant.suggestedMatch.confidenceScore,
            matchReason: variant.suggestedMatch.matchReason
          });
        }
      });
    });
    return list;
  }, [productsToAccept]);

  // Selected candidate IDs: Set of `${storeProductId}_${storeVariantId}`
  const [selectedCandidates, setSelectedCandidates] = useState<Set<string>>(
    () => new Set(candidates.map(c => `${c.storeProductId}_${c.storeVariantId}`))
  );

  const toggleSelectCandidate = (key: string) => {
    const next = new Set(selectedCandidates);
    if (next.has(key)) {
      next.delete(key);
    } else {
      next.add(key);
    }
    setSelectedCandidates(next);
  };

  const handleSelectAll = () => {
    if (selectedCandidates.size === candidates.length) {
      setSelectedCandidates(new Set());
    } else {
      setSelectedCandidates(new Set(candidates.map(c => `${c.storeProductId}_${c.storeVariantId}`)));
    }
  };

  const handleApplyMappings = () => {
    let appliedCount = 0;
    candidates.forEach(c => {
      const key = `${c.storeProductId}_${c.storeVariantId}`;
      if (selectedCandidates.has(key)) {
        mapStoreVariantToJwSku(c.storeProductId, c.storeVariantId, c.suggestedJwProductId, c.suggestedJwVariantId);
        appliedCount++;
      }
    });

    addToast({
      type: 'success',
      title: 'Smart Mappings Applied',
      message: `Confirmed and mapped ${appliedCount} variant(s) to JW Master catalog.`
    });

    if (onConfirmed) onConfirmed();
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div 
        id="confirm-suggestions-modal"
        className="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-3xl overflow-hidden flex flex-col max-h-[85vh]"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-violet-500/10 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400 flex items-center justify-center font-bold">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                Review AI Smart Match Candidates
                <span className="text-xs font-semibold px-2 py-0.5 rounded-md bg-violet-100 dark:bg-violet-950/60 text-violet-800 dark:text-violet-300">
                  {candidates.length} Candidate{candidates.length > 1 ? 's' : ''}
                </span>
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Verify AI recommendations before committing catalog mappings.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Info Banner */}
        <div className="px-6 pt-4">
          <div className="p-3.5 rounded-xl bg-violet-50/70 dark:bg-violet-950/30 border border-violet-200/80 dark:border-violet-800/50 flex items-start gap-3">
            <ShieldCheck className="w-4 h-4 text-violet-600 dark:text-violet-400 shrink-0 mt-0.5" />
            <div className="text-xs text-violet-900 dark:text-violet-200">
              <strong>Merchant Verification Required:</strong> AI suggests legal matching candidates based on SKU patterns and title similarity. No mappings are created until you inspect and approve them.
            </div>
          </div>
        </div>

        {/* Candidate List */}
        <div className="flex-1 overflow-y-auto p-6 space-y-3">
          {candidates.length === 0 ? (
            <div className="text-center py-10 text-slate-500 text-sm">
              No unmapped variants with smart match suggestions found in selection.
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={handleSelectAll}
                  className="text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 cursor-pointer flex items-center gap-2"
                >
                  <input
                    type="checkbox"
                    checked={selectedCandidates.size === candidates.length}
                    onChange={handleSelectAll}
                    className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                  />
                  <span>Select All ({selectedCandidates.size}/{candidates.length})</span>
                </button>
                <span className="text-xs text-slate-500 dark:text-slate-400">
                  Uncheck any candidates you wish to skip
                </span>
              </div>

              <div className="space-y-2.5">
                {candidates.map(c => {
                  const key = `${c.storeProductId}_${c.storeVariantId}`;
                  const isChecked = selectedCandidates.has(key);

                  return (
                    <div
                      key={key}
                      onClick={() => toggleSelectCandidate(key)}
                      className={`p-3.5 rounded-xl border transition-all cursor-pointer flex flex-col sm:flex-row sm:items-center justify-between gap-3 ${
                        isChecked
                          ? 'border-violet-300 dark:border-violet-700 bg-violet-50/20 dark:bg-violet-950/10'
                          : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-850 opacity-60'
                      }`}
                    >
                      <div className="flex items-start gap-3">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => toggleSelectCandidate(key)}
                          onClick={(e) => e.stopPropagation()}
                          className="mt-1 rounded border-slate-300 text-violet-600 focus:ring-violet-500 shrink-0"
                        />

                        <div className="space-y-1">
                          {/* Channel Item */}
                          <div className="flex items-center gap-2 text-xs">
                            <span className="font-semibold text-slate-900 dark:text-white">
                              {c.storeProductTitle}
                            </span>
                            <span className="text-slate-400">•</span>
                            <span className="text-slate-600 dark:text-slate-300">
                              {c.storeVariantTitle}
                            </span>
                            <span className="font-mono text-[11px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                              {c.channelSku || 'No SKU'}
                            </span>
                          </div>

                          {/* Arrow & Matched Master Item */}
                          <div className="flex items-center gap-2 text-xs text-violet-900 dark:text-violet-200 font-medium">
                            <ArrowRight className="w-3.5 h-3.5 text-violet-500 shrink-0" />
                            <span>Candidate Master SKU:</span>
                            <span className="font-mono font-bold text-violet-700 dark:text-violet-300 bg-violet-100 dark:bg-violet-950/60 px-1.5 py-0.5 rounded">
                              {c.suggestedJwSku}
                            </span>
                            <span className="text-slate-500 dark:text-slate-400">
                              ({c.suggestedJwProductName} - {c.suggestedJwVariantTitle})
                            </span>
                          </div>

                          {/* Match Reason */}
                          <div className="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                            <span className="font-semibold text-slate-600 dark:text-slate-400">Reason:</span>
                            <span>{c.matchReason}</span>
                          </div>
                        </div>
                      </div>

                      {/* Confidence Score Badge */}
                      <div className="shrink-0 flex items-center gap-2 self-end sm:self-center">
                        <span className={`text-xs font-bold px-2.5 py-1 rounded-lg border ${
                          c.confidenceScore >= 90
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800'
                            : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800'
                        }`}>
                          {c.confidenceScore}% Match
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between">
          <span className="text-xs text-slate-500 dark:text-slate-400">
            {selectedCandidates.size} candidate{selectedCandidates.size === 1 ? '' : 's'} selected for mapping
          </span>

          <div className="flex items-center gap-2.5">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-semibold transition-colors cursor-pointer"
            >
              Cancel
            </button>

            <button
              type="button"
              disabled={selectedCandidates.size === 0}
              onClick={handleApplyMappings}
              className="px-5 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer"
            >
              <CheckCircle2 className="w-4 h-4" />
              Confirm {selectedCandidates.size} Mapping{selectedCandidates.size === 1 ? '' : 's'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
