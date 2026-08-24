import React, { useState, useEffect, useRef } from 'react';
import {
  Palette,
  Image as ImageIcon,
  Upload,
  Check,
  X,
  Sparkles,
  Link as LinkIcon,
  Layers,
  Wand2,
  Copy,
  AlertCircle
} from 'lucide-react';

export const PRESET_COLOR_PALETTES = [
  { name: 'Midnight Black', hex: '#111827' },
  { name: 'Onyx Black', hex: '#000000' },
  { name: 'Charcoal Slate', hex: '#334155' },
  { name: 'Titanium Gray', hex: '#64748B' },
  { name: 'Silver Mist', hex: '#94A3B8' },
  { name: 'Arctic White', hex: '#F8FAFC' },
  { name: 'Pure White', hex: '#FFFFFF' },
  { name: 'Warm Cream', hex: '#FEF3C7' },
  { name: 'Forest Pine', hex: '#14532D' },
  { name: 'Sage Green', hex: '#84CC16' },
  { name: 'Emerald Green', hex: '#059669' },
  { name: 'Ocean Navy', hex: '#1E3A8A' },
  { name: 'Cobalt Blue', hex: '#2563EB' },
  { name: 'Sky Cyan', hex: '#06B6D4' },
  { name: 'Crimson Red', hex: '#DC2626' },
  { name: 'Coral Rose', hex: '#F43F5E' },
  { name: 'Rose Gold', hex: '#FB7185' },
  { name: 'Sunset Amber', hex: '#D97706' },
  { name: 'Champagne Sand', hex: '#E5D3B3' },
  { name: 'Deep Violet', hex: '#7C3AED' },
];

export function guessColorCode(text: string): string | undefined {
  if (!text) return undefined;
  const val = text.toLowerCase();
  if (val.includes('black') || val.includes('obsidian') || val.includes('noir') || val.includes('dark')) return '#111827';
  if (val.includes('white') || val.includes('arctic') || val.includes('snow') || val.includes('blanc')) return '#F8FAFC';
  if (val.includes('slate') || val.includes('gray') || val.includes('grey') || val.includes('silver') || val.includes('titanium')) return '#64748B';
  if (val.includes('cream') || val.includes('ivory') || val.includes('beige') || val.includes('sand') || val.includes('tan')) return '#FEF3C7';
  if (val.includes('pine') || val.includes('forest') || val.includes('moss') || val.includes('hunter')) return '#14532D';
  if (val.includes('sage') || val.includes('olive') || val.includes('mint') || val.includes('lime')) return '#84CC16';
  if (val.includes('green') || val.includes('emerald') || val.includes('matcha')) return '#059669';
  if (val.includes('navy') || val.includes('midnight blue') || val.includes('indigo')) return '#1E3A8A';
  if (val.includes('blue') || val.includes('cobalt') || val.includes('azure') || val.includes('sapphire')) return '#2563EB';
  if (val.includes('cyan') || val.includes('teal') || val.includes('aqua') || val.includes('turquoise')) return '#06B6D4';
  if (val.includes('red') || val.includes('crimson') || val.includes('ruby') || val.includes('scarlet')) return '#DC2626';
  if (val.includes('rose') || val.includes('pink') || val.includes('blush') || val.includes('coral')) return '#FB7185';
  if (val.includes('orange') || val.includes('amber') || val.includes('copper') || val.includes('rust')) return '#D97706';
  if (val.includes('gold') || val.includes('brass') || val.includes('champagne')) return '#E5D3B3';
  if (val.includes('purple') || val.includes('violet') || val.includes('lavender') || val.includes('plum')) return '#7C3AED';
  if (val.includes('yellow') || val.includes('sun')) return '#FACC15';
  if (val.includes('brown') || val.includes('wood') || val.includes('leather') || val.includes('coffee')) return '#78350F';
  return undefined;
}

interface VariantVisualPickerModalProps {
  isOpen: boolean;
  onClose: () => void;
  targetTitle: string;
  targetOptionName?: string;
  targetOptionValue?: string;
  initialVisualType?: 'color' | 'image' | 'none';
  initialColorCode?: string;
  initialOptionImage?: string;
  availableProductImages?: string[];
  onUpload?: (file: File) => Promise<string>;
  onSave: (result: {
    visualType: 'color' | 'image' | 'none';
    colorCode?: string;
    optionImage?: string;
    applyToAllMatching?: boolean;
  }) => void;
}

export const VariantVisualPickerModal: React.FC<VariantVisualPickerModalProps> = ({
  isOpen,
  onClose,
  targetTitle,
  targetOptionName,
  targetOptionValue,
  initialVisualType = 'none',
  initialColorCode = '#111827',
  initialOptionImage = '',
  availableProductImages = [],
  onUpload,
  onSave
}) => {
  // Determine starting mode
  const [selectedMode, setSelectedMode] = useState<'color' | 'image' | 'none'>('color');
  const [colorCode, setColorCode] = useState<string>('#111827');
  const [optionImage, setOptionImage] = useState<string>('');
  const [applyToAllMatching, setApplyToAllMatching] = useState<boolean>(true);
  const [dragActive, setDragActive] = useState<boolean>(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isOpen) {
      if (initialVisualType === 'image' || (!initialVisualType && initialOptionImage && !initialColorCode)) {
        setSelectedMode('image');
        setOptionImage(initialOptionImage || availableProductImages[0] || '');
        setColorCode(initialColorCode || '#111827');
      } else if (initialVisualType === 'color' || initialColorCode) {
        setSelectedMode('color');
        setColorCode(initialColorCode || guessColorCode(targetOptionValue || targetTitle) || '#111827');
        setOptionImage(initialOptionImage || '');
      } else {
        // Default to color mode with guess
        const guessed = guessColorCode(targetOptionValue || targetTitle);
        setSelectedMode('color');
        setColorCode(guessed || '#111827');
        setOptionImage('');
      }
      setApplyToAllMatching(true);
    }
  }, [isOpen, initialVisualType, initialColorCode, initialOptionImage, targetTitle, targetOptionValue, availableProductImages]);

  if (!isOpen) return null;

  const guessedHex = guessColorCode(targetOptionValue || targetTitle);

  const handleFileUpload = async (file: File) => {
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      alert('Please upload a PNG, JPG, WEBP, or GIF image up to 10MB.');
      return;
    }
    if (!onUpload) return;
    const result = await onUpload(file);
    setOptionImage(result);
    setSelectedMode('image');
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      void handleFileUpload(e.dataTransfer.files[0]);
    }
  };

  const handleConfirm = () => {
    if (selectedMode === 'color') {
      onSave({
        visualType: 'color',
        colorCode: colorCode || '#111827',
        optionImage: undefined, // Clear image when color is chosen (2 choose 1)
        applyToAllMatching
      });
    } else if (selectedMode === 'image') {
      onSave({
        visualType: 'image',
        optionImage: optionImage || availableProductImages[0] || '',
        colorCode: undefined, // Clear color when image is chosen (2 choose 1)
        applyToAllMatching
      });
    } else {
      onSave({
        visualType: 'none',
        colorCode: undefined,
        optionImage: undefined,
        applyToAllMatching
      });
    }
    onClose();
  };

  return (
    <div className="fixed inset-0 z-60 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs animate-in fade-in">
      <div
        id="variant-visual-picker-modal"
        className="w-full max-w-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
      >
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-950/50">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Sparkles className="w-4 h-4" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Option Visual Swatch (2 选 1)
              </h3>
              <p className="text-[11px] text-neutral-500 truncate max-w-xs sm:max-w-md">
                Configure visual for: <span className="font-semibold text-neutral-700 dark:text-neutral-300 font-mono">{targetTitle}</span>
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={onClose}
            className="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 rounded-xl"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* 2-Choose-1 Selection Switcher Bar */}
        <div className="p-4 bg-neutral-100 dark:bg-neutral-800/60 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-2">
          <span className="text-xs font-bold text-neutral-700 dark:text-neutral-300 shrink-0">
            Visual Type (2 选 1):
          </span>

          <div className="grid grid-cols-2 gap-2 flex-1 max-w-sm">
            {/* Option A: Color Palette */}
            <button
              type="button"
              onClick={() => setSelectedMode('color')}
              className={`py-2 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer ${
                selectedMode === 'color'
                  ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-2 ring-indigo-400'
                  : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800'
              }`}
            >
              <Palette className="w-4 h-4" />
              <span>Color Palette / Hex</span>
            </button>

            {/* Option B: Option Image */}
            <button
              type="button"
              onClick={() => setSelectedMode('image')}
              className={`py-2 px-3 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer ${
                selectedMode === 'image'
                  ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-2 ring-indigo-400'
                  : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800'
              }`}
            >
              <ImageIcon className="w-4 h-4" />
              <span>Option Image Upload</span>
            </button>
          </div>
        </div>

        {/* Modal Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {/* ==================================================== */}
          {/* MODE 1: COLOR PALETTE & CODE                         */}
          {/* ==================================================== */}
          {selectedMode === 'color' && (
            <div className="space-y-4 animate-in fade-in duration-150">
              {/* Color Preview & Active Hex Bar */}
              <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between gap-4">
                <div className="flex items-center gap-3.5">
                  <div
                    className="w-12 h-12 rounded-2xl border-2 border-white dark:border-neutral-700 shadow-md ring-2 ring-neutral-200 dark:ring-neutral-800 flex items-center justify-center shrink-0 transition-transform"
                    style={{ backgroundColor: colorCode }}
                  />
                  <div>
                    <div className="text-[11px] font-bold text-neutral-500 uppercase tracking-wider">
                      Selected Hex Color Code
                    </div>
                    <div className="text-base font-black font-mono text-neutral-900 dark:text-white">
                      {colorCode.toUpperCase()}
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    value={colorCode}
                    onChange={(e) => setColorCode(e.target.value.toUpperCase())}
                    className="w-9 h-9 p-0.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 cursor-pointer shadow-xs"
                    title="Open Color Wheel"
                  />
                  <input
                    type="text"
                    value={colorCode}
                    onChange={(e) => {
                      let val = e.target.value;
                      if (!val.startsWith('#') && val.length > 0) val = '#' + val;
                      setColorCode(val);
                    }}
                    placeholder="#111827"
                    className="w-24 px-2.5 py-1.5 text-xs font-mono font-bold rounded-xl border bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white uppercase"
                  />
                </div>
              </div>

              {/* Auto Guessed Color Banner if applicable */}
              {guessedHex && guessedHex.toUpperCase() !== colorCode.toUpperCase() && (
                <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300">
                    <Wand2 className="w-3.5 h-3.5" />
                    <span>Auto-detected color from name: <strong className="font-mono">{guessedHex}</strong></span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setColorCode(guessedHex)}
                    className="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-[11px] transition-colors"
                  >
                    Apply Suggestion
                  </button>
                </div>
              )}

              {/* Curated Preset Color Palette Grid */}
              <div className="space-y-2">
                <div className="flex items-center justify-between text-xs font-bold text-neutral-700 dark:text-neutral-300">
                  <span>Preset Color Swatches (Click to Apply)</span>
                  <span className="text-[10px] text-neutral-400 font-normal">20 standard shades</span>
                </div>

                <div className="grid grid-cols-5 sm:grid-cols-10 gap-2">
                  {PRESET_COLOR_PALETTES.map((palette) => {
                    const isSelected = colorCode.toUpperCase() === palette.hex.toUpperCase();
                    return (
                      <button
                        key={palette.hex + palette.name}
                        type="button"
                        onClick={() => setColorCode(palette.hex)}
                        title={`${palette.name} (${palette.hex})`}
                        className={`group relative aspect-square rounded-xl border-2 transition-all flex items-center justify-center cursor-pointer ${
                          isSelected
                            ? 'border-indigo-600 ring-2 ring-indigo-400 scale-105 shadow-md'
                            : 'border-neutral-200 dark:border-neutral-700 hover:scale-105'
                        }`}
                        style={{ backgroundColor: palette.hex }}
                      >
                        {isSelected && (
                          <Check
                            className={`w-3.5 h-3.5 font-bold ${
                              ['#FFFFFF', '#F8FAFC', '#FEF3C7', '#E5D3B3'].includes(palette.hex.toUpperCase())
                                ? 'text-black'
                                : 'text-white'
                            }`}
                          />
                        )}
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>
          )}

          {/* ==================================================== */}
          {/* MODE 2: OPTION IMAGE UPLOAD & GALLERY                */}
          {/* ==================================================== */}
          {selectedMode === 'image' && (
            <div className="space-y-4 animate-in fade-in duration-150">
              {/* Option Image Preview & Drag-Drop Box */}
              <div
                onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                onDragLeave={() => setDragActive(false)}
                onDrop={handleDrop}
                className={`p-4 rounded-2xl border-2 border-dashed transition-all flex flex-col sm:flex-row items-center gap-4 ${
                  dragActive
                    ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20'
                    : 'border-neutral-300 dark:border-neutral-700 bg-neutral-50/70 dark:bg-neutral-800/40'
                }`}
              >
                <div className="relative w-20 h-20 rounded-2xl bg-neutral-900 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center shadow-md">
                  {optionImage ? (
                    <img
                      src={optionImage}
                      alt="Option Swatch"
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <ImageIcon className="w-8 h-8 text-neutral-500" />
                  )}
                  {optionImage && (
                    <button
                      type="button"
                      onClick={() => setOptionImage('')}
                      className="absolute top-1 right-1 p-1 rounded-full bg-black/70 text-white hover:bg-rose-600 transition-colors"
                      title="Remove Image"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  )}
                </div>

                <div className="flex-1 text-center sm:text-left space-y-1.5">
                  <div className="text-xs font-bold text-neutral-900 dark:text-white">
                    Upload Variant Option Swatch Image
                  </div>
                  <p className="text-[11px] text-neutral-500">
                    Drag and drop an image file here, or browse from your computer.
                  </p>
                  <div className="pt-1 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      className="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
                    >
                      <Upload className="w-3.5 h-3.5" />
                      <span>Browse Local Image</span>
                    </button>
                    <input
                      ref={fileInputRef}
                      type="file"
                      accept="image/*"
                      onChange={(e) => {
                        if (e.target.files?.[0]) void handleFileUpload(e.target.files[0]);
                      }}
                      className="hidden"
                    />
                  </div>
                </div>
              </div>

              {/* Direct URL Input */}
              <div className="space-y-1">
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                  Or Paste External Image URL:
                </label>
                <div className="relative">
                  <LinkIcon className="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    type="url"
                    placeholder="https://images.unsplash.com/... or CDN link"
                    value={optionImage}
                    onChange={(e) => setOptionImage(e.target.value)}
                    className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                  />
                </div>
              </div>

              {/* Select from Product Gallery Images */}
              {availableProductImages.length > 0 && (
                <div className="space-y-2 pt-2">
                  <div className="flex items-center justify-between text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    <span>Select from Product Media Gallery:</span>
                    <span className="text-[10px] text-neutral-400">{availableProductImages.length} photos</span>
                  </div>

                  <div className="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    {availableProductImages.map((imgUrl, idx) => {
                      const isSelected = optionImage === imgUrl;
                      return (
                        <button
                          key={imgUrl + idx}
                          type="button"
                          onClick={() => setOptionImage(imgUrl)}
                          className={`relative aspect-square rounded-xl overflow-hidden border-2 transition-all group cursor-pointer ${
                            isSelected
                              ? 'border-indigo-600 ring-2 ring-indigo-400 scale-105 shadow-md'
                              : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-400'
                          }`}
                        >
                          <img
                            src={imgUrl}
                            alt={`Gallery ${idx + 1}`}
                            className="w-full h-full object-cover"
                          />
                          {isSelected && (
                            <div className="absolute inset-0 bg-indigo-600/40 flex items-center justify-center text-white">
                              <Check className="w-4 h-4 font-bold" />
                            </div>
                          )}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Batch Apply Checkbox */}
          {targetOptionValue && (
            <div className="p-3.5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 flex items-start gap-2.5">
              <input
                type="checkbox"
                id="apply-matching-check"
                checked={applyToAllMatching}
                onChange={(e) => setApplyToAllMatching(e.target.checked)}
                className="mt-0.5 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
              />
              <label htmlFor="apply-matching-check" className="text-xs text-neutral-800 dark:text-neutral-200 cursor-pointer">
                <span className="font-bold text-indigo-900 dark:text-indigo-300">Sync to all variants: </span>
                Apply this {selectedMode === 'color' ? 'color code' : 'option image'} to all matrix variants matching &quot;
                <span className="font-semibold underline">{targetOptionValue}</span>&quot;.
              </label>
            </div>
          )}
        </div>

        {/* Modal Footer Actions */}
        <div className="px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 flex items-center justify-between">
          <button
            type="button"
            onClick={() => {
              setSelectedMode('none');
              setColorCode('');
              setOptionImage('');
              onSave({
                visualType: 'none',
                colorCode: undefined,
                optionImage: undefined,
                applyToAllMatching
              });
              onClose();
            }}
            className="text-xs font-semibold text-neutral-500 hover:text-rose-500 transition-colors"
          >
            Clear / Reset Swatch
          </button>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
            >
              Cancel
            </button>

            <button
              type="button"
              onClick={handleConfirm}
              className="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/20"
            >
              Apply Visual Swatch
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
