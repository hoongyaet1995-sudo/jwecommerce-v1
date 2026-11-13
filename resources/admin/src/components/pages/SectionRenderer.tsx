import {containedSurfaceBackground} from '../../cms/containedSurface';
import {WideVisualEditorial} from './WideVisualEditorial';
import {sectionAnchorOffset} from '../../cms/sectionAnchorOffset';
import {PlanComparison,FramedFaq} from './PlanComparison';
import {CorporateBuilderOverview,CorporateHero,CorporateTabs,CorporateFeature,CorporatePayments,CorporateCourier,CorporateMarketplace} from './CorporateSections';
import {PartnerLogos} from './PartnerLogos';
import {IndustrialRelatedProducts} from './IndustrialRelatedProducts';
import {IndustrialGallery,IndustrialSplit,IndustrialProcessSplit,IndustrialOutlineCta} from './IndustrialDetailSections';
import {PhotoMarquee} from './PhotoMarquee';
import {EditorialGradientHero,EditorialQuote} from './WeddingEditorial';
import {purchasePriceLabel,isOnlinePurchase} from '../../cms/purchasePresentation';
import {ArtisanStory} from './ArtisanStory';
import {KidsDiscovery} from './KidsLayouts';
import {TechFeature,DeviceComparison} from './TechLayouts';
import {InlineNewsletter} from './InlineNewsletter';
import {RoutineSteps} from './BeautyLayouts';
import {FlushSplit} from './FlushSplit';
import {EditorialProductTabs} from './EditorialProductTabs';
import {EditorialStoryCards} from './EditorialStoryCards';
import {resolveStyledSection,resolvePageStyle} from '../../cms/pageStyle';
import {loadCmsFonts} from '../../cms/fontCatalog';
import {PromotionHighlights} from './PromotionHighlights';
import {resolvePromotionCards} from '../../cms/promotionHighlights';
import {EditorialProductGrid} from './EditorialProductGrid';
import {ShopNavigation} from './ShopNavigation';
import {ProductLookbook} from './ProductLookbook';
import {EditorialOverlaySlider} from './EditorialOverlaySlider';
import {EnquiryForm,ContactLocation} from './ContactSections';
import {SectionIcon} from './IconPicker';
import {PolicyContent} from './PolicyContent';
import {MediaWidthRegion} from './MediaWidthRegion';
import {PaymentMethods} from './PaymentMethods';
import {ServiceHighlights,DeliveryTable,MinimalFaq,HelpPanel} from './ShippingSections';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {PageSectionContainer} from './PageSectionContainer';
import {sectionBorderStyle} from '../../cms/sectionBorders';
import {ImageBanner} from './ImageBanner';
import {ChapterNavigation,ChapterBlock,EditorialCover,EditorialCtaBand} from './ChapterSections';
import {EditorialGallery,EditorialColumns} from './EditorialCollections';
import {ValuesList} from './ValuesList';
import {Manifesto} from './Manifesto';
import {IndustrialCards,IndustrialFaq} from './IndustrialSections';
import {SaasPackages} from './SaasPackages';
import {SaasMedia,SaasModules,SaasFaq,SaasClosing} from './SaasSections';
import React, { useState, useEffect, useRef } from 'react';
import {ScopedImage,imageSlotFrameStyle} from './ScopedImage';
import {ImageSelectionContext} from './ImageSelectionContext';
import {EditorialHero} from './EditorialHero';
import {EditorialStory} from './EditorialStory';
import { CmsMotionWrapper, MotionItem, MotionMedia, MotionButton, MotionCard } from './motion/CmsMotionWrapper';
import { PageSection, SectionItem } from '../../types';
import { isCmsFormSubmissionContract, usePageRuntime, usePageSectionRuntime } from '../../cms/PageRuntimeContext';
import {
  ArrowRight,
  ExternalLink,
  Play,
  ChevronDown,
  ChevronUp,
  ChevronLeft,
  ChevronRight,
  Sparkles,
  ShieldCheck,
  Zap,
  Globe,
  Heart,
  Truck,
  Package,
  Headphones,
  Layers,
  Award,
  CheckCircle2,
  Volume2,
  Star,
  Clock,
  Flame,
  Check,
  X,
  Mail,
  MapPin,
  Phone,
  Instagram,
  ShoppingBag,
  Sliders,
  Send,
  Lock,
  Tag,
  Building
} from 'lucide-react';

interface SectionRendererProps {
  section: PageSection;
  isEditingMode?: boolean;
  isSelected?: boolean;
  onSelect?: () => void;
  selectedImage?:string;
  onSelectImage?:(target:string)=>void;
  onSelectElement?:(target:string,role?:string)=>void;
  viewport?: 'desktop' | 'tablet' | 'mobile';
}

export const SectionRenderer: React.FC<SectionRendererProps> = ({
  section: rawSection,
  isEditingMode = false,
  isSelected = false,
  onSelect,
  selectedImage,
  onSelectImage,
  onSelectElement,
  viewport = 'desktop'
}) => {
  const runtime = usePageRuntime();
  const section=resolveStyledSection(rawSection,resolvePageStyle(runtime.page.document.style,runtime.context.themePalette));
  useEffect(()=>loadCmsFonts(),[]);
  const runtimeSection = usePageSectionRuntime(section.id);
  const [productListingIdx, setProductListingIdx] = useState(0);
  const [listingVariantIds, setListingVariantIds] = useState<Record<string,string>>({});
  const [isListingHovered, setIsListingHovered] = useState(false);
  const listingTrackRef = useRef<HTMLDivElement>(null);
  const listingItems = runtimeSection.listing?.products ?? [];
  const listingCardsPerView = viewport === 'mobile' ? 1 : viewport === 'tablet' ? 2 : section.columnsCount === 4 ? 4 : 3;
  const listingMaxIdx = Math.max(0, listingItems.length - listingCardsPerView);
  const listingMoney = (amount: number) => {
    const code = runtime.context.currency.trim().match(/^([A-Za-z]{3})(?:\s|$)/)?.[1].toUpperCase();
    return code ? new Intl.NumberFormat('en-MY', {style:'currency',currency:code}).format(amount) : `${runtime.context.currency} ${amount.toFixed(2)}`;
  };
  useEffect(() => { setProductListingIdx(0); }, [section.sourceType, section.sourceId, section.productLimit, viewport, section.columnsCount]);
  useEffect(() => {
    if (section.type !== 'product_listing' || section.listingPreset === 'industrial-related' || section.listingPreset === 'editorial-grid' || section.listingPreset === 'editorial-tabs' || section.listingPreset === 'fashion-lookbook' || ['beauty-grid','pantry-grid','pantry-tabs','tech-grid','tech-tabs','kids-grid','kids-tabs'].includes(section.listingPreset??'') || section.autoSlide === false || isListingHovered || listingMaxIdx <= 0) return;
    const timer = setInterval(() => {
      if (document.querySelector('[data-quick-add-modal][aria-hidden="false"]')) return;
      setProductListingIdx(prev => prev >= listingMaxIdx ? 0 : prev + 1);
    }, (section.autoSlideInterval || 3.5) * 1000);
    return () => clearInterval(timer);
  }, [section.type, section.listingPreset, section.autoSlide, section.autoSlideInterval, isListingHovered, listingMaxIdx]);
  const [openFaqIds, setOpenFaqIds] = useState<Record<string, boolean>>({
    [section.items?.[0]?.id || 'faq-1']: true
  });
  
  // Interactive state for Before/After slider
  const [sliderPos, setSliderPos] = useState<number>(50);
  const [isDraggingSlider, setIsDraggingSlider] = useState(false);
  const sliderContainerRef = useRef<HTMLDivElement>(null);

  // Interactive state for Category Tabs
  const [activeTab, setActiveTab] = useState<string>(
    section.tabs?.[0]?.label || section.tabCategories?.[0] || 'Over-Ear Flagship'
  );

  // Interactive state for Hero Background Slider
  const [heroSlideIdx, setHeroSlideIdx] = useState(0);
  const [isHeroHovered, setIsHeroHovered] = useState(false);

  // Interactive state for Testimonials Carousel
  const [testimonialIdx, setTestimonialIdx] = useState(0);

  // Interactive state for Newsletter / Contact Form
  const [emailInput, setEmailInput] = useState('');
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [newsletterCouponCode, setNewsletterCouponCode] = useState<string | null>(null);
  const [contactName, setContactName] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [contactSubject, setContactSubject] = useState('');
  const [contactCategory, setContactCategory] = useState('');
  const [contactMessage, setContactMessage] = useState('');
  const [contactSuccess, setContactSuccess] = useState(false);
  const [newsletterError, setNewsletterError] = useState('');

  // Countdown timer for Flash Deal with support for section.dealEndDate
  const calculateTimeLeft = () => {
    let target = section.dealEndDate ? new Date(section.dealEndDate).getTime() : 0;
    if (!target || isNaN(target)) {
      // Default: 48 hours from initial render
      target = Date.now() + (47 * 3600 + 58 * 60 + 32) * 1000;
    }
    const diff = Math.max(0, target - Date.now());
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    return { hours, minutes, seconds };
  };

  const [timeLeft, setTimeLeft] = useState(calculateTimeLeft());

  useEffect(() => {
    setTimeLeft(calculateTimeLeft());
    const timer = setInterval(() => {
      setTimeLeft(calculateTimeLeft());
    }, 1000);
    return () => clearInterval(timer);
  }, [section.dealEndDate]);

  if (!section.isVisible && !isEditingMode) {
    return null;
  }

  const toggleFaq = (id: string) => {
    setOpenFaqIds(prev => ({ ...prev, [id]: !prev[id] }));
  };

  const submitRuntimeForm = async (endpoint: string, fields: Record<string, string>): Promise<Response> => {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    return fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {}),
      },
      body: JSON.stringify(fields),
    });
  };

  const resolveFormContract = async (kind: 'contact' | 'newsletter') => {
    const direct = runtime.forms[kind][section.id];
    if (isCmsFormSubmissionContract(direct, runtime.page, section)) return direct;
    if (!isEditingMode || !runtime.forms.adminContractsEndpoint) return null;

    const response = await fetch(runtime.forms.adminContractsEndpoint, {
      credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    });
    if (!response.ok) return null;
    const body: unknown = await response.json().catch(() => null);
    if (typeof body !== 'object' || body === null || Array.isArray(body)) return null;
    const data = (body as Record<string, unknown>).data;
    if (typeof data !== 'object' || data === null || Array.isArray(data)) return null;
    const forms = (data as Record<string, unknown>).forms;
    if (typeof forms !== 'object' || forms === null || Array.isArray(forms)) return null;
    const contracts = (forms as Record<string, unknown>)[kind];
    if (typeof contracts !== 'object' || contracts === null || Array.isArray(contracts)) return null;
    const candidate = (contracts as Record<string, unknown>)[section.id];
    return isCmsFormSubmissionContract(candidate, runtime.page, section) ? candidate : null;
  };

  const readContactSuccess = (body: unknown): boolean => {
    if (typeof body !== 'object' || body === null || Array.isArray(body)) return false;
    const data = (body as Record<string, unknown>).data;
    return typeof data === 'object' && data !== null && !Array.isArray(data)
      && Object.keys(data).length === 2
      && (data as Record<string, unknown>).submitted === true
      && typeof (data as Record<string, unknown>).id === 'string';
  };

  const readNewsletterSuccess = (body: unknown): string | null | undefined => {
    if (typeof body !== 'object' || body === null || Array.isArray(body)) return undefined;
    const data = (body as Record<string, unknown>).data;
    if (typeof data !== 'object' || data === null || Array.isArray(data)) return undefined;
    const fields = data as Record<string, unknown>;
    if (fields.subscribed !== true) return undefined;
    if (fields.alreadySubscribed === true && Object.keys(fields).length === 2) return null;
    if (fields.alreadySubscribed !== true && Object.keys(fields).length === 2 && typeof fields.couponCode === 'string' && fields.couponCode.trim() !== '') return fields.couponCode;
    return undefined;
  };

  // Before/After mouse & touch handlers
  const handleSliderMove = (clientX: number) => {
    if (!sliderContainerRef.current) return;
    const rect = sliderContainerRef.current.getBoundingClientRect();
    const x = Math.max(0, Math.min(clientX - rect.left, rect.width));
    const percentage = (x / rect.width) * 100;
    setSliderPos(percentage);
  };

  const getPaddingClass = () => {
    switch (section.paddingY) {
      case 'compact': return 'py-8 sm:py-10';
      case 'spacious': return 'py-20 sm:py-28';
      case 'normal':
      default: return 'py-14 sm:py-16';
    }
  };

  const getBgStyle = () => {
    if (section.backgroundColor === 'custom' && section.customColors?.customBgColor) {
      return runtime.page.document.style||rawSection.surface||rawSection.brandPalette?(section.textColor==='light'?'text-white':'text-neutral-900'):'text-white';
    }
    switch (section.backgroundColor) {
      case 'bg-neutral-950': return 'bg-neutral-950 text-white';
      case 'bg-neutral-900': return 'bg-neutral-900 text-neutral-100';
      case 'bg-neutral-800': return 'bg-neutral-800 text-neutral-100';
      case 'bg-white': return 'bg-white text-neutral-900 border-y border-neutral-200 dark:border-neutral-800';
      case 'bg-neutral-50': return 'bg-neutral-50 text-neutral-900 border-y border-neutral-200 dark:border-neutral-800';
      case 'bg-gradient-indigo': return 'bg-gradient-to-r from-indigo-950 via-indigo-900 to-violet-950 text-white';
      case 'bg-gradient-slate': return 'bg-gradient-to-b from-neutral-900 to-neutral-950 text-white';
      case 'bg-emerald-950': return 'bg-emerald-950 text-white';
      default: return section.backgroundColor || 'bg-neutral-900 text-white';
    }
  };

  // Compute CSS Variables & Section-level Inline Styles for customizable branding
  const customColors = section.customColors;
  const sectionRootStyle: React.CSSProperties = {
    ...(runtime.page.document.style||rawSection.surface||rawSection.brandPalette?{color:customColors?.titleColor}:{}),
    ...(customColors?.eyebrowColor ? { '--sec-eyebrow': customColors.eyebrowColor } : {}),
    ...(customColors?.titleColor ? { '--sec-title': customColors.titleColor } : {}),
    ...(customColors?.contentColor ? { '--sec-desc': customColors.contentColor } : {}),
    ...(customColors?.primaryBtnBg ? { '--sec-btn-bg': customColors.primaryBtnBg } : {}),
    ...(customColors?.primaryBtnText ? { '--sec-btn-text': customColors.primaryBtnText } : {}),
    ...(customColors?.secondaryBtnBg ? { '--sec-secbtn-bg': customColors.secondaryBtnBg } : {}),
    ...(customColors?.secondaryBtnText ? { '--sec-secbtn-text': customColors.secondaryBtnText } : {}),
    ...(section.backgroundColor === 'custom' && customColors?.customBgColor
      ? { background: customColors.customBgColor }
      : {})
  } as React.CSSProperties;

  // Helper getters for direct style props with automatic fallback
  const getEyebrowStyle = (extra?: React.CSSProperties): React.CSSProperties => ({
    ...(customColors?.eyebrowColor ? { color: customColors.eyebrowColor } : {}),
    ...extra
  });

  const getTitleStyle = (extra?: React.CSSProperties): React.CSSProperties => ({
    ...(customColors?.titleColor ? { color: customColors.titleColor } : {}),
    ...extra
  });

  const getContentStyle = (extra?: React.CSSProperties): React.CSSProperties => ({
    ...(customColors?.contentColor ? { color: customColors.contentColor } : {}),
    ...extra
  });

  const getPrimaryBtnStyle = (extra?: React.CSSProperties): React.CSSProperties => ({
    ...(customColors?.primaryBtnBg ? { backgroundColor: customColors.primaryBtnBg } : {}),
    ...(customColors?.primaryBtnText ? { color: customColors.primaryBtnText } : {}),
    ...extra
  });

  const getSecondaryBtnStyle = (extra?: React.CSSProperties): React.CSSProperties => ({
    ...(customColors?.secondaryBtnBg ? { backgroundColor: customColors.secondaryBtnBg } : {}),
    ...(customColors?.secondaryBtnText ? { color: customColors.secondaryBtnText } : {}),
    ...extra
  });

  // Dynamic grid responsive class helper that works both in actual mobile views and the in-editor simulator
  const getGridColsClass = (desktopCols: number = 4) => {
    if (viewport === 'mobile') {
      return 'grid-cols-1';
    }
    if (viewport === 'tablet') {
      return desktopCols >= 3 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-2';
    }
    // Desktop default
    if (desktopCols === 2) return 'grid-cols-1 md:grid-cols-2';
    if (desktopCols === 3) return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3';
    if (desktopCols === 5) return 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5';
    if (desktopCols === 6) return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6';
    return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4';
  };

  const renderIcon = (iconName?: string) => <SectionIcon name={iconName}/>;

  // 1. Hero Banner
  const renderHeroBanner = () => {
    if(['editorial-gradient','industrial-overlay'].includes(section.heroBannerPreset??''))return <EditorialGradientHero section={section} editing={isEditingMode} isPageHeading={runtime.page.document.sections.find(item=>item.isVisible!==false)?.id===section.id}/>;
    const overlay = section.overlayOpacity ?? 50;
    return (
      <div className="relative min-h-[480px] sm:min-h-[580px] flex items-center justify-center overflow-hidden">
        {/* Background Image/Media */}
        {section.mediaUrl && (
          <MotionMedia className="absolute inset-0 w-full h-full overflow-hidden"><ScopedImage section={section} targetId="mediaUrl"
            src={section.mediaUrl}
            alt={section.title}
            className="absolute inset-0 w-full h-full object-cover"
            referrerPolicy="no-referrer"
          /></MotionMedia>
        )}
        {/* Overlay */}
        <div
          className="absolute inset-0 bg-black"
          style={{ opacity: overlay / 100 }}
        />

        {/* Content */}
        <div data-cms-hero-safe className={`relative z-10 w-full py-20 ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] mx-auto px-6 @min-[640px]:px-10 @min-[1024px]:px-16':''} ${
          section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
        }`}>
          {section.subtitle && (
            <MotionItem><span data-cms-text="eyebrow"
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="inline-block px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-widest bg-white/10 text-white backdrop-blur-md mb-4 border border-white/20"
            >
              {section.subtitle}
            </span></MotionItem>
          )}
          <MotionItem><h1 data-cms-text="title"
            style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
            className="text-3xl sm:text-5xl md:text-6xl font-black text-white tracking-tight leading-tight mb-6"
          >
            {section.title}
          </h1></MotionItem>
          {section.content && (
            <MotionItem><p data-cms-text="content"
              style={{...(getContentStyle()),...typographyStyle(section,'content')}}
              className="text-base sm:text-lg text-neutral-200 leading-relaxed mb-8 max-w-2xl mx-auto"
            >
              {section.content}
            </p></MotionItem>
          )}

          <div className={`flex flex-wrap items-center gap-4 ${
            section.textAlignment === 'left' ? 'justify-start' : section.textAlignment === 'right' ? 'justify-end' : 'justify-center'
          }`}>
            {section.buttonText && (
              <MotionButton><a data-cms-action="button"
                href={section.buttonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getPrimaryBtnStyle()}
                className="px-7 py-3.5 rounded-xl font-bold text-sm bg-indigo-600 hover:opacity-90 text-white shadow-xl shadow-indigo-600/30 transition-all duration-200 transform hover:-translate-y-0.5"
              >
                {section.buttonText}
              </a></MotionButton>
            )}
            {section.secondaryButtonText && (
              <MotionButton><a data-cms-action="button"
                href={section.secondaryButtonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getSecondaryBtnStyle()}
                className="px-7 py-3.5 rounded-xl font-bold text-sm bg-white/10 hover:bg-white/20 text-white backdrop-blur-md border border-white/20 transition-all duration-200"
              >
                {section.secondaryButtonText}
              </a></MotionButton>
            )}
          </div>
        </div>
      </div>
    );
  };

  // 2. Split Hero
  const renderSplitHero = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
        {/* Text Left */}
        <div className="lg:col-span-6 space-y-6">
          {section.subtitle && (
            <MotionItem><span data-cms-text="eyebrow"
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="text-xs font-bold uppercase tracking-widest text-indigo-400 block"
            >
              {section.subtitle}
            </span></MotionItem>
          )}
          <MotionItem><h1 data-cms-text="title"
            style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
            className="text-3xl sm:text-5xl font-black tracking-tight leading-tight text-white"
          >
            {section.title}
          </h1></MotionItem>
          {section.content && (
            <MotionItem><p data-cms-text="content"
              style={{...(getContentStyle()),...typographyStyle(section,'content')}}
              className="text-base text-neutral-300 leading-relaxed"
            >
              {section.content}
            </p></MotionItem>
          )}

          <div className="flex flex-wrap items-center gap-4 pt-2">
            {section.buttonText && (
              <MotionButton><a data-cms-action="button"
                href={section.buttonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getPrimaryBtnStyle()}
                className="px-6 py-3 rounded-xl font-bold text-sm bg-indigo-600 hover:opacity-90 text-white shadow-lg shadow-indigo-600/30 transition-all"
              >
                {section.buttonText}
              </a></MotionButton>
            )}
            {section.secondaryButtonText && (
              <MotionButton><a data-cms-action="button"
                href={section.secondaryButtonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getSecondaryBtnStyle()}
                className="px-6 py-3 rounded-xl font-bold text-sm bg-white/10 hover:bg-white/20 text-white border border-white/10 transition-all"
              >
                {section.secondaryButtonText}
              </a></MotionButton>
            )}
          </div>
        </div>

        {/* Media Right */}
        <div className="lg:col-span-6 relative">
          <MotionMedia style={imageSlotFrameStyle(section,'mediaUrl')} className="relative aspect-4/3 rounded-3xl overflow-hidden shadow-2xl border border-white/10 bg-neutral-900">
            <ScopedImage section={section} targetId="mediaUrl"
              src={section.mediaUrl || 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1000&auto=format&fit=crop&q=80'}
              alt={section.title}
              className="w-full h-full object-cover"
              referrerPolicy="no-referrer"
            />
            {/* Dynamic Floating card customizable via Section Inspector */}
            <MotionItem className="absolute bottom-6 left-6 right-6 p-4.5 rounded-2xl bg-neutral-950/85 backdrop-blur-md border border-white/15 text-white shadow-xl flex items-center justify-between gap-3">
              <div className="min-w-0">
                <span data-cms-text="eyebrow"
                  style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
                  className="text-[10px] font-bold uppercase tracking-wider text-indigo-400 block truncate"
                >
                  {section.cardBadge || 'Flagship Model'}
                </span>
                <p className="text-xs sm:text-sm font-bold truncate">
                  {section.cardTitle || 'Aurora Titanium Series X'}
                </p>
                <p className="text-[11px] text-neutral-400 truncate">
                  {section.cardSubtitle || '50mm Beryllium • LDAC 24/96'}
                </p>
              </div>
              <div className="text-right shrink-0">
                <span className="text-base sm:text-lg font-black text-white block">
                  {listingMoney(Number(section.cardPrice ?? 499))}
                </span>
                <span className="text-[10px] text-emerald-400 font-semibold block">
                  {section.cardExtra || 'Free Express DHL'}
                </span>
              </div>
            </MotionItem>
          </MotionMedia>
        </div>
      </div>
    </div>
  );

  // 3. Full Banner Background Slider (Hero Slider)
  const heroSlides = section.items && section.items.length > 0 ? section.items : [
    {
      id: 'slide-1',
      title: section.title || 'Experience Pure Acoustic Fidelity',
      subtitle: section.subtitle || 'NEW TITANIUM FLAGSHIP SERIES',
      description: section.content || 'Custom 50mm beryllium transducers engineered with active damping for zero-distortion listening.',
      imageUrl: section.mediaUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&auto=format&fit=crop&q=80',
      badge: 'Exclusive Launch',
      buttonText: section.buttonText || 'Explore Collection',
      linkUrl: section.buttonLink || '/collections/all',
      secondaryButtonText: section.secondaryButtonText || 'Watch Atelier Film',
      secondaryButtonLink: section.secondaryButtonLink || '#film'
    },
    {
      id: 'slide-2',
      title: 'Hybrid Active Noise Cancellation 4.0',
      subtitle: 'STUDIO-GRADE ISOLATION',
      description: 'Adaptive algorithmic cancellation eliminating 99.6% of ambient frequency interference for uninterrupted immersion.',
      imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1600&auto=format&fit=crop&q=80',
      badge: 'Adaptive ANC',
      buttonText: 'Discover ANC Series',
      linkUrl: '/products/aurora-anc',
      secondaryButtonText: 'Compare Models',
      secondaryButtonLink: '#compare'
    },
    {
      id: 'slide-3',
      title: 'Handcrafted Scandinavian Craftsmanship',
      subtitle: 'ATELIER LEATHER & TITANIUM',
      description: 'Aerospace-grade structural chassis paired with supple top-grain leather earcups for fatigue-free luxury.',
      imageUrl: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=1600&auto=format&fit=crop&q=80',
      badge: 'Artisan Heritage',
      buttonText: 'Explore the Atelier',
      linkUrl: '/pages/craftsmanship',
      secondaryButtonText: 'Find Nearest Lounge',
      secondaryButtonLink: '/pages/store-locator'
    }
  ];

  // Auto-play timer for Hero Slider
  useEffect(() => {
    if (section.type !== 'hero_slider') return;
    if (['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??'') && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    if (section.sliderAutoplay === false || isEditingMode) return;
    if (isHeroHovered) return;
    if (heroSlides.length <= 1) return;

    const intervalSec = section.sliderInterval || 5;
    const timer = setInterval(() => {
      setHeroSlideIdx(prev => (prev + 1) % heroSlides.length);
    }, intervalSec * 1000);

    return () => clearInterval(timer);
  }, [section.type, section.sliderPreset, section.sliderAutoplay, section.sliderInterval, isHeroHovered, heroSlides.length, isEditingMode]);

  useEffect(()=>{
    if(section.type==='hero_slider' && selectedImage) {
      const index=section.items?.findIndex(item=>`item:${item.id}:imageUrl`===selectedImage)??-1;
      if(index>=0)setHeroSlideIdx(index);
    }
  },[section.type,section.items,selectedImage]);

  const renderHeroSlider = () => {
    if(['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??''))return <EditorialOverlaySlider section={section} index={heroSlideIdx} onSelect={setHeroSlideIdx} onPause={setIsHeroHovered} isEditingMode={isEditingMode}/>;
    const currentSlide = heroSlides[heroSlideIdx % heroSlides.length] || heroSlides[0];
    const overlay = section.overlayOpacity ?? 55;
    const heightClass = section.sliderHeight === 'panoramic'
      ? 'min-h-[320px] lg:min-h-[280px]'
      : section.sliderHeight === 'fullscreen'
      ? 'min-h-[85vh] lg:min-h-[92vh]'
      : section.sliderHeight === 'compact'
      ? 'min-h-[460px]'
      : section.sliderHeight === 'spacious'
      ? 'min-h-[720px]'
      : 'min-h-[580px] lg:min-h-[660px]';

    const handlePrev = (e?: React.MouseEvent) => {
      if (e) e.stopPropagation();
      setHeroSlideIdx(prev => (prev - 1 + heroSlides.length) % heroSlides.length);
    };

    const handleNext = (e?: React.MouseEvent) => {
      if (e) e.stopPropagation();
      setHeroSlideIdx(prev => (prev + 1) % heroSlides.length);
    };

    return (
      <div
        className={`relative w-full ${heightClass} flex items-center justify-center overflow-hidden group select-none`}
        onMouseEnter={() => setIsHeroHovered(true)}
        onMouseLeave={() => setIsHeroHovered(false)}
      >
        {/* Background Slides with Cross-fade */}
        {heroSlides.map((slide, idx) => {
          const isActive = idx === (heroSlideIdx % heroSlides.length);
          const bgImg = slide.imageUrl || slide.videoUrl || section.mediaUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&auto=format&fit=crop&q=80';
          return (
            <div
              key={slide.id || idx}
              className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
                isActive ? 'opacity-100 scale-100 z-1' : 'opacity-0 scale-105 pointer-events-none z-0'
              }`}
              style={{ transitionProperty: 'opacity, transform' }}
            >
              {slide.videoUrl?<video src={slide.videoUrl} autoPlay={isActive} muted loop playsInline className="w-full h-full object-cover" />:<ScopedImage section={section} targetId={`item:${slide.id}:imageUrl`}
                src={bgImg}
                alt={slide.title || 'Hero background'}
                className="w-full h-full object-cover"
                referrerPolicy="no-referrer"
              />}
            </div>
          );
        })}

        {/* Ambient Dark Gradient & Custom Overlay */}
        <div
          className="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/60 to-black/30 z-2"
          style={{ opacity: overlay / 100 }}
        />

        {/* Slide Content Layer */}
        <div data-cms-hero-safe className={`relative z-10 w-full py-20 ${section.containerMode==='full-bleed'?'max-w-[var(--cms-page-width)] mx-auto px-6 @min-[640px]:px-10 @min-[1024px]:px-16':''} w-full transition-all duration-500 ${
          section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
        }`}>
          {/* Eyebrow Tag / Badge */}
          {(currentSlide.badge || currentSlide.subtitle || section.subtitle) && (
            <MotionItem className={`flex items-center gap-2 mb-4 ${
              section.textAlignment === 'left' ? 'justify-start' : section.textAlignment === 'right' ? 'justify-end' : 'justify-center'
            }`}>
              <span data-cms-text="eyebrow"
                style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
                className="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-widest bg-white/10 hover:bg-white/15 text-white backdrop-blur-md border border-white/20 shadow-lg transition-all"
              >
                <Sparkles data-cms-text="eyebrow" className="w-3.5 h-3.5" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} />
                {currentSlide.badge || currentSlide.subtitle || section.subtitle}
              </span>
            </MotionItem>
          )}

          {/* Primary Headline */}
          <MotionItem><h1 data-cms-text="title" data-cms-item={currentSlide.id}
            style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
            className="text-3xl sm:text-5xl md:text-6xl lg:text-7xl font-black text-white tracking-tight leading-[1.1] mb-5 drop-shadow-md"
          >
            {currentSlide.title || section.title}
          </h1></MotionItem>

          {/* Editorial Content / Description */}
          {(currentSlide.description || currentSlide.subtitle || section.content) && (
            <MotionItem><p data-cms-text="content" data-cms-item={currentSlide.id}
              style={{...(getContentStyle()),...typographyStyle(section,'content')}}
              className={`text-sm sm:text-base md:text-lg text-neutral-200 leading-relaxed mb-8 max-w-2xl drop-shadow-sm ${
                section.textAlignment === 'left' ? 'mr-auto' : section.textAlignment === 'right' ? 'ml-auto' : 'mx-auto'
              }`}
            >
              {currentSlide.description || section.content}
            </p></MotionItem>
          )}

          {/* Action CTA Buttons */}
          <div className={`flex flex-wrap items-center gap-4 pt-2 ${
            section.textAlignment === 'left' ? 'justify-start' : section.textAlignment === 'right' ? 'justify-end' : 'justify-center'
          }`}>
            {(currentSlide.buttonText || section.buttonText) && (
              <MotionButton><a data-cms-action="button"
                href={currentSlide.linkUrl || section.buttonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getPrimaryBtnStyle()}
                className="px-7 py-3.5 rounded-xl font-bold text-sm bg-indigo-600 hover:opacity-90 text-white shadow-xl shadow-indigo-600/30 transition-all duration-200 transform hover:-translate-y-0.5 inline-flex items-center gap-2 cursor-pointer"
              >
                <span>{currentSlide.buttonText || section.buttonText}</span>
                <ArrowRight className="w-4 h-4" />
              </a></MotionButton>
            )}
            {(currentSlide.secondaryButtonText || section.secondaryButtonText) && (
              <MotionButton><a data-cms-action="button"
                href={currentSlide.secondaryButtonLink || section.secondaryButtonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getSecondaryBtnStyle()}
                className="px-7 py-3.5 rounded-xl font-bold text-sm bg-white/10 hover:bg-white/20 text-white backdrop-blur-md border border-white/20 transition-all duration-200 cursor-pointer"
              >
                {currentSlide.secondaryButtonText || section.secondaryButtonText}
              </a></MotionButton>
            )}
          </div>
        </div>

        {/* Carousel Navigation Arrows */}
        {heroSlides.length > 1 && (
          <>
            <button
              onClick={handlePrev}
              aria-label="Previous Slide"
              className="absolute left-4 sm:left-8 top-1/2 -translate-y-1/2 z-20 w-11 h-11 sm:w-13 sm:h-13 rounded-2xl bg-black/40 hover:bg-white/25 text-white/90 hover:text-white backdrop-blur-md border border-white/15 shadow-2xl flex items-center justify-center transition-all duration-200 hover:scale-105 active:scale-95 cursor-pointer opacity-80 group-hover:opacity-100"
            >
              <ChevronLeft className="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
            <button
              onClick={handleNext}
              aria-label="Next Slide"
              className="absolute right-4 sm:right-8 top-1/2 -translate-y-1/2 z-20 w-11 h-11 sm:w-13 sm:h-13 rounded-2xl bg-black/40 hover:bg-white/25 text-white/90 hover:text-white backdrop-blur-md border border-white/15 shadow-2xl flex items-center justify-center transition-all duration-200 hover:scale-105 active:scale-95 cursor-pointer opacity-80 group-hover:opacity-100"
            >
              <ChevronRight className="w-5 h-5 sm:w-6 sm:h-6" />
            </button>
          </>
        )}

        {/* Bottom Slide Indicators / Pagination Pills */}
        {heroSlides.length > 1 && (
          <div className="absolute bottom-6 left-0 right-0 z-20 flex items-center justify-center gap-2 px-6">
            <div className="p-1.5 rounded-full bg-black/50 backdrop-blur-md border border-white/15 flex items-center gap-1.5 shadow-2xl">
              {heroSlides.map((slide, idx) => {
                const isActive = idx === (heroSlideIdx % heroSlides.length);
                return (
                  <button
                    key={slide.id || idx}
                    onClick={(e) => {
                      e.stopPropagation();
                      setHeroSlideIdx(idx);
                    }}
                    className={`h-2.5 transition-all duration-300 rounded-full cursor-pointer ${
                      isActive
                        ? 'w-8 bg-indigo-500 ring-2 ring-indigo-400/40 shadow-xs'
                        : 'w-2.5 bg-white/40 hover:bg-white/70'
                    }`}
                    style={isActive && customColors?.primaryBtnBg ? { backgroundColor: customColors.primaryBtnBg } : {}}
                    title={slide.title || `Slide ${idx + 1}`}
                  />
                );
              })}
            </div>

            {/* Quick Slide Index Badge */}
            <span className="hidden sm:inline-flex text-[11px] font-mono font-bold text-white/70 px-2.5 py-1 rounded-full bg-black/40 backdrop-blur-md border border-white/10">
              {String((heroSlideIdx % heroSlides.length) + 1).padStart(2, '0')} / {String(heroSlides.length).padStart(2, '0')}
            </span>
          </div>
        )}
      </div>
    );
  };

  // 3. Announcement Marquee Bar
  const renderAnnouncementBar = () => {
    const speedClass = section.tickerSpeed === 'fast' 
      ? 'animate-marquee-fast' 
      : section.tickerSpeed === 'slow' 
      ? 'animate-marquee-slow' 
      : 'animate-marquee';

    const items = section.items && section.items.length > 0 ? section.items : [
      { id: 'ann-1', title: 'COMPLIMENTARY DHL EXPRESS DELIVERY ON ORDERS OVER $200' },
      { id: 'ann-2', title: 'HANDCRAFTED IN STOCKHOLM • 3-YEAR ACOUSTIC GUARANTEE' },
      { id: 'ann-3', title: 'NEW TITANIUM DRIVER RELEASE NOW LIVE FOR PRE-ORDER' },
      { id: 'ann-4', title: 'CUSTOM LASER ENGRAVING AVAILABLE AT CHECKOUT' }
    ];

    return (
      <div
        style={getPrimaryBtnStyle({ borderBottom: '1px solid rgba(255,255,255,0.1)' })}
        className="w-full bg-indigo-600 text-white text-xs font-bold uppercase tracking-widest py-2.5 overflow-hidden relative shadow-md"
      >
        <div className={`flex whitespace-nowrap gap-12 ${speedClass}`}>
          {[...items, ...items, ...items].map((it, idx) => (
            <div key={idx} className="flex items-center gap-3 shrink-0">
              <Sparkles className="w-3.5 h-3.5 opacity-80" />
              <span data-cms-text="itemTitle" data-cms-item={it.id} style={typographyStyle(section,'itemTitle')}>{it.title}</span>
              <span className="w-1.5 h-1.5 rounded-full bg-white/40" />
            </div>
          ))}
        </div>
      </div>
    );
  };

  // 4. Rich Editorial Text Block
  const renderRichText = () => (
    <div className={`w-full ${getPaddingClass()} ${
      section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
    }`}>
      {section.subtitle && (
        <MotionItem><span data-cms-text="eyebrow"
          style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
          className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-3"
        >
          {section.subtitle}
        </span></MotionItem>
      )}
      <MotionItem><h2 data-cms-text="title"
        style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
        className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-6 whitespace-pre-line"
      >
        {section.title}
      </h2></MotionItem>
      {section.content && (
        <MotionItem><div data-cms-text="content"
          style={{...(getContentStyle()),...typographyStyle(section,'content')}}
          className={`text-sm sm:text-base opacity-85 leading-relaxed space-y-4 max-w-3xl ${section.textAlignment === 'left' ? 'mr-auto' : section.textAlignment === 'right' ? 'ml-auto' : 'mx-auto'}`}
        >
          <p className="whitespace-pre-line">{section.content}</p>
        </div></MotionItem>
      )}
      {section.quote && (
        <MotionItem><blockquote data-cms-text="content"
          style={{...(getContentStyle()),...typographyStyle(section,'content')}}
          className="my-8 p-6 rounded-2xl bg-white/5 border-l-4 border-indigo-500 text-base sm:text-lg italic font-serif opacity-90 text-left"
        >
          "{section.quote}"
          {section.authorName && (
            <footer className="mt-3 text-xs not-italic font-sans font-bold opacity-70">
              — {section.authorName}{section.authorRole ? `, ${section.authorRole}` : ''}
            </footer>
          )}
        </blockquote></MotionItem>
      )}
    </div>
  );

  // 5. Media + Text Split
  const renderMediaTextSplit = () => {
    if(section.layoutPreset?.startsWith('saas-'))return <SaasMedia section={section} editing={isEditingMode}/>;
    if(section.layoutPreset==='tech-feature')return <TechFeature section={section} editing={isEditingMode}/>;
    if(section.layoutPreset==='artisan-story')return <ArtisanStory section={section} editing={isEditingMode}/>;
    if(section.layoutPreset==='kids-discovery')return <KidsDiscovery section={section}/>;
    if(['flush-split','producer-story'].includes(section.layoutPreset??''))return <FlushSplit section={section} editing={isEditingMode}/>;
    if(['editorial-story-cards','editorial-journal-rows'].includes(section.layoutPreset??''))return <EditorialStoryCards section={section} editing={isEditingMode}/>;
    if (section.layoutPreset === 'editorial-cover') return <EditorialCover section={section}/>;
    if ((section.layoutPreset === 'editorial-story'||section.layoutPreset === 'story-with-steps')) return <EditorialStory section={section} isEditingMode={isEditingMode} />;
    if (section.layoutPreset === 'editorial-hero') {
      return <EditorialHero section={section} isEditingMode={isEditingMode} isPageHeading={runtime.page.document.sections.find(item=>item.isVisible !== false)?.id === section.id} />;
    }
    const isMediaRight = section.mediaPosition !== 'left';
    return (
      <div className={`w-full ${getPaddingClass()}`}>
        <div className={`grid grid-cols-1 ${section.mediaContainerMode==='full-bleed'?'':'lg:grid-cols-12'} gap-8 lg:gap-12 items-center`}>
          {/* Text Column */}
          <div className={`space-y-6 ${section.mediaContainerMode==='full-bleed'?'order-1':isMediaRight ? 'lg:col-span-6 lg:order-1' : 'lg:col-span-6 lg:order-2'}`}>
            {section.subtitle && (
              <MotionItem><span data-cms-text="eyebrow"
                style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
                className="text-xs font-bold uppercase tracking-widest text-indigo-400 block"
              >
                {section.subtitle}
              </span></MotionItem>
            )}
            <MotionItem><h2 data-cms-text="title"
              style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
              className="text-2xl sm:text-4xl font-extrabold tracking-tight"
            >
              {section.title}
            </h2></MotionItem>
            {section.content && (
              <MotionItem><p data-cms-text="content"
                style={{...(getContentStyle()),...typographyStyle(section,'content')}}
                className="text-sm sm:text-base opacity-80 leading-relaxed"
              >
                {section.content}
              </p></MotionItem>
            )}

            {section.items && section.items.length > 0 && (
              <div className="space-y-3 pt-2">
                {section.items.map((it, idx) => (
                  <MotionItem key={it.id || idx} className="flex items-start gap-3">
                    <div data-cms-text="eyebrow"
                      style={{...(customColors?.eyebrowColor ? { color: customColors.eyebrowColor } : {}),...typographyStyle(section,'eyebrow')}}
                      className="p-1 rounded-lg bg-indigo-500/20 text-indigo-400 mt-0.5"
                    >
                      <CheckCircle2 className="w-4 h-4" />
                    </div>
                    <div>
                      <h4 data-cms-text="itemTitle" data-cms-item={it.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-xs sm:text-sm font-bold text-white">{it.title}</h4>
                      <p data-cms-text="itemContent" data-cms-item={it.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs text-neutral-400">{it.description}</p>
                    </div>
                  </MotionItem>
                ))}
              </div>
            )}

            {section.buttonText && (
              <div className="pt-2">
                <MotionButton><a data-cms-action="button"
                  href={section.buttonLink || '#'}
                  onClick={e => isEditingMode && e.preventDefault()}
                  style={getPrimaryBtnStyle()}
                  className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-xs bg-indigo-600 hover:opacity-90 text-white shadow-md transition-all"
                >
                  <span>{section.buttonText}</span>
                  <ArrowRight className="w-4 h-4" />
                </a></MotionButton>
              </div>
            )}
          </div>

          {/* Media Column */}
          <MediaWidthRegion section={section} className={`relative ${section.mediaContainerMode==='full-bleed'?'order-2':isMediaRight ? 'lg:col-span-6 lg:order-2' : 'lg:col-span-6 lg:order-1'}`}>
            <MotionMedia style={imageSlotFrameStyle(section,'mediaUrl')} className="aspect-4/3 rounded-3xl overflow-hidden border border-white/10 shadow-2xl bg-neutral-900">
              <ScopedImage section={section} targetId="mediaUrl"
                src={section.mediaUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1000&auto=format&fit=crop&q=80'}
                alt={section.title}
                className="w-full h-full object-cover"
                referrerPolicy="no-referrer"
              />
            </MotionMedia>
          </MediaWidthRegion>
        </div>
      </div>
    );
  };

  // 6. Timeline Narrative Story
  const renderTimelineStory = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-12 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <div className="relative border-l-2 border-indigo-500/30 ml-4 sm:ml-32 space-y-12">
        {section.items?.map((item, idx) => (
          <MotionItem key={item.id || idx} className="relative pl-6 sm:pl-8 group">
            {/* Timeline Dot */}
            <div
              style={customColors?.primaryBtnBg ? { borderColor: customColors.primaryBtnBg } : {}}
              className="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-neutral-900 border-2 border-indigo-500 group-hover:bg-indigo-500 transition-colors"
            />

            {/* Date Tag */}
            <div data-cms-text="eyebrow" data-cms-item={item.id}
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="sm:absolute sm:-left-32 sm:top-1 text-xs font-bold text-indigo-400 tracking-wider mb-1 sm:mb-0"
            >
              {item.date || `Phase ${idx + 1}`}
            </div>

            <div className="p-5 rounded-2xl bg-white/5 border border-white/10 hover:border-white/20 transition-all">
              <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-base font-bold text-white mb-2">{item.title}</h3>
              <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs sm:text-sm text-neutral-300 leading-relaxed">{item.description}</p>
            </div>
          </MotionItem>
        ))}
      </div>
    </div>
  );

  // 7. Stats & Numeric Milestones
  const renderStatsCounter = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`grid ${getGridColsClass(section.columnsCount || 4)} gap-6 text-center`}>
        {section.items?.map((item, idx) => (
          <MotionCard
            key={item.id || idx}
            className="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-indigo-500/40 transition-all flex flex-col justify-center min-w-0"
          >
            <div data-cms-text="itemTitle" data-cms-item={item.id}
              style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}}
              className="text-3xl sm:text-5xl font-black tracking-tight text-white mb-2 truncate"
            >
              {item.value || '99%'}
            </div>
            <div data-cms-text="eyebrow" data-cms-item={item.id}
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="text-xs font-bold uppercase tracking-wider text-indigo-400 mb-1 truncate"
            >
              {item.title}
            </div>
            <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs text-neutral-400 line-clamp-2">{item.description}</p>
          </MotionCard>
        ))}
      </div>
    </div>
  );

  // 8. Product Grid Section
  const renderProductGrid = () => {
    const featuredProds = runtimeSection.productIds?.products ?? [];

    return (
      <div className={`w-full ${getPaddingClass()}`}>
        <div className={`mb-10 ${
          section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
        }`}>
          {section.subtitle && (
            <MotionItem><span data-cms-text="eyebrow"
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
            >
              {section.subtitle}
            </span></MotionItem>
          )}
          <MotionItem><h2 data-cms-text="title"
            style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
            className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
          >
            {section.title}
          </h2></MotionItem>
          {section.content && (
            <MotionItem><p data-cms-text="content"
              style={{...(getContentStyle()),...typographyStyle(section,'content')}}
              className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
            >
              {section.content}
            </p></MotionItem>
          )}
        </div>

        <div className={`grid ${getGridColsClass(section.columnsCount || 4)} gap-6`}>
          {featuredProds.map(prod => (
            <MotionCard data-cms-source=""
              key={prod.id}
              className="group rounded-2xl bg-white/5 border border-white/10 hover:border-indigo-500/50 transition-all duration-300 overflow-hidden flex flex-col justify-between"
            >
              <div className="relative aspect-square bg-neutral-950 overflow-hidden">
                <img
                  src={prod.images[0]}
                  alt={prod.title}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  referrerPolicy="no-referrer"
                />
                {prod.tags?.includes('Best Seller') && (
                  <span className="absolute top-3 left-3 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-indigo-600 text-white shadow-md">
                    Best Seller
                  </span>
                )}
                {isOnlinePurchase(prod) && prod.baseCompareAtPrice && (
                  <span className="absolute top-3 right-3 px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500 text-white shadow-md">
                    Sale
                  </span>
                )}
              </div>

              <div className="p-5 flex-1 flex flex-col justify-between">
                <div>
                  <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-400 block mb-1">
                    {prod.brand}
                  </span>
                  <h3 className="text-base font-bold text-white group-hover:text-indigo-300 transition-colors line-clamp-1">
                    {prod.title}
                  </h3>
                  <p className="text-xs text-neutral-400 mt-1 line-clamp-2">
                    {prod.summary}
                  </p>
                </div>

                <div className="mt-4 pt-4 border-t border-white/10 flex items-center justify-between">
                  <div>
                    <span className="text-lg font-black text-white">
                      {purchasePriceLabel(prod, Number(prod.basePrice), listingMoney)}
                    </span>
                    {isOnlinePurchase(prod) && prod.baseCompareAtPrice && (
                      <span className="text-xs text-neutral-500 line-through ml-2">
                        {listingMoney(Number(prod.baseCompareAtPrice))}
                      </span>
                    )}
                  </div>
                  <a
                    href={`/products/${prod.slug}`}
                    onClick={e => isEditingMode && e.preventDefault()}
                    className="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md transition-colors"
                  >
                    View Details
                  </a>
                </div>
              </div>
            </MotionCard>
          ))}
        </div>
      </div>
    );
  };

  // 9. Collection Tabs Showcase
  const renderProductListing = () => {
    const displayedItems = listingItems;
    // Nordic Clean persists a white canvas. Use PLP's light tokens independently
    // of the Admin shell theme, keeping the original source carousel geometry.
    const lightCards = section.backgroundColor === 'bg-white';

    // Visible cards per view calculation based on viewport and columnsCount
    const desktopCols = section.columnsCount === 4 ? 4 : 3;
    const cardsPerView = viewport === 'mobile' ? 1 : viewport === 'tablet' ? 2 : desktopCols;
    const maxIdx = Math.max(0, displayedItems.length - cardsPerView);

    // Navigation handlers
    const handlePrev = (e?: React.MouseEvent) => {
      e?.stopPropagation();
      setProductListingIdx(prev => (prev <= 0 ? maxIdx : prev - 1));
    };

    const handleNext = (e?: React.MouseEvent) => {
      e?.stopPropagation();
      setProductListingIdx(prev => (prev >= maxIdx ? 0 : prev + 1));
    };

    // Helper to abbreviate variant names for chips (e.g., 'Obsidian' -> 'Obs', 'Rich Walnut' -> 'Ric')
    const getVariantAbbr = (val: string) => {
      if (!val) return 'Std';
      const clean = val.trim();
      return clean.length <= 3 ? clean : clean.substring(0, 3);
    };

    const isNavVisible = section.showNavArrows !== false && displayedItems.length > cardsPerView;

    return (
      <div
        className={`w-full ${getPaddingClass()}`}
        onMouseEnter={() => setIsListingHovered(true)}
        onMouseLeave={() => setIsListingHovered(false)}
      >
        {/* Section Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
          <div className={`${section.textAlignment === 'center' ? 'text-center md:text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-left'}`}>
            {section.subtitle && (
              <MotionItem><span data-cms-text="eyebrow"
                style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
                className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
              >
                {section.subtitle}
              </span></MotionItem>
            )}
            <MotionItem><h2 data-cms-text="title"
              style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
              className="text-2xl sm:text-4xl font-extrabold tracking-tight"
            >
              {section.title || 'Product Listing'}
            </h2></MotionItem>
            {section.content && (
              <MotionItem><p data-cms-text="content"
                style={{...(getContentStyle()),...typographyStyle(section,'content')}}
                className="text-sm sm:text-base opacity-80 mt-2 max-w-2xl"
              >
                {section.content}
              </p></MotionItem>
            )}
          </div>

          {/* Top-right Navigation Controls */}
          {isNavVisible && (
            <MotionItem className="flex items-center gap-2 self-end shrink-0">
              <button
                type="button"
                onClick={handlePrev}
                aria-label="Previous Products"
                className="w-10 h-10 rounded-full bg-neutral-900 border border-neutral-800 hover:border-indigo-500 text-white flex items-center justify-center transition-all hover:bg-neutral-800 active:scale-95 shadow-md cursor-pointer"
              >
                <ChevronLeft className="w-5 h-5" />
              </button>
              <button
                type="button"
                onClick={handleNext}
                aria-label="Next Products"
                className="w-10 h-10 rounded-full bg-neutral-900 border border-neutral-800 hover:border-indigo-500 text-white flex items-center justify-center transition-all hover:bg-neutral-800 active:scale-95 shadow-md cursor-pointer"
              >
                <ChevronRight className="w-5 h-5" />
              </button>
            </MotionItem>
          )}
        </div>

        {/* Carousel Container with Side Navigation Overlay Buttons */}
        <div className="relative group/carousel">
          {/* Side Overlay Arrow - Left */}
          {isNavVisible && (
            <button
              type="button"
              onClick={handlePrev}
              aria-label="Previous Slide"
              className="hidden md:flex absolute -left-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-neutral-950/90 hover:bg-indigo-600 border border-neutral-800 hover:border-indigo-500 text-white items-center justify-center transition-all duration-300 shadow-xl backdrop-blur-md hover:scale-105 active:scale-95 cursor-pointer opacity-0 group-hover/carousel:opacity-100"
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
          )}

          {/* Side Overlay Arrow - Right */}
          {isNavVisible && (
            <button
              type="button"
              onClick={handleNext}
              aria-label="Next Slide"
              className="hidden md:flex absolute -right-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-neutral-950/90 hover:bg-indigo-600 border border-neutral-800 hover:border-indigo-500 text-white items-center justify-center transition-all duration-300 shadow-xl backdrop-blur-md hover:scale-105 active:scale-95 cursor-pointer opacity-0 group-hover/carousel:opacity-100"
            >
              <ChevronRight className="w-5 h-5" />
            </button>
          )}

          {/* Overflow Track */}
          <div className="overflow-hidden -mx-3 px-1 py-2">
            <div
              ref={listingTrackRef}
              data-product-listing-track
              className="flex transition-transform duration-500 ease-out"
              style={{
                transform: `translateX(-${(Math.min(productListingIdx, maxIdx) * 100) / cardsPerView}%)`
              }}
            >
              {displayedItems.map((prod, idx) => {
                // Calculate discount percent
                const commerce = prod.commerce;
                const variants = commerce?.variants ?? [];
                const selected = variants.find(v => v.id === listingVariantIds[prod.id]) ?? variants[0];
                const price = selected?.price ?? commerce?.price ?? prod.basePrice;
                const comparePrice = selected ? selected.compareAtPrice : commerce?.compareAtPrice;
                const productHref = runtime.context.mode === 'public' ? `/products/${encodeURIComponent(prod.slug)}` : undefined;
                const ImageLink = productHref ? 'a' : 'div';
                const singleVariant = variants.length === 1;
                const purchaseDisabled = runtime.context.mode !== 'public' || !commerce?.canChoose || (singleVariant && (!selected.enabled || selected.stock < 1));
                const hasDiscount = comparePrice != null && comparePrice > price && price >= 0;
                const discountPct = hasDiscount ? Math.round(((comparePrice! - price) / comparePrice!) * 100) : 0;

                // SKU format
                const skuCode = selected?.sku ?? commerce?.sku ?? '';

                // Extract variant option tags (e.g., Obs, Lun, +5)
                const rawOptions = variants.length > 1 ? variants.map(v => v.label) : [];
                const optionPills = rawOptions.map(getVariantAbbr);

                const visiblePills = optionPills.slice(0, 4);
                const overflowCount = Math.max(0, optionPills.length - 4);

                return (
                  <MotionCard data-cms-source=""
                    key={prod.id + '-' + idx}
                    className="shrink-0 px-3 transition-all duration-300"
                    style={{
                      width: `${100 / cardsPerView}%`
                    }}
                  >
                    <div className={`h-full rounded-2xl sm:rounded-3xl border transition-all duration-300 overflow-hidden flex flex-col justify-between group/card ${lightCards ? 'bg-white border-zinc-200 hover:border-zinc-300 shadow-sm hover:shadow-lg' : 'bg-[#121316] dark:bg-[#121316] border-neutral-800 hover:border-neutral-700 shadow-xl'}`}>
                      {/* 1. Edge-to-edge square product image, matching the existing PLP. */}
                      <ImageLink href={productHref} className="relative aspect-square w-full bg-white overflow-hidden rounded-t-2xl sm:rounded-t-3xl">
                        {/* Red Discount Pill (e.g. SAVE 13%, SAVE 15%, SAVE 17%) */}
                        {hasDiscount && <div className="absolute top-3.5 left-3.5 px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-black tracking-wider uppercase bg-[#ff3b30] text-white shadow-md z-10">
                          SAVE {discountPct}%
                        </div>}

                        {/* Centered Product Image */}
                        <img
                          src={(listingVariantIds[prod.id] && selected?.image) || prod.images[0] || '/images/image-not-found.png'}
                          alt={prod.title}
                          className="w-full h-full object-cover object-center group-hover/card:scale-105 transition-transform duration-500"
                          referrerPolicy="no-referrer"
                        />
                      </ImageLink>

                      {/* 2. Product Information Area */}
                      <div className={`p-5 flex-1 flex flex-col justify-between space-y-3 ${lightCards ? 'bg-white' : 'bg-[#121316]'}`}>
                        <div>
                          {/* Brand and SKU Header Row */}
                          <div className="flex items-center justify-between gap-2 mb-1.5">
                            <span className={`text-[10px] sm:text-[11px] font-bold uppercase tracking-widest truncate max-w-[55%] ${lightCards ? 'text-zinc-500' : 'text-neutral-400'}`}>
                              {prod.brand}
                            </span>
                            <span className={`text-[10px] sm:text-[11px] font-mono font-medium truncate max-w-[45%] ${lightCards ? 'text-emerald-600' : 'text-emerald-400 dark:text-emerald-400'}`}>
                              SKU: {skuCode}
                            </span>
                          </div>

                          {/* Bold Product Title */}
                          <a href={productHref}><h3 className={`text-base sm:text-lg font-bold transition-colors line-clamp-1 ${lightCards ? 'text-zinc-900 group-hover/card:text-amber-600' : 'text-white group-hover/card:text-indigo-300'}`}>
                            {prod.title}
                          </h3></a>

                          {/* Editorial Summary / Specs Subtitle */}
                          <p className={`text-xs line-clamp-2 mt-1 leading-relaxed min-h-[36px] ${lightCards ? 'text-zinc-500' : 'text-neutral-400'}`}>
                            {prod.summary}
                          </p>
                        </div>

                        {/* Variant Chips Row (Screenshot 3: Active amber chip + subtle gray chips) */}
                        <div className="flex items-center gap-1.5 flex-wrap pt-1">
                          {visiblePills.map((pill, pIdx) => (
                            <button
                              type="button"
                              aria-label={`Select ${variants[pIdx].label}`}
                              aria-pressed={selected?.id === variants[pIdx].id}
                              title={`${variants[pIdx].label}${variants[pIdx].stock < 1 ? ' (Out of Stock)' : ''}`}
                              disabled={runtime.context.mode !== 'public' || !variants[pIdx].enabled}
                              onClick={() => setListingVariantIds(current => ({...current,[prod.id]:variants[pIdx].id}))}
                              key={pIdx}
                              className={`px-2 py-0.5 rounded text-[10px] transition-colors ${
                                lightCards ? (selected?.id === variants[pIdx].id ? 'border border-amber-500 bg-amber-500/15 text-amber-600 font-bold' : 'border border-zinc-200 text-zinc-600 hover:border-zinc-400 font-medium') : selected?.id === variants[pIdx].id
                                  ? 'border border-amber-500/90 text-amber-400 bg-amber-500/10 font-bold'
                                  : 'border border-neutral-800 text-neutral-400 bg-neutral-800/60 font-medium'
                              }`}
                            >
                              {pill}
                            </button>
                          ))}
                          {overflowCount > 0 && (
                            <button type="button" disabled={runtime.context.mode !== 'public'} data-quick-add-trigger={runtime.context.mode === 'public' ? '' : undefined} data-quick-add-product-id={prod.id} data-quick-add-variant-id={selected?.id} aria-label={`View all options for ${prod.title}`} className={`px-1.5 py-0.5 rounded border text-[10px] font-medium ${lightCards ? 'border-zinc-200 text-zinc-500' : 'border-neutral-800 text-neutral-400 bg-neutral-800/60'}`}>
                              +{overflowCount}
                            </button>
                          )}
                        </div>

                        {/* Price & Quick Cart Row (Screenshot 3: RM 389.00  RM 467.00  [Shopping Bag Button]) */}
                        <div className={`pt-3 border-t flex items-center justify-between gap-2 ${lightCards ? 'border-zinc-200' : 'border-neutral-800/80'}`}>
                          <div className="flex items-baseline gap-2">
                            <span className={`text-base sm:text-lg font-black ${lightCards ? 'text-zinc-900' : 'text-white'}`}>
                              {selected?.priceLabel ?? commerce?.priceLabel ?? purchasePriceLabel(prod, price, listingMoney)}
                            </span>
                            {hasDiscount && <span className={`text-xs sm:text-sm line-through font-medium ${lightCards ? 'text-zinc-400' : 'text-neutral-500'}`}>
                              {selected?.compareAtLabel ?? commerce?.compareAtLabel ?? listingMoney(comparePrice!)}
                            </span>}
                          </div>

                          {(!prod.purchaseMode || prod.purchaseMode === 'buy') ? <form method="POST" action="/cart/items" onSubmit={event => { if (!singleVariant || purchaseDisabled) { event.preventDefault(); event.stopPropagation(); } }}>
                          <input type="hidden" name="_token" value={typeof document === 'undefined' ? '' : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''}/>
                          <input type="hidden" name="variant_id" value={selected?.id ?? commerce?.variantId ?? ''}/>
                          <input type="hidden" name="quantity" value="1"/>
                          <button
                            type={singleVariant ? 'submit' : 'button'}
                            disabled={purchaseDisabled}
                            data-quick-add-trigger={!singleVariant && runtime.context.mode === 'public' && commerce?.canChoose ? '' : undefined}
                            data-quick-add-product-id={prod.id}
                            data-quick-add-variant-id={selected?.id ?? commerce?.variantId ?? undefined}
                            className={`w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center transition-all cursor-pointer disabled:cursor-not-allowed disabled:opacity-40 active:scale-95 shrink-0 ${lightCards ? 'bg-zinc-100 text-zinc-700 hover:bg-amber-400 hover:text-zinc-950' : 'bg-neutral-800/90 hover:bg-indigo-600 hover:text-white border border-neutral-700/70 text-neutral-300 shadow-xs'}`}
                            title={singleVariant ? (purchaseDisabled ? 'Out of Stock' : 'Add to Cart') : 'Quick Add to Cart'}
                            aria-label={singleVariant ? `Add ${prod.title} to cart` : `Select options for ${prod.title}`}
                          >
                            <ShoppingBag className="w-4 h-4" />
                          </button>
                          </form> : <a href={`/products/${encodeURIComponent(prod.slug)}`} className="text-xs underline">View details</a>}
                        </div>
                      </div>
                    </div>
                  </MotionCard>
                );
              })}
            </div>
          </div>

          {/* Dots Pagination Indicator (when multiple slides exist) */}
          {maxIdx > 0 && (
            <div className="flex items-center justify-center gap-1.5 mt-6">
              {Array.from({ length: maxIdx + 1 }).map((_, dotIdx) => (
                <button
                  key={dotIdx}
                  type="button"
                  onClick={() => setProductListingIdx(dotIdx)}
                  aria-label={`Go to slide ${dotIdx + 1}`}
                  className={`transition-all rounded-full cursor-pointer ${
                    productListingIdx === dotIdx
                      ? 'w-6 h-2 bg-indigo-500'
                      : 'w-2 h-2 bg-neutral-700 hover:bg-neutral-500'
                  }`}
                />
              ))}
            </div>
          )}
        </div>
      </div>
    );
  };

  const renderCollectionTabs = () => {
    // If section.tabs is configured, use it; otherwise fallback to legacy tabCategories or default labels
    const hasConfiguredTabs = Array.isArray(section.tabs) && section.tabs.length > 0;

    // Determine active tab item
    const currentTabItem = hasConfiguredTabs
      ? (section.tabs?.find(t => t.id === activeTab || t.label === activeTab) || section.tabs?.[0])
      : null;

    const currentTabLabel = currentTabItem?.label || activeTab || section.tabCategories?.[0] || 'Over-Ear Flagship';
    const activeTabIndex = currentTabItem && section.tabs ? section.tabs.indexOf(currentTabItem) : -1;
    const tabProducts = activeTabIndex < 0
      ? []
      : (runtimeSection.tabs?.[activeTabIndex]?.products ?? []);
    const cols = currentTabItem?.productLimit === 4 || currentTabItem?.productLimit === 8 ? 4 : 3;

    return (
      <div className={`w-full ${getPaddingClass()}`}>
        <div className={`mb-8 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
          {section.subtitle && (
            <MotionItem><span data-cms-text="eyebrow"
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
            >
              {section.subtitle}
            </span></MotionItem>
          )}
          <MotionItem><h2 data-cms-text="title"
            style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
            className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
          >
            {section.title}
          </h2></MotionItem>
          {section.content && (
            <MotionItem><p data-cms-text="content"
              style={{...(getContentStyle()),...typographyStyle(section,'content')}}
              className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
            >
              {section.content}
            </p></MotionItem>
          )}

          {/* Interactive Tabs */}
          <MotionItem className="flex flex-wrap items-center justify-center gap-2 mt-6">
            {hasConfiguredTabs ? (
              section.tabs?.map((tab) => {
                const isActive = currentTabItem?.id === tab.id;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    style={isActive ? getPrimaryBtnStyle() : {}}
                    className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                      isActive
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                        : 'bg-white/5 hover:bg-white/10 text-neutral-300 border border-white/10'
                    }`}
                  >
                    {tab.label}
                  </button>
                );
              })
            ) : (
              (section.tabCategories || ['Over-Ear Flagship', 'In-Ear Monitors', 'DAC & Amps', 'Audiophile Cables']).map((tab) => {
                const isActive = activeTab === tab;
                return (
                  <button
                    key={tab}
                    onClick={() => setActiveTab(tab)}
                    style={isActive ? getPrimaryBtnStyle() : {}}
                    className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                      isActive
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                        : 'bg-white/5 hover:bg-white/10 text-neutral-300 border border-white/10'
                    }`}
                  >
                    {tab}
                  </button>
                );
              })
            )}
          </MotionItem>
        </div>

        {/* Tab Items Grid */}
        <div className={`grid ${getGridColsClass(cols)} gap-6 pt-4`}>
          {tabProducts.length > 0 ? (
            tabProducts.map((prod, idx) => (
              <MotionCard data-cms-source="" key={prod.id + idx}><a data-cms-source=""
                key={prod.id + idx}
                href={`/products/${prod.slug}`}
                onClick={e => isEditingMode && e.preventDefault()}
                className="p-4 rounded-2xl bg-white/5 border border-white/10 hover:border-indigo-500/40 transition-all flex items-center gap-4"
              >
                <img
                  src={prod.images[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80'}
                  alt={prod.title}
                  className="w-20 h-20 rounded-xl object-cover bg-neutral-950 flex-shrink-0"
                  referrerPolicy="no-referrer"
                />
                <div className="flex-1 min-w-0">
                  <span data-cms-text="eyebrow" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} className="text-[10px] font-bold text-indigo-400 uppercase truncate block">
                    {currentTabLabel}
                  </span>
                  <h4 data-cms-text="itemTitle" style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-sm font-bold text-white truncate">{prod.title}</h4>
                  <p data-cms-text="content" style={{...(getContentStyle()),...typographyStyle(section,'content')}} className="text-xs text-neutral-400 line-clamp-1">{prod.summary}</p>
                  <div data-cms-text="title" style={{...(getTitleStyle()),...typographyStyle(section,'title')}} className="mt-1 font-bold text-white text-sm">{purchasePriceLabel(prod, Number(prod.basePrice), listingMoney)}</div>
                </div>
              </a></MotionCard>
            ))
          ) : (
            <div className="col-span-full py-10 px-4 text-center rounded-2xl bg-white/5 border border-white/10 text-neutral-400">
              <Package className="w-8 h-8 mx-auto mb-2 text-neutral-500 opacity-60" />
              <p className="text-xs font-semibold text-neutral-300">No products found for this tab</p>
              <p className="text-[11px] text-neutral-500 mt-1">Assign products to this category or collection in Catalog Admin, or change tab source in the Section Inspector.</p>
            </div>
          )}
        </div>
      </div>
    );
  };

  // 10. Featured Deal / Spotlight with Real Live Countdown
  const renderFeaturedDeal = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className="p-8 sm:p-12 rounded-3xl bg-gradient-to-r from-indigo-950 via-purple-950 to-neutral-950 border border-indigo-500/30 shadow-2xl relative overflow-hidden">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          <div className="lg:col-span-7 space-y-5">
            <MotionItem className="flex items-center gap-2">
              <span className="px-3 py-1 rounded-full text-xs font-bold uppercase bg-rose-500/20 text-rose-300 border border-rose-500/30 flex items-center gap-1.5 animate-pulse">
                <Flame className="w-3.5 h-3.5 text-rose-400" />
                {section.dealBadge || 'Limited Time Flash Offer'}
              </span>
            </MotionItem>

            {section.subtitle && (
              <MotionItem><span data-cms-text="eyebrow"
                style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
                className="text-xs font-bold uppercase tracking-widest text-indigo-400 block"
              >
                {section.subtitle}
              </span></MotionItem>
            )}

            <MotionItem><h2 data-cms-text="title"
              style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
              className="text-2xl sm:text-4xl font-black text-white tracking-tight"
            >
              {section.title}
            </h2></MotionItem>
            {section.content && (
              <MotionItem><p data-cms-text="content"
                style={{...(getContentStyle()),...typographyStyle(section,'content')}}
                className="text-sm sm:text-base text-neutral-300 leading-relaxed"
              >
                {section.content}
              </p></MotionItem>
            )}

            {/* Countdown Timer with Real Ticking State */}
            <MotionItem className="flex items-center gap-3 pt-2">
              <div className="p-3 rounded-xl bg-black/60 border border-white/10 text-center min-w-[64px]">
                <span className="text-xl font-black text-white">{String(timeLeft.hours).padStart(2, '0')}</span>
                <span className="text-[10px] text-neutral-400 block uppercase font-bold">Hours</span>
              </div>
              <span className="text-xl font-bold text-neutral-500">:</span>
              <div className="p-3 rounded-xl bg-black/60 border border-white/10 text-center min-w-[64px]">
                <span className="text-xl font-black text-white">{String(timeLeft.minutes).padStart(2, '0')}</span>
                <span className="text-[10px] text-neutral-400 block uppercase font-bold">Mins</span>
              </div>
              <span className="text-xl font-bold text-neutral-500">:</span>
              <div className="p-3 rounded-xl bg-black/60 border border-white/10 text-center min-w-[64px]">
                <span className="text-xl font-black text-rose-400">{String(timeLeft.seconds).padStart(2, '0')}</span>
                <span className="text-[10px] text-neutral-400 block uppercase font-bold">Secs</span>
              </div>
            </MotionItem>

            <div className="pt-2">
              <MotionButton><a data-cms-action="button"
                href={section.buttonLink || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={getPrimaryBtnStyle()}
                className="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl font-bold text-sm bg-white text-neutral-900 hover:opacity-90 shadow-xl transition-all"
              >
                <span>{section.buttonText || 'Claim Deal'}</span>
                <ArrowRight className="w-4 h-4" />
              </a></MotionButton>
            </div>
          </div>

          <div className="lg:col-span-5">
            <MotionMedia style={imageSlotFrameStyle(section,'mediaUrl')} className="relative rounded-2xl overflow-hidden border border-white/20 shadow-2xl">
              <ScopedImage section={section} targetId="mediaUrl"
                src={section.mediaUrl || 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&auto=format&fit=crop&q=80'}
                alt={section.title}
                className="w-full aspect-square object-cover"
                referrerPolicy="no-referrer"
              />
              <div className="absolute top-4 right-4 px-3 py-1.5 rounded-xl bg-rose-600 text-white font-black text-sm shadow-lg">
                {section.discountPercent ? `SAVE ${section.discountPercent}%` : 'SAVE 25%'}
              </div>
            </MotionMedia>
          </div>
        </div>
      </div>
    </div>
  );

  // 11. Tiered Pricing Table
  const renderPricingTable = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-12 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <div className={`grid ${getGridColsClass(3)} gap-8 items-stretch`}>
        {section.items?.map((item, idx) => {
          const isSpecial = item.isHighlighted;
          return (
            <MotionCard
              key={item.id || idx}
              className={`rounded-3xl p-7 flex flex-col justify-between transition-all ${
                isSpecial
                  ? 'bg-gradient-to-b from-indigo-900/60 to-neutral-900 border-2 border-indigo-500 shadow-2xl shadow-indigo-500/20 scale-105 relative'
                  : 'bg-white/5 border border-white/10 hover:border-white/20'
              }`}
            >
              {isSpecial && (
                <div
                  style={getPrimaryBtnStyle()}
                  className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider shadow-md"
                >
                  Most Popular
                </div>
              )}

              <div>
                <div className="flex items-center justify-between mb-4">
                  <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-lg font-bold text-white">{item.title}</h3>
                  {item.badge && (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-white/10 text-neutral-300">
                      {item.badge}
                    </span>
                  )}
                </div>

                <div className="mb-4">
                  <span data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-4xl font-black text-white">{listingMoney(Number(item.price))}</span>
                  <span className="text-xs text-neutral-400 ml-1">/ one-time</span>
                </div>

                <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs text-neutral-300 mb-6 leading-relaxed">
                  {item.description}
                </p>

                <div className="space-y-2.5 mb-8 border-t border-white/10 pt-5">
                  {item.features?.map((feat, fIdx) => (
                    <div key={fIdx} className="flex items-start gap-2.5 text-xs text-neutral-200">
                      <Check className="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" />
                      <span>{feat}</span>
                    </div>
                  ))}
                </div>
              </div>

              <a
                href={item.linkUrl || '#'}
                onClick={e => isEditingMode && e.preventDefault()}
                style={isSpecial ? getPrimaryBtnStyle() : getSecondaryBtnStyle()}
                className={`w-full py-3 rounded-xl font-bold text-xs text-center transition-all ${
                  isSpecial
                    ? 'bg-indigo-600 hover:opacity-90 text-white shadow-lg shadow-indigo-600/30'
                    : 'bg-white/10 hover:bg-white/20 text-white border border-white/10'
                }`}
              >
                Select {item.title}
              </a>
            </MotionCard>
          );
        })}
      </div>
    </div>
  );

  // 12. Media Lookbook Gallery
  const renderMediaGallery = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-12 ${
        section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
      }`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <MediaWidthRegion section={section}><div className={`grid ${getGridColsClass(section.columnsCount || 3)} gap-6`}>
        {section.items?.map((item, idx) => (
          <MotionCard
            key={item.id || idx}
            data-image-slot={`item:${item.id}:imageUrl`} style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)} className="group relative aspect-4/3 rounded-2xl overflow-hidden border border-white/10 bg-neutral-900 min-w-0"
          >
            <ScopedImage section={section} targetId={`item:${item.id}:imageUrl`}
              src={item.imageUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80'}
              alt={item.title}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              referrerPolicy="no-referrer"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-90 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-5">
              <h4 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-base font-bold text-white mb-0.5 truncate">{item.title}</h4>
              {item.description && (
                <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs text-neutral-300 line-clamp-2">{item.description}</p>
              )}
            </div>
          </MotionCard>
        ))}
      </div></MediaWidthRegion>
    </div>
  );

  // 13. Video Embed Player
  const renderVideoEmbed = () => (
    <div className={`w-full ${getPaddingClass()} text-center`}>
      {section.subtitle && (
        <MotionItem><span data-cms-text="eyebrow"
          style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
          className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
        >
          {section.subtitle}
        </span></MotionItem>
      )}
      <MotionItem><h2 data-cms-text="title"
        style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
        className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-4"
      >
        {section.title}
      </h2></MotionItem>
      {section.content && (
        <MotionItem><p data-cms-text="content"
          style={{...(getContentStyle()),...typographyStyle(section,'content')}}
          className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto mb-8"
        >
          {section.content}
        </p></MotionItem>
      )}

      <MotionMedia className="relative aspect-video rounded-2xl overflow-hidden border border-white/15 shadow-2xl bg-neutral-950">
        {section.videoEmbedUrl ? (
          <iframe
            src={section.videoEmbedUrl}
            title={section.title}
            className="w-full h-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          />
        ) : (
          <div className="w-full h-full flex flex-col items-center justify-center text-neutral-400">
            <div className="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center mb-3">
              <Play className="w-8 h-8 text-white ml-1" />
            </div>
            <p className="text-sm font-semibold">Video Stream Container (Enter embed URL or upload video)</p>
          </div>
        )}
      </MotionMedia>
    </div>
  );

  // 14. Interactive Before / After Slider
  const renderBeforeAfter = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-10 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <MotionMedia
        ref={sliderContainerRef}
        onMouseMove={e => isDraggingSlider && handleSliderMove(e.clientX)}
        onTouchMove={e => isDraggingSlider && handleSliderMove(e.touches[0].clientX)}
        onMouseUp={() => setIsDraggingSlider(false)}
        onTouchEnd={() => setIsDraggingSlider(false)}
        className="relative aspect-16/9 rounded-3xl overflow-hidden border border-white/20 shadow-2xl select-none cursor-ew-resize bg-neutral-950"
      >
        {/* Before Image */}
        <ScopedImage section={section} targetId="beforeImageUrl"
          src={section.beforeImageUrl || 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=1200&auto=format&fit=crop&q=80'}
          alt="Before"
          className="absolute inset-0 w-full h-full object-cover"
          referrerPolicy="no-referrer"
        />
        <div className="absolute top-4 left-4 px-3 py-1 rounded-lg bg-black/70 text-white text-xs font-bold backdrop-blur-md">
          {section.beforeLabel || 'Original State'}
        </div>

        {/* After Image (Clipped) */}
        <div
          className="absolute inset-0 overflow-hidden"
          style={{ clipPath: `polygon(0 0, ${sliderPos}% 0, ${sliderPos}% 100%, 0 100%)` }}
        >
          <ScopedImage section={section} targetId="afterImageUrl"
            src={section.afterImageUrl || 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=1200&auto=format&fit=crop&q=80'}
            alt="After"
            className="absolute inset-0 w-full h-full object-cover"
            referrerPolicy="no-referrer"
          />
          <div
            style={getPrimaryBtnStyle()}
            className="absolute top-4 right-4 px-3 py-1 rounded-lg bg-indigo-600 text-white text-xs font-bold shadow-md"
          >
            {section.afterLabel || 'Enhanced State'}
          </div>
        </div>

        {/* Draggable Divider Handle */}
        <div
          className="absolute top-0 bottom-0 w-1 bg-white shadow-2xl cursor-ew-resize flex items-center justify-center"
          style={{ left: `${sliderPos}%` }}
          onMouseDown={() => setIsDraggingSlider(true)}
          onTouchStart={() => setIsDraggingSlider(true)}
        >
          <div className="w-9 h-9 rounded-full bg-white text-neutral-900 flex items-center justify-center shadow-2xl ring-4 ring-black/40">
            <Sliders className="w-4 h-4 rotate-90" />
          </div>
        </div>
      </MotionMedia>
    </div>
  );

  // 15. Instagram / Shoppable UGC Feed
  const renderInstagramFeed = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-10 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <div className={`grid ${viewport === 'mobile' ? 'grid-cols-2' : getGridColsClass(section.columnsCount || 4)} gap-4`}>
        {section.items?.map((item, idx) => (
          <MotionCard
            key={item.id || idx}
            data-image-slot={`item:${item.id}:imageUrl`} style={imageSlotFrameStyle(section,`item:${item.id}:imageUrl`)} className="group relative aspect-square rounded-2xl overflow-hidden bg-neutral-900 border border-white/10 min-w-0"
          >
            <ScopedImage section={section} targetId={`item:${item.id}:imageUrl`}
              src={item.imageUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80'}
              alt={item.title}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              referrerPolicy="no-referrer"
            />
            <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-4">
              <span className="self-end p-1.5 rounded-full bg-white/20 text-white backdrop-blur-md">
                <Instagram className="w-4 h-4" />
              </span>
              <div>
                <span className="text-xs font-bold text-white block truncate">{item.handle || '@aurora.creator'}</span>
                <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-[11px] text-neutral-300 line-clamp-1">{item.title}</p>
              </div>
            </div>
          </MotionCard>
        ))}
      </div>
    </div>
  );

  // 16. Feature Cards
  const renderFeatureCards = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-12 ${
        section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
      }`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <div className={`grid ${getGridColsClass(section.columnsCount || 4)} gap-6`}>
        {section.items?.map((item, idx) => (
          <MotionCard
            key={item.id || idx}
            className="p-6 rounded-2xl bg-white/5 border border-white/10 hover:border-indigo-500/40 transition-all flex flex-col justify-between min-w-0"
          >
            <div>
              <div data-cms-text="eyebrow" data-cms-item={item.id}
                style={{...(customColors?.eyebrowColor ? { color: customColors.eyebrowColor } : {}),...typographyStyle(section,'eyebrow')}}
                className="w-12 h-12 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center mb-4 ring-1 ring-indigo-500/30 shrink-0"
              >
                {renderIcon(item.icon)}
              </div>
              <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-base font-bold text-white mb-2 break-words">
                {item.title}
              </h3>
              <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs sm:text-sm text-neutral-300 leading-relaxed break-words">
                {item.description}
              </p>
            </div>
            {item.badge && (
              <div className="mt-4 pt-3 border-t border-white/10">
                <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-white/10 text-neutral-300">
                  {item.badge}
                </span>
              </div>
            )}
          </MotionCard>
        ))}
      </div>
    </div>
  );

  // 17. Trust Badges & Guarantee Row (Mobile Responsive)
  const renderIconList = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`grid ${getGridColsClass(section.columnsCount || 4)} gap-4 sm:gap-6`}>
        {section.items?.map((item, idx) => (
          <MotionCard key={item.id || idx} className="flex items-center gap-3.5 p-4 rounded-2xl bg-white/5 border border-white/10 min-w-0">
            <div data-cms-text="eyebrow" data-cms-item={item.id}
              style={{...(customColors?.eyebrowColor ? { color: customColors.eyebrowColor } : {}),...typographyStyle(section,'eyebrow')}}
              className="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center shrink-0"
            >
              {renderIcon(item.icon)}
            </div>
            <div className="min-w-0 flex-1">
              <h4 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-xs sm:text-sm font-bold text-white break-words">{item.title}</h4>
              <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs text-neutral-300 line-clamp-2">{item.description}</p>
            </div>
          </MotionCard>
        ))}
      </div>
    </div>
  );

  // 18. Comparison Matrix Table (Responsive & Editable Headers)
  const renderComparisonMatrix = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-12 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <MotionItem className="rounded-3xl border border-white/15 overflow-hidden bg-white/5 shadow-2xl overflow-x-auto">
        <div className="min-w-[500px]">
          <div className="grid grid-cols-12 p-5 bg-black/40 border-b border-white/10 text-xs font-bold uppercase tracking-wider">
            <div className="col-span-5 text-neutral-400">
              {section.comparisonDimensionName || 'ENGINEERING DIMENSION'}
            </div>
            <div data-cms-text="eyebrow" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} className="col-span-4 text-indigo-300 font-black">
              {section.comparisonOurBrandName || 'AURORA FLAGSHIP'}
            </div>
            <div className="col-span-3 text-neutral-500">
              {section.comparisonCompetitorName || 'MAINSTREAM BRANDS'}
            </div>
          </div>

          <div className="divide-y divide-white/5">
            {section.items?.map((item, idx) => (
              <div key={item.id || idx} className="grid grid-cols-12 p-4.5 items-center text-xs hover:bg-white/5 transition-colors">
                <div data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="col-span-5 font-bold text-white">{item.title}</div>
                <div className="col-span-4 font-semibold text-emerald-400 flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 flex-shrink-0" />
                  <span>{item.badge || 'Industry Leading'}</span>
                </div>
                <div data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="col-span-3 text-neutral-400 truncate">{item.description}</div>
              </div>
            ))}
          </div>
        </div>
      </MotionItem>
    </div>
  );

  // 19. FAQ Accordion
  const renderFaqAccordion = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-10 ${
        section.textAlignment === 'left' ? 'text-left' : section.textAlignment === 'right' ? 'text-right' : 'text-center'
      }`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <div className="space-y-3">
        {section.items?.map((item, idx) => {
          const isOpen = !!openFaqIds[item.id || `faq-${idx}`];
          return (
            <MotionCard
              key={item.id || idx}
              className="rounded-xl border border-white/10 bg-white/5 overflow-hidden transition-colors"
            >
              <button
                onClick={() => toggleFaq(item.id || `faq-${idx}`)}
                className="w-full p-5 flex items-center justify-between text-left font-bold text-sm sm:text-base text-white hover:text-indigo-300 transition-colors cursor-pointer"
              >
                <span data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}}>{item.title}</span>
                {isOpen ? (
                  <ChevronUp data-cms-text="eyebrow" data-cms-item={item.id} className="w-4 h-4 text-indigo-400 flex-shrink-0 ml-3" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} />
                ) : (
                  <ChevronDown className="w-4 h-4 text-neutral-400 flex-shrink-0 ml-3" />
                )}
              </button>
              {isOpen && (
                <div data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="px-5 pb-5 text-xs sm:text-sm text-neutral-300 leading-relaxed border-t border-white/5 pt-3 animate-in fade-in">
                  {item.description}
                </div>
              )}
            </MotionCard>
          );
        })}
      </div>
    </div>
  );

  // 20. Contact & Inquiries Form with Editable Inspector Fields
  const renderContactForm = () => {
    const defaultInquiryCategories = [
      { id: 'cat-1', label: 'Product Enquiry' },
      { id: 'cat-2', label: 'Order Support' },
      { id: 'cat-3', label: 'Wholesale & Partnership' },
      { id: 'cat-4', label: 'Press & Collaboration' },
      { id: 'cat-5', label: 'Other' }
    ];

    const inquiryCats = section.inquiryCategories && section.inquiryCategories.length > 0
      ? section.inquiryCategories
      : defaultInquiryCategories;

    return (
      <div className={`w-full ${getPaddingClass()}`}>
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
          <div className="lg:col-span-5 space-y-4">
          {section.subtitle && (
            <MotionItem><span data-cms-text="eyebrow"
              style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
              className="text-xs font-bold uppercase tracking-widest text-indigo-400 block"
            >
              {section.subtitle}
            </span></MotionItem>
          )}
          <MotionItem><h2 data-cms-text="title"
            style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
            className="text-2xl sm:text-4xl font-extrabold tracking-tight"
          >
            {section.title}
          </h2></MotionItem>
          {section.content && (
            <MotionItem><p data-cms-text="content"
              style={{...(getContentStyle()),...typographyStyle(section,'content')}}
              className="text-sm sm:text-base opacity-80 leading-relaxed"
            >
              {section.content}
            </p></MotionItem>
          )}

            <MotionItem className="space-y-3 pt-4 text-xs text-neutral-300">
              <div className="flex items-center gap-3">
                <Mail data-cms-text="eyebrow" className="w-4 h-4 text-indigo-400 shrink-0" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} />
                <span className="break-all">{section.formEmail || 'concierge@aurora-audio.se'}</span>
              </div>
              <div className="flex items-center gap-3">
                <Phone data-cms-text="eyebrow" className="w-4 h-4 text-indigo-400 shrink-0" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} />
                <span>{section.contactPhone || '+46 8 123 4567 (Mon–Fri 09:00–18:00 CET)'}</span>
              </div>
              {section.contactAddress && (
                <div className="flex items-center gap-3">
                  <MapPin data-cms-text="eyebrow" className="w-4 h-4 text-indigo-400 shrink-0" style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} />
                  <span>{section.contactAddress}</span>
                </div>
              )}
            </MotionItem>
          </div>

          <div className="lg:col-span-7">
            <MotionCard className="p-6 sm:p-8 rounded-3xl bg-white/5 border border-white/15 backdrop-blur-md">
              {contactSuccess ? (
                <div className="text-center py-8 space-y-3">
                  <div className="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                    <Check className="w-6 h-6" />
                  </div>
                  <h4 className="text-lg font-bold text-white">Inquiry Received</h4>
                  <p className="text-xs text-neutral-300 max-w-sm mx-auto">
                    {section.formSuccessMessage || 'Thank you! Our acoustic engineers will contact you shortly.'}
                  </p>
                </div>
              ) : (
                <form
                  onSubmit={async e => {
                    e.preventDefault();
                    try {
                      const contract = await resolveFormContract('contact');
                      if (!contract) return;
                      const response = await submitRuntimeForm(contract.endpoint, {
                        name: contactName,
                        email: contactEmail,
                        subject: contactSubject,
                        category: contactCategory,
                        message: contactMessage,
                      });
                      const body: unknown = await response.json().catch(() => null);
                      if (response.ok && readContactSuccess(body)) setContactSuccess(true);
                    } catch {
                      // The accepted ZIP5 source has no standalone contact error surface.
                    }
                  }}
                  className="space-y-4 text-xs"
                >
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="text-neutral-300 font-medium block mb-1">Your Name</label>
                      <input
                        type="text"
                        required
                        value={contactName}
                        onChange={e => setContactName(e.target.value)}
                        placeholder="e.g. Søren Lindqvist"
                        className="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/15 text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="text-neutral-300 font-medium block mb-1">Email Address</label>
                      <input
                        type="email"
                        required
                        value={contactEmail}
                        onChange={e => setContactEmail(e.target.value)}
                        placeholder="soren@example.com"
                        className="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/15 text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="text-neutral-300 font-medium block mb-1">Subject</label>
                      <input
                        type="text"
                        required
                        value={contactSubject}
                        onChange={e => setContactSubject(e.target.value)}
                        placeholder={section.subjectPlaceholder || 'e.g. Custom Engraving / Setup Inquiry'}
                        className="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/15 text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="text-neutral-300 font-medium block mb-1">Inquiry Category</label>
                      <select
                        required
                        value={contactCategory}
                        onChange={e => setContactCategory(e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/15 text-white focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                      >
                        <option value="" disabled className="bg-neutral-900 text-neutral-400">
                          {section.categoryPlaceholder || 'Select an inquiry category...'}
                        </option>
                        {inquiryCats.map(cat => (
                          <option key={cat.id} value={cat.label} className="bg-neutral-900 text-white">
                            {cat.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="text-neutral-300 font-medium block mb-1">Inquiry / Custom Request</label>
                    <textarea
                      rows={4}
                      required
                      value={contactMessage}
                      onChange={e => setContactMessage(e.target.value)}
                      placeholder={section.messagePlaceholder || "Tell us about your audio setup or custom acoustic requirements..."}
                      className="w-full px-3.5 py-2.5 rounded-xl bg-black/40 border border-white/15 text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <button
                    type="submit"
                    style={getPrimaryBtnStyle()}
                    className="w-full py-3 rounded-xl bg-indigo-600 hover:opacity-90 text-white font-bold transition-all shadow-lg flex items-center justify-center gap-2 cursor-pointer"
                  >
                    <Send className="w-4 h-4" />
                    <span>{section.contactButtonText || 'Send Inquiries to Atelier'}</span>
                  </button>
                </form>
              )}
            </MotionCard>
          </div>
        </div>
      </div>
    );
  };

  // 21. Store Locator Lounges
  const renderStoreLocator = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className={`mb-12 ${section.textAlignment === 'left' ? 'text-left' : 'text-center'}`}>
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h2 data-cms-text="title"
          style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
          className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-3"
        >
          {section.title}
        </h2></MotionItem>
        {section.content && (
          <MotionItem><p data-cms-text="content"
            style={{...(getContentStyle()),...typographyStyle(section,'content')}}
            className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto"
          >
            {section.content}
          </p></MotionItem>
        )}
      </div>

      <div className={`grid ${getGridColsClass(section.columnsCount || 3)} gap-6`}>
        {section.items?.map((item, idx) => (
          <MotionCard key={item.id || idx} className="p-6 rounded-3xl bg-white/5 border border-white/10 flex flex-col justify-between min-w-0">
            <div>
              <div className="flex items-center justify-between mb-3">
                <span data-cms-text="eyebrow" data-cms-item={item.id} style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}} className="text-[10px] font-bold uppercase tracking-wider text-indigo-400">{item.subtitle}</span>
                {item.badge && (
                  <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    {item.badge}
                  </span>
                )}
              </div>
              <h3 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-lg font-bold text-white mb-2">{item.title}</h3>
              <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs text-neutral-300 whitespace-pre-line leading-relaxed mb-6">
                {item.description}
              </p>
            </div>

            <a
              href={item.linkUrl || '#'}
              onClick={e => isEditingMode && e.preventDefault()}
              style={getSecondaryBtnStyle()}
              className="py-2.5 rounded-xl text-xs font-bold text-center bg-white/10 hover:bg-white/20 text-white border border-white/15 transition-all"
            >
              Book Listening Session
            </a>
          </MotionCard>
        ))}
      </div>
    </div>
  );

  // 22. Testimonials Review Slider Carousel
  const renderTestimonials = () => {
    if(section.testimonialPreset==='editorial-quote')return <EditorialQuote section={section}/>;
    const items = section.items || [];
    const itemsPerPage = viewport === 'mobile' ? 1 : viewport === 'tablet' ? 2 : 3;
    const maxIdx = Math.max(0, items.length - itemsPerPage);
    const visibleItems = items.slice(testimonialIdx, testimonialIdx + itemsPerPage);

    const handlePrev = () => {
      setTestimonialIdx(prev => Math.max(0, prev - 1));
    };

    const handleNext = () => {
      setTestimonialIdx(prev => Math.min(maxIdx, prev + 1));
    };

    return (
      <div className={`w-full ${getPaddingClass()}`}>
        {/* Header & Slider Navigation Arrows */}
        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
          <div className={`${section.textAlignment === 'center' ? 'text-center sm:text-left' : ''}`}>
            {section.subtitle && (
              <MotionItem><span data-cms-text="eyebrow"
                style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
                className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
              >
                {section.subtitle}
              </span></MotionItem>
            )}
            <MotionItem><h2 data-cms-text="title"
              style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
              className="text-2xl sm:text-4xl font-extrabold tracking-tight"
            >
              {section.title}
            </h2></MotionItem>
            {section.content && (
              <MotionItem><p data-cms-text="content"
                style={{...(getContentStyle()),...typographyStyle(section,'content')}}
                className="text-sm sm:text-base opacity-80 mt-2 max-w-xl"
              >
                {section.content}
              </p></MotionItem>
            )}
          </div>

          {/* Carousel Arrows if more than 3 cards or on mobile */}
          {items.length > itemsPerPage && (
            <div className="flex items-center gap-2 self-end sm:self-auto">
              <button
                type="button"
                onClick={handlePrev}
                disabled={testimonialIdx === 0}
                className="p-2.5 rounded-xl border border-white/15 bg-white/5 hover:bg-white/15 disabled:opacity-30 disabled:pointer-events-none text-white transition-colors cursor-pointer"
                title="Previous Testimonials"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <button
                type="button"
                onClick={handleNext}
                disabled={testimonialIdx >= maxIdx}
                className="p-2.5 rounded-xl border border-white/15 bg-white/5 hover:bg-white/15 disabled:opacity-30 disabled:pointer-events-none text-white transition-colors cursor-pointer"
                title="Next Testimonials"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>

        {/* Carousel Grid */}
        <div className={`grid ${getGridColsClass(itemsPerPage)} gap-6`}>
          {visibleItems.map((item, idx) => (
            <MotionCard
              key={item.id || idx}
              className="p-6 sm:p-7 rounded-3xl bg-white/5 border border-white/10 hover:border-indigo-500/40 transition-all flex flex-col justify-between min-w-0"
            >
              <div>
                {/* 5 Stars Rating */}
                <div className="flex items-center gap-1 mb-4">
                  {[...Array(item.rating || 5)].map((_, i) => (
                    <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                  ))}
                  <span className="text-[11px] font-bold text-amber-400 ml-1.5">
                    {item.rating || 5}.0
                  </span>
                </div>

                {item.title && (
                  <h4 data-cms-text="itemTitle" data-cms-item={item.id} style={{...(getTitleStyle()),...typographyStyle(section,'itemTitle')}} className="text-base font-bold text-white mb-2 break-words">{item.title}</h4>
                )}
                
                <p data-cms-text="itemContent" data-cms-item={item.id} style={{...(getContentStyle()),...typographyStyle(section,'itemContent')}} className="text-xs sm:text-sm text-neutral-300 leading-relaxed italic mb-6 break-words">
                  "{item.description}"
                </p>
              </div>

              {/* Reviewer Meta Avatar & Title */}
              <div className="flex items-center gap-3.5 pt-4 border-t border-white/10 min-w-0">
                {item.authorAvatar ? (
                  <ScopedImage section={section} targetId={`item:${item.id}:authorAvatar`}
                    src={item.authorAvatar}
                    alt={item.author || 'Reviewer'}
                    className="w-11 h-11 rounded-full object-cover ring-2 ring-indigo-500/40 shrink-0"
                    referrerPolicy="no-referrer"
                  />
                ) : (
                  <div
                    style={customColors?.primaryBtnBg ? { backgroundColor: customColors.primaryBtnBg } : {}}
                    className="w-11 h-11 rounded-full bg-indigo-600/30 text-indigo-300 font-bold text-sm flex items-center justify-center ring-2 ring-indigo-500/40 shrink-0"
                  >
                    {(item.author || 'A')[0]}
                  </div>
                )}
                <div className="min-w-0 flex-1">
                  <div className="text-xs sm:text-sm font-bold text-white truncate">{item.author}</div>
                  <div className="text-[11px] text-neutral-400 truncate">{item.authorRole}</div>
                </div>
                {item.badge && (
                  <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-white/10 text-neutral-300 shrink-0">
                    {item.badge}
                  </span>
                )}
              </div>
            </MotionCard>
          ))}
        </div>

        {/* Carousel Pagination Dots */}
        {items.length > itemsPerPage && (
          <div className="flex items-center justify-center gap-1.5 mt-8">
            {Array.from({ length: maxIdx + 1 }).map((_, dotIdx) => (
              <button
                key={dotIdx}
                type="button"
                onClick={() => setTestimonialIdx(dotIdx)}
                className={`h-1.5 rounded-full transition-all cursor-pointer ${
                  testimonialIdx === dotIdx ? 'w-6 bg-indigo-500' : 'w-2 bg-white/20 hover:bg-white/40'
                }`}
                style={testimonialIdx === dotIdx && customColors?.primaryBtnBg ? { backgroundColor: customColors.primaryBtnBg } : {}}
                title={`Go to page ${dotIdx + 1}`}
              />
            ))}
          </div>
        )}
      </div>
    );
  };

  // 23. Logo Cloud (Press & Studios with Image & Text Support)
  const renderLogoCloud = () => (
    <div className={`w-full ${getPaddingClass()}`}>
      <div className="text-center mb-8">
        {section.subtitle && (
          <MotionItem><span data-cms-text="eyebrow"
            style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
            className="text-[11px] font-bold uppercase tracking-widest text-indigo-400 block mb-1"
          >
            {section.subtitle}
          </span></MotionItem>
        )}
        <MotionItem><h3 data-cms-text="title" style={{...(getTitleStyle()),...typographyStyle(section,'title')}} className="text-sm sm:text-base font-bold text-neutral-300">{section.title}</h3></MotionItem>
      </div>

      <div className="flex flex-wrap items-center justify-center gap-8 sm:gap-14">
        {section.items?.map((item, idx) => {
          const content = (
            <MotionItem key={item.id || idx} className="text-center group transition-all duration-300">
              {item.imageUrl ? (
                <ScopedImage section={section} targetId={`item:${item.id}:imageUrl`}
                  src={item.imageUrl}
                  alt={item.title}
                  className="h-7 sm:h-9 max-w-[130px] sm:max-w-[160px] object-contain filter grayscale brightness-125 group-hover:grayscale-0 group-hover:brightness-100 transition-all opacity-80 group-hover:opacity-100 mx-auto"
                  referrerPolicy="no-referrer"
                />
              ) : (
                <span data-cms-text="itemTitle" data-cms-item={item.id} style={typographyStyle(section,'itemTitle')} className="text-base sm:text-xl font-black tracking-wider text-white group-hover:text-indigo-400 transition-colors">
                  {item.title}
                </span>
              )}
              {item.description && (
                <span data-cms-text="itemContent" data-cms-item={item.id} style={typographyStyle(section,'itemContent')} className="block text-[10px] text-neutral-400 font-medium mt-1 group-hover:text-neutral-300">
                  {item.description}
                </span>
              )}
            </MotionItem>
          );

          if (item.linkUrl && item.linkUrl !== '#') {
            return (
              <a
                key={item.id || idx}
                href={item.linkUrl}
                target="_blank"
                rel="noreferrer"
                onClick={e => isEditingMode && e.preventDefault()}
              >
                {content}
              </a>
            );
          }
          return content;
        })}
      </div>
    </div>
  );

  // 24. CTA Strip Banner
  const renderCtaStrip = () => (
    <div className={`w-full ${getPaddingClass()} text-center`}>
      {section.subtitle && (
        <MotionItem><span data-cms-text="eyebrow"
          style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
          className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
        >
          {section.subtitle}
        </span></MotionItem>
      )}
      <MotionItem><h2 data-cms-text="title"
        style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
        className="text-2xl sm:text-4xl font-extrabold tracking-tight mb-4"
      >
        {section.title}
      </h2></MotionItem>
      {section.content && (
        <MotionItem><p data-cms-text="content"
          style={{...(getContentStyle()),...typographyStyle(section,'content')}}
          className="text-sm sm:text-base opacity-80 max-w-2xl mx-auto mb-8"
        >
          {section.content}
        </p></MotionItem>
      )}

      <div className="flex flex-wrap items-center justify-center gap-4">
        {section.buttonText && (
          <MotionButton><a data-cms-action="button"
            href={section.buttonLink || '#'}
            onClick={e => isEditingMode && e.preventDefault()}
            style={getPrimaryBtnStyle()}
            className="px-7 py-3.5 rounded-xl font-bold text-sm bg-indigo-600 hover:opacity-90 text-white shadow-xl shadow-indigo-600/30 transition-all"
          >
            {section.buttonText}
          </a></MotionButton>
        )}
        {section.secondaryButtonText && (
          <MotionButton><a data-cms-action="button"
            href={section.secondaryButtonLink || '#'}
            onClick={e => isEditingMode && e.preventDefault()}
            style={getSecondaryBtnStyle()}
            className="px-6 py-3.5 rounded-xl font-bold text-sm bg-white/10 text-white hover:bg-white/20 border border-white/20 transition-all"
          >
            {section.secondaryButtonText}
          </a></MotionButton>
        )}
      </div>
    </div>
  );

  // 25. Newsletter Signup Strip
  const renderNewsletterSignup = () => {
    const hasResolvedCoupon = Boolean(
      section.newsletterCouponId && runtimeSection.newsletterCouponId?.valid,
    );

    const handleNewsletterSubmit = async (e: React.FormEvent) => {
      e.preventDefault();
      if (!emailInput || !emailInput.includes('@')) return;

      // Task 7 facts validate coupon identity only. Task 12 owns persistence and code release.
      if (!hasResolvedCoupon && !isEditingMode) {
        setNewsletterError('Selected subscriber coupon is unavailable or expired. Please contact support.');
        return;
      }

      try {
        const contract = await resolveFormContract('newsletter');
        if (!contract) {
          setNewsletterError('Selected subscriber coupon is unavailable or expired. Please contact support.');
          return;
        }
        const response = await submitRuntimeForm(contract.endpoint, {email: emailInput});
        if (!response.ok) {
          setNewsletterError('Selected subscriber coupon is unavailable or expired. Please contact support.');
          return;
        }
        const couponCode = readNewsletterSuccess(await response.json().catch(() => null));
        if (couponCode === undefined) {
          setNewsletterError('Selected subscriber coupon is unavailable or expired. Please contact support.');
          return;
        }
        setNewsletterError('');
        setNewsletterCouponCode(couponCode);
        setIsSubmitted(true);
      } catch {
        setNewsletterError('Selected subscriber coupon is unavailable or expired. Please contact support.');
      }
    };

    if(section.newsletterPreset==='inline-signup')return <InlineNewsletter section={section} email={emailInput} onEmail={value=>{setEmailInput(value);setNewsletterError('');}} onSubmit={handleNewsletterSubmit} success={isSubmitted} error={newsletterError} couponCode={newsletterCouponCode} disabled={isEditingMode||!hasResolvedCoupon}/>;
    return (
      <div className={`w-full ${getPaddingClass()} text-center`}>
      {section.subtitle && (
        <MotionItem><span data-cms-text="eyebrow"
          style={{...(getEyebrowStyle()),...typographyStyle(section,'eyebrow')}}
          className="text-xs font-bold uppercase tracking-widest text-indigo-400 block mb-2"
        >
          {section.subtitle}
        </span></MotionItem>
      )}
      <MotionItem><h2 data-cms-text="title"
        style={{...(getTitleStyle()),...typographyStyle(section,'title')}}
        className="text-2xl sm:text-4xl font-black tracking-tight text-white mb-3"
      >
        {section.title}
      </h2></MotionItem>
      {section.content && (
        <MotionItem><p data-cms-text="content"
          style={{...(getContentStyle()),...typographyStyle(section,'content')}}
          className="text-sm sm:text-base text-neutral-300 max-w-xl mx-auto mb-8"
        >
          {section.content}
        </p></MotionItem>
      )}

        {isSubmitted ? (
          <MotionItem className="p-5 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-sm font-semibold max-w-md mx-auto space-y-2">
            <p>{section.formSuccessMessage || 'Your subscriber voucher has been sent to your email!'}</p>
            {newsletterCouponCode && (
              <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-200 font-mono text-xs">
                <span>Voucher Code:</span>
                <span className="font-bold tracking-wider">{newsletterCouponCode}</span>
              </div>
            )}
          </MotionItem>
        ) : (
          <MotionItem><form
            onSubmit={handleNewsletterSubmit}
            className="flex flex-col sm:flex-row items-center justify-center gap-3 max-w-md mx-auto"
          >
            <input
              type="email"
              required
              value={emailInput}
              onChange={e => {
                setEmailInput(e.target.value);
                if (newsletterError) setNewsletterError('');
              }}
              placeholder="Enter your VIP email address..."
              className="w-full px-4 py-3 rounded-xl bg-black/50 border border-white/20 text-white placeholder-neutral-400 text-xs focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
            />
            <MotionButton><button data-cms-action="button"
              type="submit"
              style={getPrimaryBtnStyle()}
              className="w-full sm:w-auto px-6 py-3 rounded-xl bg-indigo-600 hover:opacity-90 text-white font-bold text-xs whitespace-nowrap shadow-lg transition-colors cursor-pointer"
            >
              {section.buttonText || 'Subscribe'}
            </button></MotionButton>
          </form></MotionItem>
        )}

        {newsletterError && !isSubmitted && (
          <p className="text-xs text-rose-400 mt-3 font-medium max-w-md mx-auto">
            {newsletterError}
          </p>
        )}
      </div>
    );
  };

  const renderSectionContent = () => {
    switch (section.type) {
      case 'hero_banner': if(['corporate-centered','corporate-split-composite','corporate-centered-composite'].includes(section.heroBannerPreset??''))return <CorporateHero section={section} editing={isEditingMode}/>; return renderHeroBanner();
      case 'split_hero': return renderSplitHero();
      case 'hero_slider': return renderHeroSlider();
      case 'announcement_bar': return renderAnnouncementBar();
      case 'rich_text': return section.richTextPreset==='industrial-split'?<IndustrialSplit section={section}/>:section.richTextPreset==='manifesto'?<Manifesto section={section}/>:renderRichText();
      case 'media_text_split': if(['contained-wide-centered','wide-visual-centered','contained-wide-editorial','wide-visual-editorial','inset-surface-panel','split-visual-surface'].includes(section.layoutPreset??''))return <WideVisualEditorial section={section} editing={isEditingMode}/>; if(['image-highlights','team-story-overview','corporate-builder-overview'].includes(section.layoutPreset??''))return <CorporateBuilderOverview section={section} editing={isEditingMode}/>; if(section.layoutPreset==='corporate-marketplace')return <CorporateMarketplace section={section} editing={isEditingMode}/>; if(section.layoutPreset==='corporate-courier')return <CorporateCourier section={section} editing={isEditingMode}/>; if(section.layoutPreset==='corporate-payments')return <CorporatePayments section={section} editing={isEditingMode}/>; if(['corporate-section-story','corporate-feature','corporate-support','corporate-image-story'].includes(section.layoutPreset??''))return <CorporateFeature section={section} editing={isEditingMode}/>; return renderMediaTextSplit();
      case 'chapter_navigation': return <ChapterNavigation section={section} isEditingMode={isEditingMode}/>;
      case 'chapter_block': return <ChapterBlock section={section} isEditingMode={isEditingMode}/>;
      case 'timeline_story': return renderTimelineStory();
      case 'stats_counter': return renderStatsCounter();
      case 'product_grid': return renderProductGrid();
      case 'product_listing': return section.listingPreset==='industrial-related'?<IndustrialRelatedProducts section={section} products={listingItems} editing={isEditingMode}/>:section.listingPreset==='fashion-lookbook'?<ProductLookbook section={section} products={listingItems} context={runtime.context} editing={isEditingMode}/>:['editorial-tabs','pantry-tabs','tech-tabs','kids-tabs'].includes(section.listingPreset??'')?<EditorialProductTabs section={section} facts={runtimeSection} context={runtime.context} editing={isEditingMode}/>:['industrial-related','editorial-grid','beauty-grid','pantry-grid','tech-grid','kids-grid'].includes(section.listingPreset??'')?<EditorialProductGrid section={section} products={listingItems} context={runtime.context} editing={isEditingMode}/>:renderProductListing();
      case 'collection_tabs': return renderCollectionTabs();
      case 'featured_deal': return renderFeaturedDeal();
      case 'pricing_table': if(section.pricingPreset==='plan-comparison')return <PlanComparison section={section} facts={runtimeSection.planComparison} links={runtime.links} editing={isEditingMode}/>; return section.pricingPreset==='saas-packages'?<SaasPackages section={section} currency={runtime.context.currency} editing={isEditingMode}/>:renderPricingTable();
      case 'promotion_highlights': return <PromotionHighlights section={section} offers={runtimeSection.promotions?.offers??[]} editing={isEditingMode}/>;
      case 'shop_navigation': return <ShopNavigation section={section} catalog={runtime.catalog} editing={isEditingMode}/>;
      case 'media_gallery': if(section.galleryPreset==='product-preview-tabs')return <CorporateTabs section={section} editing={isEditingMode}/>; return ['industrial-mosaic','album-browser'].includes(section.galleryPreset??'')?<IndustrialGallery section={section} editing={isEditingMode}/>:section.galleryPreset==='photo-marquee'?<PhotoMarquee section={section} editing={isEditingMode}/>:['routine-steps','corporate-illustrated-steps'].includes(section.galleryPreset??'')?<RoutineSteps section={section} editing={isEditingMode}/>:['focused-steps','scroll-stack-cards','editorial-knowledge-triptych','image-flow','connected-feature-rows','image-cards-contrast','staggered-focus-cards','image-cards-three-staggered','image-cards-four-mosaic','image-cards-three','corporate-connections','corporate-scenarios','editorial-montage','editorial-cards','editorial-triptych','portfolio-cards'].includes(section.galleryPreset??'')?<EditorialGallery section={section} authoredSection={rawSection} editing={isEditingMode}/>:renderMediaGallery();
      case 'video_embed': return renderVideoEmbed();
      case 'before_after': return renderBeforeAfter();
      case 'instagram_feed': return renderInstagramFeed();
      case 'policy_content': return <PolicyContent section={section} isEditingMode={isEditingMode}/>;
      case 'payment_methods': return <PaymentMethods section={section}/>;
      case 'delivery_table': return <DeliveryTable section={section}/>;
      case 'feature_cards': return section.featureCardsPreset?.startsWith('saas-')?<SaasModules section={section} editing={isEditingMode}/>: section.featureCardsPreset==='industrial-process-split'?<IndustrialProcessSplit section={section}/>:['industrial-cards','industrial-process'].includes(section.featureCardsPreset??'')?<IndustrialCards section={section} editing={isEditingMode}/>: section.featureCardsPreset==='service-highlights'?<ServiceHighlights section={section} renderIcon={renderIcon}/>:['editorial-columns','numbered-process'].includes(section.featureCardsPreset??'')?<EditorialColumns section={section}/>:section.featureCardsPreset==='values-list'?<ValuesList section={section}/>:renderFeatureCards();
      case 'values_list': return <ValuesList section={section}/>;
      case 'icon_list': return renderIconList();
      case 'comparison_matrix': return section.comparisonPreset==='device-table'?<DeviceComparison section={section} editing={isEditingMode}/>:renderComparisonMatrix();
      case 'faq_accordion': if(section.faqPreset==='framed-faq')return <FramedFaq section={section}/>; return ['pricing-card-faq','pricing-band-faq','corporate-starter-faq','saas-split'].includes(section.faqPreset??'')?<SaasFaq section={section} pricing={runtimeSection.starterPricing} editing={isEditingMode}/>: section.faqPreset==='industrial-split'?<IndustrialFaq key={`${section.id}-${section.faqInitiallyOpen}`} section={section}/>: section.faqPreset==='minimal'?<MinimalFaq key={`${section.id}-${section.faqInitiallyOpen}`} section={section}/>:renderFaqAccordion();
      case 'contact_form': return section.contactPreset==='enquiry'?<EnquiryForm section={section} isEditingMode={isEditingMode}/>:renderContactForm();
      case 'store_locator': return section.contactPreset==='location'?<ContactLocation section={section}/>:renderStoreLocator();
      case 'testimonials_slider': return renderTestimonials();
      case 'logo_cloud': return section.logoPreset === 'partners' ? <PartnerLogos section={section} editing={isEditingMode}/> : renderLogoCloud();
      case 'cta_strip': return ['editorial-illustrated','illustrated-panel-right','illustrated-panel','gradient-panel','saas-closing'].includes(section.ctaPreset??'')?<SaasClosing section={section} editing={isEditingMode}/>: section.ctaPreset==='industrial-outline'?<IndustrialOutlineCta section={section} editing={isEditingMode}/>:section.ctaPreset==='help-panel'?<HelpPanel section={section} isEditingMode={isEditingMode}/>:section.ctaPreset==='editorial-band'?<EditorialCtaBand section={section} isEditingMode={isEditingMode}/>:section.ctaPreset==='image-banner'?<ImageBanner section={section} isEditingMode={isEditingMode}/>:renderCtaStrip();
      case 'newsletter_signup': return renderNewsletterSignup();
      default: return renderRichText();
    }
  };

  if(section.type==='promotion_highlights'&&!isEditingMode&&!resolvePromotionCards(section,runtimeSection.promotions?.offers??[]).length)return null;
  return (
    <section data-cms-shared-style={runtime.page.document.style?'':undefined} data-cms-surface={rawSection.surface} data-cms-editor={isEditingMode?'':undefined}
      id={`section-${section.id}`}
      style={{...sectionRootStyle,...containedSurfaceBackground(section),...(sectionAnchorOffset(section)?{scrollMarginTop:sectionAnchorOffset(section)}:{}),...sectionBorderStyle(section),'--cms-page-width':runtime.page.document.containerWidth==='site'?'var(--storefront-content-width, 1280px)':runtime.page.document.containerWidth==='full'?'100%':runtime.page.document.containerWidth==='wide'?'1440px':'1280px',...(runtime.page.document.containerWidth==='site'?{'--cms-page-gutter':'var(--storefront-content-gutter, 24px)'}:{}),...(runtime.page.document.style?{'--cms-button-radius':`${runtime.page.document.style.buttonRadius??0}px`,'--cms-button-height':`${{compact:36,normal:44,large:52}[runtime.page.document.style.buttonSize??'normal']}px`}: {})} as React.CSSProperties}
      onClickCapture={isEditingMode?event=>{
        const element=event.target as HTMLElement;
        const target=element.closest<HTMLElement>('[data-image-slot]')?.dataset.imageSlot;
        if(target){event.preventDefault();onSelectImage?.(target);return;}
        if(element.closest('[data-cms-source]')){event.preventDefault();onSelectElement?.('source');return;}
        const text=element.closest<HTMLElement>('[data-cms-text]');
        if(text){const role=text.dataset.cmsText!;const item=text.dataset.cmsItem;onSelectElement?.(item?`items:${item}`:({eyebrow:'subtitle',title:'title',lead:'leadText',content:'content',itemTitle:'itemTitle',itemContent:'itemContent'} as Record<string,string>)[role],role);return;}
        const action=element.closest<HTMLElement>('[data-cms-action],[data-editorial-action]');
        if(action){event.preventDefault();onSelectElement?.(action.dataset.cmsItem?`items:${action.dataset.cmsItem}`:'button');return;}
        if(element.closest('form'))onSelectElement?.('form');
      }:undefined}
      onClick={isEditingMode ? onSelect : undefined}
      className={`@container relative transition-all duration-200 ${getBgStyle()} ${
        isEditingMode
          ? `cursor-pointer hover:ring-2 hover:ring-indigo-500/50 ${
              isSelected ? 'ring-2 ring-indigo-500 shadow-xl' : ''
            }`
          : ''
      }`}
    >
      {runtime.page.document.style&&<style>{`[data-cms-shared-style] [data-cms-action="button"],[data-cms-shared-style] [data-editorial-action="button"]{border-radius:var(--cms-button-radius);min-height:var(--cms-button-height)}`}</style>}
      {isEditingMode&&<style>{`[data-cms-editor] [data-cms-text]:hover{outline:1px dashed #7965ff;outline-offset:3px}`}</style>}
      {/* Editor Active Badge */}
      {isEditingMode && isSelected && (
        <div className="absolute top-3 left-3 z-30 flex items-center gap-2 px-2.5 py-1 rounded-md bg-indigo-600 text-white text-[11px] font-bold shadow-lg">
          <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
          Active: {(section.type || '').replace(/_/g, ' ').toUpperCase()}
        </div>
      )}

      <ImageSelectionContext.Provider value={{editing:isEditingMode,selected:selectedImage,select:target=>{
        onSelectImage?.(target);
        if(section.type==='hero_slider') {const index=section.items?.findIndex(item=>`item:${item.id}:imageUrl`===target)??-1;if(index>=0)setHeroSlideIdx(index);}
      }}}>
      <CmsMotionWrapper preset={section.galleryPreset==='scroll-stack-cards'?'none':section.motionPreset} replayKey={`${section.id}-${section.motionPreset}`} isEditingMode={isEditingMode}>
        <PageSectionContainer width={runtime.page.document.containerWidth} fullBleed={section.containerMode==='full-bleed'}>{renderSectionContent()}</PageSectionContainer>
      </CmsMotionWrapper>
      </ImageSelectionContext.Provider>
    </section>
  );
};
