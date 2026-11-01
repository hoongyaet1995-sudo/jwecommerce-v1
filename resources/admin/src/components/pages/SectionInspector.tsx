import {PartnerLogoSettings} from './PartnerLogoSettings';
import {IndustrialGallerySettings} from './IndustrialGallerySettings';
import {PhotoMarqueeSettings} from './PhotoMarqueeSettings';
import {TechComparisonSettings} from './TechLayouts';
import {NewsletterLayoutSettings} from './InlineNewsletter';
import {contentFieldKeys,sectionContentFields} from '../../cms/contentCapabilities';
import {SaasLayoutSettings,SaasItemSettings} from './SaasSettings';
import {VisualChoiceSelect} from './VisualChoiceSelect';
import {EditorialStoryCardSettings} from './EditorialStoryCardSettings';
import {ContentDisclosure} from './ContentDisclosure';
import {SectionLayoutGroup,hasSectionLayout} from './SectionLayoutGroup';
import type {BrandPalette,PageStyle} from '../../cms/pageStyle';
import {SectionStyleSettings} from './SectionStyleSettings';
import {PromotionHighlightSettings} from './PromotionHighlightSettings';
import type {PromotionOffer} from '../../cms/promotionHighlights';
import {ProductListingSettings} from './ProductListingSettings';
import {ShopNavigationSettings} from './ShopNavigationSettings';
import {HeroSliderLayouts} from './HeroSliderLayouts';
import {InspectorDeleteButton} from './InspectorDeleteButton';
import {itemSupports,supportedNewItem,repeatedItemFields} from '../../cms/repeatedItemContract';
import {PaymentSettings} from './PaymentSettings';
import {ContactSettings} from './ContactSettings';
import {IconPicker} from './IconPicker';
import {PolicySettings} from './PolicySettings';
import {WidthChoiceCards} from './WidthChoiceCards';
import {supportsMediaWidth} from './MediaWidthRegion';
import {ShippingContentSettings,ShippingDesignSettings} from './ShippingSettings';
import {TypographySettings} from './TypographySettings';
import {textRoles,textRoleLabels,roleFields,type TextRole,type InspectorTab} from '../../cms/inspectorCapabilities';
import {SectionBorderSettings} from './SectionBorderSettings';
import {ChapterSettings} from './ChapterSettings';
import {CtaStripSettings} from './CtaStripSettings';
import { useAdminCurrency } from '../../context/useAdminCurrency';
import { MOTION_PRESET_DETAILS } from './motion/CmsMotionWrapper';
import { SectionMotionPreset } from '../../types';
import { Play } from 'lucide-react';
import React, { useRef } from 'react';
import {ImageTargetInspector} from './ImageTargetInspector';
import {imageTargets,updateImageTarget} from '../../cms/imageTargets';
import {MediaTextSplitSettings} from './MediaTextSplitSettings';
import {RichTextSettings} from './RichTextSettings';
import {isManifesto} from '../../cms/richTextPresets';
import {ValuesListSettings,ValuesListEditor} from './ValuesListSettings';
import {ValuesListColors} from './ValuesListColors';
import {InspectorToggle,InspectorSwitch} from './InspectorToggle';
import {isEditorialMediaTextSplit} from '../../cms/mediaTextSplitPresets';
const isValuesList=(section:PageSection)=>section.type==='values_list';
import {
  PageSection,
  SectionItem,
  Product,
  SectionColorConfig,
  PageCollectionTab as CollectionTabMapping,
  InquiryCategory as ContactInquiryCategory,
  Category,
  Collection,
  Promotion,
} from '../../types';
import { useAdmin } from '../../context/AdminContext';
import { primaryProductImage } from '../../catalogMedia';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import { uploadMediaAsset } from '../../integration/pageData';
import { fetchCoupons } from '../../integration/couponData';
import { hasOwnContractField } from '../../cms/pageContract';
import { createSectionColorDefaults, mergeSectionColorDefaults } from '../../cms/sectionColorDefaults';
import {
  X,
  Trash2,
  Plus,
  Image as ImageIcon,
  Video,
  Upload,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Sliders,
  Palette,
  Eye,
  EyeOff,
  Link,
  HelpCircle,
  Sparkles,
  ShoppingBag,
  Layers,
  ChevronDown,
  ChevronUp,
  GripVertical,
  Search,
  Folder,
  FolderArchive,
  PanelRightClose,
  PanelRightOpen,
  Star,
  Clock,
  Flame,
  Scale,
  Mail,
  MapPin,
  Instagram,
  ShieldCheck,
  Award,
  Truck,
  Globe,
  Heart,
  Zap,
  Volume2,
  Calendar,
  Phone,
  Building,
  CheckCircle2,
  RotateCcw,
  Paintbrush,
  Check,
  ArrowRightLeft,
  Copy,
  SunMedium,
  Compass,
  AlertCircle
} from 'lucide-react';

export const INSPECTOR_SECTION_CONTROL_FIELDS = [
  'title',
  'isVisible',
  'subtitle',
  'content',
  'cardBadge',
  'cardTitle',
  'cardSubtitle',
  'cardPrice',
  'cardExtra',
  'tickerSpeed',
  'productIds',
  'dealEndDate',
  'dealBadge',
  'discountPercent',
  'beforeImageUrl',
  'beforeLabel',
  'afterImageUrl',
  'afterLabel',
  'videoEmbedUrl',
  'comparisonDimensionName',
  'comparisonOurBrandName',
  'comparisonCompetitorName',
  'formEmail',
  'contactPhone',
  'contactAddress',
  'contactButtonText',
  'formSuccessMessage',
  'subjectPlaceholder',
  'categoryPlaceholder',
  'messagePlaceholder',
  'inquiryCategories',
  'newsletterCouponId',
  'tabCategories',
  'tabs',
  'sliderHeight',
  'sliderAutoplay',
  'sliderInterval',
  'overlayOpacity',
  'mediaUrl',
  'buttonText',
  'buttonLink',
  'secondaryButtonText',
  'secondaryButtonLink',
  'items',
  'textAlignment',
  'paddingY',
  'backgroundColor',
  'customColors',
] as const;

export const INSPECTOR_ITEM_CONTROL_FIELDS = [
  'title',
  'description',
  'imageUrl',
  'badge',
  'buttonText',
  'linkUrl',
  'secondaryButtonText',
  'secondaryButtonLink',
  'thirdButtonText',
  'thirdButtonLink',
  'authorRole',
  'rating',
  'authorAvatar',
  'handle',
  'value',
  'date',
  'price',
  'icon',
] as const;

export const INSPECTOR_COLOR_CONTROL_FIELDS = [
  'eyebrowColor',
  'titleColor',
  'contentColor',
  'primaryBtnBg',
  'primaryBtnText',
  'secondaryBtnBg',
  'secondaryBtnText',
  'customBgColor',
] as const;

export const INSPECTOR_COLLECTION_TAB_CONTROL_FIELDS = [
  'id',
  'label',
  'sourceType',
  'sourceId',
  'productLimit',
] as const;

export const INSPECTOR_INQUIRY_CATEGORY_CONTROL_FIELDS = [
  'id',
  'label',
] as const;

const visibleOwnFields = <Field extends string>(
  value: object,
  fields: readonly Field[],
): Field[] => fields.filter(field => hasOwnContractField(value, field));

export const visibleInspectorFields = (value: object) => (
  visibleOwnFields(value, INSPECTOR_SECTION_CONTROL_FIELDS)
);

export const visibleInspectorItemFields = (value: object) => (
  visibleOwnFields(value, INSPECTOR_ITEM_CONTROL_FIELDS)
);

export const visibleInspectorColorFields = (value: object) => (
  visibleOwnFields(value, INSPECTOR_COLOR_CONTROL_FIELDS)
);

export const visibleInspectorCollectionTabFields = (value: object) => (
  visibleOwnFields(value, INSPECTOR_COLLECTION_TAB_CONTROL_FIELDS)
);

export const visibleInspectorInquiryCategoryFields = (value: object) => (
  visibleOwnFields(value, INSPECTOR_INQUIRY_CATEGORY_CONTROL_FIELDS)
);

interface MediaUploadFieldProps {
  label: string;
  value: string;
  onChange: (url: string) => void;
  onUpload?: (url: string) => void;
  placeholder?: string;
  accept?: string;
  helpText?: string;
  isVideo?: boolean;
}

const MediaUploadField: React.FC<MediaUploadFieldProps> = ({
  label,
  value,
  onChange,
  onUpload,
  placeholder = 'https://images.unsplash.com/... or upload file',
  accept = 'image/*',
  helpText,
  isVideo = false
}) => {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isUploading, setIsUploading] = React.useState(false);
  const { addToast } = useAdmin();

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || isUploading) return;

    setIsUploading(true);
    const completeUpload = onUpload ?? onChange;

    try {
      const url = await uploadMediaAsset(readAdminBootstrap(), file);
      completeUpload(url);
    } catch (error) {
      addToast({
        type: 'error',
        title: 'Media Upload Failed',
        message: error instanceof Error ? error.message : 'Unable to upload this media file.',
      });
    } finally {
      e.target.value = '';
      setIsUploading(false);
    }
  };

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between">
        <span className="text-neutral-600 dark:text-neutral-400 font-medium text-xs block">
          {label}
        </span>
        {value && (
          <InspectorDeleteButton label="Clear image"
            type="button"
            onClick={() => onChange('')}
            className="text-[10px] text-rose-500 hover:underline cursor-pointer"
           />
        )}
      </div>

      <div className="flex items-center gap-1.5">
        <div className="relative flex-1 min-w-0">
          <input
            type="text"
            value={value || ''}
            onChange={e => onChange(e.target.value)}
            placeholder={placeholder}
            className="w-full pl-2.5 pr-8 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white truncate focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          />
          {isVideo ? (
            <Video className="w-3.5 h-3.5 text-neutral-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          ) : (
            <ImageIcon className="w-3.5 h-3.5 text-neutral-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          )}
        </div>

        {/* Upload Button */}
        <button
          type="button"
          disabled={isUploading}
          onClick={() => fileInputRef.current?.click()}
          className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-xs font-semibold shrink-0 cursor-pointer shadow-xs transition-colors"
          title="Upload image or video from your device"
        >
          <Upload className="w-3.5 h-3.5 text-indigo-500" />
          <span>Upload</span>
        </button>

        <input
          ref={fileInputRef}
          type="file"
          accept={accept}
          onChange={handleFileChange}
          className="hidden"
        />
      </div>

      {helpText && (
        <p className="text-[10px] text-neutral-400">{helpText}</p>
      )}

      {/* Visual Thumbnail Preview if image URL exists */}
      {value && !isVideo && (
        <div className="relative w-full h-20 rounded-xl overflow-hidden border border-neutral-200 dark:border-neutral-700 bg-neutral-900 group">
          <img
            src={value}
            alt="Asset Preview"
            className="w-full h-full object-cover"
            onError={(e) => {
              (e.target as HTMLElement).style.display = 'none';
            }}
          />
          <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
            <span className="text-[10px] text-white font-medium bg-black/60 px-2 py-0.5 rounded-full">
              Preview
            </span>
          </div>
        </div>
      )}
    </div>
  );
};

// Custom Background & Gradient Controller
interface CustomBackgroundControlsProps {
  value: string;
  onChange: (value: string) => void;
}

const CURATED_GRADIENT_PRESETS = [
  {
    id: 'midnight-onyx',
    name: 'Midnight Onyx',
    css: 'linear-gradient(135deg, #09090b 0%, #18181b 50%, #000000 100%)',
    from: '#09090b',
    to: '#000000'
  },
  {
    id: 'deep-titanium',
    name: 'Aurora Titanium',
    css: 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%)',
    from: '#0f172a',
    to: '#1e293b'
  },
  {
    id: 'cyber-indigo',
    name: 'Cyber Indigo',
    css: 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #312e81 100%)',
    from: '#0f172a',
    to: '#312e81'
  },
  {
    id: 'emerald-abyss',
    name: 'Emerald Abyss',
    css: 'linear-gradient(135deg, #022c22 0%, #064e3b 50%, #065f46 100%)',
    from: '#022c22',
    to: '#065f46'
  },
  {
    id: 'cyber-cyan',
    name: 'Neon Cyan Horizon',
    css: 'linear-gradient(135deg, #020617 0%, #0c4a6e 50%, #0369a1 100%)',
    from: '#020617',
    to: '#0369a1'
  },
  {
    id: 'velvet-dusk',
    name: 'Velvet Orchid',
    css: 'linear-gradient(135deg, #180928 0%, #2e1065 50%, #3b0764 100%)',
    from: '#180928',
    to: '#3b0764'
  },
  {
    id: 'sunset-ember',
    name: 'Sunset Ember',
    css: 'linear-gradient(135deg, #18181b 0%, #450a0a 50%, #7f1d1d 100%)',
    from: '#18181b',
    to: '#7f1d1d'
  },
  {
    id: 'obsidian-gold',
    name: 'Champagne Obsidian',
    css: 'linear-gradient(135deg, #1c1917 0%, #292524 50%, #451a03 100%)',
    from: '#1c1917',
    to: '#451a03'
  },
  {
    id: 'spotlight-center',
    name: 'Center Spotlight',
    css: 'radial-gradient(circle at center, #1e293b 0%, #09090b 100%)',
    from: '#1e293b',
    to: '#09090b'
  },
  {
    id: 'top-halo-cyan',
    name: 'Top Ambient Glow',
    css: 'radial-gradient(circle at top, #0e7490 0%, #020617 85%)',
    from: '#0e7490',
    to: '#020617'
  },
  {
    id: 'purple-ambient',
    name: 'Corner Violet Aura',
    css: 'radial-gradient(circle at top right, #581c87 0%, #0a0a0a 85%)',
    from: '#581c87',
    to: '#0a0a0a'
  },
  {
    id: 'pure-minimal-light',
    name: 'Nordic Clean Light',
    css: 'linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%)',
    from: '#ffffff',
    to: '#f1f5f9'
  }
];

const SOLID_SWATCHES = [
  { name: 'Onyx Black', hex: '#09090b' },
  { name: 'Slate Navy', hex: '#0f172a' },
  { name: 'Midnight Indigo', hex: '#1e1b4b' },
  { name: 'Deep Forest', hex: '#052e16' },
  { name: 'Crimson Wine', hex: '#450a0a' },
  { name: 'Dark Violet', hex: '#2e1065' },
  { name: 'Gunmetal Gray', hex: '#1e293b' },
  { name: 'Clean White', hex: '#ffffff' },
];

const ANGLE_PRESETS = [
  { label: '0° ↑', angle: 0, title: 'To Top' },
  { label: '45° ↗', angle: 45, title: 'To Top Right' },
  { label: '90° →', angle: 90, title: 'To Right' },
  { label: '135° ↘', angle: 135, title: 'To Bottom Right' },
  { label: '180° ↓', angle: 180, title: 'To Bottom' },
  { label: '225° ↙', angle: 225, title: 'To Bottom Left' },
  { label: '270° ←', angle: 270, title: 'To Left' },
  { label: '315° ↖', angle: 315, title: 'To Top Left' },
];

const RADIAL_POSITIONS = [
  { id: 'circle at center', label: 'Center Spotlight' },
  { id: 'circle at top', label: 'Top Ambient Glow' },
  { id: 'circle at bottom', label: 'Bottom Underglow' },
  { id: 'ellipse at top left', label: 'Top-Left Corner' },
  { id: 'ellipse at top right', label: 'Top-Right Corner' },
];

const CustomBackgroundControls: React.FC<CustomBackgroundControlsProps> = ({ value, onChange }) => {
  // Infer initial mode from value
  const initialMode = value?.startsWith('radial-gradient')
    ? 'radial'
    : value?.startsWith('linear-gradient')
    ? 'linear'
    : value?.startsWith('#') || value?.startsWith('rgb')
    ? 'solid'
    : 'linear';

  const [mode, setMode] = React.useState<'solid' | 'linear' | 'radial' | 'presets'>(initialMode);
  const [solidColor, setSolidColor] = React.useState(value?.startsWith('#') ? value : '#0f172a');
  const [fromColor, setFromColor] = React.useState('#0f172a');
  const [toColor, setToColor] = React.useState('#1e1b4b');
  const [angle, setAngle] = React.useState(135);
  const [radialPos, setRadialPos] = React.useState('circle at center');
  const [copied, setCopied] = React.useState(false);

  // Sync internal state when external value changes
  React.useEffect(() => {
    if (!value) return;
    if (value.startsWith('linear-gradient')) {
      const match = value.match(/linear-gradient\(([\d]+)deg,\s*([#a-fA-F0-9]+|[a-z]+|rgba?\([^)]+\))\s*0%,\s*(?:[#a-fA-F0-9]+|[a-z]+|rgba?\([^)]+\)\s*\d+%,\s*)?([#a-fA-F0-9]+|[a-z]+|rgba?\([^)]+\))\s*100%\)/);
      if (match) {
        setAngle(parseInt(match[1], 10) || 135);
        if (match[2]?.startsWith('#')) setFromColor(match[2]);
        if (match[3]?.startsWith('#')) setToColor(match[3]);
      }
    } else if (value.startsWith('radial-gradient')) {
      const match = value.match(/radial-gradient\(([^,]+),\s*([#a-fA-F0-9]+|[a-z]+|rgba?\([^)]+\))\s*0%,\s*([#a-fA-F0-9]+|[a-z]+|rgba?\([^)]+\))\s*(?:\d+%)?\)/);
      if (match) {
        setRadialPos(match[1].trim());
        if (match[2]?.startsWith('#')) setFromColor(match[2]);
        if (match[3]?.startsWith('#')) setToColor(match[3]);
      }
    } else if (value.startsWith('#')) {
      setSolidColor(value);
    }
  }, [value]);

  const handleApplySolid = (color: string) => {
    setSolidColor(color);
    onChange(color);
  };

  const handleApplyLinear = (from = fromColor, to = toColor, deg = angle) => {
    setFromColor(from);
    setToColor(to);
    setAngle(deg);
    const grad = `linear-gradient(${deg}deg, ${from} 0%, ${to} 100%)`;
    onChange(grad);
  };

  const handleApplyRadial = (from = fromColor, to = toColor, pos = radialPos) => {
    setFromColor(from);
    setToColor(to);
    setRadialPos(pos);
    const grad = `radial-gradient(${pos}, ${from} 0%, ${to} 100%)`;
    onChange(grad);
  };

  const handleInvertColors = () => {
    const newFrom = toColor;
    const newTo = fromColor;
    if (mode === 'linear') {
      handleApplyLinear(newFrom, newTo, angle);
    } else if (mode === 'radial') {
      handleApplyRadial(newFrom, newTo, radialPos);
    }
  };

  const handleModeChange = (newMode: 'solid' | 'linear' | 'radial' | 'presets') => {
    setMode(newMode);
    if (newMode === 'solid') {
      onChange(solidColor);
    } else if (newMode === 'linear') {
      handleApplyLinear(fromColor, toColor, angle);
    } else if (newMode === 'radial') {
      handleApplyRadial(fromColor, toColor, radialPos);
    }
  };

  const handleCopyCSS = () => {
    if (!value) return;
    navigator.clipboard.writeText(`background: ${value};`);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  const currentDisplayVal = value || 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%)';

  return (
    <div className="p-3.5 rounded-2xl bg-neutral-100/90 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700/80 space-y-3.5 shadow-xs">
      {/* Header & Mode Segmented Switcher */}
      <div className="flex items-center justify-between">
        <label className="text-xs font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
          <Sparkles className="w-3.5 h-3.5 text-indigo-500" />
          Canvas Background Engine
        </label>
        <span className="text-[10px] uppercase font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded-md">
          {mode === 'solid' ? 'Solid Color' : mode === 'linear' ? 'Linear Gradient' : mode === 'radial' ? 'Radial Gradient' : 'Curated Library'}
        </span>
      </div>

      {/* 4 Mode Tabs */}
      <div className="grid grid-cols-4 gap-1 p-1 bg-neutral-200/70 dark:bg-neutral-900/60 rounded-xl">
        <button
          type="button"
          onClick={() => handleModeChange('solid')}
          className={`py-1.5 text-[11px] font-semibold rounded-lg transition-all cursor-pointer ${
            mode === 'solid'
              ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          Solid
        </button>
        <button
          type="button"
          onClick={() => handleModeChange('linear')}
          className={`py-1.5 text-[11px] font-semibold rounded-lg transition-all cursor-pointer ${
            mode === 'linear'
              ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          Linear
        </button>
        <button
          type="button"
          onClick={() => handleModeChange('radial')}
          className={`py-1.5 text-[11px] font-semibold rounded-lg transition-all cursor-pointer ${
            mode === 'radial'
              ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          Radial
        </button>
        <button
          type="button"
          onClick={() => handleModeChange('presets')}
          className={`py-1.5 text-[11px] font-semibold rounded-lg transition-all cursor-pointer ${
            mode === 'presets'
              ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
          }`}
        >
          Presets
        </button>
      </div>

      {/* Live Preview Strip */}
      <div className="space-y-1">
        <div
          className="w-full h-9 rounded-xl border border-black/20 dark:border-white/20 shadow-inner flex items-center justify-between px-3 transition-all"
          style={{ background: currentDisplayVal }}
        >
          <span className="text-[10px] font-mono font-semibold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)] truncate max-w-[190px]">
            {value || 'Default Canvas'}
          </span>
          <button
            type="button"
            onClick={handleCopyCSS}
            className="text-[10px] px-2 py-0.5 rounded-md bg-black/50 hover:bg-black/80 text-white font-medium backdrop-blur-xs flex items-center gap-1 cursor-pointer transition-colors shadow-xs"
            title="Copy CSS background code"
          >
            {copied ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3" />}
            {copied ? 'Copied' : 'CSS'}
          </button>
        </div>
      </div>

      {/* TAB 1: SOLID COLOR MODE */}
      {mode === 'solid' && (
        <div className="space-y-3 pt-1">
          <div className="flex items-center gap-2">
            <input
              type="color"
              value={solidColor.startsWith('#') ? solidColor : '#0f172a'}
              onChange={e => handleApplySolid(e.target.value)}
              className="w-9 h-9 rounded-xl border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent shadow-xs"
            />
            <div className="flex-1">
              <span className="text-[10px] text-neutral-500 dark:text-neutral-400 block mb-0.5 font-medium">
                Hex / Color Code
              </span>
              <input
                type="text"
                value={solidColor}
                onChange={e => handleApplySolid(e.target.value)}
                placeholder="#0f172a"
                className="w-full px-2.5 py-1.5 text-xs font-mono rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>

          {/* Quick Solid Swatches */}
          <div className="space-y-1.5 pt-1">
            <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
              Quick Swatches
            </span>
            <div className="grid grid-cols-4 gap-1.5">
              {SOLID_SWATCHES.map((swatch, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => handleApplySolid(swatch.hex)}
                  className="flex items-center gap-1.5 p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 hover:border-indigo-500 cursor-pointer transition-colors text-left"
                  title={swatch.name}
                >
                  <span
                    className="w-3.5 h-3.5 rounded-md border border-black/20 dark:border-white/20 shrink-0"
                    style={{ backgroundColor: swatch.hex }}
                  />
                  <span className="text-[10px] font-medium text-neutral-700 dark:text-neutral-300 truncate">
                    {swatch.name.split(' ')[0]}
                  </span>
                </button>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: LINEAR GRADIENT MODE */}
      {mode === 'linear' && (
        <div className="space-y-3 pt-1">
          {/* Gradient Color Stops (From & To) */}
          <div className="grid grid-cols-2 gap-2">
            {/* Start Color */}
            <div className="space-y-1">
              <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
                Start Stop (0%)
              </span>
              <div className="flex items-center gap-1.5">
                <input
                  type="color"
                  value={fromColor.startsWith('#') ? fromColor : '#0f172a'}
                  onChange={e => handleApplyLinear(e.target.value, toColor, angle)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent shrink-0"
                />
                <input
                  type="text"
                  value={fromColor}
                  onChange={e => handleApplyLinear(e.target.value, toColor, angle)}
                  className="w-full px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            {/* End Color */}
            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
                  End Stop (100%)
                </span>
                <button
                  type="button"
                  onClick={handleInvertColors}
                  className="text-[9px] text-indigo-500 hover:underline flex items-center gap-0.5 cursor-pointer"
                  title="Invert gradient colors"
                >
                  <ArrowRightLeft className="w-2.5 h-2.5" /> Invert
                </button>
              </div>
              <div className="flex items-center gap-1.5">
                <input
                  type="color"
                  value={toColor.startsWith('#') ? toColor : '#1e1b4b'}
                  onChange={e => handleApplyLinear(fromColor, e.target.value, angle)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent shrink-0"
                />
                <input
                  type="text"
                  value={toColor}
                  onChange={e => handleApplyLinear(fromColor, e.target.value, angle)}
                  className="w-full px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>
          </div>

          {/* Direction Angles: Quick Compass Buttons */}
          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 flex items-center gap-1">
                <Compass className="w-3 h-3 text-neutral-400" /> Direction Angle
              </span>
              <span className="text-[10px] font-mono font-bold text-indigo-600 dark:text-indigo-400">
                {angle}°
              </span>
            </div>
            <div className="grid grid-cols-4 gap-1">
              {ANGLE_PRESETS.map(item => (
                <button
                  key={item.angle}
                  type="button"
                  onClick={() => handleApplyLinear(fromColor, toColor, item.angle)}
                  className={`py-1 text-[10px] font-semibold rounded-md border transition-colors cursor-pointer text-center ${
                    angle === item.angle
                      ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                      : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 hover:border-neutral-400'
                  }`}
                  title={item.title}
                >
                  {item.label}
                </button>
              ))}
            </div>

            {/* Continuous Angle Range Slider */}
            <div className="flex items-center gap-2 pt-1">
              <input
                type="range"
                min="0"
                max="360"
                step="5"
                value={angle}
                onChange={e => handleApplyLinear(fromColor, toColor, parseInt(e.target.value, 10))}
                className="flex-1 h-1.5 bg-neutral-200 dark:bg-neutral-700 rounded-lg appearance-none cursor-pointer accent-indigo-600"
              />
              <span className="text-[10px] font-mono text-neutral-400 w-8 text-right shrink-0">
                {angle}°
              </span>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: RADIAL GRADIENT MODE */}
      {mode === 'radial' && (
        <div className="space-y-3 pt-1">
          {/* Radial Color Stops */}
          <div className="grid grid-cols-2 gap-2">
            <div className="space-y-1">
              <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
                Center Glow
              </span>
              <div className="flex items-center gap-1.5">
                <input
                  type="color"
                  value={fromColor.startsWith('#') ? fromColor : '#1e1b4b'}
                  onChange={e => handleApplyRadial(e.target.value, toColor, radialPos)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent shrink-0"
                />
                <input
                  type="text"
                  value={fromColor}
                  onChange={e => handleApplyRadial(e.target.value, toColor, radialPos)}
                  className="w-full px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
                  Outer Edge
                </span>
                <button
                  type="button"
                  onClick={handleInvertColors}
                  className="text-[9px] text-indigo-500 hover:underline flex items-center gap-0.5 cursor-pointer"
                  title="Invert radial colors"
                >
                  <ArrowRightLeft className="w-2.5 h-2.5" /> Invert
                </button>
              </div>
              <div className="flex items-center gap-1.5">
                <input
                  type="color"
                  value={toColor.startsWith('#') ? toColor : '#09090b'}
                  onChange={e => handleApplyRadial(fromColor, e.target.value, radialPos)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent shrink-0"
                />
                <input
                  type="text"
                  value={toColor}
                  onChange={e => handleApplyRadial(fromColor, e.target.value, radialPos)}
                  className="w-full px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>
          </div>

          {/* Radial Position Select */}
          <div className="space-y-1">
            <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
              Spotlight Origin Position
            </span>
            <VisualChoiceSelect
              aria-label="Spotlight origin position"
              value={radialPos}
              onChange={e => handleApplyRadial(fromColor, toColor, e.target.value)}
              className="w-full px-2.5 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium cursor-pointer"
            >
              {RADIAL_POSITIONS.map(pos => (
                <option key={pos.id} value={pos.id}>{pos.label}</option>
              ))}
            </VisualChoiceSelect>
          </div>
        </div>
      )}

      {/* TAB 4: CURATED PRESETS GALLERY */}
      {mode === 'presets' && (
        <div className="space-y-2 pt-1">
          <span className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
            1-Click Designer Palettes
          </span>
          <div className="grid grid-cols-2 gap-1.5 max-h-56 overflow-y-auto pr-1">
            {CURATED_GRADIENT_PRESETS.map(preset => (
              <button
                key={preset.id}
                type="button"
                onClick={() => onChange(preset.css)}
                className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 hover:border-indigo-500 text-left transition-all cursor-pointer group"
              >
                <div
                  className="w-full h-5 rounded-lg mb-1.5 border border-black/20 dark:border-white/20 shadow-xs"
                  style={{ background: preset.css }}
                />
                <div className="text-[10px] font-bold text-neutral-800 dark:text-neutral-200 truncate group-hover:text-indigo-500">
                  {preset.name}
                </div>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Manual Raw CSS Value Input for Advanced Users */}
      <div className="pt-2 border-t border-neutral-200 dark:border-neutral-700/80 space-y-1">
        <label className="text-[10px] font-semibold text-neutral-500 dark:text-neutral-400 block">
          Direct CSS Background String
        </label>
        <input
          type="text"
          value={value || ''}
          onChange={e => onChange(e.target.value)}
          placeholder="linear-gradient(...) or #hex"
          className="w-full px-2.5 py-1.5 text-[11px] font-mono rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
        />
        <p className="text-[9px] text-neutral-400">
          Supports full CSS gradient syntax, hex codes, or rgba values.
        </p>
      </div>
    </div>
  );
};

// -------------------------------------------------------------
// Searchable Source Selector for Tab Data Mapping (Category / Collection)
// -------------------------------------------------------------
interface SearchableSourcePickerProps {
  sourceType: 'category' | 'collection';
  sourceId: string;
  onChange: (sourceId: string, autoLabel?: string) => void;
  categories: Category[];
  collections: Collection[];
}

const SearchableSourcePicker: React.FC<SearchableSourcePickerProps> = ({
  sourceType,
  sourceId,
  onChange,
  categories,
  collections
}) => {
  const [isOpen, setIsOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const pickerRef = useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (pickerRef.current && !pickerRef.current.contains(e.target as Node)) setIsOpen(false);
    };
    if (isOpen) document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const selectedCategory = categories.find(c => c.id === sourceId);
  const selectedCollection = collections.find(c => c.id === sourceId);
  const filteredCategories = categories.filter(c =>
    c.name.toLowerCase().includes(search.toLowerCase()) || c.slug.toLowerCase().includes(search.toLowerCase())
  );
  const filteredCollections = collections.filter(c =>
    c.title.toLowerCase().includes(search.toLowerCase()) || c.slug.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="relative" ref={pickerRef}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full flex items-center justify-between px-2.5 py-2 rounded-xl border text-xs font-medium transition-all text-left cursor-pointer ${
          sourceId
            ? 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white hover:border-indigo-400'
            : 'border-amber-300 dark:border-amber-700/80 bg-amber-50/50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-300'
        }`}
      >
        <div className="flex items-center gap-2 truncate min-w-0">
          {sourceType === 'category' ? (
            <Folder className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
          ) : (
            <FolderArchive className="w-3.5 h-3.5 text-purple-500 shrink-0" />
          )}
          <span className="truncate">
            {sourceType === 'category'
              ? selectedCategory?.name || 'Select Category...'
              : selectedCollection?.title || 'Select Collection...'}
          </span>
        </div>
        <div className="flex items-center gap-1.5 shrink-0 ml-1">
          {sourceType === 'category' && selectedCategory && (
            <span className="text-[9px] px-1.5 py-0.5 rounded bg-neutral-200/60 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-mono">
              {selectedCategory.productCount || 0} items
            </span>
          )}
          {sourceType === 'collection' && selectedCollection && (
            <span className="text-[9px] px-1.5 py-0.5 rounded bg-neutral-200/60 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-mono">
              {selectedCollection.productCount || 0} items
            </span>
          )}
          <ChevronDown className={`w-3.5 h-3.5 text-neutral-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
        </div>
      </button>

      {isOpen && (
        <div className="absolute left-0 right-0 top-full mt-1.5 z-50 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-xl p-2 space-y-1.5 max-h-60 overflow-y-auto animate-in fade-in-50 duration-100">
          <div className="relative">
            <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              autoFocus
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder={sourceType === 'category' ? 'Search categories...' : 'Search collections...'}
              className="w-full pl-8 pr-2.5 py-1 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
          </div>

          <div className="space-y-0.5 max-h-40 overflow-y-auto pr-0.5">
            {sourceType === 'category' ? (
              filteredCategories.length > 0 ? (
                filteredCategories.map(cat => {
                  const isSelected = cat.id === sourceId;
                  return (
                    <button
                      key={cat.id}
                      type="button"
                      onClick={() => { onChange(cat.id, cat.name); setIsOpen(false); setSearch(''); }}
                      className={`w-full flex items-center justify-between px-2 py-1.5 rounded-lg text-xs transition-colors text-left cursor-pointer ${
                        isSelected
                          ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-semibold'
                          : 'hover:bg-neutral-100 dark:hover:bg-neutral-800/70 text-neutral-700 dark:text-neutral-300'
                      }`}
                    >
                      <div className="flex items-center gap-2 truncate">
                        <Folder className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                        <span className="truncate">{cat.name}</span>
                      </div>
                      <div className="flex items-center gap-1 shrink-0 ml-2">
                        <span className="text-[10px] text-neutral-400 font-mono">{cat.productCount || 0}</span>
                        {isSelected && <Check className="w-3.5 h-3.5 text-indigo-500" />}
                      </div>
                    </button>
                  );
                })
              ) : <div className="py-3 text-center text-[11px] text-neutral-400">No matching categories found</div>
            ) : (
              filteredCollections.length > 0 ? (
                filteredCollections.map(col => {
                  const isSelected = col.id === sourceId;
                  return (
                    <button
                      key={col.id}
                      type="button"
                      onClick={() => { onChange(col.id, col.title); setIsOpen(false); setSearch(''); }}
                      className={`w-full flex items-center justify-between px-2 py-1.5 rounded-lg text-xs transition-colors text-left cursor-pointer ${
                        isSelected
                          ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-semibold'
                          : 'hover:bg-neutral-100 dark:hover:bg-neutral-800/70 text-neutral-700 dark:text-neutral-300'
                      }`}
                    >
                      <div className="flex items-center gap-2 truncate">
                        <FolderArchive className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                        <span className="truncate">{col.title}</span>
                      </div>
                      <div className="flex items-center gap-1 shrink-0 ml-2">
                        <span className="text-[10px] text-neutral-400 font-mono">{col.productCount || 0}</span>
                        {isSelected && <Check className="w-3.5 h-3.5 text-indigo-500" />}
                      </div>
                    </button>
                  );
                })
              ) : <div className="py-3 text-center text-[11px] text-neutral-400">No matching collections found</div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

// -------------------------------------------------------------
// TAB DATA MAPPING Panel for Section Inspector (section.type === 'collection_tabs')
// -------------------------------------------------------------
interface TabDataMappingPanelProps {
  tabs: CollectionTabMapping[];
  categories: Category[];
  collections: Collection[];
  onChange: (tabs: CollectionTabMapping[]) => void;
  addToast?: (toast: { type: 'success' | 'error' | 'info' | 'warning'; title: string; message: string }) => void;
}

const TabDataMappingPanel: React.FC<TabDataMappingPanelProps> = ({ tabs, categories, collections, onChange, addToast }) => {
  const [draggedIdx, setDraggedIdx] = React.useState<number | null>(null);

  const handleAddTab = () => {
    const defaultCat = categories[tabs.length % (categories.length || 1)] || categories[0];
    const newTab: CollectionTabMapping = {
      id: `tab-${Date.now()}-${Math.random().toString(36).substring(2, 6)}`,
      label: defaultCat ? defaultCat.name : `Tab ${tabs.length + 1}`,
      sourceType: 'category', sourceId: defaultCat?.id || '', productLimit: 4
    };
    onChange([...tabs, newTab]);
    addToast?.({ type: 'success', title: 'Tab Added', message: `Created "${newTab.label}" mapping.` });
  };
  const handleUpdateTab = (index: number, updates: Partial<CollectionTabMapping>) => {
    const updated = [...tabs]; updated[index] = { ...updated[index], ...updates }; onChange(updated);
  };
  const handleRemoveTab = (index: number) => {
    const removedLabel = tabs[index]?.label;
    onChange(tabs.filter((_, idx) => idx !== index));
    addToast?.({ type: 'info', title: 'Tab Removed', message: `Removed "${removedLabel || 'Tab'}" from mapping.` });
  };
  const handleMoveTab = (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= tabs.length) return;
    const updated = [...tabs]; const [moved] = updated.splice(index, 1); updated.splice(targetIdx, 0, moved); onChange(updated);
  };
  const handleDragStart = (e: React.DragEvent, index: number) => { setDraggedIdx(index); e.dataTransfer.effectAllowed = 'move'; };
  const handleDragOver = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    if (draggedIdx === null || draggedIdx === index) return;
    const updated = [...tabs]; const [draggedItem] = updated.splice(draggedIdx, 1); updated.splice(index, 0, draggedItem);
    setDraggedIdx(index); onChange(updated);
  };

  return (
    <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
      <div className="flex items-center justify-between">
        <div>
          <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">Tab Data Mapping ({tabs.length})</label>
          <span className="text-[10px] text-neutral-400">Map each customer tab to a live category or collection</span>
        </div>
        <button type="button" onClick={handleAddTab} className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold shadow-xs cursor-pointer transition-colors">
          <Plus className="w-3.5 h-3.5" /><span>Add Tab</span>
        </button>
      </div>

      {tabs.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-neutral-200 dark:border-neutral-800 p-6 text-center text-neutral-400 bg-neutral-50/50 dark:bg-neutral-900/30 space-y-2">
          <Layers className="w-8 h-8 mx-auto text-neutral-400 dark:text-neutral-600 opacity-60" />
          <p className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">No Collection Tabs Configured</p>
          <p className="text-[11px] text-neutral-500 max-w-xs mx-auto">Add tabs to filter products dynamically by catalog Category or Collection.</p>
          <button type="button" onClick={handleAddTab} className="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-xs cursor-pointer">
            <Plus className="w-3.5 h-3.5" /><span>Add First Tab</span>
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          {tabs.map((tab, idx) => {
            const visibleTabFields = visibleInspectorCollectionTabFields(tab);
            const hasTabField = (field: typeof INSPECTOR_COLLECTION_TAB_CONTROL_FIELDS[number]) => visibleTabFields.includes(field);
            const hasValidSource = hasTabField('sourceId') && Boolean(tab.sourceId);
            return (
              <div key={hasTabField('id') ? (tab.id || idx) : idx} draggable onDragStart={e => handleDragStart(e, idx)} onDragOver={e => handleDragOver(e, idx)} onDragEnd={() => setDraggedIdx(null)}
                className={`p-3.5 rounded-2xl border transition-all space-y-2.5 ${draggedIdx === idx ? 'opacity-50 border-indigo-500 bg-indigo-50/30 dark:bg-indigo-950/30' : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/60 shadow-xs'}`}>
                <div className="flex items-center justify-between gap-2">
                  <div className="flex items-center gap-1.5 min-w-0">
                    <div className="cursor-grab active:cursor-grabbing p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200" title="Drag to reorder"><GripVertical className="w-3.5 h-3.5" /></div>
                    <span className="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 shrink-0">Tab #{idx + 1}</span>
                    {hasTabField('sourceId') && !hasValidSource && <span className="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-500 dark:text-amber-400 border border-amber-500/20 font-medium">No Source</span>}
                  </div>
                  <div className="flex items-center gap-1 shrink-0">
                    <button type="button" disabled={idx === 0} onClick={() => handleMoveTab(idx, 'up')} className="p-1 rounded text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 disabled:opacity-20 disabled:pointer-events-none cursor-pointer" title="Move Up"><ChevronUp className="w-3.5 h-3.5" /></button>
                    <button type="button" disabled={idx === tabs.length - 1} onClick={() => handleMoveTab(idx, 'down')} className="p-1 rounded text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 disabled:opacity-20 disabled:pointer-events-none cursor-pointer" title="Move Down"><ChevronDown className="w-3.5 h-3.5" /></button>
                    <button type="button" onClick={() => handleRemoveTab(idx)} className="p-1 rounded text-neutral-400 hover:text-rose-500 transition-colors cursor-pointer ml-1" title="Remove Tab"><Trash2 className="w-3.5 h-3.5" /></button>
                  </div>
                </div>

                {hasTabField('label') && (
                <div>
                  <label className="text-[10px] font-semibold text-neutral-600 dark:text-neutral-400 block mb-1">Customer-Facing Tab Label</label>
                  <input type="text" value={tab.label} onChange={e => handleUpdateTab(idx, { label: e.target.value })} placeholder="e.g. Flagship Headphones" className="w-full px-2.5 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500" />
                </div>
                )}

                {hasTabField('sourceType') && (
                <div className="space-y-1.5">
                  <label className="text-[10px] font-semibold text-neutral-600 dark:text-neutral-400 block">Source Type & Catalog Selection</label>
                  <div className="grid grid-cols-2 gap-1.5 p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700/60">
                    <button type="button" onClick={() => { const firstCat = categories[0]?.id || ''; handleUpdateTab(idx, { sourceType: 'category', sourceId: firstCat, label: tab.label === 'New Tab' || !tab.label ? (categories[0]?.name || tab.label) : tab.label }); }}
                      className={`py-1 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer ${tab.sourceType === 'category' ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs font-bold' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'}`}>
                      <Folder className="w-3.5 h-3.5 text-indigo-500" /><span>Category</span>
                    </button>
                    <button type="button" onClick={() => { const firstCol = collections[0]?.id || ''; handleUpdateTab(idx, { sourceType: 'collection', sourceId: firstCol, label: tab.label === 'New Tab' || !tab.label ? (collections[0]?.title || tab.label) : tab.label }); }}
                      className={`py-1 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer ${tab.sourceType === 'collection' ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs font-bold' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'}`}>
                      <FolderArchive className="w-3.5 h-3.5 text-purple-500" /><span>Collection</span>
                    </button>
                  </div>
                  {hasTabField('sourceId') && (
                    <SearchableSourcePicker sourceType={tab.sourceType} sourceId={tab.sourceId} onChange={(sourceId, autoLabel) => handleUpdateTab(idx, { sourceId, ...(hasTabField('label') ? { label: (!tab.label || tab.label.startsWith('New Tab') || tab.label.startsWith('Tab #')) && autoLabel ? autoLabel : tab.label } : {}) })} categories={categories} collections={collections} />
                  )}
                </div>
                )}

                {hasTabField('productLimit') && (
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-[10px] font-semibold text-neutral-600 dark:text-neutral-400 block">Product Limit</label>
                    <span className="text-[10px] text-neutral-400 font-mono">Max {tab.productLimit || 4} items</span>
                  </div>
                  <div className="grid grid-cols-4 gap-1.5">
                    {([3, 4, 6, 8] as const).map(limit => (
                      <button key={limit} type="button" onClick={() => handleUpdateTab(idx, { productLimit: limit })} className={`py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${(tab.productLimit || 4) === limit ? 'bg-indigo-600 text-white shadow-xs' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'}`}>{limit}</button>
                    ))}
                  </div>
                </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

interface SectionInspectorProps {
  pagePalette?:BrandPalette;
  followTheme?:boolean;
  pageTypography?:PageStyle['typography'];
  promotionOffers?:PromotionOffer[];
  selectedElement?:{target:string;role?:string;revision:number};
  selectedImage?:string;
  onSelectImage?:(target:string)=>void;
  pageId: string;
  section: PageSection | null;
  onChange: (updated: PageSection) => void;
  onMediaUpload: (
    pageId: string,
    sectionId: string,
    updater: (section: PageSection) => PageSection,
  ) => void;
  onDelete: (id: string) => void;
  onClose: () => void;
  onToggleCollapse?: () => void;
  onReplayMotion?: () => void;
  onApplyMotionToAll?: (preset: SectionMotionPreset) => void;
}

export const SectionInspector: React.FC<SectionInspectorProps> = ({
  promotionOffers=[],
  pagePalette,
  followTheme,
  pageTypography,
  pageId,
  section,
  selectedElement,
  selectedImage,
  onSelectImage,
  onChange,
  onMediaUpload,
  onDelete,
  onClose,
  onToggleCollapse,
  onReplayMotion,
  onApplyMotionToAll
}) => {
  const [inspectorTab,setInspectorTab]=React.useState<InspectorTab>('content');
  const [textRole,setTextRole]=React.useState<TextRole>('title');
  const bodyRef=React.useRef<HTMLDivElement>(null);
  const viewedGroup=React.useRef<string|undefined>('Typography');
  const previousSection=React.useRef(section?.id);
  const switchingSection=previousSection.current!==section?.id;
  React.useLayoutEffect(()=>{previousSection.current=section?.id;if(!switchingSection)return;const root=bodyRef.current;if(!root||!viewedGroup.current)return;const summary=[...root.querySelectorAll<HTMLElement>('summary')].find(e=>e.textContent?.trim()===viewedGroup.current&&e.closest('[hidden]')===null);if(summary){(summary.parentElement as HTMLDetailsElement).open=true;requestAnimationFrame(()=>summary.scrollIntoView?.({block:'start'}));}},[section?.id]);

  React.useEffect(()=>{if(!selectedElement||inspectorTab!=='content')return;const map:Record<string,TextRole>={subtitle:'eyebrow',title:'title',content:'content',leadText:'lead',itemTitle:'itemTitle',itemContent:'itemContent'};if(selectedElement.role)setTextRole(selectedElement.role as TextRole);else if(map[selectedElement.target])setTextRole(map[selectedElement.target]);},[selectedElement?.revision]);
  React.useLayoutEffect(()=>{if(!selectedElement||inspectorTab==='advanced'||(switchingSection&&inspectorTab==='design'))return;const root=bodyRef.current;if(!root)return;const target=selectedElement.target;const itemId=target.startsWith('items:')?target.slice(6):undefined;const control=itemId?[...root.querySelectorAll<HTMLElement>('[data-inspector-item]')].find(e=>e.dataset.inspectorItem===itemId):[...root.querySelectorAll<HTMLElement>('[data-inspector-field]')].find(e=>e.dataset.inspectorField===target);const fallback=target==='source'?root.querySelector<HTMLElement>(`[data-inspector-group="${section.type==='product_listing'?6:section.type==='collection_tabs'?4:5}"]`):target==='form'?root.querySelector<HTMLElement>('[data-inspector-group="11"], [data-inspector-group="12"]'):target==='image'?root.querySelector<HTMLElement>('[data-image-target-inspector]'):target==='button'?root.querySelector<HTMLElement>('[data-inspector-group="21"]'):target.startsWith('item')?root.querySelector<HTMLElement>('[data-inspector-group="22"], [data-inspector-group="23"]'):null;const element=inspectorTab==='design'?root.querySelector<HTMLElement>(target==='image'?'[data-inspector-image-design]':'[data-inspector-typography]'):control??fallback;if(!element)return;let parent=element.parentElement;while(parent&&parent!==root){if(parent.tagName==='DETAILS')(parent as HTMLDetailsElement).open=true;parent=parent.parentElement;}element.scrollIntoView?.({block:'nearest',behavior:'smooth'});if(element.matches('input,textarea,select'))element.focus({preventScroll:true});element.animate?.([{boxShadow:'0 0 0 2px #7965ff'},{boxShadow:'0 0 0 2px #7965ff'},{boxShadow:'none'}],{duration:1000});},[selectedElement?.revision,inspectorTab]);
  const { money, currencyCode } = useAdminCurrency();
  const { products, categories, collections, addToast } = useAdmin();
  const [couponPromotions, setCouponPromotions] = React.useState<Promotion[]>([]);

  React.useEffect(() => {
    let active = true;
    void fetchCoupons(readAdminBootstrap())
      .then(data => { if (active) setCouponPromotions(data.promotions); })
      .catch(() => { if (active) setCouponPromotions([]); });
    return () => { active = false; };
  }, []);

  if (!section) {
    return (
      <div className="h-full flex flex-col items-center justify-center p-8 text-center text-neutral-400">
        <div className="w-full flex justify-end px-2 pb-4">
          {onToggleCollapse && (
            <button
              onClick={onToggleCollapse}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
              title="Collapse Inspector"
            >
              <PanelRightClose className="w-4 h-4" />
            </button>
          )}
        </div>
        <Sliders className="w-12 h-12 mb-3 text-neutral-300 dark:text-neutral-700" />
        <p className="text-sm font-semibold text-neutral-600 dark:text-neutral-300">No Section Selected</p>
        <p className="text-xs text-neutral-400 mt-1 max-w-[200px]">
          Click any section in the preview canvas or outline list to customize its media, text, items, and layout.
        </p>
      </div>
    );
  }

  const contentFields=sectionContentFields(section);
  const hasSectionField = (key: string): boolean => {
    if(contentFieldKeys.has(key))return contentFields.includes(key);
    return hasOwnContractField(section,key)||(section.type==='shop_navigation'&&['textAlignment','paddingY'].includes(key));
  };
  const hasAnySectionField = (keys: readonly string[]): boolean => keys.some(hasSectionField);
  const sectionColors = hasSectionField('customColors')
    && section.customColors !== null
    && typeof section.customColors === 'object'
    && !Array.isArray(section.customColors)
    ? section.customColors
    : null;
  const hasColorField = (key: string): boolean => (
    sectionColors !== null && hasOwnContractField(sectionColors, key)
    && !((isValuesList(section)||['chapter_block','chapter_navigation'].includes(section.type)||section.layoutPreset==='editorial-cover') && ['primaryBtnBg','primaryBtnText','secondaryBtnBg','secondaryBtnText'].includes(key))
  );
  const hasItemField = (_item: SectionItem, key: string): boolean => itemSupports(section,key);
  const hasAnyItemField = (item: SectionItem, keys: readonly string[]): boolean => keys.some(key => hasItemField(item, key));

  const updateField = <K extends keyof PageSection>(key: K, value: PageSection[K]) => {
    onChange({
      ...section,
      [key]: value
    });
  };

  const updateUploadedField = (
    key: 'beforeImageUrl' | 'afterImageUrl' | 'videoEmbedUrl' | 'mediaUrl',
    url: string,
  ) => {
    onMediaUpload(pageId, section.id, latest => ({ ...latest, [key]: url }));
  };

  const updateUploadedItemField = (
    itemId: string,
    key: 'imageUrl' | 'authorAvatar',
    url: string,
  ) => {
    onMediaUpload(pageId, section.id, latest => ({
      ...latest,
      items: (latest.items || []).map(item => item.id === itemId ? { ...item, [key]: url } : item),
    }));
  };

  // Sub-item manager
  const handleAddItem = () => {
    if(section.type==='pricing_table'&&section.pricingPreset==='saas-packages'){updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'New plan',description:'',pricingMode:'contact',pricingLabel:'Contact us',features:[],buttonText:'',linkUrl:''}]);return;}
    if(section.type==='feature_cards'&&section.featureCardsPreset?.startsWith('saas-')){updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'New module',description:'',icon:'Layers',buttonText:'',linkUrl:''}]);return;}
    if(section.type==='media_text_split'&&section.layoutPreset==='artisan-story'){updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'New craft photograph',imageUrl:''}]);return;}
    if(section.type==='product_listing'&&section.listingPreset==='fashion-lookbook'){updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'New editorial card',description:'',imageUrl:'',buttonText:'Explore',linkUrl:'/shop'}]);return;}
    if (section.type === 'hero_slider' && ['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??'')) {
      updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'New collection',subtitle:'COLLECTION',description:'Add your description.',imageUrl:'',buttonText:'Explore collection',linkUrl:'',caption:''}]);return;
    }
    if (section.type === 'hero_slider') {
      const newSlide: SectionItem = {
        id: 'slide-' + Date.now(),
        title: 'Cinematic Audio Experience',
        subtitle: 'NEW ATELIER RELEASE',
        description: 'Engineered with custom transducers for reference-grade soundstage and deep spatial immersion.',
        imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1600&auto=format&fit=crop&q=80',
        badge: 'Special Edition',
        linkUrl: '/products/aurora-anc',
        buttonText: 'Shop New Release',
        secondaryButtonText: 'View Lookbook',
        secondaryButtonLink: '/pages/lookbook'
      };
      updateField('items', [...(section.items || []), newSlide]);
      return;
    }

    if(section.galleryPreset==='photo-marquee'){updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'',imageUrl:''}]);return;}
    if(section.type==='testimonials_slider'&&section.testimonialPreset==='editorial-quote'){updateField('items',[...(section.items??[]),{id:crypto.randomUUID(),title:'Sample attribution',description:'Add approved client words.'}]);return;}
    if (['editorial-columns','numbered-process'].includes(section.featureCardsPreset??'')||(section.galleryPreset?.startsWith('editorial-')||section.galleryPreset==='portfolio-cards')) {
      const item:SectionItem={id:crypto.randomUUID(),title:section.type==='media_gallery'?'New photograph':'New idea',description:'Add your description.'};
      if(section.type==='media_gallery')item.imageUrl='';
      updateField('items',[...(section.items??[]),item]);return;
    }
    const defaultIcon = section.type === 'testimonials_slider' ? 'Star' : 'Sparkles';
    const newItem: SectionItem = {
      id: 'item-' + Date.now(),
      title: section.type === 'logo_cloud' ? 'Brand name' : section.type === 'testimonials_slider' ? 'Søren Lindqvist' : 'New Feature / Card Title',
      description: section.type === 'logo_cloud' ? '' : section.type === 'testimonials_slider' ? 'The most transparent midrange I have ever heard in thirty years of mastering.' : 'Add informative description or specifications here.',
      icon: defaultIcon,
      imageUrl: section.type === 'logo_cloud' ? '' : section.type === 'media_gallery'
        ? 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&auto=format&fit=crop&q=80'
        : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
      linkUrl: '#',
      badge: section.type === 'testimonials_slider' ? 'Verified Buyer' : 'Feature',
      value: '99%',
      date: '2026',
      rating: 5,
      author: 'Søren Lindqvist',
      authorRole: 'Grammy-Nominated Producer (Stockholm)',
      authorAvatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
      handle: '@aurora.sound',
      price: 299,
      features: ['Precision Driver', 'Lossless Audio', '2-Year Guarantee']
    };
    updateField('items', [...(section.items || []), supportedNewItem(section,newItem)]);
  };

  const handleUpdateItem = (index: number, updatedItem: Partial<SectionItem>) => {
    const items = [...(section.items || [])];
    items[index] = { ...items[index], ...updatedItem };
    updateField('items', items);
  };

  const handleDeleteItem = (index: number) => {
    const items = [...(section.items || [])];
    items.splice(index, 1);
    updateField('items', items);
  };

  const bgOptions = [
    { value: 'bg-neutral-950', label: 'Dark Charcoal (#0a0a0a)' },
    { value: 'bg-neutral-900', label: 'Dark Slate (#171717)' },
    { value: 'bg-neutral-800', label: 'Neutral Gray (#262626)' },
    { value: 'bg-white', label: 'Crisp White' },
    { value: 'bg-neutral-50', label: 'Subtle Off-White' },
    { value: 'bg-neutral-100', label: 'Light Gray' },
    { value: 'bg-gradient-indigo', label: 'Indigo Gradient' },
    { value: 'bg-gradient-slate', label: 'Slate Gradient' },
    { value: 'bg-emerald-950', label: 'Deep Emerald' },
    { value: 'custom', label: '✨ Custom Brand Color / Gradient' }
  ];

  // Preset Color Palettes for 1-Click Theme Application
  const colorPresets = [
    {
      id: 'default-tech',
      name: 'Default Tech (Indigo & Dark)',
      eyebrowColor: '#818cf8',
      titleColor: '#ffffff',
      contentColor: '#d4d4d8',
      primaryBtnBg: '#4f46e5',
      primaryBtnText: '#ffffff',
      secondaryBtnBg: 'rgba(255, 255, 255, 0.1)',
      secondaryBtnText: '#ffffff',
      bgClass: 'bg-neutral-900',
      preview: ['#818cf8', '#ffffff', '#4f46e5']
    },
    {
      id: 'midnight-gold',
      name: 'Midnight Luxury (Gold & Black)',
      eyebrowColor: '#fbbf24',
      titleColor: '#ffffff',
      contentColor: '#e5e5e5',
      primaryBtnBg: '#f59e0b',
      primaryBtnText: '#000000',
      secondaryBtnBg: 'rgba(251, 191, 36, 0.15)',
      secondaryBtnText: '#fbbf24',
      bgClass: 'bg-neutral-950',
      preview: ['#fbbf24', '#ffffff', '#f59e0b']
    },
    {
      id: 'cyber-neon',
      name: 'Cyber Cyan & Electric Blue',
      eyebrowColor: '#38bdf8',
      titleColor: '#ffffff',
      contentColor: '#cbd5e1',
      primaryBtnBg: '#0284c7',
      primaryBtnText: '#ffffff',
      secondaryBtnBg: 'rgba(56, 189, 248, 0.15)',
      secondaryBtnText: '#38bdf8',
      bgClass: 'bg-neutral-950',
      preview: ['#38bdf8', '#ffffff', '#0284c7']
    },
    {
      id: 'emerald-luxury',
      name: 'Deep Forest & Emerald',
      eyebrowColor: '#34d399',
      titleColor: '#ffffff',
      contentColor: '#d1fae5',
      primaryBtnBg: '#059669',
      primaryBtnText: '#ffffff',
      secondaryBtnBg: 'rgba(52, 211, 153, 0.15)',
      secondaryBtnText: '#6ee7b7',
      bgClass: 'bg-emerald-950',
      preview: ['#34d399', '#ffffff', '#059669']
    },
    {
      id: 'nordic-light',
      name: 'Nordic Clean (Monochrome Light)',
      eyebrowColor: '#4f46e5',
      titleColor: '#111827',
      contentColor: '#4b5563',
      primaryBtnBg: '#111827',
      primaryBtnText: '#ffffff',
      secondaryBtnBg: '#f3f4f6',
      secondaryBtnText: '#111827',
      bgClass: 'bg-white',
      preview: ['#4f46e5', '#111827', '#4b5563']
    },
    {
      id: 'crimson-bold',
      name: 'Crimson Accent & Charcoal',
      eyebrowColor: '#f87171',
      titleColor: '#ffffff',
      contentColor: '#e5e5e5',
      primaryBtnBg: '#dc2626',
      primaryBtnText: '#ffffff',
      secondaryBtnBg: 'rgba(239, 68, 68, 0.15)',
      secondaryBtnText: '#fca5a5',
      bgClass: 'bg-neutral-900',
      preview: ['#f87171', '#ffffff', '#dc2626']
    }
  ];

  const updateColorField = (key: keyof SectionColorConfig, val: string) => {
    const nextColors = {
      ...mergeSectionColorDefaults(sectionColors || undefined),
      [key]: val
    };
    updateField('customColors', nextColors);
  };

  const applyColorPreset = (preset: typeof colorPresets[0]) => {
    if (!sectionColors) return;
    const nextColors = { ...sectionColors };
    const presetColors: SectionColorConfig = {
      eyebrowColor: preset.eyebrowColor,
      titleColor: preset.titleColor,
      contentColor: preset.contentColor,
      primaryBtnBg: preset.primaryBtnBg,
      primaryBtnText: preset.primaryBtnText,
      secondaryBtnBg: preset.secondaryBtnBg,
      secondaryBtnText: preset.secondaryBtnText
    };

    for (const [key, value] of Object.entries(presetColors)) {
      if (hasColorField(key)) nextColors[key as keyof SectionColorConfig] = value;
    }

    onChange({
      ...section,
      ...(hasSectionField('backgroundColor') ? { backgroundColor: preset.bgClass } : {}),
      customColors: nextColors
    });
  };

  const resetColorsToDefault = () => {
    onChange({
      ...section,
      customColors: createSectionColorDefaults(),
    });
  };

  const iconOptions = [
    'Sparkles', 'ShieldCheck', 'Zap', 'Globe', 'Heart', 'Truck', 'Award', 'Volume2', 'Star', 'Clock', 'ShoppingBag', 'Mail'
  ];

  const editorialCollection=['industrial-mosaic','album-browser'].includes(section.galleryPreset??'')||section.galleryPreset==='photo-marquee'||section.galleryPreset==='routine-steps'||(section.galleryPreset?.startsWith('editorial-')||section.galleryPreset==='portfolio-cards')||['editorial-columns','numbered-process'].includes(section.featureCardsPreset??'');
  const hasItems = !(section.type==='comparison_matrix'&&section.comparisonPreset==='device-table') && repeatedItemFields(section).length>0 && ((section.type==='media_text_split'&&(!section.layoutPreset||['default','producer-story','tech-feature','kids-discovery','artisan-story'].includes(section.layoutPreset??'default'))) || !['promotion_highlights','shop_navigation','policy_content','chapter_block','chapter_navigation','delivery_table','media_text_split'].includes(section.type));

  const hasMedia = !['editorial-story-cards','editorial-journal-rows','artisan-story'].includes(section.layoutPreset??'') && (section.type!=='cta_strip'||section.ctaPreset==='image-banner') && hasSectionField('mediaUrl') && [
    'hero_banner',
    'split_hero',
    'media_text_split',
    'featured_deal',
    'cta_strip',
    'chapter_block'
  ].includes(section.type);

  const hasButtons = section.layoutPreset!=='editorial-cover' && hasAnySectionField([
    'buttonText',
    'buttonLink',
    'secondaryButtonText',
    'secondaryButtonLink',
  ]) && [
    'hero_banner',
    'split_hero',
    'hero_slider',
    'media_text_split',
    'featured_deal',
    'cta_strip',
    'newsletter_signup',
    'shop_navigation',
    'product_listing',
    'contact_form',
  ].includes(section.type);

  // Preset time offsets for Deal Countdown
  const setDealDateOffset = (hours: number) => {
    const d = new Date(Date.now() + hours * 3600 * 1000);
    // Format to YYYY-MM-DDTHH:mm
    const pad = (n: number) => (n < 10 ? '0' + n : n);
    const formatted = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    updateField('dealEndDate', formatted);
  };

  return (
    <div className="h-full flex flex-col bg-white dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-800 overflow-hidden">
      {/* Header */}
      <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/70 dark:bg-neutral-950/70">
        <div className="flex items-center gap-2 min-w-0">
          <span className="p-1.5 rounded-lg bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
            <Sliders className="w-4 h-4" />
          </span>
          <div className="truncate">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white truncate">
              Section Inspector
            </h3>
            <span className="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 uppercase">
              {section.type.replace(/_/g, ' ')}
            </span>
          </div>
        </div>

        <div className="flex items-center gap-1">
          {hasSectionField('isVisible') && (
            <button
              onClick={() => updateField('isVisible', !section.isVisible)}
              className={`p-1.5 rounded-lg transition-colors cursor-pointer ${
                section.isVisible
                  ? 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-200'
                  : 'text-amber-500 bg-amber-500/10'
              }`}
              title={section.isVisible ? 'Hide Section' : 'Show Section'}
            >
              {section.isVisible ? <Eye className="w-4 h-4" /> : <EyeOff className="w-4 h-4" />}
            </button>
          )}
          <button
            onClick={() => onDelete(section.id)}
            className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer"
            title="Delete Section"
          >
            <Trash2 className="w-4 h-4" />
          </button>
          {onToggleCollapse && (
            <button
              onClick={onToggleCollapse}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
              title="Collapse Inspector"
            >
              <PanelRightClose className="w-4 h-4" />
            </button>
          )}
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition-colors cursor-pointer"
            title="Deselect Section"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      </div>

      <div role="tablist" aria-label="Inspector categories" className="grid grid-cols-3 px-3 border-b border-neutral-200 dark:border-neutral-800">{(['content','design','advanced'] as InspectorTab[]).map(tab=><button key={tab} role="tab" aria-selected={inspectorTab===tab} aria-controls={`inspector-panel-${section.id}`} onClick={()=>setInspectorTab(tab)} className={`py-3 text-xs font-semibold capitalize border-b-2 ${inspectorTab===tab?'border-indigo-500 text-indigo-500':'border-transparent text-neutral-500'}`}>{tab}</button>)}</div>
      {/* Form Controls */}
      <div ref={bodyRef} id={`inspector-panel-${section.id}`} role="tabpanel" onClickCapture={event=>{const element=event.target as HTMLElement;const group=element.closest('details.cms-settings-group');const summary=group?.querySelector(':scope > summary');if(summary)viewedGroup.current=summary.textContent?.trim();}} onFocusCapture={event=>{const group=(event.target as HTMLElement).closest('details.cms-settings-group');const summary=group?.querySelector(':scope > summary');if(summary)viewedGroup.current=summary.textContent?.trim();}} className="flex-1 overflow-y-auto p-4 space-y-5 text-xs">
        {selectedElement&&<div className="p-3 rounded-lg bg-indigo-500/10 text-indigo-500 flex justify-between gap-2"><span>Selected: {selectedElement.target.startsWith('items:')?'List item':selectedElement.target==='image'?'Image':selectedElement.target==='button'?'Button':textRoleLabels[({subtitle:'eyebrow',title:'title',content:'content',leadText:'lead'} as Record<string,TextRole>)[selectedElement.target]]??'Section content'}</span>{(textRoles(section).length>0||selectedElement.target==='image')&&<button className="underline" onClick={()=>setInspectorTab(inspectorTab==='design'?'content':'design')}>{inspectorTab==='design'?'Edit content':'Style'}</button>}</div>}
        <div hidden={inspectorTab!=='design'}><SaasLayoutSettings section={section} onChange={onChange}/>{section.type==='logo_cloud'&&<PartnerLogoSettings section={section} onChange={onChange}/>}{hasSectionLayout(section)&&<SectionLayoutGroup key={section.type} section={section}>{section.type==='hero_banner'&&<label className="block text-xs">Hero layout<VisualChoiceSelect aria-label="Hero layout" value={section.heroBannerPreset??'default'} onChange={event=>onChange({...section,heroBannerPreset:event.target.value as PageSection['heroBannerPreset']})}><option value="default">Classic</option><option value="editorial-gradient">Editorial Gradient</option><option value="industrial-overlay">Industrial Overlay</option></VisualChoiceSelect></label>}{section.type==='testimonials_slider'&&<label className="block text-xs">Testimonial layout<VisualChoiceSelect aria-label="Testimonial layout" value={section.testimonialPreset??'default'} onChange={event=>onChange({...section,testimonialPreset:event.target.value as PageSection['testimonialPreset']})}><option value="default">Classic Slider</option><option value="editorial-quote">Editorial Quote</option></VisualChoiceSelect></label>}{section.type==='media_gallery'&&section.galleryPreset==='photo-marquee'&&<PhotoMarqueeSettings section={section} onChange={onChange}/>}<IndustrialGallerySettings section={section} onChange={onChange}/><TechComparisonSettings mode="design" section={section} onChange={onChange}/><NewsletterLayoutSettings section={section} onChange={onChange}/><PromotionHighlightSettings mode="design" section={section} onChange={onChange} offers={promotionOffers}/><ProductListingSettings mode="design" section={section} onChange={onChange} categories={categories} collections={collections} products={products}/><ShopNavigationSettings mode="design" section={section} onChange={onChange} categories={categories} collections={collections}/><PolicySettings mode="design" section={section} onChange={onChange}/><PaymentSettings mode="design" section={section} onChange={onChange}/><ContactSettings mode="design" section={section} onChange={onChange}/><ChapterSettings mode="design" section={section} onChange={onChange}/><ShippingDesignSettings section={section} onChange={patch=>onChange({...section,...patch})}/><div hidden={inspectorTab!=='design'} data-inspector-group={0}>{(section.type==='media_gallery'||section.type==='feature_cards')&&<div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <label className="block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Layout preset
            <VisualChoiceSelect aria-label="Collection layout preset" value={section.type==='media_gallery'?section.galleryPreset??'default':section.featureCardsPreset??'default'} onChange={event=>onChange({...section,...(section.type==='media_gallery'?{galleryPreset:event.target.value as PageSection['galleryPreset']}:{featureCardsPreset:event.target.value as PageSection['featureCardsPreset']}),...(event.target.value==='default'?{showSectionHeading:true}:{})})} className="w-full mt-2 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white">
              <option value="default">Default</option>{section.type==='media_gallery'?<><option value="industrial-mosaic">Industrial Mosaic — large image and details</option><option value="album-browser">Album Browser — groups and lightbox</option><option value="photo-marquee">Photo Marquee — moving photographs</option><option value="editorial-triptych">Photo Triptych — equal photographs</option><option value="portfolio-cards">Portfolio Cards — photograph, caption and link</option><option value="editorial-montage">Editorial Montage — staggered photographs</option><option value="editorial-cards">Editorial Cards — photograph and caption</option><option value="routine-steps">Routine Steps — numbered photograph cards</option></>:<>{section.featureCardsPreset==='values-list'&&<option value="values-list">Legacy Values List</option>}<option value="industrial-cards">Industrial Cards — numbered services</option><option value="saas-modules">SaaS Module Strip</option><option value="saas-addons">SaaS Add-ons</option><option value="industrial-process-split">Industrial Process Split — heading and numbered rows</option><option value="industrial-process">Industrial Process — four-step workflow</option><option value="numbered-process">Numbered Process — steps in columns</option><option value="editorial-columns">Editorial Columns — simple text columns</option><option value="service-highlights">Service Highlights — icon and text row</option></>}
            </VisualChoiceSelect>
          </label>
          {(section.galleryPreset==='photo-marquee'||section.galleryPreset==='routine-steps'||(section.galleryPreset?.startsWith('editorial-')||section.galleryPreset==='portfolio-cards')||['editorial-columns','numbered-process','service-highlights','industrial-cards','industrial-process','industrial-process-split'].includes(section.featureCardsPreset??''))&&<>
            <InspectorToggle label="Show section heading" checked={section.showSectionHeading!==false} onChange={checked=>updateField('showSectionHeading',checked)}/>
            {section.galleryPreset!=='photo-marquee'&&section.featureCardsPreset!=='industrial-process-split'&&<label className="block text-neutral-500">Columns<VisualChoiceSelect aria-label="Editorial columns" value={section.columnsCount??3} onChange={event=>updateField('columnsCount',Number(event.target.value) as PageSection['columnsCount'])} className="w-full mt-1 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"><option value={2}>Two</option><option value={3}>Three</option><option value={4}>Four</option></VisualChoiceSelect></label>}
            <p className="text-[11px] leading-relaxed text-neutral-500">Edit and reorder entries below. Image layouts use the shared image tools. Mobile stacks entries in their saved order.</p>
          </>}
        </div>}</div>
<div hidden={inspectorTab!=='design'} data-inspector-group={3}>{section.type === 'announcement_bar' && hasSectionField('tickerSpeed') && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Marquee Ticker Speed
            </label>
            <div className="grid grid-cols-3 gap-2">
              {(['slow', 'normal', 'fast'] as const).map(spd => (
                <button
                  key={spd}
                  type="button"
                  onClick={() => updateField('tickerSpeed', spd)}
                  className={`py-1.5 capitalize text-center rounded-lg border transition-colors cursor-pointer ${
                    section.tickerSpeed === spd || (spd === 'normal' && !section.tickerSpeed)
                      ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                      : 'border-neutral-200 dark:border-neutral-700 text-neutral-500'
                  }`}
                >
                  {spd}
                </button>
              ))}
            </div>
          </div>
        )}</div><div hidden={inspectorTab!=='design'} data-inspector-group={13}>{section.type === 'hero_slider' && hasAnySectionField(['sliderHeight', 'sliderAutoplay', 'sliderInterval', 'overlayOpacity']) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Hero Slider Settings
            </label>

            <HeroSliderLayouts section={section} onChange={onChange}/>
            {/* Slider Height Preset */}
            {hasSectionField('sliderHeight') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Banner Height</span>
              <div className="grid grid-cols-4 gap-1.5">
                {[
                  { value: 'panoramic', label: 'Panoramic' },
                  { value: 'compact', label: 'Compact' },
                  { value: 'normal', label: 'Medium' },
                  { value: 'spacious', label: 'Tall' },
                  { value: 'fullscreen', label: 'Full Screen' }
                ].map(h => (
                  <button
                    key={h.value}
                    type="button"
                    onClick={() => updateField('sliderHeight', h.value as 'panoramic' | 'compact' | 'normal' | 'spacious' | 'fullscreen')}
                    className={`py-1.5 px-2 rounded-lg text-xs font-semibold border transition-all cursor-pointer truncate ${
                      (section.sliderHeight || 'spacious') === h.value
                        ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400'
                        : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                    }`}
                  >
                    {h.label}
                  </button>
                ))}
              </div>
            </div>
            )}

            {/* Autoplay & Rotation Interval */}
            {hasAnySectionField(['sliderAutoplay', 'sliderInterval']) && (
            <div className="grid grid-cols-2 gap-2 pt-1">
              {hasSectionField('sliderAutoplay') && (
              <InspectorToggle label="Autoplay" checked={section.sliderAutoplay !== false} onChange={checked => updateField('sliderAutoplay', checked)} />
              )}

              {hasSectionField('sliderInterval') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Interval (seconds)</span>
                <select
                  value={section.sliderInterval ?? 5}
                  onChange={e => updateField('sliderInterval', Number(e.target.value))}
                  className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                >
                  <option value={3}>3 seconds (Fast)</option>
                  <option value={5}>5 seconds (Standard)</option>
                  <option value={7}>7 seconds (Relaxed)</option>
                  <option value={10}>10 seconds (Slow)</option>
                </select>
              </div>
              )}
            </div>
            )}

          </div>
        )}</div><div hidden={inspectorTab!=='design'} data-inspector-group={15}><MediaTextSplitSettings showIntroduction={false} section={section} onChange={patch=>onChange({...section,...patch})} /></div><div hidden={inspectorTab!=='design'} data-inspector-group={16}>{isValuesList(section) && <ValuesListSettings section={section} onChange={patch=>onChange({...section,...patch})} />}</div><div hidden={inspectorTab!=='design'} data-inspector-group={17}>{section.type==='cta_strip' && <CtaStripSettings section={section} onChange={patch=>onChange({...section,...patch})} />}</div><div hidden={inspectorTab!=='design'} data-inspector-group={18}>{section.type==='rich_text' && <RichTextSettings mode="design" section={section} onChange={patch=>onChange({...section,...patch})} />}</div></SectionLayoutGroup>}</div><div hidden={inspectorTab!=='content'}>{['promotion_highlights','shop_navigation','policy_content','payment_methods','contact_form','store_locator','delivery_table'].includes(section.type)||(section.type==='chapter_block'&&section.chapterPreset==='policy')||(section.type==='media_text_split'&&['story-with-steps','editorial-story-cards','editorial-journal-rows'].includes(section.layoutPreset??''))?<ContentDisclosure title="Section content"><PromotionHighlightSettings mode="content" section={section} onChange={onChange} offers={promotionOffers} assetControl={target=><MediaUploadField label="Image URL / Upload" value={target.url} onChange={url=>onChange(updateImageTarget(section,target.id,{mediaUrl:url}))} onUpload={url=>onMediaUpload(pageId,section.id,latest=>updateImageTarget(latest,target.id,{mediaUrl:url}))} placeholder="Image URL or upload" />}/><ShopNavigationSettings mode="content" section={section} onChange={onChange} categories={categories} collections={collections} assetControl={target=><MediaUploadField label="Image override / Upload" value={target.url} onChange={url=>onChange(updateImageTarget(section,target.id,{mediaUrl:url}))} onUpload={url=>onMediaUpload(pageId,section.id,latest=>updateImageTarget(latest,target.id,{mediaUrl:url}))} placeholder="Use destination image, or upload" />}/><PolicySettings mode="content" section={section} onChange={onChange} onSelectImage={onSelectImage} assetControl={target=><MediaUploadField label="Image URL / File Upload" value={target.url} onChange={url=>onChange(updateImageTarget(section,target.id,{mediaUrl:url}))} onUpload={url=>onMediaUpload(pageId,section.id,latest=>updateImageTarget(latest,target.id,{mediaUrl:url}))} placeholder="Image URL or upload" />}/><PaymentSettings mode="content" section={section} onChange={onChange}/><ContactSettings mode="content" section={section} onChange={onChange}/><EditorialStoryCardSettings section={section} onChange={onChange} assetControl={target=><MediaUploadField label="Story image / Upload" value={target.url} onChange={url=>onChange(updateImageTarget(section,target.id,{mediaUrl:url}))} onUpload={url=>onMediaUpload(pageId,section.id,latest=>updateImageTarget(latest,target.id,{mediaUrl:url}))} placeholder="Image URL or upload"/>}/><ShippingContentSettings section={section} onChange={patch=>onChange({...section,...patch})}/></ContentDisclosure>:null}</div>
        {/* 1. Header & Text Content */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={1}><ContentDisclosure title="Text & headings">{hasAnySectionField(['title', 'subtitle', 'content']) && section.showSectionHeading!==false && !(section.type==='hero_slider'&&['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??'')) && (
        <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
            Text & Hierarchy
          </label>

          {hasSectionField('subtitle') && (
          <div>
            <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">
              Subtitle / Eyebrow Tag
            </span>
            <input data-inspector-field="subtitle"
              type="text"
              value={section.subtitle || ''}
              onChange={e => updateField('subtitle', e.target.value)}
              placeholder="e.g. THE DESIGN MANIFESTO"
              className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          )}

          {section.type!=='chapter_block'||section.chapterPreset!=='principles'?null:<p className="text-xs text-neutral-500">Edit each principle below. The chapter subtitle appears in navigation.</p>}
          {!(section.type==='chapter_block'&&section.chapterPreset==='principles') && hasSectionField('title') && (!isValuesList(section) || section.valuesShowHeading) && section.showSectionHeading!==false && (
          <div>
            <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">
              Primary Headline Title <span className="text-rose-500">*</span>
            </span>
            {section.type==='promotion_highlights' || section.type==='chapter_block' || isEditorialMediaTextSplit(section) || isManifesto(section) ? <textarea data-inspector-field="title"
              rows={3}
              aria-label={section.type==='promotion_highlights'?'Promotion headline':section.type==='chapter_block'?'Chapter headline':section.layoutPreset==='editorial-cover'?'Cover headline':isManifesto(section)?'Manifesto headline':section.layoutPreset==='editorial-story'?'Editorial story headline':'Editorial hero headline'}
              value={section.title}
              onChange={e => updateField('title', e.target.value)}
              placeholder="Section main heading"
              className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white font-semibold focus:ring-2 focus:ring-indigo-500"
            /> : <input data-inspector-field="title"
              type="text"
              value={section.title}
              onChange={e => updateField('title', e.target.value)}
              placeholder="Section main heading"
              className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white font-semibold focus:ring-2 focus:ring-indigo-500"
            />}
          </div>
          )}

          {(section.layoutPreset==='editorial-cover'||section.layoutPreset==='editorial-hero'||section.layoutPreset==='kids-discovery')&&<label className="block text-neutral-500">Introduction<input data-inspector-field="leadText" aria-label="Cover introduction" value={section.leadText??''} onChange={e=>updateField('leadText',e.target.value)} className="w-full mt-2 px-3 py-2 rounded-xl border border-neutral-700 bg-neutral-900 text-white"/></label>}

          {!(section.type==='chapter_block'&&section.chapterPreset==='principles') && hasSectionField('content') && (!isValuesList(section) || section.valuesShowHeading) && (
          <div>
            <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">
              Editorial Description
            </span>
            <textarea data-inspector-field="content"
              rows={3}
              value={section.content || ''}
              onChange={e => updateField('content', e.target.value)}
              placeholder="Descriptive copy or narrative block..."
              className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          )}
        </div>
        )}</ContentDisclosure></div>
        <div hidden={inspectorTab!=='design'}>{supportsMediaWidth(section)&&<div className="pb-4"><WidthChoiceCards label="Media width" value={section.mediaContainerMode??(section.layoutPreset==='editorial-cover'&&section.containerMode==='full-bleed'?'full-bleed':'page')} onChange={value=>onChange({...section,mediaContainerMode:value})}/><p className="mt-2 text-xs text-neutral-500">Changes the media region only. Text stays aligned with the section container. Full-width split media stacks below the text.</p></div>}<TypographySettings inheritedTypography={pageTypography} section={section} onChange={onChange} selectedRole={textRole} onSelectRole={setTextRole}/>{imageTargets(section).length>0&&<details data-inspector-image-design open={selectedElement?.target==='image'} className="cms-settings-group"><summary>Image appearance</summary><div className="cms-settings-group-content">            {/* Dark Overlay Opacity */}
            {hasSectionField('overlayOpacity') && (
            <div>
              <div className="flex items-center justify-between text-neutral-600 dark:text-neutral-400 mb-1">
                <span>Dark Overlay Contrast</span>
                <span className="font-bold">{section.overlayOpacity ?? 55}%</span>
              </div>
              <input
                type="range"
                min="0"
                max="90"
                step="5"
                value={section.overlayOpacity ?? 55}
                onChange={e => updateField('overlayOpacity', Number(e.target.value))}
                className="w-full accent-indigo-600"
              />
            </div>
            )}
<ImageTargetInspector mode="design" section={section} selected={selectedImage} onSelect={onSelectImage} onChange={onChange} assetControl={()=>null}/></div></details>}</div>
        <div hidden={inspectorTab!=='advanced'} className="text-neutral-500 leading-relaxed"><p>Page width stays in Page Settings. Typography defaults preserve the selected preset.</p><details open className="cms-settings-group" data-inspector-container><summary>Section content width</summary><div className="cms-settings-group-content"><WidthChoiceCards label="Section content width" value={section.containerMode??'page'} onChange={value=>updateField('containerMode',value)}/><p className="mt-2 text-xs">Follow Page uses the width and side spacing in Page Settings. Full Width removes the outer content limit and side spacing at every screen size. Text and cards retain the selected layout's proportions.</p></div></details></div>


        {/* Split Hero Card Overlay Controls */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={2}><ContentDisclosure title="Featured product">{section.type === 'split_hero' && hasAnySectionField(['cardBadge', 'cardTitle', 'cardSubtitle', 'cardPrice', 'cardExtra']) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Floating Card Preview Data
            </label>
            <div className="grid grid-cols-2 gap-2">
              {hasSectionField('cardBadge') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Card Eyebrow</span>
                <input
                  type="text"
                  value={section.cardBadge || ''}
                  onChange={e => updateField('cardBadge', e.target.value)}
                  placeholder="e.g. Flagship Model"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
              {hasSectionField('cardTitle') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Card Title</span>
                <input
                  type="text"
                  value={section.cardTitle || ''}
                  onChange={e => updateField('cardTitle', e.target.value)}
                  placeholder="e.g. Aurora Titanium Series X"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
            </div>
            {hasSectionField('cardSubtitle') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Card Subtitle / Specs</span>
              <input
                type="text"
                value={section.cardSubtitle || ''}
                onChange={e => updateField('cardSubtitle', e.target.value)}
                placeholder="e.g. 50mm Beryllium • LDAC 24/96"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
            <div className="grid grid-cols-2 gap-2">
              {hasSectionField('cardPrice') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Price ({currencyCode})</span>
                <input
                  type="text"
                  value={section.cardPrice ?? ''}
                  onChange={e => updateField('cardPrice', e.target.value)}
                  placeholder="499"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
              {hasSectionField('cardExtra') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Badge / Extra</span>
                <input
                  type="text"
                  value={section.cardExtra || ''}
                  onChange={e => updateField('cardExtra', e.target.value)}
                  placeholder="e.g. Free Express DHL"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
            </div>
          </div>
        )}</ContentDisclosure></div>

        {/* Announcement Ticker Speed Control */}


        {/* Collection Tabs - Tab Data Mapping Panel */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={4}><ContentDisclosure title="Collection tabs">{section.type === 'collection_tabs' && hasSectionField('tabs') && (
          <TabDataMappingPanel
            tabs={Array.isArray(section.tabs) ? section.tabs : []}
            categories={categories}
            collections={collections}
            onChange={updatedTabs => {
              onChange({
                ...section,
                tabs: updatedTabs,
                ...(hasSectionField('tabCategories') ? { tabCategories: updatedTabs.map(t => t.label) } : {})
              });
            }}
            addToast={addToast}
          />
        )}</ContentDisclosure></div>

        {/* Product Grid - Product Selection */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={5}><ContentDisclosure title="Products">{section.type === 'product_grid' && hasSectionField('productIds') && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
                Select Featured Products ({section.productIds?.length || 0} selected)
              </label>
              {section.productIds && section.productIds.length > 0 && (
                <button
                  type="button"
                  onClick={() => updateField('productIds', [])}
                  className="text-[10px] text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 underline cursor-pointer"
                >
                  Reset (Show All)
                </button>
              )}
            </div>
            <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
              Enable the products you want to feature in this grid, or leave blank to display your catalog automatically.
            </p>
            <div className="max-h-56 overflow-y-auto space-y-1.5 border border-neutral-200 dark:border-neutral-700 rounded-xl p-2 bg-neutral-50 dark:bg-neutral-900/50">
              {products.map(p => {
                const isSelected = section.productIds?.includes(p.id);
                return (
                  <div
                    key={p.id}
                    className={`flex items-center gap-2.5 p-2 rounded-lg cursor-pointer transition-colors ${
                      isSelected
                        ? 'bg-indigo-50 dark:bg-indigo-950/60 text-neutral-900 dark:text-white border border-indigo-200 dark:border-indigo-800'
                        : 'hover:bg-neutral-100 dark:hover:bg-neutral-800/60 text-neutral-700 dark:text-neutral-300'
                    }`}
                  >

                    <img
                      src={primaryProductImage(p)}
                      alt={p.title}
                      className="w-7 h-7 rounded object-cover bg-neutral-800 shrink-0"
                    />
                    <div className="flex-1 min-w-0">
                      <p className="font-semibold text-xs truncate">{p.title}</p>
                      <p className="text-[10px] text-neutral-400">{money(p.basePrice)}</p>
                    </div>
                    <InspectorSwitch label={`Include ${p.title}`} checked={!!isSelected} onChange={checked=>{
                      const current=section.productIds||[];
                      updateField('productIds',checked?[...current,p.id]:current.filter(id=>id!==p.id));
                    }}/>
                  </div>
                );
              })}
            </div>
          </div>
        )}</ContentDisclosure></div>

        <div hidden={inspectorTab!=='content'} data-inspector-group={6}><ContentDisclosure title={['editorial-tabs','pantry-tabs','tech-tabs'].includes(section.listingPreset??'')?'Product tabs':'Product source'}><ProductListingSettings mode="content" section={section} onChange={onChange} categories={categories} collections={collections} products={products}/></ContentDisclosure></div>

        {/* Featured Deal Countdown, Discount & Badge */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={7}><ContentDisclosure title="Deal details">{section.type === 'featured_deal' && hasAnySectionField(['dealEndDate', 'dealBadge', 'discountPercent']) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Deal Countdown & Discounts
            </label>

            {hasSectionField('dealEndDate') && (
            <div>
              <div className="flex items-center justify-between mb-1">
                <span className="text-neutral-600 dark:text-neutral-400 font-medium block">
                  Countdown End Date & Time
                </span>
                <Clock className="w-3.5 h-3.5 text-indigo-500" />
              </div>
              <input
                type="datetime-local"
                value={section.dealEndDate || ''}
                onChange={e => updateField('dealEndDate', e.target.value)}
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
              <div className="flex items-center gap-1 mt-1.5">
                <span className="text-[10px] text-neutral-400 mr-1">Quick Presets:</span>
                <button
                  type="button"
                  onClick={() => setDealDateOffset(24)}
                  className="px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-[10px] font-medium text-neutral-600 dark:text-neutral-300 hover:bg-indigo-50 hover:text-indigo-600 cursor-pointer"
                >
                  +24h
                </button>
                <button
                  type="button"
                  onClick={() => setDealDateOffset(48)}
                  className="px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-[10px] font-medium text-neutral-600 dark:text-neutral-300 hover:bg-indigo-50 hover:text-indigo-600 cursor-pointer"
                >
                  +48h
                </button>
                <button
                  type="button"
                  onClick={() => setDealDateOffset(72)}
                  className="px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-[10px] font-medium text-neutral-600 dark:text-neutral-300 hover:bg-indigo-50 hover:text-indigo-600 cursor-pointer"
                >
                  +3 Days
                </button>
                <button
                  type="button"
                  onClick={() => setDealDateOffset(168)}
                  className="px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-[10px] font-medium text-neutral-600 dark:text-neutral-300 hover:bg-indigo-50 hover:text-indigo-600 cursor-pointer"
                >
                  +7 Days
                </button>
              </div>
            </div>
            )}

            <div className="grid grid-cols-2 gap-2">
              {hasSectionField('dealBadge') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Flash Badge</span>
                <input
                  type="text"
                  value={section.dealBadge || ''}
                  onChange={e => updateField('dealBadge', e.target.value)}
                  placeholder="e.g. LIMITED TIME FLASH OFFER"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
              {hasSectionField('discountPercent') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Discount %</span>
                <input
                  type="number"
                  value={section.discountPercent ?? 25}
                  onChange={e => updateField('discountPercent', Number(e.target.value))}
                  placeholder="25"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
            </div>
          </div>
        )}</ContentDisclosure></div>

        {/* Before / After Slider Media */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={8}><ContentDisclosure title="Before & after">{section.type === 'before_after' && hasAnySectionField(['beforeImageUrl', 'beforeLabel', 'afterImageUrl', 'afterLabel']) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Before / After Images & Labels
            </label>
            {hasSectionField('beforeImageUrl') && imageTargets(section).length===0 && (
            <MediaUploadField
              label="Before Image"
              value={section.beforeImageUrl || ''}
              onChange={url => updateField('beforeImageUrl', url)}
              onUpload={url => updateUploadedField('beforeImageUrl', url)}
              placeholder="https://... (Standard Environment)"
            />
            )}
            {hasSectionField('beforeLabel') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Before Label</span>
              <input
                type="text"
                value={section.beforeLabel || ''}
                onChange={e => updateField('beforeLabel', e.target.value)}
                placeholder="e.g. Standard Ambience"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
            {hasSectionField('afterImageUrl') && imageTargets(section).length===0 && (
            <MediaUploadField
              label="After Image"
              value={section.afterImageUrl || ''}
              onChange={url => updateField('afterImageUrl', url)}
              onUpload={url => updateUploadedField('afterImageUrl', url)}
              placeholder="https://... (Aurora Acoustic Isolation)"
            />
            )}
            {hasSectionField('afterLabel') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">After Label</span>
              <input
                type="text"
                value={section.afterLabel || ''}
                onChange={e => updateField('afterLabel', e.target.value)}
                placeholder="e.g. Aurora Pure Silence"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
          </div>
        )}</ContentDisclosure></div>

        {/* Video Embed Player */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={9}><ContentDisclosure title="Video">{section.type === 'video_embed' && hasSectionField('videoEmbedUrl') && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Video Player Source
            </label>
            <MediaUploadField
              label="Video URL (YouTube / Vimeo / MP4)"
              value={section.videoEmbedUrl || ''}
              onChange={url => updateField('videoEmbedUrl', url)}
              onUpload={url => updateUploadedField('videoEmbedUrl', url)}
              placeholder="https://www.youtube.com/embed/... or .mp4 file"
              accept="video/*"
              isVideo={true}
              helpText="Supports YouTube embeds, Vimeo links, or uploaded direct MP4 video files."
            />
          </div>
        )}</ContentDisclosure></div>

        {/* Comparison Matrix Table Headers */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={10}><ContentDisclosure title="Comparison"><TechComparisonSettings mode="content" section={section} onChange={onChange}/>{section.type === 'comparison_matrix' && hasAnySectionField(['comparisonDimensionName', 'comparisonOurBrandName', 'comparisonCompetitorName']) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Comparison Table Headers
            </label>
            {hasSectionField('comparisonDimensionName') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Feature Dimension Header</span>
              <input
                type="text"
                value={section.comparisonDimensionName || ''}
                onChange={e => updateField('comparisonDimensionName', e.target.value)}
                placeholder="ENGINEERING DIMENSION"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
            <div className="grid grid-cols-2 gap-2">
              {hasSectionField('comparisonOurBrandName') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Our Brand Header</span>
                <input
                  type="text"
                  value={section.comparisonOurBrandName || ''}
                  onChange={e => updateField('comparisonOurBrandName', e.target.value)}
                  placeholder="AURORA FLAGSHIP"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
              {hasSectionField('comparisonCompetitorName') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Competitor Header</span>
                <input
                  type="text"
                  value={section.comparisonCompetitorName || ''}
                  onChange={e => updateField('comparisonCompetitorName', e.target.value)}
                  placeholder="MAINSTREAM BRANDS"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
            </div>
          </div>
        )}</ContentDisclosure></div>

        {/* Contact Form Details */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={11}><ContentDisclosure title="Contact form">{section.type === 'contact_form' && section.contactPreset!=='enquiry' && hasAnySectionField(['formEmail', 'contactPhone', 'contactAddress', 'contactButtonText', 'formSuccessMessage', 'subjectPlaceholder', 'categoryPlaceholder', 'messagePlaceholder', 'inquiryCategories']) && (
          <div className="space-y-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Contact & Atelier Details
            </label>
            {hasSectionField('formEmail') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">
                Concierge Email Address
              </span>
              <input
                type="email"
                value={section.formEmail ?? ''}
                onChange={e => updateField('formEmail', e.target.value)}
                placeholder="concierge@aurora-audio.se"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
            {hasSectionField('contactPhone') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">
                Phone & Operating Hours
              </span>
              <input
                type="text"
                value={section.contactPhone ?? ''}
                onChange={e => updateField('contactPhone', e.target.value)}
                placeholder="+46 8 123 4567 (Mon–Fri 09:00–18:00 CET)"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
            {hasSectionField('contactAddress') && (
            <div>
              <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">
                Physical Atelier / Office Address
              </span>
              <input
                type="text"
                value={section.contactAddress ?? ''}
                onChange={e => updateField('contactAddress', e.target.value)}
                placeholder="Acoustic Atelier, Stockholm, Sweden"
                className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
              />
            </div>
            )}
            {/* Field Placeholders */}
            {hasAnySectionField(['subjectPlaceholder', 'categoryPlaceholder', 'messagePlaceholder']) && (
            <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 space-y-3">
              <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
                Field Placeholders
              </label>
              {hasSectionField('subjectPlaceholder') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Subject Placeholder</span>
                <input type="text" value={section.subjectPlaceholder ?? ''} onChange={e => updateField('subjectPlaceholder', e.target.value)} placeholder="e.g. Custom Engraving / Setup Inquiry" className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white" />
              </div>
              )}
              {hasSectionField('categoryPlaceholder') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Category Placeholder</span>
                <input type="text" value={section.categoryPlaceholder ?? ''} onChange={e => updateField('categoryPlaceholder', e.target.value)} placeholder="Select an inquiry category..." className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white" />
              </div>
              )}
              {hasSectionField('messagePlaceholder') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Message / Inquiry Placeholder</span>
                <input type="text" value={section.messagePlaceholder ?? ''} onChange={e => updateField('messagePlaceholder', e.target.value)} placeholder="Tell us about your audio setup or custom acoustic requirements..." className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white" />
              </div>
              )}
            </div>
            )}

            {/* Repeatable Inquiry Categories */}
            {hasSectionField('inquiryCategories') && (
            <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
                    Inquiry Categories ({(section.inquiryCategories || []).length})
                  </label>
                  <span className="text-[10px] text-neutral-400">Dropdown options for visitor inquiries</span>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    const current = section.inquiryCategories || [
                      { id: 'cat-1', label: 'Product Enquiry' },
                      { id: 'cat-2', label: 'Order Support' },
                      { id: 'cat-3', label: 'Wholesale & Partnership' },
                      { id: 'cat-4', label: 'Press & Collaboration' },
                      { id: 'cat-5', label: 'Other' }
                    ];
                    const newCat: ContactInquiryCategory = { id: `cat-${Date.now()}-${Math.random().toString(36).substring(2, 6)}`, label: 'New Category' };
                    updateField('inquiryCategories', [...current, newCat]);
                  }}
                  className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold shadow-xs cursor-pointer transition-colors"
                ><Plus className="w-3.5 h-3.5" /><span>Add Category</span></button>
              </div>

              {(!section.inquiryCategories || section.inquiryCategories.length === 0) ? (
                <div className="rounded-xl border border-dashed border-neutral-200 dark:border-neutral-800 p-4 text-center text-neutral-400 bg-neutral-50/50 dark:bg-neutral-900/30 space-y-2">
                  <p className="text-xs font-semibold text-neutral-600 dark:text-neutral-300">No Custom Categories Configured</p>
                  <p className="text-[11px] text-neutral-500">Default categories will be displayed. Click below to customize the inquiry options.</p>
                  <button type="button" onClick={() => updateField('inquiryCategories', [
                    { id: 'cat-1', label: 'Product Enquiry' },
                    { id: 'cat-2', label: 'Order Support' },
                    { id: 'cat-3', label: 'Wholesale & Partnership' },
                    { id: 'cat-4', label: 'Press & Collaboration' },
                    { id: 'cat-5', label: 'Other' }
                  ])} className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-xs cursor-pointer">
                    <Plus className="w-3.5 h-3.5" /><span>Initialize Default Categories</span>
                  </button>
                </div>
              ) : (
                <div className="space-y-2">
                  {section.inquiryCategories.map((cat, idx) => {
                    const cats = section.inquiryCategories!;
                    const visibleCategoryFields = visibleInspectorInquiryCategoryFields(cat);
                    const hasCategoryField = (field: typeof INSPECTOR_INQUIRY_CATEGORY_CONTROL_FIELDS[number]) => visibleCategoryFields.includes(field);
                    return (
                      <div key={hasCategoryField('id') ? (cat.id || idx) : idx} className="flex items-center gap-2 p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/60 dark:bg-neutral-800/40 shadow-xs">
                        <span className="text-[10px] font-mono text-neutral-400 w-4 text-center shrink-0">{idx + 1}</span>
                        {hasCategoryField('label') && (
                          <input type="text" value={cat.label} onChange={e => updateField('inquiryCategories', cats.map((item, i) => i === idx ? { ...item, label: e.target.value } : item))} placeholder="Category Label" className="flex-1 min-w-0 px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium focus:ring-1 focus:ring-indigo-500" />
                        )}
                        <div className="flex items-center gap-0.5 shrink-0">
                          <button type="button" disabled={idx === 0} onClick={() => { const updated = [...cats]; const [moved] = updated.splice(idx, 1); updated.splice(idx - 1, 0, moved); updateField('inquiryCategories', updated); }} className="p-1 rounded text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 disabled:opacity-20 disabled:pointer-events-none cursor-pointer" title="Move Up"><ChevronUp className="w-3.5 h-3.5" /></button>
                          <button type="button" disabled={idx === cats.length - 1} onClick={() => { const updated = [...cats]; const [moved] = updated.splice(idx, 1); updated.splice(idx + 1, 0, moved); updateField('inquiryCategories', updated); }} className="p-1 rounded text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 disabled:opacity-20 disabled:pointer-events-none cursor-pointer" title="Move Down"><ChevronDown className="w-3.5 h-3.5" /></button>
                          <button type="button" onClick={() => updateField('inquiryCategories', cats.filter((_, i) => i !== idx))} className="p-1 rounded text-neutral-400 hover:text-rose-500 cursor-pointer" title="Delete Category"><Trash2 className="w-3.5 h-3.5" /></button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
            )}

            {/* Submit button & Success Message */}
            {hasAnySectionField(['contactButtonText', 'formSuccessMessage']) && (
            <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 space-y-3">
              {hasSectionField('contactButtonText') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Submit Button Label</span>
                <input type="text" value={section.contactButtonText ?? ''} onChange={e => updateField('contactButtonText', e.target.value)} placeholder="Send Inquiries to Atelier" className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white" />
              </div>
              )}
              {hasSectionField('formSuccessMessage') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Success Message</span>
                <input type="text" value={section.formSuccessMessage ?? ''} onChange={e => updateField('formSuccessMessage', e.target.value)} placeholder="Thank you! Your message has been routed to our engineers." className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white" />
              </div>
              )}
            </div>
            )}
          </div>
        )}</ContentDisclosure></div>

        {/* Newsletter Signup Form Config */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={12}><ContentDisclosure title="Newsletter">{section.type === 'newsletter_signup' && hasAnySectionField(['formSuccessMessage', 'newsletterCouponId']) && (() => {
          const now = new Date();
          const eligibleCoupons = couponPromotions.flatMap(promo => {
            if (promo.promotionType !== 'coupon_code' || !promo.isActive) return [];
            if (promo.startsAt && new Date(promo.startsAt) > now) return [];
            if (promo.endsAt && new Date(promo.endsAt) < now) return [];
            if (promo.totalUsageLimit !== null && promo.totalUsageCount >= promo.totalUsageLimit) return [];
            if (!promo.coupons || promo.coupons.length === 0) return [];
            const coupon = promo.coupons.find(candidate => candidate.usageLimit === null || candidate.usageCount < candidate.usageLimit);
            if (!coupon) return [];
            let discountText = 'Discount';
            if (promo.action.actionType === 'fixed_discount' && promo.action.discountAmount != null) discountText = `RM${promo.action.discountAmount} OFF`;
            else if (promo.action.actionType === 'percentage_discount' && promo.action.discountPercentage != null) discountText = `${promo.action.discountPercentage}% OFF`;
            else if (promo.action.actionType === 'free_shipping') discountText = 'Free Shipping';
            else if (promo.action.actionType === 'bundle_fixed_price' && promo.action.bundleFixedPrice != null) discountText = `RM${promo.action.bundleFixedPrice} Fixed`;
            const expiryText = promo.endsAt ? new Date(promo.endsAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
            const parts = [coupon.code, discountText, promo.name];
            if (expiryText) parts.push(`Exp: ${expiryText}`);
            return [{ id: promo.id, label: parts.join(' — ') }];
          });
          const currentCouponId = section.newsletterCouponId || '';
          const isInvalidOrExpired = Boolean(currentCouponId && !eligibleCoupons.some(c => c.id === currentCouponId));
          return (
            <div className="space-y-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
              <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">Newsletter Configuration</label>
              {hasSectionField('formSuccessMessage') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Success Message</span>
                <input type="text" value={section.formSuccessMessage ?? ''} onChange={e => updateField('formSuccessMessage', e.target.value)} placeholder="Your subscriber voucher has been sent to your email!" className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white" />
              </div>
              )}
              {hasSectionField('newsletterCouponId') && (
              <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 space-y-3">
                <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">SUBSCRIBER INCENTIVE COUPON</label>
                {eligibleCoupons.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-neutral-200 dark:border-neutral-800 p-3.5 text-center text-neutral-400 bg-neutral-50/50 dark:bg-neutral-900/30">
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">No active coupon-code promotions are available. Create or activate one in Promotions &amp; Discounts.</p>
                  </div>
                ) : (
                  <div>
                    <select value={currentCouponId} onChange={e => updateField('newsletterCouponId', e.target.value)} className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white text-xs cursor-pointer focus:ring-2 focus:ring-indigo-500">
                      <option value="">Select an active subscriber coupon...</option>
                      {isInvalidOrExpired && <option value={currentCouponId} disabled>{currentCouponId} (Unavailable or Expired)</option>}
                      {eligibleCoupons.map(c => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                  </div>
                )}
                {isInvalidOrExpired && (
                  <div className="flex items-start gap-2 p-3 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs">
                    <AlertCircle className="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                    <span>Selected coupon is unavailable or expired. Choose another coupon before publishing.</span>
                  </div>
                )}
              </div>
              )}
            </div>
          );
        })()}</ContentDisclosure></div>

        {/* Hero Background Slider Dedicated Settings */}


        <div hidden={inspectorTab!=='content'}>{section.type==='rich_text'&&<RichTextSettings mode="content" section={section} onChange={patch=>onChange({...section,...patch})}/>}</div><div hidden={inspectorTab!=='content'} data-inspector-group={14}><ContentDisclosure title="Chapter content"><ChapterSettings mode="content" section={section} onChange={onChange}/></ContentDisclosure></div>





        <div hidden={inspectorTab!=='content'} data-inspector-group={19}><ContentDisclosure title="Images">{!(section.type==='policy_content'||(section.type==='chapter_block'&&section.chapterPreset==='policy'))&&<ImageTargetInspector mode="content" section={section} selected={selectedImage} onSelect={onSelectImage} onChange={onChange} assetControl={target=><MediaUploadField label="Image URL / File Upload" value={target.url} onChange={url=>onChange(updateImageTarget(section,target.id,{mediaUrl:url}))} onUpload={url=>onMediaUpload(pageId,section.id,latest=>updateImageTarget(latest,target.id,{mediaUrl:url}))} placeholder="https://images.unsplash.com/... or upload" />} />}</ContentDisclosure></div>

        {/* Section Main Media & Image Upload */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={20}><ContentDisclosure title="Media">{hasMedia && imageTargets(section).length===0 && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Section Main Media Asset
            </label>

            <MediaUploadField
              label={isEditorialMediaTextSplit(section) ? 'Image URL / File Upload' : 'Image or Video URL / File Upload'}
              value={section.mediaUrl || ''}
              onChange={url => updateField('mediaUrl', url)}
              onUpload={url => updateUploadedField('mediaUrl', url)}
              placeholder="https://images.unsplash.com/... or upload"
            />

            {section.type === 'hero_banner' && !['editorial-gradient','industrial-overlay'].includes(section.heroBannerPreset??'') && hasSectionField('overlayOpacity') && (
              <div>
                <div className="flex items-center justify-between text-neutral-600 dark:text-neutral-400 mb-1">
                  <span>Dark Overlay Opacity</span>
                  <span className="font-bold">{section.overlayOpacity ?? 50}%</span>
                </div>
                <input
                  type="range"
                  min="0"
                  max="90"
                  step="5"
                  value={section.overlayOpacity ?? 50}
                  onChange={e => updateField('overlayOpacity', Number(e.target.value))}
                  className="w-full accent-indigo-600"
                />
              </div>
            )}
          </div>
        )}</ContentDisclosure></div>

        {/* Action Buttons */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={21}><ContentDisclosure title="Buttons & links">{hasButtons && !(section.type==='hero_slider'&&['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??'')) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
              Call to Action Buttons
            </label>

            <div className="grid grid-cols-2 gap-2">
              {hasSectionField('buttonText') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Primary Button</span>
                <input data-inspector-field="buttonText"
                  type="text"
                  value={section.buttonText || ''}
                  onChange={e => updateField('buttonText', e.target.value)}
                  placeholder="e.g. Shop Now"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
              {hasSectionField('buttonLink') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Primary Link</span>
                <input data-inspector-field="buttonLink"
                  type="text"
                  value={section.buttonLink || ''}
                  onChange={e => updateField('buttonLink', e.target.value)}
                  placeholder="/collections/..."
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
            </div>

            <div className="grid grid-cols-2 gap-2">
              {hasSectionField('secondaryButtonText') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Secondary Button</span>
                <input
                  type="text"
                  value={section.secondaryButtonText || ''}
                  onChange={e => updateField('secondaryButtonText', e.target.value)}
                  placeholder="e.g. Learn More"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
              {hasSectionField('secondaryButtonLink') && (
              <div>
                <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Secondary Link</span>
                <input
                  type="text"
                  value={section.secondaryButtonLink || ''}
                  onChange={e => updateField('secondaryButtonLink', e.target.value)}
                  placeholder="/pages/about"
                  className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
                />
              </div>
              )}
            </div>
          </div>
        )}</ContentDisclosure></div>

        {/* Sub-Items List Editor (for Multi-card sections & Hero Slider) */}
        <div hidden={inspectorTab!=='content'} data-inspector-group={22}><ContentDisclosure title="Values">{isValuesList(section) && <ValuesListEditor section={section} onChange={patch=>onChange({...section,...patch})} />}</ContentDisclosure></div>
        <div hidden={inspectorTab!=='content'} data-inspector-group={23}><ContentDisclosure title="Item cards">{hasItems && !isValuesList(section) && (
          <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
            <div className="flex items-center justify-between">
              <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                {editorialCollection?(section.type==='media_gallery'?'Photographs':'Text columns'):section.type === 'hero_slider' ? 'Background Carousel Slides' : 'Item Cards'} ({section.items?.length || 0})
              </label>
              <button
                type="button"
                onClick={handleAddItem}
                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold shadow-xs cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>{editorialCollection?(section.type==='media_gallery'?'Add Photo':'Add Column'):section.type === 'logo_cloud' ? 'Add Logo' : section.type === 'hero_slider' ? 'Add Slide' : 'Add Card'}</span>
              </button>
            </div>

            <div className="space-y-3 max-h-96 overflow-y-auto pr-1">
              {section.items?.map((item, idx) => (
                <div
                  data-inspector-item={item.id}
                  key={item.id || idx}
                  className="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/50 space-y-2.5"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                      {editorialCollection?`${section.type==='media_gallery'?'Photo':'Column'} ${idx+1}`:section.type === 'hero_slider' ? `Slide #${idx + 1}` : `Card #${idx + 1}`}
                    </span>
                    {<div className="flex gap-2 ml-auto mr-3">{([-1,1] as const).map(offset=><button key={offset} type="button" aria-label={`Move item ${idx+1} ${offset===-1?'up':'down'}`} disabled={idx+offset<0||idx+offset>=(section.items?.length??0)} onClick={()=>{const items=[...(section.items??[])];[items[idx],items[idx+offset]]=[items[idx+offset],items[idx]];updateField('items',items);}} className="text-[10px] text-neutral-500 hover:text-indigo-500 disabled:opacity-30">{offset===-1?'Up':'Down'}</button>)}</div>}
                    <button
                      type="button"
                      onClick={() => handleDeleteItem(idx)}
                      className="text-neutral-400 hover:text-rose-500 cursor-pointer"
                      title={section.type==='hero_slider'?'Delete slide':'Delete item'}
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>

                      {hasItemField(item,'author')&&<label className="block text-[10px] text-neutral-400">Reviewer name<input className="w-full px-2 py-1 rounded border border-neutral-700 bg-transparent" value={item.author??''} onChange={e=>handleUpdateItem(idx,{author:e.target.value})}/></label>}
                      {section.type==='pricing_table'&&section.pricingPreset==='saas-packages'&&<SaasItemSettings item={item} index={idx} onChange={patch=>handleUpdateItem(idx,patch)}/>}
                      {hasItemField(item,'features')&&<label className="block text-[10px] text-neutral-400">Features (one per line)<textarea className="w-full px-2 py-1 rounded border border-neutral-700 bg-transparent" value={(item.features??[]).join('\n')} onChange={e=>handleUpdateItem(idx,{features:e.target.value.split('\n')})}/></label>}
                      {hasItemField(item,'isHighlighted')&&<InspectorToggle label="Highlight this plan" checked={item.isHighlighted===true} onChange={checked=>handleUpdateItem(idx,{isHighlighted:checked})}/>}
                  {/* Hero Slider Dedicated Slide Fields */}
                      {hasItemField(item,'subtitle')&&<label className="block text-[10px] text-neutral-400">Eyebrow<input className="w-full px-2 py-1 rounded border border-neutral-700 bg-transparent" value={item.subtitle??''} onChange={e=>handleUpdateItem(idx,{subtitle:e.target.value})}/></label>}
                      {hasItemField(item,'category')&&<label className="block text-xs text-neutral-500">Image group<input aria-label="Image group" className="w-full mt-1 p-2 rounded border dark:border-neutral-700 dark:bg-neutral-900" value={item.category??''} onChange={e=>handleUpdateItem(idx,{category:e.target.value})}/></label>}
                      {section.type!=='hero_slider'&&hasItemField(item,'buttonText')&&<label className="block text-xs text-neutral-500">Button label<input className="w-full p-2 rounded border dark:border-neutral-700 dark:bg-neutral-900" value={item.buttonText??''} onChange={e=>handleUpdateItem(idx,{buttonText:e.target.value})}/></label>}
                      {section.type!=='hero_slider'&&hasItemField(item,'linkUrl')&&<label className="block text-[10px] text-neutral-400">Destination<input className="w-full px-2 py-1 rounded border border-neutral-700 bg-transparent" value={item.linkUrl??''} onChange={e=>handleUpdateItem(idx,{linkUrl:e.target.value})}/></label>}
                  {section.type === 'hero_slider' ? (
                    <div className="space-y-2.5">
                      {/* Background Image upload for this slide */}
                      {hasItemField(item, 'imageUrl') && !imageTargets(section).some(target=>target.itemId===item.id && target.field==='imageUrl') && (
                      <MediaUploadField
                        label="Slide Background Image / Video"
                        value={item.imageUrl || ''}
                        onChange={url => handleUpdateItem(idx, { imageUrl: url })}
                        onUpload={url => updateUploadedItemField(item.id, 'imageUrl', url)}
                        placeholder="https://images.unsplash.com/... or upload"
                      />
                      )}

                      {hasItemField(item,'videoUrl')&&<MediaUploadField label="Slide video URL / File Upload" value={item.videoUrl??''} onChange={url=>handleUpdateItem(idx,{videoUrl:url})} onUpload={url=>updateUploadedItemField(item.id,'videoUrl',url)} accept="video/*" />}
                      {/* Eyebrow Badge */}
                      {hasItemField(item, 'badge') && (
                      <div>
                        <span className="text-[10px] text-neutral-400 block mb-0.5">Eyebrow Tag / Badge</span>
                        <input
                          type="text"
                          value={item.badge || ''}
                          onChange={e => handleUpdateItem(idx, { badge: e.target.value })}
                          placeholder="e.g. Flagship Launch 2026"
                          className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs font-semibold"
                        />
                      </div>
                      )}

                      {/* Headline Title */}
                      {hasItemField(item, 'title') && (
                      <div>
                        <span className="text-[10px] text-neutral-400 block mb-0.5">Slide Headline</span>
                        <textarea
                          rows={2}
                          aria-label="Slide headline"
                          value={item.title || ''}
                          onChange={e => handleUpdateItem(idx, { title: e.target.value })}
                          placeholder="Add your headline"
                          className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-bold"
                        />
                      </div>
                      )}

                      {/* Description Copy */}
                      {hasItemField(item, 'description') && !(section.type==='logo_cloud'&&section.logoPreset==='partners') && (
                      <div>
                        <span className="text-[10px] text-neutral-400 block mb-0.5">Slide Description Copy</span>
                        <textarea
                          rows={2}
                          value={item.description || ''}
                          onChange={e => handleUpdateItem(idx, { description: e.target.value })}
                          placeholder="Compelling story copy for this slide..."
                          className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs"
                        />
                      </div>
                      )}

                      {hasItemField(item,'thirdButtonText')&&<label className="block text-xs text-neutral-500">Third link label<input className="w-full p-2 rounded border dark:border-neutral-700 dark:bg-neutral-900" value={item.thirdButtonText??''} onChange={e=>handleUpdateItem(idx,{thirdButtonText:e.target.value})}/></label>}
                      {hasItemField(item,'thirdButtonLink')&&<label className="block text-xs text-neutral-500">Third link URL<input className="w-full p-2 rounded border dark:border-neutral-700 dark:bg-neutral-900" value={item.thirdButtonLink??''} onChange={e=>handleUpdateItem(idx,{thirdButtonLink:e.target.value})}/></label>}
                      {hasItemField(item,'caption')&&<label className="block text-[10px] text-neutral-400">Caption (optional)<textarea rows={2} aria-label="Slide caption" value={item.caption??''} onChange={e=>handleUpdateItem(idx,{caption:e.target.value})} className="w-full mt-1 px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs"/></label>}
                      {/* Buttons for Slide */}
                      {hasAnyItemField(item, ['buttonText', 'linkUrl']) && (
                      <div className="grid grid-cols-2 gap-2 pt-1 border-t border-neutral-200/60 dark:border-neutral-800/60">
                        {hasItemField(item, 'buttonText') && (
                        <div>
                          <span className="text-[10px] text-neutral-400 block mb-0.5">Primary Button</span>
                          <input
                            type="text"
                            value={item.buttonText || ''}
                            onChange={e => handleUpdateItem(idx, { buttonText: e.target.value })}
                            placeholder="Shop Now"
                            className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs"
                          />
                        </div>
                        )}
                        {hasItemField(item, 'linkUrl') && (
                        <div>
                          <span className="text-[10px] text-neutral-400 block mb-0.5">Primary Link</span>
                          <input
                            type="text"
                            value={item.linkUrl || ''}
                            onChange={e => handleUpdateItem(idx, { linkUrl: e.target.value })}
                            placeholder="/products/..."
                            className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs"
                          />
                        </div>
                        )}
                      </div>
                      )}

                      {hasAnyItemField(item, ['secondaryButtonText', 'secondaryButtonLink']) && (
                      <div className="grid grid-cols-2 gap-2">
                        {hasItemField(item, 'secondaryButtonText') && (
                        <div>
                          <span className="text-[10px] text-neutral-400 block mb-0.5">Secondary Button</span>
                          <input
                            type="text"
                            value={item.secondaryButtonText || ''}
                            onChange={e => handleUpdateItem(idx, { secondaryButtonText: e.target.value })}
                            placeholder="Explore Story"
                            className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs"
                          />
                        </div>
                        )}
                        {hasItemField(item, 'secondaryButtonLink') && (
                        <div>
                          <span className="text-[10px] text-neutral-400 block mb-0.5">Secondary Link</span>
                          <input
                            type="text"
                            value={item.secondaryButtonLink || ''}
                            onChange={e => handleUpdateItem(idx, { secondaryButtonLink: e.target.value })}
                            placeholder="#specs"
                            className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white text-xs"
                          />
                        </div>
                        )}
                      </div>
                      )}
                    </div>
                  ) : (
                    <>
                      {/* Title / Name */}
                      {hasItemField(item, 'title') && (
                      <div>
                        <span className="text-[10px] text-neutral-400 block mb-0.5">
                          {section.type === 'testimonials_slider' ? 'Reviewer Name / Headline' : section.type === 'logo_cloud' ? 'Brand name' : 'Card Title'}
                        </span>
                        {section.listingPreset==='fashion-lookbook'?<textarea aria-label="Editorial card title" rows={3} value={item.title??''} onChange={e=>handleUpdateItem(idx,{title:e.target.value})} className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium"/>:(                        <input
                          type="text"
                          value={item.title || ''}
                          onChange={e => handleUpdateItem(idx, { title: e.target.value })}
                          placeholder="Title / Name"
                          className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-medium"
                        />)}
                      </div>
                      )}

                      {/* Description / Review Quote */}
                      {hasItemField(item, 'description') && !(section.type==='logo_cloud'&&section.logoPreset==='partners') && (
                      <div>
                        <span className="text-[10px] text-neutral-400 block mb-0.5">
                          {section.type === 'testimonials_slider' ? 'Testimonial Quote' : section.type === 'logo_cloud' ? 'Award / Score Snippet' : 'Description'}
                        </span>
                        <textarea
                          rows={2}
                          value={item.description || ''}
                          onChange={e => handleUpdateItem(idx, { description: e.target.value })}
                          placeholder="Description copy or quote..."
                          className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                        />
                      </div>
                      )}

                      {/* Testimonial Special Fields */}
                      {section.type === 'testimonials_slider' && hasAnyItemField(item, ['authorRole', 'rating', 'authorAvatar']) && (
                        <div className="space-y-2 pt-1 border-t border-neutral-200/60 dark:border-neutral-800/60">
                          <div className="grid grid-cols-2 gap-2">
                            {hasItemField(item, 'authorRole') && (
                            <div>
                              <span className="text-[10px] text-neutral-400 block mb-0.5">Reviewer Role / Title</span>
                              <input
                                type="text"
                                value={item.authorRole || ''}
                                onChange={e => handleUpdateItem(idx, { authorRole: e.target.value })}
                                placeholder="e.g. Mastering Engineer"
                                className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                              />
                            </div>
                            )}
                            {hasItemField(item, 'rating') && (
                            <div>
                              <span className="text-[10px] text-neutral-400 block mb-0.5">Rating (1 to 5 Stars)</span>
                              <select
                                value={item.rating ?? 5}
                                onChange={e => handleUpdateItem(idx, { rating: Number(e.target.value) })}
                                className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white font-semibold"
                              >
                                <option value={5}>★★★★★ (5 Stars)</option>
                                <option value={4}>★★★★☆ (4 Stars)</option>
                                <option value={3}>★★★☆☆ (3 Stars)</option>
                                <option value={2}>★★☆☆☆ (2 Stars)</option>
                                <option value={1}>★☆☆☆☆ (1 Star)</option>
                              </select>
                            </div>
                            )}
                          </div>

                          {/* Reviewer Avatar Upload */}
                          {hasItemField(item, 'authorAvatar') && !imageTargets(section).some(target=>target.itemId===item.id && target.field==='authorAvatar') && (
                          <MediaUploadField
                            label="User Avatar Photo"
                            value={item.authorAvatar || ''}
                            onChange={url => handleUpdateItem(idx, { authorAvatar: url })}
                            onUpload={url => updateUploadedItemField(item.id, 'authorAvatar', url)}
                            placeholder="https://... avatar URL"
                          />
                          )}
                        </div>
                      )}

                      {/* Instagram handle */}
                      {section.type === 'instagram_feed' && hasItemField(item, 'handle') && (
                        <div>
                          <span className="text-[10px] text-neutral-400 block mb-0.5">Instagram Handle / Creator</span>
                          <input
                            type="text"
                            value={item.handle || ''}
                            onChange={e => handleUpdateItem(idx, { handle: e.target.value })}
                            placeholder="@aurora.sound"
                            className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                          />
                        </div>
                      )}

                      {/* Specific Fields by Type */}
                      {hasAnyItemField(item, ['value', 'date', 'price', 'icon', 'badge']) && (
                      <div className="grid grid-cols-2 gap-2">
                        {section.type === 'stats_counter' && hasItemField(item, 'value') && (
                          <div>
                            <span className="text-[10px] text-neutral-400 block mb-0.5">Value (e.g. 99.4%)</span>
                            <input
                              type="text"
                              value={item.value || ''}
                              onChange={e => handleUpdateItem(idx, { value: e.target.value })}
                              className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                            />
                          </div>
                        )}

                        {section.type === 'timeline_story' && hasItemField(item, 'date') && (
                          <div>
                            <span className="text-[10px] text-neutral-400 block mb-0.5">Year / Date</span>
                            <input
                              type="text"
                              value={item.date || ''}
                              onChange={e => handleUpdateItem(idx, { date: e.target.value })}
                              className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                            />
                          </div>
                        )}

                        {section.type === 'pricing_table' && hasItemField(item, 'price') && !(section.pricingPreset==='saas-packages'&&item.pricingMode==='contact') && (
                          <div>
                            <span className="text-[10px] text-neutral-400 block mb-0.5">Price ({currencyCode})</span>
                            <input
                              type="number"
                              value={item.price ?? ''}
                              onChange={e => handleUpdateItem(idx, { price: e.target.value==='' && section.pricingPreset==='saas-packages' ? undefined : Number(e.target.value) })}
                              className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                            />
                          </div>
                        )}

                        {hasItemField(item, 'icon') && (
                          <div className="col-span-2 min-w-0">
                            <span className="text-[10px] text-neutral-400 block mb-0.5">Icon</span>
                            <IconPicker value={item.icon || 'Sparkles'} onChange={icon => handleUpdateItem(idx, {icon})}/>
                          </div>
                        )}

                        {hasItemField(item, 'badge') && (section.layoutPreset==='kids-discovery'?<label className="block text-xs">Entry group<select aria-label={`Entry ${idx+1} group`} value={item.badge==='care'?'care':'milestone'} onChange={e=>handleUpdateItem(idx,{badge:e.target.value})} className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-transparent"><option value="care">Materials & care</option><option value="milestone">Milestone</option></select></label>:<div>
                            <span className="text-[10px] text-neutral-400 block mb-0.5">Badge</span>
                            <input
                              type="text"
                              value={item.badge || ''}
                              onChange={e => handleUpdateItem(idx, { badge: e.target.value })}
                              className="w-full px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                            />
                          </div>
                        )}
                      </div>
                      )}
                    </>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}</ContentDisclosure></div>

        {/* 9. Layout, Columns & Styling */}
        <div hidden={inspectorTab!=='design'} data-inspector-group={24}><details className="cms-settings-group"><summary>Layout & spacing</summary><div className="cms-settings-group-content">{hasAnySectionField(['textAlignment', 'paddingY', 'backgroundColor']) && (
        <div className="space-y-3">
          <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">
            Layout & Theme
          </label>

          {/* Alignment */}
          {hasSectionField('textAlignment') && !(editorialCollection&&section.showSectionHeading===false) && !(section.type==='cta_strip' && section.ctaPreset!=='default' && section.ctaPreset!==undefined) && !isEditorialMediaTextSplit(section) && !isManifesto(section) && !isValuesList(section) && (
          <div>
            <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Text Alignment</span>
            <div className="grid grid-cols-3 gap-2">
              <button
                type="button"
                onClick={() => updateField('textAlignment', 'left')}
                className={`py-1.5 flex items-center justify-center rounded-lg border transition-colors cursor-pointer ${
                  section.textAlignment === 'left' || (section.type === 'shop_navigation' && !section.textAlignment)
                    ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                    : 'border-neutral-200 dark:border-neutral-700 text-neutral-500'
                }`}
              >
                <AlignLeft className="w-4 h-4 mr-1" /> Left
              </button>
              <button
                type="button"
                onClick={() => updateField('textAlignment', 'center')}
                className={`py-1.5 flex items-center justify-center rounded-lg border transition-colors cursor-pointer ${
                  section.textAlignment === 'center' || (section.type !== 'shop_navigation' && !section.textAlignment)
                    ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                    : 'border-neutral-200 dark:border-neutral-700 text-neutral-500'
                }`}
              >
                <AlignCenter className="w-4 h-4 mr-1" /> Center
              </button>
              <button
                type="button"
                onClick={() => updateField('textAlignment', 'right')}
                className={`py-1.5 flex items-center justify-center rounded-lg border transition-colors cursor-pointer ${
                  section.textAlignment === 'right'
                    ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                    : 'border-neutral-200 dark:border-neutral-700 text-neutral-500'
                }`}
              >
                <AlignRight className="w-4 h-4 mr-1" /> Right
              </button>
            </div>
          </div>
          )}



          {/* Padding */}
          {hasSectionField('paddingY') && (
          <div>
            <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1 block">Vertical Spacing</span>
            <div className="grid grid-cols-3 gap-2">
              {(['compact', 'normal', 'spacious'] as const).map(pad => (
                <button
                  key={pad}
                  type="button"
                  onClick={() => updateField('paddingY', pad)}
                  className={`p-2 capitalize text-center rounded-lg border transition-colors cursor-pointer ${
                    section.paddingY === pad || (pad === 'normal' && !section.paddingY)
                      ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold'
                      : 'border-neutral-200 dark:border-neutral-700 text-neutral-500'
                  }`}
                >
                  <span aria-hidden="true" className="flex h-14 bg-neutral-500/10 items-center justify-center mb-2"><span className="w-4/5 border-y border-dashed border-indigo-400/50 flex items-center justify-center" style={{height:pad==='compact'?24:pad==='normal'?38:52}}><span className="h-2 w-2/3 bg-indigo-400 rounded-sm"/></span></span>{pad}
                </button>
              ))}
            </div>
          </div>
          )}

          <SectionBorderSettings section={section} onChange={patch=>onChange({...section,...patch})} />

          {/* Background Color Palette */}
          {hasSectionField('backgroundColor') && (
          <div>
            <span className="text-neutral-600 dark:text-neutral-400 font-medium mb-1.5 block">
              Background Canvas Style
            </span>
            <VisualChoiceSelect
              aria-label="Background canvas style"
              value={section.backgroundColor || 'bg-neutral-900'}
              onChange={e => updateField('backgroundColor', e.target.value)}
              className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"
            >
              {bgOptions.map(bg => (
                <option key={bg.value} value={bg.value}>{bg.label}</option>
              ))}
            </VisualChoiceSelect>
          </div>
          )}

          {/* Custom Background & Gradient Controller */}
          {hasSectionField('backgroundColor') && section.backgroundColor === 'custom' && hasColorField('customBgColor') && (
            <CustomBackgroundControls
              value={sectionColors?.customBgColor || ''}
              onChange={val => updateColorField('customBgColor', val)}
            />
          )}
        </div>
        )}</div></details></div>

        {/* 9. Entrance Motion & Stagger Presets */}
        <div hidden={inspectorTab!=='design'} data-inspector-group={25}><details className="cms-settings-group"><summary>Motion</summary><div className="cms-settings-group-content"><div className="pt-4 border-t border-neutral-200 dark:border-neutral-800 space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Sparkles className="w-4 h-4 text-indigo-500" />
              <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-900 dark:text-white">
                Entrance Motion
              </label>
            </div>
            <div className="flex items-center gap-1.5">
              {onReplayMotion && (
                <button
                  type="button"
                  onClick={onReplayMotion}
                  className="text-[10px] font-semibold text-neutral-600 dark:text-neutral-300 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1 cursor-pointer transition-colors px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700"
                  title="Replay entrance motion in live preview"
                >
                  <Play className="w-2.5 h-2.5 text-indigo-500" /> Replay
                </button>
              )}
              {onApplyMotionToAll && (
                <button
                  type="button"
                  onClick={() => onApplyMotionToAll(section.motionPreset || 'subtle-lift')}
                  className="text-[10px] font-semibold text-neutral-600 dark:text-neutral-300 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1 cursor-pointer transition-colors px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700"
                  title="Apply this motion preset to all sections on the current page"
                >
                  <Layers className="w-2.5 h-2.5 text-indigo-500" /> Apply All
                </button>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-1.5">
            {(
              [
                'subtle-lift',
                'fade-in',
                'smooth-zoom',
                'dynamic-pop',
                'none'
              ] as SectionMotionPreset[]
            ).map(presetKey => {
              const info = MOTION_PRESET_DETAILS[presetKey];
              const isSelected = (section.motionPreset || 'subtle-lift') === presetKey;
              return (
                <button
                  key={presetKey}
                  type="button"
                  onClick={() => updateField('motionPreset', presetKey)}
                  className={`w-full p-2.5 rounded-xl border text-left flex items-start justify-between transition-all cursor-pointer ${
                    isSelected
                      ? 'border-indigo-600 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-950 dark:text-white ring-1 ring-indigo-500 shadow-xs'
                      : 'border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30 text-neutral-700 dark:text-neutral-300 hover:border-neutral-300 dark:hover:border-neutral-700'
                  }`}
                >
                  <div className="space-y-0.5 pr-2">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold">{info.label}</span>
                      <span className="text-[9px] font-mono px-1.5 py-0.2 rounded bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 font-semibold">
                        {info.iconTag}
                      </span>
                    </div>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400 leading-tight">
                      {info.desc}
                    </p>
                  </div>
                  {isSelected && (
                    <Check className="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                  )}
                </button>
              );
            })}
          </div>
        </div></div></details></div>

        {/* 10. Brand Palette & Color Customization (Secondary Color Tuner) */}
        <div hidden={inspectorTab!=='design'} data-inspector-group={26}><details className="cms-settings-group"><summary>Palette defaults & colors</summary><div className="cms-settings-group-content"><SectionStyleSettings section={section} onChange={onChange} pagePalette={pagePalette} followTheme={followTheme}/>{sectionColors !== null && (
        <div className="pt-4 border-t border-neutral-200 dark:border-neutral-800 space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Paintbrush className="w-4 h-4 text-indigo-500" />
              <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-900 dark:text-white">
                Brand Palette & Colors
              </label>
            </div>
            {sectionColors && (
              <button
                type="button"
                onClick={resetColorsToDefault}
                className="text-[10px] text-neutral-400 hover:text-rose-500 flex items-center gap-1 cursor-pointer transition-colors"
                title="Reset this section to default styling"
              >
                <RotateCcw className="w-3 h-3" /> Reset
              </button>
            )}
          </div>

          {/* 1-Click Color Themes Preset Bar */}
          <div>
            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400 block mb-2">
              Quick Theme Presets
            </span>
            <div className="grid grid-cols-2 gap-2">
              {colorPresets.map(preset => (
                <button
                  key={preset.id}
                  type="button"
                  onClick={() => applyColorPreset(preset)}
                  className="p-2 text-left rounded-xl border border-neutral-200 dark:border-neutral-700/80 bg-neutral-50 dark:bg-neutral-800/40 hover:border-indigo-500 dark:hover:border-indigo-500 transition-all cursor-pointer group"
                >
                  <div className="flex items-center gap-1.5 mb-1.5">
                    {preset.preview.map((c, i) => (
                      <span
                        key={i}
                        className="w-3.5 h-3.5 rounded-full border border-black/20 dark:border-white/20 shadow-xs"
                        style={{ backgroundColor: c }}
                      />
                    ))}
                  </div>
                  <div className="text-[11px] font-semibold text-neutral-800 dark:text-neutral-200 truncate group-hover:text-indigo-500">
                    {preset.name}
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Granular Color Tuners */}
          <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/60 space-y-3.5">
            <div className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
              Micro Color Controls
            </div>

            {/* 1. Eyebrow Tag Color */}
            {hasColorField('eyebrowColor') && (
            <div className="flex items-center justify-between gap-2">
              <div>
                <span className="text-xs font-medium text-neutral-700 dark:text-neutral-300 block">
                  Subtitle / Eyebrow Tag
                </span>
                <span className="text-[10px] text-neutral-400">Badge accent & eyebrow highlight</span>
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="color"
                  value={sectionColors?.eyebrowColor || '#818cf8'}
                  onChange={e => updateColorField('eyebrowColor', e.target.value)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                />
                <input
                  type="text"
                  value={sectionColors?.eyebrowColor || ''}
                  placeholder="#818cf8"
                  onChange={e => updateColorField('eyebrowColor', e.target.value)}
                  className="w-20 px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>
            )}

            {/* 2. Primary Headline Title Color */}
            {hasColorField('titleColor') && (
            <div className="flex items-center justify-between gap-2">
              <div>
                <span className="text-xs font-medium text-neutral-700 dark:text-neutral-300 block">
                  Primary Headline Title
                </span>
                <span className="text-[10px] text-neutral-400">Section heading typography</span>
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="color"
                  value={sectionColors?.titleColor || '#ffffff'}
                  onChange={e => updateColorField('titleColor', e.target.value)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                />
                <input
                  type="text"
                  value={sectionColors?.titleColor || ''}
                  placeholder="#ffffff"
                  onChange={e => updateColorField('titleColor', e.target.value)}
                  className="w-20 px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>
            )}

            {/* 3. Description / Content Color */}
            {hasColorField('contentColor') && (
            <div className="flex items-center justify-between gap-2">
              <div>
                <span className="text-xs font-medium text-neutral-700 dark:text-neutral-300 block">
                  Editorial Description
                </span>
                <span className="text-[10px] text-neutral-400">Body paragraph & subtitle text</span>
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="color"
                  value={sectionColors?.contentColor || '#d4d4d8'}
                  onChange={e => updateColorField('contentColor', e.target.value)}
                  className="w-7 h-7 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                />
                <input
                  type="text"
                  value={sectionColors?.contentColor || ''}
                  placeholder="#d4d4d8"
                  onChange={e => updateColorField('contentColor', e.target.value)}
                  className="w-20 px-2 py-1 text-[11px] font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                />
              </div>
            </div>
            )}

            {/* 4. Primary Button Color (Bg + Text) */}
            {isValuesList(section) && <ValuesListColors section={section} onChange={patch=>onChange({...section,...patch})} />}
            {(hasColorField('primaryBtnBg') || hasColorField('primaryBtnText')) && (
            <div className="pt-2 border-t border-neutral-200 dark:border-neutral-700/60 space-y-2">
              <div className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                Primary Button (Main CTA)
              </div>
              <div className="grid grid-cols-2 gap-2">
                {hasColorField('primaryBtnBg') && (
                <div className="flex items-center gap-1.5">
                  <input
                    type="color"
                    value={sectionColors?.primaryBtnBg || '#4f46e5'}
                    onChange={e => updateColorField('primaryBtnBg', e.target.value)}
                    className="w-6 h-6 rounded-md border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                    title="Button Background"
                  />
                  <input
                    type="text"
                    placeholder="Bg #4f46e5"
                    value={sectionColors?.primaryBtnBg || ''}
                    onChange={e => updateColorField('primaryBtnBg', e.target.value)}
                    className="w-full px-2 py-1 text-[10px] font-mono rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                  />
                </div>
                )}
                {hasColorField('primaryBtnText') && (
                <div className="flex items-center gap-1.5">
                  <input
                    type="color"
                    value={sectionColors?.primaryBtnText || '#ffffff'}
                    onChange={e => updateColorField('primaryBtnText', e.target.value)}
                    className="w-6 h-6 rounded-md border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                    title="Button Text Color"
                  />
                  <input
                    type="text"
                    placeholder="Text #ffffff"
                    value={sectionColors?.primaryBtnText || ''}
                    onChange={e => updateColorField('primaryBtnText', e.target.value)}
                    className="w-full px-2 py-1 text-[10px] font-mono rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                  />
                </div>
                )}
              </div>
            </div>
            )}

            {/* 5. Secondary Button Color (Bg + Text) */}
            {(hasColorField('secondaryBtnBg') || hasColorField('secondaryBtnText')) && (
            <div className="pt-2 border-t border-neutral-200 dark:border-neutral-700/60 space-y-2">
              <div className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                Secondary Button (Alternative CTA)
              </div>
              <div className="grid grid-cols-2 gap-2">
                {hasColorField('secondaryBtnBg') && (
                <div className="flex items-center gap-1.5">
                  <input
                    type="color"
                    value={sectionColors?.secondaryBtnBg?.startsWith('#') ? sectionColors.secondaryBtnBg : '#333333'}
                    onChange={e => updateColorField('secondaryBtnBg', e.target.value)}
                    className="w-6 h-6 rounded-md border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                    title="Secondary Button Background"
                  />
                  <input
                    type="text"
                    placeholder="Bg rgba/Hex"
                    value={sectionColors?.secondaryBtnBg || ''}
                    onChange={e => updateColorField('secondaryBtnBg', e.target.value)}
                    className="w-full px-2 py-1 text-[10px] font-mono rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                  />
                </div>
                )}
                {hasColorField('secondaryBtnText') && (
                <div className="flex items-center gap-1.5">
                  <input
                    type="color"
                    value={sectionColors?.secondaryBtnText || '#ffffff'}
                    onChange={e => updateColorField('secondaryBtnText', e.target.value)}
                    className="w-6 h-6 rounded-md border border-neutral-300 dark:border-neutral-600 cursor-pointer bg-transparent"
                    title="Secondary Button Text Color"
                  />
                  <input
                    type="text"
                    placeholder="Text #ffffff"
                    value={sectionColors?.secondaryBtnText || ''}
                    onChange={e => updateColorField('secondaryBtnText', e.target.value)}
                    className="w-full px-2 py-1 text-[10px] font-mono rounded border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white"
                  />
                </div>
                )}
              </div>
            </div>
            )}
          </div>
        </div>
        )}</div></details></div>
      </div>
    </div>
  );
};
