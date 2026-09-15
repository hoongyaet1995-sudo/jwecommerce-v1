import React, { useState, useMemo, useEffect } from 'react';

import {
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Maximize2,
  Eye,
  Info,
  Check,
  Sparkles,
  Layers,
  X
} from 'lucide-react';

export interface VariationImageGroup {
  optionName: string; // Data-driven name, e.g. "Color", "Edition", "Style"
  optionValue: string; // e.g. "Black", "Blue", "Pink", "Maroon"
  candidates: {
    id: string;
    imageUrl: string;
    sku: string;
    label?: string;
  }[];
}

interface ShopeeVariationImagesSectionProps {
  variationGroups: VariationImageGroup[];
  selectedImages: Record<string,string>;
  onSelectImage:(optionValue:string,imageUrl:string)=>void;
}
export const ShopeeVariationImagesSection: React.FC<ShopeeVariationImagesSectionProps> = ({variationGroups,selectedImages,onSelectImage}) => {
  const [previewModalImage, setPreviewModalImage] = useState<{
    url: string;
    title: string;
    sku: string;
  } | null>(null);

  // Compute status for each group
  const groupStatuses = useMemo(() => {
    return variationGroups.map(group => {
      const candidateCount = group.candidates.length;
      const currentSelected = selectedImages[group.optionValue];

      let status: 'auto_selected' | 'choose_image' | 'selected';
      if (candidateCount === 1) {
        status = 'auto_selected';
      } else if (group.candidates.some(candidate=>candidate.imageUrl===currentSelected)) {
        status = 'selected';
      } else {
        status = 'choose_image';
      }

      return {
        group,
        candidateCount,
        currentSelected: currentSelected || (candidateCount === 1 ? group.candidates[0].imageUrl : ''),
        status
      };
    });
  }, [variationGroups, selectedImages]);

  // Calculate readiness metrics
  const totalGroups = groupStatuses.length;
  const readyGroups = groupStatuses.filter(g => g.status === 'auto_selected' || g.status === 'selected');
  const readyCount = readyGroups.length;
  const pendingGroups = groupStatuses
    .filter(g => g.status === 'choose_image')
    .map(g => g.group.optionValue);

  const isAllResolved = pendingGroups.length === 0;

  return (
    <div id="shopee-variation-images-section" className="space-y-4">
      {/* Header Bar with Readiness Tracker */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80">
        <div>
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
              <Layers className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
              Shopee Variation Images ({variationGroups[0]?.optionName || 'Option'} Dimension)
            </span>
            <span
              className={`text-[11px] font-bold px-2 py-0.5 rounded-full border ${
                isAllResolved
                  ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                  : 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'
              }`}
            >
              {readyCount} of {totalGroups} groups ready
            </span>
          </div>
          <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">
            Shopee requires exactly one primary image per variation option value.
          </p>
        </div>

        {/* Informational Policy Badge */}
        <div className="text-[11px] text-neutral-500 dark:text-neutral-400 flex items-center gap-1.5 bg-white dark:bg-neutral-900 px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 shrink-0">
          <Info className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
          <span>Used for this store listing only. JW product images stay unchanged.</span>
        </div>
      </div>

      {/* Variation Groups Grid (2 Columns on wide screens, 1 Column on mobile) */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
        {groupStatuses.map(({ group, status, currentSelected, candidateCount }) => {
          const isPending = status === 'choose_image';
          const isAuto = status === 'auto_selected';
          const isChosen = status === 'selected';

          return (
            <div
              key={group.optionValue}
              className={`p-3.5 rounded-xl border transition-all flex flex-col justify-between ${
                isPending
                  ? 'border-amber-400 dark:border-amber-500/80 bg-amber-50/20 dark:bg-amber-950/15 shadow-xs'
                  : isChosen
                  ? 'border-indigo-600/70 dark:border-indigo-500/70 bg-indigo-50/20 dark:bg-indigo-950/15'
                  : 'border-neutral-200 dark:border-neutral-700/80 bg-white dark:bg-neutral-900/70'
              }`}
            >
              {/* Group Header: Option Name, Option Value, and Status Pill */}
              <div className="flex items-center justify-between gap-2 pb-2.5 border-b border-neutral-100 dark:border-neutral-800">
                <div className="min-w-0">
                  <span className="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 block truncate">
                    {group.optionName}
                  </span>
                  <span className="text-xs font-bold text-neutral-900 dark:text-white truncate block">
                    {group.optionValue}
                  </span>
                </div>

                <div>
                  {isAuto && (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                      <Check className="w-3 h-3 text-emerald-500" />
                      Auto-selected
                    </span>
                  )}
                  {isChosen && (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                      <CheckCircle2 className="w-3 h-3 text-indigo-600 dark:text-indigo-400" />
                      Selected
                    </span>
                  )}
                  {isPending && (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700 animate-pulse">
                      <AlertTriangle className="w-3 h-3 text-amber-600 dark:text-amber-400" />
                      Choose an image
                    </span>
                  )}
                </div>
              </div>

              {/* Body: Single Auto-Selected Compact Display vs Multi-Candidate Selection */}
              {isAuto ? (
                /* Auto-selected: Compact view without expanding large candidate areas */
                <div className="pt-2.5 flex items-center gap-3">
                  <div className="relative group shrink-0">
                    <img
                      src={group.candidates[0].imageUrl}
                      alt={group.optionValue}
                      className="w-14 h-14 rounded-lg object-contain bg-neutral-100 dark:bg-neutral-800 p-1 border border-neutral-200 dark:border-neutral-700"
                    />
                    <button
                      type="button"
                      title="Enlarge preview"
                      onClick={e => {
                        e.stopPropagation();
                        setPreviewModalImage({
                          url: group.candidates[0].imageUrl,
                          title: `${group.optionName}: ${group.optionValue}`,
                          sku: group.candidates[0].sku
                        });
                      }}
                      className="absolute inset-0 bg-black/40 text-white rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                    >
                      <Eye className="w-4 h-4" />
                    </button>
                  </div>

                  <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-medium text-neutral-800 dark:text-neutral-200 truncate">
                      {group.candidates[0].label || `${group.optionValue} Master Asset`}
                    </p>
                    <p className="text-[10px] font-mono text-neutral-400 dark:text-neutral-500 truncate">
                      SKU: {group.candidates[0].sku}
                    </p>
                    <p className="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5 flex items-center gap-1">
                      <Check className="w-3 h-3" />
                      Single candidate resolved automatically
                    </p>
                  </div>
                </div>
              ) : (
                /* Multi-Candidate Conflict: Expanded Candidate Cards */
                <div className="pt-2.5 space-y-2">
                  <div className="flex items-center justify-between text-[11px]">
                    <span className="text-neutral-500 dark:text-neutral-400 font-medium">
                      {candidateCount} candidate photos found:
                    </span>
                    <span className="text-[10px] text-neutral-400">
                      Click image to pick for Shopee
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    {group.candidates.map(candidate => {
                      const isCandidateSelected = currentSelected === candidate.imageUrl;

                      return (
                        <div
                          key={candidate.id}
                          role="button" tabIndex={0} aria-label={`${group.optionValue}: ${candidate.sku}`} aria-pressed={isCandidateSelected}
                          onKeyDown={e=>{if(e.target===e.currentTarget&&(e.key==="Enter"||e.key===" ")){e.preventDefault();onSelectImage(group.optionValue,candidate.imageUrl);}}}
                          onClick={() => onSelectImage(group.optionValue, candidate.imageUrl)}
                          className={`relative p-2 rounded-xl border transition-all cursor-pointer flex flex-col justify-between ${
                            isCandidateSelected
                              ? 'border-indigo-600 dark:border-indigo-500 bg-white dark:bg-neutral-800 ring-2 ring-indigo-500/30 shadow-xs'
                              : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50/60 dark:bg-neutral-800/40 hover:border-neutral-300 dark:hover:border-neutral-600'
                          }`}
                        >
                          {/* Image Box with Independent Zoom Button */}
                          <div className="relative aspect-square w-full rounded-lg bg-neutral-100 dark:bg-neutral-900/80 p-1 flex items-center justify-center overflow-hidden border border-neutral-200/60 dark:border-neutral-700/60">
                            <img
                              src={candidate.imageUrl}
                              alt={candidate.label || candidate.sku}
                              className="w-full h-full object-contain"
                            />

                            {/* Checkmark indicator when selected */}
                            {isCandidateSelected && (
                              <div className="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                                <Check className="w-3 h-3" />
                              </div>
                            )}

                            {/* Independent Zoom Button (e.stopPropagation prevents card selection) */}
                            <button
                              type="button"
                              title="Enlarge preview"
                              onClick={e => {
                                e.stopPropagation();
                                setPreviewModalImage({
                                  url: candidate.imageUrl,
                                  title: `${group.optionName}: ${group.optionValue}`,
                                  sku: candidate.sku
                                });
                              }}
                              className="absolute bottom-1.5 right-1.5 p-1 rounded-md bg-black/60 hover:bg-black/80 text-white transition-colors cursor-pointer"
                            >
                              <Maximize2 className="w-3 h-3" />
                            </button>
                          </div>

                          {/* Secondary SKU and description */}
                          <div className="mt-1.5">
                            <div className="text-[11px] font-mono text-neutral-800 dark:text-neutral-200 truncate font-semibold">
                              {candidate.sku}
                            </div>
                            <div className="text-[10px] text-neutral-400 truncate">
                              {candidate.label || 'Variant asset'}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Bottom Status Banner: Details which groups still need attention */}
      {!isAllResolved ? (
        <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300 flex items-center justify-between gap-2">
          <div className="flex items-center gap-2 min-w-0">
            <AlertCircle className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
            <span className="truncate">
              <strong>Pending image selection for:</strong> {pendingGroups.join(', ')}
            </span>
          </div>
          <span className="text-[11px] font-semibold text-amber-700 dark:text-amber-400 shrink-0">
            Publish is disabled until resolved
          </span>
        </div>
      ) : (
        <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <span>All {totalGroups} variation image groups resolved and ready for Shopee catalog broadcast.</span>
        </div>
      )}

      {/* Image Preview Lightbox Modal */}
      {previewModalImage && (
        <div
          className="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs animate-in fade-in duration-150"
          onClick={() => setPreviewModalImage(null)}
        >
          <div
            className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl p-4 max-w-md w-full space-y-3 shadow-2xl relative"
            onClick={e => e.stopPropagation()}
          >
            <div className="flex items-center justify-between pb-2 border-b border-neutral-200 dark:border-neutral-800">
              <div>
                <h4 className="text-xs font-bold text-neutral-900 dark:text-white">
                  {previewModalImage.title}
                </h4>
                <p className="text-[10px] font-mono text-neutral-400">
                  SKU: {previewModalImage.sku}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setPreviewModalImage(null)}
                className="p-1 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="aspect-square w-full rounded-xl bg-neutral-100 dark:bg-neutral-950 p-2 flex items-center justify-center overflow-hidden border border-neutral-200 dark:border-neutral-800">
              <img
                src={previewModalImage.url}
                alt={previewModalImage.title}
                className="w-full h-full object-contain"
              />
            </div>

            <div className="flex items-center justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
              <span>Full resolution variation preview</span>
              <button
                type="button"
                onClick={() => setPreviewModalImage(null)}
                className="px-3 py-1 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 font-semibold rounded-lg text-xs"
              >
                Close Preview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
