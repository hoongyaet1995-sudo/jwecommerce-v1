import React, { useState } from 'react';
import { PageSection, PageSectionType } from '../../types';
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
  Scale
} from 'lucide-react';

interface AddSectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onAdd: (newSection: PageSection) => void;
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

export const SECTION_TEMPLATES: SectionTemplateOption[] = [
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
    title: 'Press Mentions & Partner Brand Logos',
    category: 'Social Proof',
    description: 'Logo marquee highlighting press accolades (Wired, SoundOnSound, TechCrunch, The Verge) and mastering studios.',
    icon: HeartHandshake,
    defaultData: {
      type: 'logo_cloud',
      title: 'Featured in Global Publications & Studios',
      subtitle: 'CRITICALLY ACCLAIMED',
      content: '',
      columnsCount: 5,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'compact',
      isVisible: true,
      items: [
        { id: 'logo-1', title: 'WIRED', description: '“Headphone of the Year”' },
        { id: 'logo-2', title: 'SOUND ON SOUND', description: '“Reference Class”' },
        { id: 'logo-3', title: 'THE VERGE', description: '9.5 / 10 Score' },
        { id: 'logo-4', title: 'AUDIOPHILE ON', description: 'Editor’s Choice' },
        { id: 'logo-5', title: 'ROLLING STONE', description: 'Top Tier Audio' }
      ]
    }
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

export const AddSectionModal: React.FC<AddSectionModalProps> = ({
  isOpen,
  onClose,
  onAdd
}) => {
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [searchQuery, setSearchQuery] = useState<string>('');

  if (!isOpen) return null;

  const categories = [
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
    const matchSearch =
      searchQuery.trim() === '' ||
      tmpl.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      tmpl.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
      tmpl.category.toLowerCase().includes(searchQuery.toLowerCase());
    return matchCategory && matchSearch;
  });

  const handleSelectTemplate = (template: SectionTemplateOption) => {
    const newSection: PageSection = {
      ...template.defaultData,
      id: 'sec-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7)
    };
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
        <div className="px-6 py-3.5 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/90 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
          {/* Search Input */}
          <div className="relative flex-1 max-w-sm">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              placeholder="Search sections (e.g. Hero, Testimonials, Pricing, Deal)..."
              className="w-full pl-9 pr-3 py-1.5 rounded-xl text-xs border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          {/* Quick Category Selector */}
          <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
            {categories.map(cat => {
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
                  key={tmpl.type}
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
