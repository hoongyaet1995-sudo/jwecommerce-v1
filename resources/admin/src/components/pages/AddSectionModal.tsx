import {containedWideCentered,containedWideEditorial,insetSurfacePanel,centeredWideVisual,splitVisualSurface,wideVisualEditorial} from '../../cms/wideVisualEditorial';
import {planComparison,framedFaq} from '../../cms/planComparison';
import {focusedSteps} from '../../cms/focusedSteps';
import {scrollStackCards} from '../../cms/scrollStackCards';
import {teamStoryOverview} from '../../cms/teamStoryOverview';
import {knowledgeTriptych} from '../../cms/knowledgeTriptych';
import {illustratedCtaRight,illustratedCta,editorialIllustratedCta} from '../../cms/illustratedCta';
import {pricingCardFaq,pricingBandFaq} from '../../cms/pricingBandFaq';
import {connectedFeatureRows,isConnectedRows} from '../../cms/connectedFeatureRows';
import {imageHighlights,isImageHighlights} from '../../cms/imageHighlights';
import {focusImageCards,contrastImageCards,staggeredImageCards,threeImageCards,fourMosaicImageCards,isFixedImageCards} from '../../cms/imageCardsThree';
import solutionDetail from '../../cms/logisticsSolutionTemplate.json';
import galleryDetail from '../../cms/logisticsGalleryTemplate.json';
import {logisticsSections} from '../../cms/logisticsTemplates';
import weddingTemplate from '../../cms/weddingTemplate.json';
import jewelryTemplate from '../../cms/jewelryTemplate.json';
import kidsTemplate from '../../cms/kidsTemplate.json';
import techTemplate from '../../cms/techTemplate.json';
import {promotionHighlightDefaults} from '../../cms/promotionHighlights';
import marketTemplate from '../../cms/marketTemplate.json';
import fashionTemplate from '../../cms/fashionTemplate.json';
import {collectionBannerDefaults} from '../../cms/collectionBanners';
import {policyLayouts} from './PolicySettings';
import {shippingChaptersSections} from '../../cms/shippingChaptersTemplate';
import {shippingEditorialSections} from '../../cms/shippingEditorialTemplate';
import {corporateGrowthSections,corporateLandingSections,corporatePaymentSection,corporateCourierSection,corporateMarketplaceSection} from '../../cms/corporateLanding';
import {gradientClosing} from '../../cms/gradientClosing';
import {pricingFaqSection} from '../../cms/websiteBuilderStarterFaq';
import {websiteBuilderConnections} from '../../cms/websiteBuilderConnections';
import {websiteBuilderWorkflow} from '../../cms/websiteBuilderWorkflow';
import {websiteBuilderScenarios} from '../../cms/websiteBuilderScenarios';
import {websiteBuilderStory} from '../../cms/websiteBuilderStory';
import {websiteBuilderOverview} from '../../cms/websiteBuilderOverview';
import {imageFlow} from '../../cms/imageFlow';
import {centeredCompositeHero} from '../../cms/centeredCompositeHero';
import {splitCompositeHero} from '../../cms/websiteBuilderHero';
import {saasSections} from '../../cms/saasTemplates';
import React, { useState } from 'react';
import { Category, Collection, PageSection, PageSectionType } from '../../types';
import { createCollectionTabMappings } from '../../cms/collectionTabMappings';
import { createSectionColorDefaults } from '../../cms/sectionColorDefaults';
import {aboutChaptersSections} from '../../cms/aboutChaptersTemplate';
import {aboutStudioSections} from '../../cms/aboutStudioTemplate';
import {
  X,
  Sparkles,
  Image,
  Video,
  FileText,
  Columns,
  ShoppingBag,
  HelpCircle,
  Megaphone,
  PlaySquare,
  Check,
  Search,
  Sliders,
  TrendingUp,
  Layers,
  Star,
  CheckCircle2,
  Mail,
  Clock,
  Split,
  MapPin,
  Instagram,
  Grid,
  ShieldCheck,
  HeartHandshake,
  Tag,
  Flame,
  Scale,
  LayoutTemplate
} from 'lucide-react';

interface AddSectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onAdd: (newSection: PageSection) => void;
  categories: Category[];
  collections: Collection[];
}

export interface SectionTemplateOption {
  type: PageSectionType;
  title: string;
  category: 'Header & Intro' | 'Storytelling & Narrative' | 'Commerce & Shop' | 'Media & Visuals' | 'Benefits & Trust' | 'Support & Contact' | 'Social Proof' | 'Conversion & Leads';
  description: string;
  icon: React.ElementType;
  badge?: string;
  defaultData: Omit<PageSection, 'id'>;
}

const SOURCE_SECTION_TEMPLATES: SectionTemplateOption[] = [
 {type:'media_gallery',title:'Connected Feature Rows',category:'Benefits & Trust',description:'Three alternating image and text rows with independent buttons.',icon:LayoutTemplate,defaultData:connectedFeatureRows('connected-rows-starter')},
 {type:'media_text_split',title:'Image & Highlights',category:'Benefits & Trust',description:'One main image and four editable icon, title and description highlights.',icon:LayoutTemplate,defaultData:imageHighlights('highlights-starter')},
 {type:'media_gallery',title:'Image Cards — Four Mosaic',category:'Media & Visuals',description:'Four fixed images with editable titles and descriptions in a staggered mosaic.',icon:LayoutTemplate,defaultData:fourMosaicImageCards('four-mosaic-starter')},
 {type:'media_gallery',title:'Image Cards — Three',category:'Media & Visuals',description:'Three fixed images with editable titles and descriptions. No links or extra card controls.',icon:LayoutTemplate,defaultData:threeImageCards('image-cards-starter')},
 {type:'cta_strip',title:'Editorial Illustrated CTA',category:'Conversion & Leads',description:'An editorial callout with editable copy, one foreground artwork and one action.',icon:LayoutTemplate,defaultData:editorialIllustratedCta('editorial-cta')},
 {type:'cta_strip',title:'Illustrated CTA Panel — Image Right',category:'Conversion & Leads',description:'Editable copy and action beside a replaceable illustration on the right.',icon:LayoutTemplate,defaultData:illustratedCtaRight('illustrated-right')},
 {type:'cta_strip',title:'Illustrated CTA Panel',category:'Conversion & Leads',description:'One foreground illustration beside editable heading, body and action. Add your own image and destination.',icon:LayoutTemplate,defaultData:illustratedCta('illustrated-cta')},
 {type:'cta_strip',title:'Gradient CTA Panel',category:'Conversion & Leads',description:'A gradient panel with editable heading, description and one button. Add your own destination.',icon:LayoutTemplate,defaultData:gradientClosing('gradient-cta')},
 {type:'faq_accordion',title:'Pricing Card & FAQ',category:'Support & Contact',description:'External heading, a reusable pricing card and editable questions side by side.',icon:LayoutTemplate,defaultData:pricingCardFaq('pricing-card-faq')},
 {type:'faq_accordion',title:'Pricing Band & FAQ',category:'Support & Contact',description:'A horizontal pricing band above editable questions and answers. Enter your own plan or reference existing pricing.',icon:LayoutTemplate,defaultData:pricingBandFaq('pricing-band-faq')},
 {type:'faq_accordion',title:'Pricing Card & FAQ',category:'Support & Contact',description:'An editable pricing card beside FAQs. Enter your own pricing or reference an existing plan.',icon:LayoutTemplate,defaultData:pricingFaqSection('pricing-faq')},
 {type:'media_gallery',title:'Website Connected Features',category:'Benefits & Trust',description:'Independent illustrations, copy and feature-page buttons. Only buttons navigate.',icon:LayoutTemplate,defaultData:websiteBuilderConnections('website-connections-starter')},
 {type:'media_gallery',title:'Website Illustrated Steps',category:'Storytelling & Narrative',description:'Independent illustrations and editable step copy with automatic numbering and connecting arrows.',icon:LayoutTemplate,defaultData:websiteBuilderWorkflow('website-workflow-starter')},
 {type:'media_gallery',title:'Business Website Scenarios',category:'Media & Visuals',description:'Independent website preview images with editable icons and names, without links.',icon:LayoutTemplate,defaultData:websiteBuilderScenarios('website-scenarios-starter')},
 {type:'media_text_split',title:'Website Section Story',category:'Storytelling & Narrative',description:'Editable narrative and icon labels beside one transparent layered-section illustration.',icon:LayoutTemplate,defaultData:websiteBuilderStory('website-story-starter')},
 {type:'media_gallery',title:'Staggered Focus Cards',category:'Media & Visuals',description:'Three editable image cards with an optional contrasting center card.',icon:LayoutTemplate,defaultData:focusImageCards('focus-cards')},
 {type:'media_gallery',title:'Contrast Image Cards',category:'Media & Visuals',description:'Three editable pictures with page-brand contrasting copy surfaces.',icon:LayoutTemplate,defaultData:contrastImageCards('contrast-cards')},
 {type:'media_text_split',title:'Inset Surface Panel',category:'Storytelling & Narrative',description:'Editable picture and copy in a rounded Light or Contrast panel.',icon:LayoutTemplate,defaultData:insetSurfacePanel('inset-panel')},
 {type:'media_gallery',title:'Three Images — Staggered',category:'Media & Visuals',description:'Three independent images with editable headings and descriptions. Open staggered presentation, no links.',icon:LayoutTemplate,defaultData:staggeredImageCards('staggered-starter')},
 {type:'media_text_split',title:'Wide Visual — Centered Header',category:'Storytelling & Narrative',description:'Editable centered headline and introduction above one image. No tabs, cards or actions.',icon:LayoutTemplate,defaultData:centeredWideVisual('centered-visual-starter')},
 {type:'media_text_split',title:'Split Visual — Surface Panel',category:'Storytelling & Narrative',description:'Editable title and description beside one image inside a page-brand surface panel. No cards or actions.',icon:LayoutTemplate,defaultData:splitVisualSurface('surface-panel-starter')},
 {type:'media_text_split',title:'Wide Visual — Centered Media Panel',category:'Storytelling & Narrative',description:'Editable centered introduction above a rounded media-only Light or Contrast panel.',icon:LayoutTemplate,defaultData:containedWideCentered('centered-panel')},
 {type:'media_text_split',title:'Wide Visual — Contained Media Panel',category:'Storytelling & Narrative',description:'Editable introduction above a rounded media-only Light or Contrast panel.',icon:LayoutTemplate,defaultData:containedWideEditorial('contained-visual')},
 {type:'media_text_split',title:'Wide Visual — Editorial Header',category:'Storytelling & Narrative',description:'Editable headline and introduction above one wide image or GIF. No cards or actions.',icon:LayoutTemplate,defaultData:wideVisualEditorial('wide-visual-starter')},
 {type:'media_text_split',title:'Website Builder Overview',category:'Storytelling & Narrative',description:'One editable builder illustration with independent icon labels. No tabs or actions.',icon:LayoutTemplate,defaultData:websiteBuilderOverview('website-builder-starter')},
 {type:'pricing_table',title:'Plan Comparison',category:'Conversion & Leads',description:'Compare editable plans or reference existing plan facts and terms.',icon:LayoutTemplate,defaultData:planComparison('comparison-starter')},
 {type:'faq_accordion',title:'Framed FAQ',category:'Support & Contact',description:'Editable questions beside a compact heading, with framed answers.',icon:LayoutTemplate,defaultData:framedFaq('framed-faq-starter')},
 {type:'media_gallery',title:'Focused Steps',category:'Storytelling & Narrative',description:'Editable step navigation with one focused image, explanation and progress controls.',icon:LayoutTemplate,defaultData:focusedSteps('focused-starter')},
 {type:'media_gallery',title:'Scroll Stack Cards',category:'Storytelling & Narrative',description:'Editable image and text steps that stack as you scroll, with a static mobile layout.',icon:LayoutTemplate,defaultData:scrollStackCards('stack-starter')},
 {type:'media_text_split',title:'Team Story Overview',category:'Storytelling & Narrative',description:'One composite image beside editable copy and icon labels.',icon:LayoutTemplate,defaultData:teamStoryOverview('team-story-starter')},
 {type:'media_gallery',title:'Editorial Knowledge Triptych',category:'Storytelling & Narrative',description:'Editorial heading with three independently editable image and text cards.',icon:LayoutTemplate,defaultData:knowledgeTriptych('knowledge-starter')},
 {type:'media_gallery',title:'Image Flow',category:'Storytelling & Narrative',description:'Three replaceable card images with staggered float and page-brand SVG connectors. Card copy stays inside each image.',icon:LayoutTemplate,defaultData:imageFlow('image-flow-starter')},
 {type:'hero_banner',title:'Centered Composite Hero',category:'Header & Intro',description:'Page-brand aligned headline, two optional actions and independently replaceable wide artwork.',icon:LayoutTemplate,defaultData:centeredCompositeHero('centered-composite-starter')},
 {type:'hero_banner',title:'Split Image Hero',category:'Header & Intro',description:'Editable split Hero with replaceable artwork and two optional buttons. Add your own destinations.',icon:LayoutTemplate,defaultData:splitCompositeHero('split-image-hero')},
 {type:'media_text_split',title:'Corporate Marketplace',category:'Storytelling & Narrative',description:'Connected marketplace artwork, split introduction, editable benefit icons and feature-page action.',icon:LayoutTemplate,defaultData:corporateMarketplaceSection('marketplace-starter')},
 {type:'media_text_split',title:'Corporate Courier',category:'Storytelling & Narrative',description:'Parcel handoff artwork, delivery journey, editable courier logo marquee and Shipping action.',icon:LayoutTemplate,defaultData:corporateCourierSection('courier-starter')},
 {type:'media_text_split',title:'Corporate Payments',category:'Storytelling & Narrative',description:'Centered payment story with embedded workflow artwork, editable logo marquee and feature-page action.',icon:LayoutTemplate,defaultData:corporatePaymentSection('payments-starter')},
 ...corporateGrowthSections('growth-starter').map((s,i)=>({type:s.type,title:i===0?'Corporate AI Support':'Corporate Marketing Studio',category:'Storytelling & Narrative' as SectionTemplateOption['category'],description:'Editable corporate story with independent photograph, benefits, workflow and feature-page actions.',icon:LayoutTemplate,defaultData:s})),
 ...corporateLandingSections('corporate-starter').map((s,i)=>({type:s.type,title:['Corporate Centered Hero','Product Preview Tabs','Corporate Feature Story'][i],category:(i===0?'Header & Intro':i===1?'Media & Visuals':'Storytelling & Narrative') as SectionTemplateOption['category'],description:'Editable corporate layout with site-aligned content and reusable media.',icon:LayoutTemplate,defaultData:s})),
 ...saasSections('saas-starter').map(s=>({type:s.type,title:s.pricingPreset==='saas-packages'?'SaaS Packages':s.layoutPreset==='saas-hero'?'SaaS Product Hero':s.layoutPreset==='saas-analytics'?'SaaS Analytics Showcase':s.type==='faq_accordion'?'SaaS Split FAQ':s.type==='cta_strip'?'SaaS Closing CTA':s.featureCardsPreset==='saas-modules'?'SaaS Module Strip':s.featureCardsPreset==='saas-addons'?'SaaS Add-ons':s.mediaPosition==='left'?'SaaS Showcase — Media Left':'SaaS Showcase — Media Right',category:(s.type==='pricing_table'||s.type==='cta_strip'?'Conversion & Leads':s.type==='faq_accordion'?'Support & Contact':'Storytelling & Narrative') as SectionTemplateOption['category'],description:'Spacious SaaS layout with shared editable content, media and actions.',icon:LayoutTemplate,defaultData:s})),
 ...([0,1,5,6] as const).map((index):SectionTemplateOption=>{const section=logisticsSections({},'industrial-starter')[index];const {id,...defaultData}=section;return {type:section.type,title:index===0?'Industrial Container Hero':index===1?'Industrial Service Cards':index===5?'Industrial Workflow':'Industrial FAQ',category:index===0?'Header & Intro':index===6?'Support & Contact':'Benefits & Trust',description:'Editable industrial layout with shared content, typography, links and motion controls.',icon:Layers,badge:'Industrial',defaultData};}),
  {type:'promotion_highlights',title:'Promotion Highlights',category:'Commerce & Shop',description:'Live promotion offers in photographic cards. Automatic or selected active vouchers with shared content, image and design controls.',icon:Tag,badge:'Editorial',defaultData:promotionHighlightDefaults()},
  {type:'shop_navigation',title:'Collection Banners',category:'Commerce & Shop',description:'Image-overlay collection banners with editable copy, links and visual proportions. One banner per row on mobile.',icon:Image,badge:'Editorial',defaultData:collectionBannerDefaults()},
  {type:'shop_navigation',title:'Shop Navigation',category:'Commerce & Shop',description:'Linked photograph cards for categories, collections or custom destinations. Shared image and typography controls.',icon:Grid,badge:'Editorial',defaultData:{type:'shop_navigation',title:'Shop by room',subtitle:'',content:'',buttonText:'Explore all rooms',buttonLink:'/shop',isVisible:true,containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',paddingY:'normal',columnsCount:5,textAlignment:'left',backgroundColor:'custom',textColor:'dark',customColors:{...createSectionColorDefaults(),customBgColor:'#f7f6f2',titleColor:'#292b24',contentColor:'#66685d',primaryBtnBg:'#454e37'},typography:{title:{fontFamily:'serif',fontSize:32,fontWeight:400},itemTitle:{fontFamily:'serif',fontSize:18,fontWeight:400}},items:[{"id": "shop-living", "title": "Living Room", "sourceType": "custom", "linkUrl": "/shop", "imageUrl": "/storage/cms/media/e6774f4c-4d03-4ff8-98b3-152174211cae.png"}, {"id": "shop-dining", "title": "Dining Room", "sourceType": "custom", "linkUrl": "/shop", "imageUrl": "/storage/cms/media/712c6f25-32db-459b-96cb-39e4a6fad163.png"}, {"id": "shop-lighting", "title": "Lighting", "sourceType": "custom", "linkUrl": "/shop", "imageUrl": "/storage/cms/media/28ab0cb3-b015-4596-bd2e-9f0028bf94e9.png"}, {"id": "shop-decor", "title": "Decor", "sourceType": "custom", "linkUrl": "/shop", "imageUrl": "/storage/cms/media/524de4a8-d5ae-4fd8-8502-f05e9d6ecfbc.png"}, {"id": "shop-bedroom", "title": "Bedroom", "sourceType": "custom", "linkUrl": "/shop", "imageUrl": "/storage/cms/media/cccfed70-efe4-4185-be48-8a0165212c71.png"}]}},
  {
    type:'hero_slider',title:'Editorial Overlay Slider',category:'Header & Intro',
    description:'A photographic carousel with an editable light text panel, actions and optional captions.',icon:Sliders,badge:'Editorial',
    defaultData:{type:'hero_slider',title:'The Considered Home',subtitle:'THE CONSIDERED HOME',content:'Considered pieces for everyday living.',isVisible:true,sliderPreset:'editorial-overlay',containerMode:'full-bleed',sectionBorder:'none',motionPreset:'subtle-lift',paddingY:'compact',backgroundColor:'bg-white',textColor:'dark',textAlignment:'left',sliderHeight:'normal',sliderAutoplay:true,sliderInterval:7,overlayOpacity:0,
      customColors:{...createSectionColorDefaults(),eyebrowColor:'#626547',titleColor:'#292b24',contentColor:'#66685d',primaryBtnBg:'#454e37',primaryBtnText:'#ffffff',secondaryBtnBg:'#faf8f1',secondaryBtnText:'#454e37'},
      items:[{id:'editorial-slide-1',title:'Make room\nfor everyday\nbeauty.',subtitle:'',description:'Thoughtful pieces for a more\nmeaningful home.',imageUrl:'/storage/cms/media/b773291f-3f89-42ec-b036-efb180c23494.png',buttonText:'Shop the edit',linkUrl:'/shop',caption:'Home\nfor a brighter\neveryday.'}]
    }
  },
  // --- 1. HEADER & INTRO ---
  {
    type: 'hero_banner',
    title: 'Hero Billboard & Video Banner',
    category: 'Header & Intro',
    description: 'Impactful full-width billboard with background image/video, subtitle, headline, paragraph, and 2 CTA buttons.',
    icon: Image,
    badge: 'Popular',
    defaultData: {
      type: 'hero_banner',
      title: 'New Season Collection 2026',
      subtitle: 'EXCLUSIVE RELEASE',
      content: 'Discover acoustic perfection through precision engineering and timeless Scandinavian craftsmanship.',
      mediaUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&auto=format&fit=crop&q=80',
      mediaType: 'image',
      buttonText: 'Shop New Arrivals',
      buttonLink: '/collections/new-arrivals',
      secondaryButtonText: 'Explore Lookbook',
      secondaryButtonLink: '/pages/lookbook',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'spacious',
      overlayOpacity: 50,
      isVisible: true
    }
  },
  {
    type: 'hero_slider',
    title: 'Full Banner Background Slider',
    category: 'Header & Intro',
    description: 'Cinematic full-width background carousel with animated auto-rotating slides, custom overlay badges, headlines, CTA buttons, and interactive dots/arrows.',
    icon: Sliders,
    badge: 'Popular',
    defaultData: {
      type: 'hero_slider',
      title: 'Experience Pure Acoustic Fidelity',
      subtitle: 'NEW TITANIUM FLAGSHIP SERIES',
      content: 'Custom 50mm beryllium transducers engineered with active damping for zero-distortion listening.',
      buttonText: 'Explore Collection',
      buttonLink: '/collections/all',
      secondaryButtonText: 'Watch Atelier Film',
      secondaryButtonLink: '#film',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'spacious',
      overlayOpacity: 55,
      sliderInterval: 5,
      sliderAutoplay: true,
      sliderHeight: 'spacious',
      isVisible: true,
      items: [
        {
          id: 'slide-1',
          title: 'Experience Pure Acoustic Fidelity',
          subtitle: 'FLAGSHIP SERIES 2026',
          description: 'Custom 50mm beryllium transducers engineered with active damping for zero-distortion listening.',
          imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&auto=format&fit=crop&q=80',
          badge: 'Exclusive Launch',
          linkUrl: '/products/aurora-flagship',
          buttonText: 'Shop Flagship — $499',
          secondaryButtonText: 'View Tech Specs'
        },
        {
          id: 'slide-2',
          title: 'Hybrid Active Noise Cancellation 4.0',
          subtitle: 'STUDIO-GRADE ISOLATION',
          description: 'Adaptive algorithmic cancellation eliminating 99.6% of ambient frequency interference for uninterrupted immersion.',
          imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1600&auto=format&fit=crop&q=80',
          badge: 'Adaptive ANC',
          linkUrl: '/products/aurora-anc',
          buttonText: 'Discover ANC Series',
          secondaryButtonText: 'Compare Models'
        },
        {
          id: 'slide-3',
          title: 'Handcrafted Scandinavian Craftsmanship',
          subtitle: 'ATELIER LEATHER & TITANIUM',
          description: 'Aerospace-grade structural chassis paired with supple top-grain leather earcups for fatigue-free luxury.',
          imageUrl: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=1600&auto=format&fit=crop&q=80',
          badge: 'Artisan Heritage',
          linkUrl: '/pages/craftsmanship',
          buttonText: 'Explore the Atelier',
          secondaryButtonText: 'Find Nearest Lounge'
        }
      ]
    }
  },
  {
    type: 'split_hero',
    title: 'Split Hero with Floating Product',
    category: 'Header & Intro',
    description: 'Modern asymmetrical 2-column hero featuring left-aligned compelling copy and right-side interactive product highlight card.',
    icon: Split,
    badge: 'New',
    defaultData: {
      type: 'split_hero',
      title: 'Next-Gen Wireless Audiophile Acoustics',
      subtitle: 'TITANIUM SIGNATURE SERIES',
      content: 'Engineered with 50mm custom beryllium drivers, active magnetic damping, and 60-hour lossless wireless runtime.',
      mediaUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1000&auto=format&fit=crop&q=80',
      mediaType: 'image',
      buttonText: 'Order Now — $499',
      buttonLink: '/products/aurora-flagship',
      secondaryButtonText: 'Read Tech Specs',
      secondaryButtonLink: '#specs',
      textAlignment: 'left',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      dealBadge: 'In Stock • Ships in 24h',
      cardBadge: 'Flagship Model',
      cardTitle: 'Aurora Titanium Series X',
      cardSubtitle: '50mm Beryllium • LDAC 24/96',
      cardPrice: 499,
      cardExtra: 'Free Express DHL',
      isVisible: true
    }
  },
  {
    type: 'announcement_bar',
    title: 'Marquee Announcement & Ticker Bar',
    category: 'Header & Intro',
    description: 'Dynamic scrolling marquee ticker highlighting free shipping, limited-time flash codes, or store notices.',
    icon: Megaphone,
    defaultData: {
      type: 'announcement_bar',
      title: 'Global Express Dispatch Available • 30-Day In-Home Trial • 5-Year Acoustic Hardware Guarantee',
      subtitle: 'LIMITED TIME NOTICE',
      content: '',
      tickerSpeed: 'normal',
      backgroundColor: 'bg-gradient-indigo',
      textColor: 'light',
      paddingY: 'compact',
      isVisible: true,
      items: [
        { id: 'ann-1', title: 'Global Express Dispatch Available', icon: 'Truck', badge: 'Fast Delivery' },
        { id: 'ann-2', title: '30-Day In-Home Acoustic Trial', icon: 'Sparkles', badge: 'Risk-Free' },
        { id: 'ann-3', title: '5-Year Atelier Acoustic Guarantee', icon: 'ShieldCheck', badge: 'Warranty' },
        { id: 'ann-4', title: 'Complimentary Bespoke Monogramming', icon: 'Award', badge: 'Limited' }
      ]
    }
  },

  // --- 2. STORYTELLING & NARRATIVE ---
  {
    type: 'media_text_split',
    title: 'Media & Story Split',
    category: 'Storytelling & Narrative',
    description: 'Side-by-side balanced 2-column layout pairing high-res photography or video with editorial narrative.',
    icon: Columns,
    defaultData: {
      type: 'media_text_split',
      title: 'Monolithic Titanium CNC Milling',
      subtitle: 'CRAFTSMANSHIP & INNOVATION',
      content: 'Each chassis is machined over 4.5 hours from an aerospace-grade titanium ingot. Zero resonance, ultra-durable, and featherlight.',
      mediaUrl: 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1000&auto=format&fit=crop&q=80',
      mediaType: 'image',
      mediaPosition: 'right',
      mediaAspectRatio: '4:3',
      buttonText: 'Learn About Materials',
      buttonLink: '/pages/materials',
      textAlignment: 'left',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'rich_text',
    title: 'Editorial Text & Quote',
    category: 'Storytelling & Narrative',
    description: 'Clean reading layout featuring headline, rich paragraph blocks, and an elegant pull-quote with author badge.',
    icon: FileText,
    defaultData: {
      type: 'rich_text',
      title: 'The Manifesto of Pure Sound',
      subtitle: 'OUR PHILOSOPHY',
      content: 'Music is not just background vibrations; it is an intimate emotional connection between artist and listener.',
      quote: '“We refuse to compromise on acoustic fidelity. If a component does not elevate the music, it has no place in our design.”',
      authorName: 'Henrik Vang',
      authorRole: 'Chief Acoustic Architect',
      authorAvatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'timeline_story',
    title: 'Brand Milestone & Heritage Timeline',
    category: 'Storytelling & Narrative',
    description: 'Vertical or sequential timeline showcasing brand heritage, product evolution, and engineering breakthroughs.',
    icon: Clock,
    badge: 'New',
    defaultData: {
      type: 'timeline_story',
      title: 'A Decade of Acoustic Mastery',
      subtitle: 'OUR HERITAGE (2016 – 2026)',
      content: 'Trace the milestones that established Aurora as the premier Scandinavian boutique audio maker.',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'time-1',
          date: '2016',
          title: 'Stockholm Atelier Founded',
          description: 'A small group of ex-aerospace acoustic engineers built the first zero-resonance headphone prototype.',
          badge: 'Origins'
        },
        {
          id: 'time-2',
          date: '2019',
          title: 'Beryllium Transducer Patent',
          description: 'Patented our micro-vapor deposition diaphragm technique, achieving ultra-linear frequency response.',
          badge: 'Breakthrough'
        },
        {
          id: 'time-3',
          date: '2023',
          title: 'Global Studio Adoption',
          description: 'Over 800 certified mastering and mixing studios worldwide deployed Aurora monitoring headsets.',
          badge: 'Industry Standard'
        },
        {
          id: 'time-4',
          date: '2026',
          title: 'Universal Wireless Architecture',
          description: 'Pioneering zero-compression 24-bit/96kHz lossless wireless transmission for reference fidelity on the go.',
          badge: 'Latest Release'
        }
      ]
    }
  },
  {
    type: 'stats_counter',
    title: 'Impact Numbers & Metric Counters',
    category: 'Storytelling & Narrative',
    description: 'High-visibility metric banner displaying key figures, satisfaction rates, awards won, and customer count.',
    icon: TrendingUp,
    defaultData: {
      type: 'stats_counter',
      title: 'Trusted by Producers & Audiophiles Worldwide',
      subtitle: 'PROVEN EXCELLENCE',
      content: 'Quantifying our decade-long commitment to uncompromising acoustic purity.',
      columnsCount: 4,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'stat-1',
          value: '99.4%',
          title: 'Acoustic Clarity Rating',
          description: 'Laboratory tested harmonic distortion under 0.05% THD'
        },
        {
          id: 'stat-2',
          value: '48+',
          title: 'International Design Awards',
          description: 'Recognized by RedDot, iF Gold, and Good Design Tokyo'
        },
        {
          id: 'stat-3',
          value: '120k+',
          title: 'Headphones Hand-Calibrated',
          description: 'Individually measured in Stockholm anechoic chamber'
        },
        {
          id: 'stat-4',
          value: '5-Year',
          title: 'Hardware Guarantee',
          description: 'Comprehensive warranty with complimentary repair logistics'
        }
      ]
    }
  },


  {type:'shop_navigation',title:'Skin Need Cards',category:'Commerce & Shop',description:'Photographic cards with editable overlay titles, descriptions and destinations.',icon:Image,defaultData:{type:'shop_navigation',title:'Shop by skin need',isVisible:true,shopNavigationPreset:'image-overlay',columnsCount:4,items:[],containerMode:'page',motionPreset:'subtle-lift',paddingY:'compact'}},
  {type:'shop_navigation',title:'Ingredient Caption Cards',category:'Commerce & Shop',description:'Photographs with light caption panels and editable ingredient guides.',icon:Image,defaultData:{type:'shop_navigation',title:'Thoughtful ingredients',isVisible:true,shopNavigationPreset:'image-caption',columnsCount:3,items:[],containerMode:'page',motionPreset:'subtle-lift',paddingY:'compact'}},
  {type:'product_listing',title:'Beauty Essentials',category:'Commerce & Shop',description:'Live catalog grid with centered details and purchase actions.',icon:ShoppingBag,defaultData:{type:'product_listing',title:'Daily essentials',isVisible:true,listingPreset:'beauty-grid',sourceType:'manual',productIds:[],productLimit:4,columnsCount:4,containerMode:'page',motionPreset:'subtle-lift',paddingY:'compact'}},
  {type:'media_gallery',title:'Routine Photo Steps',category:'Storytelling & Narrative',description:'Numbered photographic steps. Add, remove and reorder every step.',icon:Image,defaultData:{type:'media_gallery',title:'Your daily routine',isVisible:true,galleryPreset:'routine-steps',columnsCount:3,items:[],containerMode:'page',motionPreset:'subtle-lift',paddingY:'compact'}},
  {type:'media_text_split',title:'Flush Split Story',category:'Storytelling & Narrative',description:'Edge-to-edge photograph beside editable text and a button.',icon:Image,defaultData:{type:'media_text_split',title:'Your story',isVisible:true,layoutPreset:'flush-split',mediaPosition:'right',mediaUrl:'',content:'',buttonText:'Explore',buttonLink:'',containerMode:'page',motionPreset:'subtle-lift',paddingY:'compact'}},
  {type:'shop_navigation',title:'Catalog Caption Cards',category:'Commerce & Shop',description:'Editable photographs, content and supported destinations.',icon:Image,defaultData:{"type": "shop_navigation", "title": "Catalog Caption Cards", "isVisible": true, "shopNavigationPreset": "catalog-captions", "containerMode": "page", "paddingY": "compact", "motionPreset": "subtle-lift", "columnsCount": 6, "items": []}},
  {type:'shop_navigation',title:'Recipe Cards',category:'Commerce & Shop',description:'Editable photographs, content and supported destinations.',icon:Image,defaultData:{"type": "shop_navigation", "title": "Recipe Cards", "isVisible": true, "shopNavigationPreset": "recipe-cards", "containerMode": "page", "paddingY": "compact", "motionPreset": "subtle-lift", "columnsCount": 3, "items": []}},
  {type:'product_listing',title:'Pantry Shop Grid',category:'Commerce & Shop',description:'Editable photographs, content and supported destinations.',icon:ShoppingBag,defaultData:{"type": "product_listing", "title": "Pantry Shop Grid", "isVisible": true, "listingPreset": "pantry-grid", "containerMode": "page", "paddingY": "compact", "motionPreset": "subtle-lift", "sourceType": "manual", "productIds": [], "columnsCount": 4, "productLimit": 4}},
  {type:'product_listing',title:'Pantry Occasion Tabs',category:'Commerce & Shop',description:'Editable photographs, content and supported destinations.',icon:ShoppingBag,defaultData:{"type": "product_listing", "title": "Pantry Occasion Tabs", "isVisible": true, "listingPreset": "pantry-tabs", "containerMode": "page", "paddingY": "compact", "motionPreset": "subtle-lift", "productTabs": [], "columnsCount": 4, "productLimit": 4}},
  {type:'media_text_split',title:'Producer Story',category:'Storytelling & Narrative',description:'Editable photographs, content and supported destinations.',icon:Image,defaultData:{"type": "media_text_split", "title": "Producer Story", "isVisible": true, "layoutPreset": "producer-story", "containerMode": "page", "paddingY": "compact", "motionPreset": "subtle-lift", "mediaPosition": "left", "mediaUrl": "", "content": "", "items": []}},
  {type:'newsletter_signup',title:'Inline Newsletter Strip',category:'Conversion & Leads',description:'Compact email signup using the existing subscriber coupon service.',icon:Mail,defaultData:{type:'newsletter_signup',title:'Join Our Table',content:'Stay in the loop.',buttonText:'Subscribe',isVisible:true,newsletterPreset:'inline-signup',containerMode:'page',paddingY:'compact',motionPreset:'subtle-lift'}},
  // --- 3. COMMERCE & SHOP ---
  {
    type: 'product_grid',
    title: 'Product Showcase Grid',
    category: 'Commerce & Shop',
    description: 'Dynamic responsive grid displaying catalog products with live pricing, badges, and quick links.',
    icon: ShoppingBag,
    badge: 'Essential',
    defaultData: {
      type: 'product_grid',
      title: 'Featured Acoustic Essentials',
      subtitle: 'CURATED PICKS',
      content: 'Hand-selected instruments and accessories for discerning audiophiles.',
      columnsCount: 3,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type:'product_listing',title:'Editorial Product Tabs',category:'Commerce & Shop',description:'Switch between product groups in a clean editorial grid. Edit tab names and product sources in Content.',icon:Sliders,badge:'New',
    defaultData:{type:'product_listing',title:'Made for the way you live',isVisible:true,listingPreset:'editorial-tabs',productTabs:[{id:'tab-all',label:'All products',sourceType:'all'}],productLimit:4,columnsCount:4,imageRatio:'4:3',buttonText:'Shop all',buttonLink:'/shop',containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',backgroundColor:'bg-white',textColor:'dark',paddingY:'compact',textAlignment:'left',customColors:{titleColor:'#292b24',contentColor:'#6d7267',primaryBtnBg:'#292b24'}}
  },
  {
    type:'product_listing',title:'Editorial Product Grid',category:'Commerce & Shop',description:'A clean product grid with live catalog prices, handpicked or taxonomy sources and wishlist links.',icon:Sliders,badge:'New',
    defaultData:{type:'product_listing',title:'Objects worth coming home to',isVisible:true,listingPreset:'editorial-grid',sourceType:'manual',productIds:[],productLimit:4,columnsCount:4,imageRatio:'1:1',buttonText:'Shop all products',buttonLink:'/shop',containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',backgroundColor:'bg-white',textColor:'dark',paddingY:'normal',textAlignment:'left',customColors:{titleColor:'#292b24',contentColor:'#56594f',primaryBtnBg:'#292b24'}}
  },
  {
    type: 'product_listing',
    title: 'Product Listing Carousel',
    category: 'Commerce & Shop',
    description: 'Interactive product slider with auto-sliding, side navigation arrows, category/collection catalog mapping, and discount cards.',
    icon: Sliders,
    badge: 'New',
    defaultData: {
      type: 'product_listing',
      title: 'Trending Hardware & Lifestyle Tech',
      subtitle: 'FEATURED DISCOUNTS',
      content: 'Explore limited-time promotional deals with instant dispatch and premium warranty.',
      sourceType: 'category',
      sourceId: '',
      productLimit: 6,
      autoSlide: true,
      autoSlideInterval: 3.5,
      showNavArrows: true,
      columnsCount: 3,
      textAlignment: 'left',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'collection_tabs',
    title: 'Category & Collection Tab Showcase',
    category: 'Commerce & Shop',
    description: 'Filterable interactive tab bar switching between Over-Ear, In-Ear, DAC Amps, and Cables effortlessly.',
    icon: Layers,
    badge: 'New',
    defaultData: {
      type: 'collection_tabs',
      title: 'Explore Aurora Audio Ecosystem',
      subtitle: 'BROWSE BY CATEGORY',
      content: 'Discover engineered precision for every listening environment.',
      tabCategories: ['Electronics & Gadgets', 'Premium Audio', 'Best Sellers 2026', 'Studio Essentials'],
      tabs: [
        { id: 'tab-1', label: 'Electronics & Gadgets', sourceType: 'category', sourceId: 'cat-electronics', productLimit: 4 },
        { id: 'tab-2', label: 'Premium Audio', sourceType: 'category', sourceId: 'cat-audio', productLimit: 4 },
        { id: 'tab-3', label: 'Best Sellers 2026', sourceType: 'collection', sourceId: 'col-best-sellers', productLimit: 4 },
        { id: 'tab-4', label: 'Studio Essentials', sourceType: 'collection', sourceId: 'col-studio-essentials', productLimit: 4 }
      ],
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'featured_deal',
    title: 'Flash Deal & Product Spotlight',
    category: 'Commerce & Shop',
    description: 'High-converting spotlight section featuring live countdown timer, discount pill, and direct checkout link.',
    icon: Flame,
    badge: 'High Converting',
    defaultData: {
      type: 'featured_deal',
      title: 'Limited Edition: Midnight Matte Titanium',
      subtitle: 'EXCLUSIVE FLASH OFFER',
      content: 'Only 150 serialized units produced. Includes bespoke Italian leather flight case and balanced silver cable.',
      mediaUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1000&auto=format&fit=crop&q=80',
      mediaType: 'image',
      discountPercent: 25,
      dealBadge: 'Save $120 • 48 Hours Only',
      buttonText: 'Claim Special Edition ($379)',
      buttonLink: '/products/aurora-flagship',
      secondaryButtonText: 'View Serial Registry',
      secondaryButtonLink: '/pages/authenticity',
      textAlignment: 'left',
      backgroundColor: 'bg-gradient-indigo',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'pricing_table',
    title: 'Tiered Pricing & Plan Comparison',
    category: 'Commerce & Shop',
    description: '3-tier pricing cards with featured plan highlight, itemized checkmarks, and primary checkout buttons.',
    icon: Tag,
    badge: 'New',
    defaultData: {
      type: 'pricing_table',
      title: 'Choose Your Sound Tier',
      subtitle: 'TRANSPARENT EDITIONS',
      content: 'Whether for daily commute or critical studio mastering, find the exact configuration for your ears.',
      columnsCount: 3,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'price-1',
          title: 'Essential Edition',
          price: 299,
          badge: 'Commuter',
          description: 'Precision dynamic drivers for everyday high-resolution listening.',
          features: [
            '40mm Beryllium-coated drivers',
            'Bluetooth 5.3 with AAC/aptX',
            '35h Battery life with ANC',
            'Durable aluminum frame',
            '2-Year warranty'
          ],
          linkUrl: '/products/aurora-essential'
        },
        {
          id: 'price-2',
          title: 'Reference Master',
          price: 499,
          badge: 'Most Popular',
          isHighlighted: true,
          description: 'Open-back planar magnetic transducers calibrated for mastering engineers.',
          features: [
            '50mm Custom Planar Magnetic',
            'Lossless LDAC & 24/96 stream',
            'Handmade lambskin ear cushions',
            'Acoustically matched pairs (±0.5dB)',
            '5-Year comprehensive warranty',
            'Complimentary Hard Shell Case'
          ],
          linkUrl: '/products/aurora-flagship'
        },
        {
          id: 'price-3',
          title: 'Bespoke Atelier',
          price: 899,
          badge: 'Custom Engraved',
          description: 'Machined from Grade-5 titanium ingot with pure silver balanced cabling.',
          features: [
            'Hand-selected driver matching',
            'Bespoke laser-etched serial & name',
            'Pure 99.999% OCC Silver Litz Cable',
            'Walnut wooden display stand',
            'Lifetime Atelier Concierge Care'
          ],
          linkUrl: '/products/aurora-custom'
        }
      ]
    }
  },

  // --- 4. MEDIA & VISUALS ---
  {
    type: 'media_gallery',
    title: 'Visual Lookbook Gallery',
    category: 'Media & Visuals',
    description: 'Multi-column photo lookbook with hover zoom, title overlays, and caption links.',
    icon: Image,
    defaultData: {
      type: 'media_gallery',
      title: 'In the Wild: Everyday Sound',
      subtitle: 'COMMUNITY & EDITORIAL',
      content: 'Explore how creators and artists integrate Aurora gear into their daily creative workflows.',
      columnsCount: 3,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'gal-1',
          title: 'Nordic Studio Setup',
          description: 'Reykjavik Recording Session',
          imageUrl: 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?w=800&auto=format&fit=crop&q=80',
          linkUrl: '/pages/studios'
        },
        {
          id: 'gal-2',
          title: 'Commuter Series',
          description: 'Subway Isolation in Tokyo',
          imageUrl: 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=800&auto=format&fit=crop&q=80',
          linkUrl: '/pages/commute'
        },
        {
          id: 'gal-3',
          title: 'Acoustic Chamber',
          description: 'Anechoic Frequency Verification',
          imageUrl: 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=800&auto=format&fit=crop&q=80',
          linkUrl: '/pages/labs'
        }
      ]
    }
  },
  {
    type: 'video_embed',
    title: 'Video Stream Player Embed',
    category: 'Media & Visuals',
    description: 'Responsive video embed for YouTube, Vimeo, or direct MP4 streams with full controls.',
    icon: PlaySquare,
    defaultData: {
      type: 'video_embed',
      title: 'Behind the Design: The Titanium Journey',
      subtitle: 'DOCUMENTARY SHORT FILM',
      content: 'Watch how our industrial engineers craft the monolithic headband gimbals in our Stockholm atelier.',
      videoEmbedUrl: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'before_after',
    title: 'Interactive Before & After Slider',
    category: 'Media & Visuals',
    description: 'Interactive comparison slider demonstrating noise cancellation isolation, craftsmanship, or acoustic refinement.',
    icon: Sliders,
    badge: 'Interactive',
    defaultData: {
      type: 'before_after',
      title: 'Active Isolation vs Raw Environment',
      subtitle: 'NOISE REDUCTION VISUALIZER',
      content: 'Drag the slider to experience the dramatic 38dB broadband acoustic attenuation of Aurora Hybrid ANC.',
      beforeImageUrl: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=1200&auto=format&fit=crop&q=80',
      afterImageUrl: 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=1200&auto=format&fit=crop&q=80',
      beforeLabel: 'Standard Ambience (Crowded)',
      afterLabel: 'Aurora Pure Silence (38dB Attenuated)',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'instagram_feed',
    title: 'Shoppable Social Grid & UGC Wall',
    category: 'Media & Visuals',
    description: 'Instagram-style photo grid featuring verified customer tags, creator handles, and direct shop links.',
    icon: Instagram,
    badge: 'New',
    defaultData: {
      type: 'instagram_feed',
      title: 'Seen on Creators Worldwide',
      subtitle: 'TAG @AURORA.AUDIO TO BE FEATURED',
      content: 'Join over 100,000 audiophiles sharing their listening setups across the globe.',
      columnsCount: 4,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'ig-1',
          title: 'Late night master session',
          handle: '@audio.marcus',
          imageUrl: 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?w=600&auto=format&fit=crop&q=80',
          linkUrl: 'https://instagram.com'
        },
        {
          id: 'ig-2',
          title: 'Stockholm commute companion',
          handle: '@elena_nordic',
          imageUrl: 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=600&auto=format&fit=crop&q=80',
          linkUrl: 'https://instagram.com'
        },
        {
          id: 'ig-3',
          title: 'Unboxing the Titanium edition',
          handle: '@techunfiltered',
          imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80',
          linkUrl: 'https://instagram.com'
        },
        {
          id: 'ig-4',
          title: 'Vinyl & Aurora matching pair',
          handle: '@vinyl_soul',
          imageUrl: 'https://images.unsplash.com/photo-1539185441755-769473a23570?w=600&auto=format&fit=crop&q=80',
          linkUrl: 'https://instagram.com'
        }
      ]
    }
  },

  // --- 5. BENEFITS & TRUST ---
  {
    type: 'values_list',
    title: 'Values List',
    category: 'Benefits & Trust',
    description: 'Numbered editorial rows for brand values, principles or commitments. Titles, descriptions and automatic numbering.',
    icon: Layers,
    defaultData: {
      type: 'values_list',title:'Our values',subtitle:'OUR VALUES',content:'',
      isVisible:true,valuesShowHeading:false,valuesShowDividers:true,valuesRowSpacing:'normal',
      valuesNumberColor:'#d4d4d4',valuesDividerColor:'#e5e5e5',
      backgroundColor:'bg-white',textColor:'dark',paddingY:'normal',
      customColors:{eyebrowColor:'#4f46e5',titleColor:'#171717',contentColor:'#737373'},
      items:[
        {id:'value-1',title:'Thoughtful choices',description:'We prioritise materials, design and function that make sense — for you and for the world around us.'},
        {id:'value-2',title:'Clear information',description:'We keep things straightforward, from product details to how we work, so you can shop with confidence.'},
        {id:'value-3',title:'Everyday usefulness',description:'We design for real life — pieces that are versatile, durable and made to be used, not just kept.'},
      ],
    },
  },
  {
    type: 'feature_cards',
    title: 'Value Proposition & Feature Cards',
    category: 'Benefits & Trust',
    description: '3 to 4 highlight cards with customizable icons, bold titles, descriptive copy, and metric tags.',
    icon: Sparkles,
    badge: 'Popular',
    defaultData: {
      type: 'feature_cards',
      title: 'Engineered Without Compromise',
      subtitle: 'THE AURORA ADVANTAGE',
      content: 'Every product is built upon our foundational acoustic standards.',
      columnsCount: 4,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'f-1',
          title: 'Lossless Wireless',
          description: 'Ultra-low latency 24-bit/96kHz high-resolution audio streaming.',
          icon: 'Sparkles',
          badge: 'LDAC & aptX'
        },
        {
          id: 'f-2',
          title: 'Handcrafted Cushions',
          description: 'Supple full-grain leather filled with slow-rebound acoustic foam.',
          icon: 'Heart',
          badge: 'Ergonomic'
        },
        {
          id: 'f-3',
          title: 'Modular Architecture',
          description: 'Easily replaceable headbands, batteries, and magnetic earcups.',
          icon: 'ShieldCheck',
          badge: '10-Year Life'
        },
        {
          id: 'f-4',
          title: 'Carbon Neutral Delivery',
          description: '100% offset international express shipping on every order.',
          icon: 'Globe',
          badge: 'Eco Pledge'
        }
      ]
    }
  },
  {
    type: 'icon_list',
    title: 'Horizontal Trust Badges & Guarantee Row',
    category: 'Benefits & Trust',
    description: 'Streamlined trust row featuring Free Worldwide Delivery, 30-Day Return, 5-Year Warranty, and 24/7 Concierge.',
    icon: ShieldCheck,
    badge: 'Trust Builder',
    defaultData: {
      type: 'icon_list',
      title: 'The Aurora Guarantee',
      subtitle: 'RISK-FREE BUYING EXPERIENCE',
      content: '',
      columnsCount: 4,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'compact',
      isVisible: true,
      items: [
        {
          id: 'trust-1',
          title: 'Complimentary Express Shipping',
          description: 'Delivered in 2-4 business days via DHL Express',
          icon: 'Truck'
        },
        {
          id: 'trust-2',
          title: '30-Day In-Home Trial',
          description: 'Test in your home listening setup with zero return fees',
          icon: 'Heart'
        },
        {
          id: 'trust-3',
          title: '5-Year Hardware Guarantee',
          description: 'Comprehensive acoustic coverage and free part replacement',
          icon: 'ShieldCheck'
        },
        {
          id: 'trust-4',
          title: 'Stockholm Atelier Support',
          description: 'Direct audio engineering support for pairing & DAC advice',
          icon: 'Sparkles'
        }
      ]
    }
  },
  {
    type: 'comparison_matrix',
    title: 'Product Feature vs Competitor Matrix',
    category: 'Benefits & Trust',
    description: 'Side-by-side comparison table highlighting superior build materials, battery, distortion, and warranty vs mainstream brands.',
    icon: Scale,
    badge: 'New',
    defaultData: {
      type: 'comparison_matrix',
      title: 'How Aurora Compares to Mainstream Audio',
      subtitle: 'TRANSPARENT BENCHMARK',
      content: 'We engineer for lifespans measured in decades, not disposable annual product cycles.',
      comparisonOurBrandName: 'Aurora Flagship',
      comparisonCompetitorName: 'Mainstream Plastic Brands',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'comp-1',
          title: 'Chassis Material',
          description: 'Aviation-grade Monolithic Titanium vs Molded Injection Polycarbonate',
          badge: 'Aurora Wins'
        },
        {
          id: 'comp-2',
          title: 'Driver Diaphragm',
          description: '50mm Pure Vapor-Deposited Beryllium vs Standard Mylar film',
          badge: 'Zero Resonance'
        },
        {
          id: 'comp-3',
          title: 'Wireless Audio Resolution',
          description: 'Uncompressed 24-Bit / 96kHz Lossless LDAC vs Compressed 16-Bit SBC',
          badge: 'Studio Pure'
        },
        {
          id: 'comp-4',
          title: 'Repairability & Lifespan',
          description: '100% User Modular (Magnetic Pads, Swappable Battery) vs Glued Shut Obsolescence',
          badge: '10+ Year Life'
        },
        {
          id: 'comp-5',
          title: 'Warranty Coverage',
          description: '5-Year Global Direct Atelier vs 1-Year Limited Retailer',
          badge: '5x Longer'
        }
      ]
    }
  },

  // --- 6. SUPPORT & CONTACT ---
  {
    type: 'faq_accordion',
    title: 'FAQ & Accordion List',
    category: 'Support & Contact',
    description: 'Expandable Q&A accordions for customer service, shipping policies, technical guides, or care.',
    icon: HelpCircle,
    badge: 'Essential',
    defaultData: {
      type: 'faq_accordion',
      title: 'Frequently Asked Questions',
      subtitle: 'HELP & POLICIES',
      content: 'Quick answers about compatibility, warranty, and worldwide logistics.',
      textAlignment: 'left',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'faq-new-1',
          title: 'Is there a trial period for home listening?',
          description: 'Yes, we offer a 30-day risk-free home listening trial with complimentary return shipping.'
        },
        {
          id: 'faq-new-2',
          title: 'What DAC/Amps do you recommend with Aurora Flagship?',
          description: 'Aurora headphones are easily driven by portable devices, but scale extraordinarily with dedicated balanced R2R DACs.'
        },
        {
          id: 'faq-new-3',
          title: 'How do I clean and condition the lambskin leather pads?',
          description: 'Gently wipe with a dry microfiber cloth. We recommend applying organic leather balm once every 6 months.'
        },
        {
          id: 'faq-new-4',
          title: 'Do you offer international customs-cleared shipping?',
          description: 'All orders to North America, Europe, and Asia include DDP (Delivered Duty Paid)—no surprise import fees upon arrival.'
        }
      ]
    }
  },
  {type:'contact_form',title:'Enquiry Form',category:'Support & Contact',description:'Editable enquiry fields with submission disabled.',icon:Mail,defaultData:{type:'contact_form',contactPreset:'enquiry',customColors:{...createSectionColorDefaults(),titleColor:'#171717',contentColor:'#737373',eyebrowColor:'#4f46e5'},title:'Talk to our team.',content:'Our team is here to help.',contactButtonText:'Send enquiry',inquiryCategories:[{id:'order',label:'Order & delivery'},{id:'other',label:'Other enquiries'}],backgroundColor:'bg-white',textColor:'dark',paddingY:'normal',isVisible:true}},
  {type:'store_locator',title:'Location & Map',category:'Support & Contact',description:'Visitor address, hours and optional Google Maps embed.',icon:MapPin,defaultData:{type:'store_locator',contactPreset:'location',customColors:{...createSectionColorDefaults(),titleColor:'#171717',contentColor:'#737373',eyebrowColor:'#4f46e5'},title:'Visit our studio.',contactAddress:'',mapEmbedUrl:'',mapVisible:true,items:[{id:'hours',title:'Opening hours',description:'Add your opening hours',icon:'Clock'}],backgroundColor:'bg-white',textColor:'dark',paddingY:'normal',isVisible:true}},
  {
    type: 'contact_form',
    title: 'Interactive Support & Inquiries Form',
    category: 'Support & Contact',
    description: 'Embedded contact module with name, email, subject, inquiry category, and live feedback response.',
    icon: Mail,
    badge: 'Interactive',
    defaultData: {
      type: 'contact_form',
      title: 'Connect with Our Acoustic Atelier',
      subtitle: 'DEDICATED CONCIERGE',
      content: 'Have questions about pairing with your DAC setup, or looking for custom engraving? Our engineers respond within 24 hours.',
      formEmail: 'concierge@aurora-audio.se',
      contactPhone: '+46 8 123 4567 (Mon–Fri 09:00–18:00 CET)',
      contactAddress: 'Acoustic Atelier, Stockholm, Sweden',
      contactButtonText: 'Send Inquiries to Atelier',
      formSuccessMessage: 'Thank you! Your message has been routed to our Stockholm acoustic engineers.',
      subjectPlaceholder: 'e.g. Custom Engraving / Setup Inquiry',
      categoryPlaceholder: 'Select an inquiry category...',
      messagePlaceholder: 'Tell us about your audio setup or custom acoustic requirements...',
      inquiryCategories: [
        { id: 'cat-1', label: 'Product Enquiry' },
        { id: 'cat-2', label: 'Order Support' },
        { id: 'cat-3', label: 'Wholesale & Partnership' },
        { id: 'cat-4', label: 'Press & Collaboration' },
        { id: 'cat-5', label: 'Other' }
      ],
      textAlignment: 'left',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'store_locator',
    title: 'Flagship Atelier & Store Locator',
    category: 'Support & Contact',
    description: 'Physical listening lounge locations with opening hours, addresses, phone numbers, and booking links.',
    icon: MapPin,
    defaultData: {
      type: 'store_locator',
      title: 'Experience Aurora in Person',
      subtitle: 'FLAGSHIP LISTENING LOUNGES',
      content: 'Step inside our acoustically treated listening rooms in Stockholm, Tokyo, and New York for an unhurried session.',
      columnsCount: 3,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'store-1',
          title: 'Stockholm Atelier & Lab',
          subtitle: 'FLAGSHIP & FACTORY',
          description: 'Birger Jarlsgatan 14, 114 34 Stockholm, Sweden\nMon–Sat: 10:00 – 19:00',
          badge: 'Listening Bar Open',
          linkUrl: 'tel:+4681234567'
        },
        {
          id: 'store-2',
          title: 'Tokyo Ginza Lounge',
          subtitle: 'RETAIL & AUDIOPHILE BAR',
          description: '6-10-1 Ginza, Chuo City, Tokyo 104-0061, Japan\nDaily: 11:00 – 20:00',
          badge: 'Private Booths',
          linkUrl: 'tel:+81312345678'
        },
        {
          id: 'store-3',
          title: 'New York SoHo Studio',
          subtitle: 'MASTERING SUITE',
          description: '450 Broome Street, New York, NY 10013, USA\nTue–Sun: 11:00 – 18:00',
          badge: 'By Appointment',
          linkUrl: 'tel:+12125550199'
        }
      ]
    }
  },

  // --- 7. SOCIAL PROOF & REVIEWS ---
  {
    type: 'testimonials_slider',
    title: 'Customer Reviews & Rating Cards',
    category: 'Social Proof',
    description: 'Star-rating customer feedback cards with verified buyer tags, sound signature commentary, and user photos.',
    icon: Star,
    badge: 'Popular',
    defaultData: {
      type: 'testimonials_slider',
      title: 'What Audiophiles & Producers Say',
      subtitle: '4.95 / 5 AVERAGE ACROSS 1,800+ REVIEWS',
      content: 'Read verified listening impressions from Grammy-nominated producers and audio enthusiasts.',
      columnsCount: 3,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {
          id: 'rev-1',
          title: 'The most transparent midrange I have ever heard',
          description: '“The separation between vocal harmonics and acoustic guitar reverb tails is nothing short of breathtaking. I mix my entire new album with these.”',
          author: 'Søren Lindqvist',
          authorRole: 'Grammy-Nominated Producer (Stockholm)',
          authorAvatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
          rating: 5,
          badge: 'Verified Master Engineer'
        },
        {
          id: 'rev-2',
          title: 'Worth every single penny for comfort and sound',
          description: '“I wear headphones 8 hours a day for mastering work. The lambskin memory foam earcups distribute pressure evenly with zero fatigue.”',
          author: 'Maya Chen',
          authorRole: 'Senior Audio Director (Tokyo)',
          authorAvatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80',
          rating: 5,
          badge: 'Verified Buyer'
        },
        {
          id: 'rev-3',
          title: 'Titanium craftsmanship is in a league of its own',
          description: '“In a world of creaking plastic audio gear, holding the Aurora Titanium chassis feels like an heirloom timepiece. Spectacular build.”',
          author: 'David Vance',
          authorRole: 'Sound Designer (London)',
          authorAvatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&auto=format&fit=crop&q=80',
          rating: 5,
          badge: 'Verified Buyer'
        }
      ]
    }
  },
  {
    type: 'logo_cloud',
    title: 'Partner Logos',
    category: 'Social Proof',
    description: 'Upload original brand logos. Choose static placement or continuous scrolling.',
    icon: HeartHandshake,
    defaultData: {
      type: 'logo_cloud',
      customColors: {titleColor:'#233c2d',eyebrowColor:'#718168',contentColor:'#74806f'}, title: 'Brands & Software We Offer', logoPreset: 'partners', logoHeight: 42, logoGap: 48, marqueeSpeed: 32,
      subtitle: 'OUR RANGE',
      content: '',
      columnsCount: 5,
      textAlignment: 'center',
      backgroundColor: 'bg-white',
      textColor: 'dark',
      paddingY: 'compact',
      isVisible: true,
      items: []
    }
  },

  {
    type: 'cta_strip', title: 'Editorial Image Banner', category: 'Conversion & Leads',
    description: 'Background photograph with left-aligned editorial copy and a clear call to action.', icon: Megaphone, badge: 'Editorial',
    defaultData: {type:'cta_strip',ctaPreset:'image-banner',ctaBannerHeight:'normal',content:'',mediaUrl:'',secondaryButtonText:'',secondaryButtonLink:'',title:'Simple things,\nconsidered.',subtitle:'EXPLORE',buttonText:'Explore the collection',buttonLink:'/shop',isVisible:true,backgroundColor:'bg-white',textColor:'light',paddingY:'compact',imageSlots:[{target:'mediaUrl',imageOverlay:true,imageOverlayOpacity:30}],customColors:{eyebrowColor:'#ffffff',titleColor:'#ffffff',contentColor:'#ffffff',primaryBtnBg:'#4f46e5',primaryBtnText:'#ffffff'}}
  },

  // --- 8. CONVERSION & LEADS ---
  {
    type: 'cta_strip',
    title: 'Call to Action & Banner',
    category: 'Conversion & Leads',
    description: 'Promotional high-contrast banner with headline, description, primary & secondary action links.',
    icon: Megaphone,
    badge: 'High Converting',
    defaultData: {
      type: 'cta_strip',
      title: 'Ready to Transform How You Hear Music?',
      subtitle: 'JOIN THE AUDIOPHILE CIRCLE',
      content: 'Enjoy complimentary express worldwide delivery and our 5-year acoustic hardware warranty.',
      buttonText: 'Shop All Headphones',
      buttonLink: '/categories/audio',
      secondaryButtonText: 'Contact Concierge',
      secondaryButtonLink: '/contact',
      textAlignment: 'center',
      backgroundColor: 'bg-gradient-indigo',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  },
  {
    type: 'newsletter_signup',
    title: 'Lead Capture & VIP Newsletter Strip',
    category: 'Conversion & Leads',
    description: 'Email subscriber lead capture box with instant coupon incentive ($30 off first order) and privacy promise.',
    icon: Mail,
    badge: 'Lead Gen',
    defaultData: {
      type: 'newsletter_signup',
      title: 'Receive $30 Off Your First Order',
      subtitle: 'JOIN THE AURORA INSIDER CIRCLE',
      content: 'Be the first to access limited-batch atelier releases, studio master recordings, and private events.',
      buttonText: 'Claim $30 Voucher',
      formSuccessMessage: 'Your subscriber voucher has been sent to your email!',
      newsletterCouponId: '',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true
    }
  }
];

const studioStarter=(index:number):Omit<PageSection,'id'>=>{const {id,...data}=aboutStudioSections('Your store','studio-starter')[index];return data;};
const EDITORIAL_SECTION_TEMPLATES:SectionTemplateOption[]=[
  {type:'media_gallery',title:'Editorial Montage',category:'Header & Intro',description:'A centered introduction with staggered portrait photographs. Each image uses shared image tools.',icon:Image,badge:'Editorial',defaultData:studioStarter(0)},
  {type:'media_gallery',title:'Editorial Image Cards',category:'Media & Visuals',description:'Photographs with simple titles and captions beneath. No card borders or overlays.',icon:Grid,badge:'Editorial',defaultData:studioStarter(3)},
  {type:'feature_cards',title:'Editorial Text Columns',category:'Storytelling & Narrative',description:'Clean columns of headings and paragraphs for brand ideas, principles and values.',icon:Columns,badge:'Editorial',defaultData:studioStarter(2)},
];
const shippingChapterStarter=(index:number):Omit<PageSection,'id'>=>{const {id,...data}=shippingChaptersSections('shipping-chapter-starter')[index];return data;};
const chapterStarter=(index:number):Omit<PageSection,'id'>=>{const {id,...data}=aboutChaptersSections('Your store','chapter-starter')[index];return data;};
const MEDIA_PRESET_TEMPLATES:SectionTemplateOption[]=(['editorial-hero','editorial-story','editorial-story-cards','editorial-journal-rows'] as const).map(layoutPreset=>({type:'media_text_split',title:({'editorial-hero':'Editorial Hero','editorial-story':'Editorial Story','editorial-story-cards':'Editorial Story Cards','editorial-journal-rows':'Editorial Journal Rows'})[layoutPreset],category:layoutPreset==='editorial-hero'?'Header & Intro':'Storytelling & Narrative',description:'Reusable image and narrative layout with editable content, media and actions.',icon:Columns,badge:'Editorial',defaultData:{type:'media_text_split',layoutPreset,title:'Made for everyday.',subtitle:'YOUR STORY',content:'Introduce your store and what matters to you.',mediaUrl:'',mediaPosition:'left',buttonText:'Explore',buttonLink:'/shop',buttonStyle:layoutPreset==='editorial-story-cards'?'text-link':'button',isVisible:true,backgroundColor:'bg-white',textColor:'dark',containerMode:'page',sectionBorder:'none',motionPreset:'subtle-lift',...(['editorial-story-cards','editorial-journal-rows'].includes(layoutPreset)?{items:[{id:'story-starter',title:'Your story',description:'Introduce the article.',imageUrl:'',buttonText:'Read the story',linkUrl:'/shop'}]}:{})}}));
const CHAPTER_SECTION_TEMPLATES:SectionTemplateOption[]=[
 {type:'media_text_split',title:'Editorial Cover',category:'Header & Intro',description:'A bold heading, introduction and panoramic photograph.',icon:Image,badge:'Editorial',defaultData:chapterStarter(0)},
 {type:'chapter_navigation',title:'Chapter Navigation',category:'Storytelling & Narrative',description:'Automatically links to visible Chapter Blocks, with matching numbers and labels.',icon:Layers,badge:'Editorial',defaultData:chapterStarter(1)},
 {type:'chapter_block',title:'Chapter Process',category:'Storytelling & Narrative',description:'A numbered preparation chapter with service steps.',icon:Layers,badge:'Editorial',defaultData:shippingChapterStarter(2)},
 {type:'chapter_block',title:'Chapter Delivery',category:'Storytelling & Narrative',description:'A numbered delivery chapter with live shipping options and photographs.',icon:Layers,badge:'Editorial',defaultData:shippingChapterStarter(3)},
 {type:'chapter_block',title:'Chapter Actions',category:'Conversion & Leads',description:'A numbered tracking chapter with primary and secondary actions.',icon:Layers,badge:'Editorial',defaultData:shippingChapterStarter(4)},
 {type:'chapter_block',title:'Chapter Story',category:'Storytelling & Narrative',description:'A numbered chapter with narrative paragraphs and a wide photograph.',icon:FileText,badge:'Editorial',defaultData:chapterStarter(2)},
 {type:'chapter_block',title:'Chapter Perspective',category:'Storytelling & Narrative',description:'A numbered chapter with two photographs and a point of view.',icon:Columns,badge:'Editorial',defaultData:chapterStarter(3)},
 {type:'chapter_block',title:'Chapter Principles',category:'Storytelling & Narrative',description:'A numbered chapter with clear title and description rows.',icon:Layers,badge:'Editorial',defaultData:chapterStarter(4)},
 {type:'cta_strip',title:'Editorial CTA Band',category:'Conversion & Leads',description:'A lateral headline and action on a solid brand background.',icon:Megaphone,badge:'Editorial',defaultData:chapterStarter(5)},
];
const SHIPPING_SECTION_TEMPLATES:SectionTemplateOption[]=[
 ...shippingEditorialSections('shipping-starter').slice(1).map((section,index)=>{const {id,...defaultData}=section;return {type:section.type,title:['Service Highlights','Delivery Table','Story with Steps','Minimal FAQ','Help Panel'][index],category:(['Benefits & Trust','Support & Contact','Storytelling & Narrative','Support & Contact','Conversion & Leads'] as const)[index],description:'Reusable delivery and customer service layout. Edit content and appearance in the Inspector.',icon:FileText,badge:'Editorial',defaultData};}),
];
const POLICY_SECTION_TEMPLATES:SectionTemplateOption[]=policyLayouts.map(([policyPreset,label])=>({type:'policy_content',title:`Policy ${label}`,category:'Support & Contact',description:'Reusable policy guidance with shared typography, links and image settings.',icon:FileText,badge:'Policy',defaultData:{type:'policy_content',title:'Policy guidance',subtitle:'GUIDELINES',content:'Explain the next steps for your customers.',isVisible:true,policyPreset,policyNote:'Replace sample copy with your approved store policy.',containerMode:'page',backgroundColor:'bg-white',textColor:'dark',sectionBorder:'none',motionPreset:'subtle-lift',items:[{id:'policy-entry',title:'Guideline',description:'To edit: add your approved policy.',...(policyPreset==='tabs'?{category:'Guidelines'}:{})}]}}));
const PAYMENT_SECTION_TEMPLATES:SectionTemplateOption[]=(['cards','list'] as const).map(paymentLayout=>({type:'payment_methods',title:paymentLayout==='cards'?'Payment Cards':'Payment List',category:'Support & Contact',description:'Automatic payment methods from saved store settings.',icon:ShieldCheck,defaultData:{type:'payment_methods',title:'Ways to pay.',subtitle:'PAYMENT OPTIONS',content:'Choose the available method that suits your order.',paymentLayout,customColors:{...createSectionColorDefaults(),titleColor:'#171717',contentColor:'#737373',eyebrowColor:'#4f46e5'},paymentNote:'Final availability depends on your order, currency and delivery address. Confirm at checkout.',isVisible:true,backgroundColor:'bg-white',textColor:'dark',motionPreset:'subtle-lift',containerMode:'page',sectionBorder:'none'}}));
const MARKET_SECTION_TEMPLATES:SectionTemplateOption[]=[0,2].map(index=>{const {id,...data}=structuredClone(marketTemplate.sections[index]) as PageSection;return {type:data.type,title:index===0?'Market Hero':'Market Offers',category:index===0?'Header & Intro':'Commerce & Shop',description:'Editable market photo cards with authored actions and optional display labels.',icon:ShoppingBag,badge:'Market',defaultData:data};});
const FASHION_SECTION_TEMPLATES:SectionTemplateOption[]=[0,3,7].map(index=>{const {id,...data}=structuredClone(fashionTemplate.sections[index]) as PageSection;return {type:data.type,title:index===0?'Fashion Hero':index===3?'Fashion Banners':'Product Lookbook',category:index===0?'Header & Intro':'Commerce & Shop',description:'Editable fashion layout with owned text, photos and destinations.',icon:ShoppingBag,badge:'Fashion',defaultData:data};});
const TECH_SECTION_TEMPLATES:SectionTemplateOption[]=[{index:2,title:'Tech Device Grid',category:'Commerce & Shop',icon:ShoppingBag},{index:4,title:'Featured Device',category:'Media & Visuals',icon:Columns},{index:5,title:'Device Comparison',category:'Commerce & Shop',icon:Scale},{index:6,title:'Tech Interest Tabs',category:'Commerce & Shop',icon:ShoppingBag}].map(({index,title,category,icon})=>({type:techTemplate.sections[index].type as PageSectionType,title,category:category as SectionTemplateOption['category'],icon,description:'Editable device content, media and supported actions. Catalog data remains authoritative.',defaultData:techTemplate.sections[index] as unknown as Omit<PageSection,'id'>}));
const KIDS_SECTION_TEMPLATES:SectionTemplateOption[]=[{index:3,title:'Nursery & Playtime Banners',category:'Commerce & Shop',icon:Columns},{index:1,title:'Age Guide Cards',category:'Commerce & Shop',icon:ShoppingBag},{index:2,title:'Little Essentials Grid',category:'Commerce & Shop',icon:ShoppingBag},{index:4,title:'Little Milestones',category:'Storytelling & Narrative',icon:Columns},{index:5,title:'Play, Rest & Outdoors Tabs',category:'Commerce & Shop',icon:ShoppingBag}].map(({index,title,category,icon})=>({type:kidsTemplate.sections[index].type as PageSectionType,title,category:category as SectionTemplateOption['category'],icon,description:'Editable family content, photographs and supported actions. Catalog data remains authoritative.',defaultData:kidsTemplate.sections[index] as unknown as Omit<PageSection,'id'>}));
const WEDDING_SECTION_TEMPLATES:SectionTemplateOption[]=[{index:0,title:'Editorial Gradient Hero'},{index:2,title:'Photo Marquee'},{index:3,title:'Portfolio Cards'},{index:6,title:'Numbered Process'},{index:7,title:'Editorial Quote'}].map(({index,title})=>({type:weddingTemplate.sections[index].type as PageSection['type'],title,category:index===2?'Media & Visuals':index===0?'Header & Intro':index===7?'Social Proof':'Storytelling & Narrative',description:'Editable editorial photographs, narrative and service content.',icon:Columns,defaultData:weddingTemplate.sections[index] as unknown as Omit<PageSection,'id'>}));
const JEWELRY_SECTION_TEMPLATES:SectionTemplateOption[]=[{type:'media_text_split',title:'Artisan Story',category:'Storytelling & Narrative',description:'Central narrative framed by editable craft photographs, with supported links and images.',icon:Columns,defaultData:jewelryTemplate.sections[4] as unknown as Omit<PageSection,'id'>}];
const DETAIL_SECTION_TEMPLATES:SectionTemplateOption[]=[...[{index:1,title:'Industrial Split Introduction'},{index:3,title:'Industrial Media Mosaic'},{index:4,title:'Industrial Process Split'},{index:5,title:'Industrial Related Containers'},{index:6,title:'Industrial Outline CTA'}].map(({index,title})=>({type:solutionDetail.sections[index].type as PageSection['type'],title,category:(index===3?'Media & Visuals':index===5?'Commerce & Shop':'Storytelling & Narrative') as SectionTemplateOption['category'],description:'Editable industrial layout with shared text, media and actions.',icon:Columns,defaultData:solutionDetail.sections[index] as unknown as Omit<PageSection,'id'>})),{type:'media_gallery' as const,title:'Album Browser',category:'Media & Visuals' as const,description:'Photograph groups, captions, load more and lightbox.',icon:Columns,defaultData:galleryDetail.sections[0] as unknown as Omit<PageSection,'id'>}];
export const SECTION_TEMPLATES: SectionTemplateOption[] = [...DETAIL_SECTION_TEMPLATES,...WEDDING_SECTION_TEMPLATES,...JEWELRY_SECTION_TEMPLATES,...KIDS_SECTION_TEMPLATES,...TECH_SECTION_TEMPLATES,...MARKET_SECTION_TEMPLATES,...FASHION_SECTION_TEMPLATES,...MEDIA_PRESET_TEMPLATES,...PAYMENT_SECTION_TEMPLATES,...SOURCE_SECTION_TEMPLATES,...EDITORIAL_SECTION_TEMPLATES,...CHAPTER_SECTION_TEMPLATES,...SHIPPING_SECTION_TEMPLATES,...POLICY_SECTION_TEMPLATES].map(template => ({
  ...template,
  defaultData: {
    ...template.defaultData,
    customColors: {...createSectionColorDefaults(),...template.defaultData.customColors},
  },
}));

const normalizeSectionSearch = (value: string) => value.toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
export function matchesSectionSearch(template: SectionTemplateOption, query: string): boolean {
  const terms = normalizeSectionSearch(query).split(' ').filter(Boolean);
  const searchable = normalizeSectionSearch([template.type, template.title, template.description, template.category].join(' '));
  return terms.every(term => searchable.includes(term));
}

export const AddSectionModal: React.FC<AddSectionModalProps> = ({
  isOpen,
  onClose,
  onAdd,
  categories,
  collections,
}) => {
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [searchQuery, setSearchQuery] = useState<string>('');

  if (!isOpen) return null;

  const templateCategories = [
    'All',
    'Header & Intro',
    'Storytelling & Narrative',
    'Commerce & Shop',
    'Media & Visuals',
    'Benefits & Trust',
    'Support & Contact',
    'Social Proof',
    'Conversion & Leads'
  ];

  const filteredTemplates = SECTION_TEMPLATES.filter(tmpl => {
    const matchCategory = selectedCategory === 'All' || tmpl.category === selectedCategory;
    const matchSearch = matchesSectionSearch(tmpl, searchQuery);
    return matchCategory && matchSearch;
  });

  const handleSelectTemplate = (template: SectionTemplateOption) => {
    const collectionTabs = template.type === 'collection_tabs'
      ? createCollectionTabMappings(categories, collections)
      : null;
    const newSection: PageSection = {
      ...JSON.parse(JSON.stringify(template.defaultData)),
      ...(collectionTabs ? {
        tabs: collectionTabs,
        tabCategories: collectionTabs.map(tab => tab.label),
      } : {}),
      id: 'sec-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7)
    };
    if (newSection.pricingPreset==='saas-packages'||newSection.featureCardsPreset?.startsWith('saas-')||newSection.faqPreset==='saas-split'||newSection.featureCardsPreset?.startsWith('industrial-')||newSection.faqPreset==='industrial-split'||newSection.comparisonPreset==='device-table'||newSection.layoutPreset==='tech-feature'||newSection.type==='promotion_highlights'||newSection.type==='chapter_block'||newSection.galleryPreset?.startsWith('editorial-')||newSection.featureCardsPreset==='editorial-columns') {
      newSection.items=newSection.items?.map((item,index)=>({...item,id:`${newSection.id}-item-${index}`}));
    }
    if(isConnectedRows(newSection)||isFixedImageCards(newSection)||isImageHighlights(newSection))newSection.items=newSection.items?.map((item,index)=>({...item,id:`${newSection.id}-item-${index}`}));
    newSection.productTabs=newSection.productTabs?.map((tab,index)=>({...tab,id:`${newSection.id}-tab-${index}`}));
    if(newSection.heroBannerPreset==='corporate-centered')newSection.secondaryButtonLink='';
    if(newSection.layoutPreset?.startsWith('saas-')){if(newSection.buttonLink?.startsWith('#section-saas-starter-'))newSection.buttonLink='';if(newSection.secondaryButtonLink?.startsWith('#section-saas-starter-'))newSection.secondaryButtonLink='';}
    onAdd(newSection);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs animate-in fade-in">
      <div
        id="add-section-modal"
        className="w-full max-w-5xl max-h-[90vh] flex flex-col rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden"
      >
        {/* Modal Header */}
        <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/70 dark:bg-neutral-950/70">
          <div className="flex items-center gap-3">
            <span className="p-2.5 rounded-2xl bg-indigo-600 text-white shadow-md ring-2 ring-indigo-500/20">
              <Sparkles className="w-5 h-5" />
            </span>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-lg font-bold text-neutral-900 dark:text-white">
                  Add Page Section
                </h2>
                <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                  {SECTION_TEMPLATES.length} Archetypes
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Choose a modular eCommerce section archetype to insert into your live page canvas
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Filter Toolbar: Search + Category Pills */}
        <div className="px-6 py-3.5 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/90 flex flex-col gap-3 min-w-0">
          {/* Search Input */}
          <div className="relative w-full min-w-0">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              aria-label="Filter sections"
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              placeholder="Search by section type or layout (e.g. Policy Content, Hero)..."
              className="w-full pl-9 pr-9 py-2 rounded-xl text-xs border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
            />
            {searchQuery && (
              <button
                aria-label="Clear section search"
                onClick={() => setSearchQuery('')}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          {/* Quick Category Selector */}
          <div className="flex w-full min-w-0 items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none [&>button]:shrink-0">
            {templateCategories.map(cat => {
              const isActive = selectedCategory === cat;
              return (
                <button
                  key={cat}
                  onClick={() => setSelectedCategory(cat)}
                  className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all cursor-pointer ${
                    isActive
                      ? 'bg-indigo-600 text-white shadow-xs'
                      : 'bg-neutral-100 dark:bg-neutral-800/70 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-white'
                  }`}
                >
                  {cat}
                </button>
              );
            })}
          </div>
        </div>

        {/* Templates Grid */}
        <div className="flex-1 overflow-y-auto p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4.5 bg-neutral-50/40 dark:bg-neutral-950/40">
          {filteredTemplates.length === 0 ? (
            <div className="col-span-full py-16 text-center text-neutral-400">
              <Search className="w-10 h-10 mx-auto mb-2 opacity-40" />
              <p className="text-sm font-semibold text-neutral-600 dark:text-neutral-300">No matching sections found</p>
              <p className="text-xs text-neutral-400 mt-1">Try clearing your search query or selecting "All" categories</p>
              <button
                onClick={() => {
                  setSearchQuery('');
                  setSelectedCategory('All');
                }}
                className="mt-4 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold shadow-sm hover:bg-indigo-500"
              >
                Reset Filters
              </button>
            </div>
          ) : (
            filteredTemplates.map((tmpl) => {
              const Icon = tmpl.icon;
              return (
                <button
                  key={`${tmpl.type}-${tmpl.title}-${JSON.stringify(tmpl.defaultData)}`}
                  onClick={() => handleSelectTemplate(tmpl)}
                  className="group p-5 rounded-2xl border text-left flex flex-col justify-between transition-all duration-200
                    bg-white dark:bg-neutral-900/90
                    border-neutral-200 dark:border-neutral-800/80
                    hover:border-indigo-500 hover:shadow-xl hover:shadow-indigo-500/10 hover:-translate-y-0.5 cursor-pointer"
                >
                  <div>
                    <div className="flex items-center justify-between mb-3.5">
                      <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors shadow-2xs">
                        <Icon className="w-5 h-5" />
                      </div>
                      <div className="flex items-center gap-1.5">
                        {tmpl.badge && (
                          <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 border border-indigo-500/30">
                            {tmpl.badge}
                          </span>
                        )}
                        <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400">
                          {tmpl.category}
                        </span>
                      </div>
                    </div>

                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 mb-1.5 transition-colors">
                      {tmpl.title}
                    </h3>
                    <p className="text-[10px] uppercase tracking-wider text-indigo-500 mb-2">Section type: {tmpl.type.replace(/_/g, ' ')}</p>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed line-clamp-3">
                      {tmpl.description}
                    </p>
                  </div>

                  <div className="mt-4 pt-3.5 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                    <span className="group-hover:translate-x-0.5 transition-transform">Insert Section</span>
                    <span className="p-1 rounded-md bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                      <Check className="w-3.5 h-3.5" />
                    </span>
                  </div>
                </button>
              );
            })
          )}
        </div>

        {/* Modal Footer */}
        <div className="p-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-950/70 flex items-center justify-between">
          <p className="text-xs text-neutral-500 dark:text-neutral-400">
            Showing <strong className="text-neutral-900 dark:text-white">{filteredTemplates.length}</strong> available modular sections. All contents and media can be customized in the Inspector.
          </p>
          <button
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold rounded-xl bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-300 dark:hover:bg-neutral-700 transition-colors cursor-pointer"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
};
