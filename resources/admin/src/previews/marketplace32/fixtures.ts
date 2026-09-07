import type {Product, Category, MarketplaceConnection, MarketplaceStoreProduct} from './types';
// ZIP32 selected sample data. Isolated preview only; never operational records.
export const INITIAL_CATEGORIES: Category[] = [
  {
    id: 'cat-electronics',
    name: 'Electronics & Gadgets',
    slug: 'electronics',
    parentId: null,
    icon: 'Laptop',
    image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&auto=format&fit=crop&q=80',
    description: 'High performance consumer tech, audio equipment, and smart accessories.',
    seoTitle: 'Buy Premium Electronics & Audio Online | Aurora Commerce',
    seoDescription: 'Explore our studio-grade headphones, smart gadgets, and everyday carry gear.',
    status: 'active',
    sortOrder: 1,
    productCount: 42,
    createdAt: '2026-01-10'
  },
  {
    id: 'cat-audio',
    name: 'Premium Audio',
    slug: 'audio',
    parentId: 'cat-electronics',
    icon: 'Headphones',
    image: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=500&auto=format&fit=crop&q=80',
    description: 'Active Noise Cancelling over-ear headphones and lossless DACs.',
    seoTitle: 'Audiophile Headphones & Sound Gear',
    seoDescription: 'Engineered for crystalline highs and thunderous sub-bass.',
    status: 'active',
    sortOrder: 2,
    productCount: 18,
    createdAt: '2026-01-12'
  },
  {
    id: 'cat-wearables',
    name: 'Wearables & Health',
    slug: 'wearables',
    parentId: 'cat-electronics',
    icon: 'Watch',
    image: 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=500&auto=format&fit=crop&q=80',
    description: 'Biometric tracking smart rings, chronographs, and health wearables.',
    seoTitle: 'Smart Rings & Biometric Wearables',
    seoDescription: 'Track sleep, HRV, and athletic performance seamlessly.',
    status: 'active',
    sortOrder: 3,
    productCount: 14,
    createdAt: '2026-01-15'
  },
  {
    id: 'cat-apparel',
    name: 'Apparel & Technical Wear',
    slug: 'apparel',
    parentId: null,
    icon: 'Shirt',
    image: 'https://images.unsplash.com/photo-1523381294911-8d3cead13475?w=500&auto=format&fit=crop&q=80',
    description: 'Waterproof technical shells, merino wool knitwear, and modular commute garments.',
    seoTitle: 'Minimalist Technical Outerwear & Merino Wear',
    seoDescription: 'Built for urban commuting and harsh environmental weather.',
    status: 'active',
    sortOrder: 4,
    productCount: 29,
    createdAt: '2026-01-20'
  },
  {
    id: 'cat-bags',
    name: 'Bags & Carry Gear',
    slug: 'bags',
    parentId: 'cat-apparel',
    icon: 'Briefcase',
    image: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&auto=format&fit=crop&q=80',
    description: 'Cordura ballistic nylon packs, duffles, and organizer pouches.',
    seoTitle: 'Weatherproof Travel Bags & Everyday Packs',
    seoDescription: 'Lifetime warranty bags designed for creators and travelers.',
    status: 'active',
    sortOrder: 5,
    productCount: 15,
    createdAt: '2026-02-01'
  }
];

export const INITIAL_PRODUCTS: Product[] = [
  {
    id: 'prod-01',
    title: 'Aurora Studio Pro Wireless Headphones',
    slug: 'aurora-studio-pro-wireless-headphones',
    brand: 'AcousticLab',
    categoryId: 'cat-audio',
    collectionIds: ['col-summer', 'col-nomad'],
    summary: 'Flagship hybrid ANC headphones with custom 45mm beryllium drivers and 60-hour playback.',
    descriptionHtml: '<p>The <strong>Aurora Studio Pro</strong> delivers reference-grade acoustics with dual-feedforward active noise cancellation, aptX Lossless streaming, and ultra-plush memory foam ear cups.</p>',
    images: [
      'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: true,
    basePrice: 299,
    baseCompareAtPrice: 349,
    featured: true,
    tags: ['audio', 'bluetooth', 'anc', 'summer', 'flagship'],
    totalInventory: 245,
    options: [
      {
        id: 'opt-color',
        name: 'Color',
        values: ['Midnight Black', 'Arctic White', 'Forest Pine'],
        displayType: 'color_palette',
        valueVisuals: {
          'Midnight Black': { visualType: 'color', colorCode: '#111827' },
          'Arctic White': { visualType: 'color', colorCode: '#F8FAFC' },
          'Forest Pine': { visualType: 'color', colorCode: '#14532D' }
        }
      },
      {
        id: 'opt-edition',
        name: 'Edition',
        values: ['Standard Audio', 'Studio Master ANC']
      }
    ],
    variants: [
      {
        id: 'var-101',
        sku: 'AUR-BLK-STD',
        title: 'Midnight Black / Standard Audio',
        options: { 'Color': 'Midnight Black', 'Edition': 'Standard Audio' },
        price: 299,
        compareAtPrice: 349,
        costPrice: 110,
        inventoryQuantity: 50,
        weightGrams: 310,
        barcode: '850021001',
        image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
        visualType: 'color',
        colorCode: '#111827',
        enabled: true
      },
      {
        id: 'var-102',
        sku: 'AUR-BLK-ANC',
        title: 'Midnight Black / Studio Master ANC',
        options: { 'Color': 'Midnight Black', 'Edition': 'Studio Master ANC' },
        price: 349,
        compareAtPrice: 399,
        costPrice: 135,
        inventoryQuantity: 45,
        weightGrams: 325,
        barcode: '850021002',
        image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
        visualType: 'color',
        colorCode: '#111827',
        enabled: true
      },
      {
        id: 'var-103',
        sku: 'AUR-WHT-STD',
        title: 'Arctic White / Standard Audio',
        options: { 'Color': 'Arctic White', 'Edition': 'Standard Audio' },
        price: 299,
        compareAtPrice: 349,
        costPrice: 110,
        inventoryQuantity: 38,
        weightGrams: 310,
        barcode: '850021003',
        image: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=400&auto=format&fit=crop&q=80',
        visualType: 'color',
        colorCode: '#F8FAFC',
        enabled: true
      },
      {
        id: 'var-104',
        sku: 'AUR-WHT-ANC',
        title: 'Arctic White / Studio Master ANC',
        options: { 'Color': 'Arctic White', 'Edition': 'Studio Master ANC' },
        price: 349,
        compareAtPrice: 399,
        costPrice: 135,
        inventoryQuantity: 42,
        weightGrams: 325,
        barcode: '850021004',
        image: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=400&auto=format&fit=crop&q=80',
        visualType: 'color',
        colorCode: '#F8FAFC',
        enabled: true
      },
      {
        id: 'var-105',
        sku: 'AUR-GRN-STD',
        title: 'Forest Pine / Standard Audio',
        options: { 'Color': 'Forest Pine', 'Edition': 'Standard Audio' },
        price: 299,
        compareAtPrice: 349,
        costPrice: 110,
        inventoryQuantity: 35,
        weightGrams: 310,
        barcode: '850021005',
        image: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=400&auto=format&fit=crop&q=80',
        visualType: 'color',
        colorCode: '#14532D',
        enabled: true
      },
      {
        id: 'var-106',
        sku: 'AUR-GRN-ANC',
        title: 'Forest Pine / Studio Master ANC',
        options: { 'Color': 'Forest Pine', 'Edition': 'Studio Master ANC' },
        price: 349,
        compareAtPrice: 399,
        costPrice: 135,
        inventoryQuantity: 35,
        weightGrams: 325,
        barcode: '850021006',
        image: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=400&auto=format&fit=crop&q=80',
        visualType: 'color',
        colorCode: '#14532D',
        enabled: true
      }
    ],
    seoTitle: 'Aurora Studio Pro Headphones | Lossless Audio ANC',
    seoDescription: 'High resolution wireless headphones with active noise cancellation and ergonomic fit.',
    highlights: [
      { id: 'hl-1', text: 'Custom 45mm Beryllium diaphragm drivers with ultra-low distortion (<0.05% THD)', badge: 'Reference' },
      { id: 'hl-2', text: 'Hybrid dual-feedforward active noise cancellation canceling up to 42dB ambient noise', badge: 'Hybrid ANC' },
      { id: 'hl-3', text: 'Lossless Snapdragon Sound, aptX Adaptive & LDAC 24-bit/96kHz transmission', badge: 'Hi-Res Audio' },
      { id: 'hl-4', text: '60-hour continuous playback battery with 15-minute quick USB-C recharge (8 hrs)', badge: '60H Battery' }
    ],
    specifications: [
      { id: 'sp-1', label: 'Driver Architecture', value: '45mm Custom Pure Beryllium Foil Drivers', group: 'Acoustics' },
      { id: 'sp-2', label: 'Frequency Response', value: '5 Hz – 48,000 Hz (Hi-Res Certified)', group: 'Acoustics' },
      { id: 'sp-3', label: 'Bluetooth Codecs', value: 'LDAC, aptX Adaptive, aptX Lossless, AAC, SBC', group: 'Connectivity' },
      { id: 'sp-4', label: 'Battery Capacity', value: '1,050 mAh Li-Po (Up to 60 hrs ANC off / 45 hrs ANC on)', group: 'Power' },
      { id: 'sp-5', label: 'Chassis Material', value: 'Aircraft-grade anodized aluminum & memory foam ear cushions', group: 'Materials' },
      { id: 'sp-6', label: 'Total Weight', value: '310 grams', group: 'Dimensions' }
    ],
    shippingPolicy: {
      useStoreDefault: true,
      shippingBadgeText: 'Complimentary Express Shipping Included',
      leadTimeText: 'Order in the next 3 hrs 12 mins for dispatch today. Estimated arrival: 3–5 business days.',
      policyTitle: 'Shipping & 30-Day Return Guarantee',
      policyContentHtml: 'Enjoy worldwide express delivery insured by FedEx/DHL. All headphones ship with serialized authenticity certs. 30-day risk-free in-home trial included.'
    },
    customAccordions: [
      {
        id: 'acc-box-1',
        title: 'What’s In The Box',
        content: '• 1x Aurora Studio Pro Headphones\n• 1x Magnetic Hardshell Travel Vault Case\n• 1x 1.5m Braided 3.5mm OFC Audio Cable\n• 1x Braided USB-C to USB-C Fast Charge Cable\n• 1x Gold-plated 6.35mm Studio Adapter'
      },
      {
        id: 'acc-care-1',
        title: 'Care & Ear Cushion Maintenance',
        content: 'Ear cushions are magnetically detachable and can be wiped with a damp microfiber cloth. Store in the hard case to protect gimbal hinges.'
      }
    ],
    crossSellProductIds: ['prod-02', 'prod-03'],
    ratingScore: 4.9,
    reviewCount: 142,
    reviews: [
      {
        id: 'rev-1',
        author: 'Alexander S.',
        rating: 5,
        date: '2 days ago',
        title: 'Unbelievably resolving soundstage and stellar ANC',
        comment: 'The sub-bass extension and crystal highs on these beryllium drivers match open-back studio monitors. Invaluable for travel and concentration.',
        verifiedBuyer: true,
        variantPurchased: 'Midnight Black / Studio Master ANC',
        helpfulCount: 38
      },
      {
        id: 'rev-2',
        author: 'Elena Rostova',
        rating: 5,
        date: '1 week ago',
        title: 'Comfortable enough for 8-hour mixing sessions',
        comment: 'Ear cups are plush and do not exert pressure on glasses. Premium aluminum feel throughout.',
        verifiedBuyer: true,
        variantPurchased: 'Arctic White / Studio Master ANC',
        helpfulCount: 19
      }
    ],
    createdAt: '2026-03-01',
    updatedAt: '2026-08-10'
  },
  {
    id: 'prod-02',
    title: 'Nexus 75% Custom Mechanical Keyboard',
    slug: 'nexus-75-mechanical-keyboard',
    brand: 'KeyCraft Studio',
    categoryId: 'cat-electronics',
    collectionIds: ['col-nomad'],
    summary: 'CNC aluminum gasket mounted mechanical keyboard with hot-swappable PCB and south-facing RGB.',
    descriptionHtml: '<p>Precision milled from solid 6063 aerospace aluminum with pre-lubed stabilizer switches and acoustic IXPE foam dampening.</p>',
    images: [
      'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: true,
    basePrice: 189,
    baseCompareAtPrice: 219,
    featured: true,
    tags: ['keyboard', 'desk', 'workspace', 'aluminum', 'gadget'],
    totalInventory: 112,
    options: [
      {
        id: 'opt-switch',
        name: 'Switch Type',
        values: ['Linear Quartz 45g', 'Tactile Matcha 58g']
      },
      {
        id: 'opt-case',
        name: 'Case Finish',
        values: ['Anodized Space Gray', 'E-Coat Cream'],
        displayType: 'option_image',
        valueVisuals: {
          'Anodized Space Gray': { visualType: 'image', optionImage: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=200&auto=format&fit=crop&q=80' },
          'E-Coat Cream': { visualType: 'image', optionImage: 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=200&auto=format&fit=crop&q=80' }
        }
      }
    ],
    variants: [
      {
        id: 'var-201',
        sku: 'NEX-LIN-GRY',
        title: 'Linear Quartz 45g / Anodized Space Gray',
        options: { 'Switch Type': 'Linear Quartz 45g', 'Case Finish': 'Anodized Space Gray' },
        price: 189,
        compareAtPrice: 219,
        inventoryQuantity: 32,
        weightGrams: 1420,
        image: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&auto=format&fit=crop&q=80',
        visualType: 'image',
        optionImage: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=200&auto=format&fit=crop&q=80',
        enabled: true
      },
      {
        id: 'var-202',
        sku: 'NEX-LIN-CRM',
        title: 'Linear Quartz 45g / E-Coat Cream',
        options: { 'Switch Type': 'Linear Quartz 45g', 'Case Finish': 'E-Coat Cream' },
        price: 199,
        compareAtPrice: 229,
        inventoryQuantity: 28,
        weightGrams: 1420,
        image: 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=400&auto=format&fit=crop&q=80',
        visualType: 'image',
        optionImage: 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=200&auto=format&fit=crop&q=80',
        enabled: true
      },
      {
        id: 'var-203',
        sku: 'NEX-TAC-GRY',
        title: 'Tactile Matcha 58g / Anodized Space Gray',
        options: { 'Switch Type': 'Tactile Matcha 58g', 'Case Finish': 'Anodized Space Gray' },
        price: 189,
        compareAtPrice: 219,
        inventoryQuantity: 26,
        weightGrams: 1420,
        image: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&auto=format&fit=crop&q=80',
        visualType: 'image',
        optionImage: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=200&auto=format&fit=crop&q=80',
        enabled: true
      },
      {
        id: 'var-204',
        sku: 'NEX-TAC-CRM',
        title: 'Tactile Matcha 58g / E-Coat Cream',
        options: { 'Switch Type': 'Tactile Matcha 58g', 'Case Finish': 'E-Coat Cream' },
        price: 199,
        compareAtPrice: 229,
        inventoryQuantity: 26,
        weightGrams: 1420,
        image: 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=400&auto=format&fit=crop&q=80',
        visualType: 'image',
        optionImage: 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=200&auto=format&fit=crop&q=80',
        enabled: true
      }
    ],
    seoTitle: 'Nexus 75% Custom Mechanical Keyboard | Aluminum Gasket',
    createdAt: '2026-04-10',
    updatedAt: '2026-08-05'
  },
  {
    id: 'prod-03',
    title: 'Vanguard Cordura 35L Weekender Pack',
    slug: 'vanguard-cordura-35l-weekender',
    brand: 'Nomadics',
    categoryId: 'cat-bags',
    collectionIds: ['col-summer', 'col-nomad'],
    summary: 'Weather-sealed 500D Cordura ballistic pack with clamshell opening and padded 16" laptop vault.',
    descriptionHtml: '<p>Built to withstand airline overhead cabins and downpours alike with YKK Aquaguard zippers and magnetic Fidlock buckles.</p>',
    images: [
      'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: false,
    basePrice: 165,
    baseCompareAtPrice: 195,
    featured: false,
    tags: ['bags', 'cordura', 'travel', 'waterproof'],
    totalInventory: 64,
    options: [],
    variants: [
      {
        id: 'var-301',
        sku: 'VAN-35L-OBS',
        title: 'Default Obsidian Black',
        options: {},
        price: 165,
        compareAtPrice: 195,
        inventoryQuantity: 64,
        weightGrams: 1200,
        enabled: true
      }
    ],
    createdAt: '2026-05-12',
    updatedAt: '2026-08-01'
  },
  {
    id: 'prod-04',
    title: 'Aether Studio ANC Wireless',
    slug: 'aether-studio-anc-wireless',
    brand: 'AETHER ACOUSTICS',
    categoryId: 'cat-electronics',
    collectionIds: ['col-summer', 'col-nomad'],
    summary: 'Planar Magnetic Studio Monitor with Adaptive Noise Cancellation',
    descriptionHtml: '<p>Engineered with custom planar magnetic diaphragms and zero-latency wireless streaming for uncompromising studio fidelity.</p>',
    images: [
      'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?w=800&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: true,
    basePrice: 389,
    baseCompareAtPrice: 467,
    featured: true,
    tags: ['audio', 'wireless', 'anc', 'flagship'],
    totalInventory: 88,
    options: [
      {
        id: 'opt-variant-p4',
        name: 'Finish',
        values: ['Obsidian', 'Lunar White', 'Slate Gray', 'Bronze']
      }
    ],
    variants: [
      {
        id: 'var-401',
        sku: 'AET-OBS-MAT',
        title: 'Obsidian / Matte',
        options: { 'Finish': 'Obsidian' },
        price: 389,
        compareAtPrice: 467,
        inventoryQuantity: 42,
        weightGrams: 320,
        enabled: true
      }
    ],
    createdAt: '2026-06-10',
    updatedAt: '2026-08-20'
  },
  {
    id: 'prod-05',
    title: 'Solis Modular Desk Shelf System',
    slug: 'solis-modular-desk-shelf-system',
    brand: 'NORDIC ATELIER',
    categoryId: 'cat-electronics',
    collectionIds: ['col-nomad'],
    summary: 'Solid American Walnut & CNC Milled Anodized Rail',
    descriptionHtml: '<p>Ergonomically elevated display riser milled from certified sustainable American walnut with integrated cable management channels.</p>',
    images: [
      'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: true,
    basePrice: 220,
    baseCompareAtPrice: 260,
    featured: true,
    tags: ['desk', 'workspace', 'walnut', 'minimalist'],
    totalInventory: 55,
    options: [
      {
        id: 'opt-wood-p5',
        name: 'Timber',
        values: ['Rich Walnut', 'Nordic Oak', 'Rich Cherry']
      }
    ],
    variants: [
      {
        id: 'var-501',
        sku: 'SKU-SOLIS-WAL-STD',
        title: 'Rich Walnut / Standard',
        options: { 'Timber': 'Rich Walnut' },
        price: 220,
        compareAtPrice: 260,
        inventoryQuantity: 30,
        weightGrams: 2800,
        enabled: true
      }
    ],
    createdAt: '2026-06-12',
    updatedAt: '2026-08-22'
  },
  {
    id: 'prod-06',
    title: 'Chronos Calibre 9 Automatic Field Watch',
    slug: 'chronos-calibre-9-automatic-field',
    brand: 'HOROLOGY LAB',
    categoryId: 'cat-electronics',
    collectionIds: ['col-summer'],
    summary: 'Swiss Sellita SW200-1 Automatic Movement & Grade 5 Titanium',
    descriptionHtml: '<p>Ultra-lightweight aerospace grade 5 titanium casing with double anti-reflective sapphire crystal and 200m water resistance.</p>',
    images: [
      'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: true,
    basePrice: 650,
    baseCompareAtPrice: 780,
    featured: true,
    tags: ['watch', 'horology', 'titanium', 'automatic'],
    totalInventory: 24,
    options: [
      {
        id: 'opt-strap-p6',
        name: 'Case / Strap',
        values: ['Stealth Gray', 'Arctic Silver']
      }
    ],
    variants: [
      {
        id: 'var-601',
        sku: 'SKU-CHR-C9-BLK',
        title: 'Stealth Gray / Titanium',
        options: { 'Case / Strap': 'Stealth Gray' },
        price: 650,
        compareAtPrice: 780,
        inventoryQuantity: 14,
        weightGrams: 85,
        enabled: true
      }
    ],
    createdAt: '2026-06-15',
    updatedAt: '2026-08-25'
  },
  {
    id: 'prod-07',
    title: 'Apex Mechanical Gaming Station',
    slug: 'apex-mechanical-gaming-station',
    brand: 'VALKYRIE GEAR',
    categoryId: 'cat-electronics',
    collectionIds: ['col-nomad', 'col-summer'],
    summary: 'Precision Hall-effect magnetic switches with 8000Hz polling rate',
    descriptionHtml: '<p>Ultra-responsive gaming keyboard engineered for rapid trigger actuation and customizable magnetic switch resistance.</p>',
    images: [
      'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?w=800&auto=format&fit=crop&q=80'
    ],
    status: 'active',
    hasVariants: false,
    basePrice: 280,
    baseCompareAtPrice: 340,
    featured: true,
    tags: ['gaming', 'keyboard', 'electronics'],
    totalInventory: 40,
    options: [],
    variants: [
      {
        id: 'var-701',
        sku: 'SKU-APX-8K-RGB',
        title: 'Carbon Black',
        options: {},
        price: 280,
        compareAtPrice: 340,
        inventoryQuantity: 40,
        weightGrams: 1100,
        enabled: true
      }
    ],
    createdAt: '2026-07-01',
    updatedAt: '2026-08-28'
  }
];

export const INITIAL_MARKETPLACE_CONNECTIONS: MarketplaceConnection[] = [
  {
    id: 'conn-shopee-01',
    platform: 'shopee',
    shopName: 'Aurora Audio Flagship Mall',
    shopNickname: 'Shopee Official MY',
    shopId: '293849102',
    partnerId: '849201',
    sellerId: 'aurora_audio.os',
    region: 'MY',
    appKey: '4f81c9a03b554e2d83',
    appSecret: '••••••••••••••••••••••••••••9e21',
    accessToken: 'shopee_live_auth_91823910394857201948372',
    refreshToken: 'shopee_refresh_token_09823419082391',
    tokenExpiresAt: new Date(Date.now() + 29 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'connected',
    connectedAt: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString(),
    lastFetchedAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    autoSyncStock: true,
    autoPullOrders: true,
    webhookRegistered: true,
    webhookSecret: 'whsec_shopee_3948201948201',
    syncIntervalMinutes: 5,
    scopes: ['orders.read_write', 'fulfillment.write', 'inventory.sync', 'pricing.manage'],
    authorizedScopesSummary: ['Product Catalog Read', 'Order Retrieval & Status', 'Warehouse Stock Push', 'AWB Courier Generation'],
    lastSyncAt: new Date(Date.now() - 1 * 60 * 1000).toISOString(),
    orderCount: 389,
    productCount: 24,
    priceMarkupPercent: 0,
    stockBufferPercent: 10,
    defaultCourier: 'Shopee Supported Logistics (SPX)',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=120&auto=format&fit=crop&q=80'
  },
  {
    id: 'conn-lazada-01',
    platform: 'lazada',
    shopName: 'Aurora Audio LazMall Store',
    shopNickname: 'Lazada LazMall MY',
    shopId: 'MY102938482',
    sellerId: 'seller_laz_aurora_my',
    region: 'MY',
    appKey: '10928301',
    appSecret: '••••••••••••••••••••••••••••7c44',
    accessToken: 'lazada_sec_tok_84920194830192847291',
    refreshToken: 'lazada_ref_tok_10294820192847',
    tokenExpiresAt: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'connected',
    connectedAt: new Date(Date.now() - 45 * 24 * 60 * 60 * 1000).toISOString(),
    lastFetchedAt: new Date(Date.now() - 25 * 60 * 1000).toISOString(),
    autoSyncStock: true,
    autoPullOrders: true,
    webhookRegistered: true,
    webhookSecret: 'whsec_laz_9847201928374',
    syncIntervalMinutes: 5,
    scopes: ['orders.read_write', 'fulfillment.write', 'inventory.sync'],
    authorizedScopesSummary: ['Catalog Read', 'Orders Sync', 'Inventory Push'],
    lastSyncAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
    orderCount: 98,
    productCount: 16,
    priceMarkupPercent: 0,
    stockBufferPercent: 5,
    defaultCourier: 'Lazada Logistics Express (LEX)',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=120&auto=format&fit=crop&q=80'
  },
  {
    id: 'conn-shopee-02',
    platform: 'shopee',
    shopName: 'Aurora Audio Outlet Store',
    shopNickname: 'Shopee Secondary Outlet',
    shopId: '481029381',
    partnerId: '849201',
    sellerId: 'aurora_outlet.my',
    region: 'MY',
    appKey: '4f81c9a03b554e2d83',
    appSecret: '••••••••••••••••••••••••••••9e21',
    accessToken: 'shopee_expired_token_0019283',
    refreshToken: 'shopee_refresh_token_expired',
    tokenExpiresAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(), // Expired 2 days ago
    status: 'expired',
    connectedAt: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString(),
    lastFetchedAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
    autoSyncStock: false,
    autoPullOrders: false,
    webhookRegistered: false,
    syncIntervalMinutes: 5,
    scopes: ['orders.read_write', 'inventory.sync'],
    authorizedScopesSummary: ['Catalog Read', 'Order Sync', 'Inventory Push'],
    lastSyncAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
    orderCount: 42,
    productCount: 8,
    priceMarkupPercent: 0,
    stockBufferPercent: 5,
    defaultCourier: 'Shopee Supported Logistics (SPX)',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=120&auto=format&fit=crop&q=80'
  }
];

export const INITIAL_MARKETPLACE_STORE_PRODUCTS: MarketplaceStoreProduct[] = [
  // 1. Shopee Mall: Aurora Audio Flagship Mall (conn-shopee-01) - Audio Category
  {
    id: 'ext-prod-tt-01',
    connectionId: 'conn-shopee-01',
    platform: 'shopee',
    shopId: '293849102',
    shopName: 'Aurora Audio Flagship Mall',
    channelProductId: 'SP_PRD_98492019',
    title: 'Aurora Studio Pro Wireless Headphones Planar ANC (Special Edition)',
    imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Electronics & Audio > Over-Ear Headphones',
    status: 'active',
    updatedAt: new Date(Date.now() - 35 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-tt-101',
        channelSkuId: 'TTS_SKU_10293848',
        channelSellerSku: 'AUR-BLK-ANC-TT',
        title: 'Midnight Black / Studio Master ANC',
        specOptions: { 'Color': 'Midnight Black', 'Edition': 'Studio Master ANC' },
        price: 349.00,
        currency: 'MYR',
        stock: 45,
        imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-102',
        mappedJwSku: 'AUR-BLK-ANC',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Midnight Black / Studio Master ANC',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 45,
        mappedJwPrice: 349.00,
        mappedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Hazim (Lead Ops)'
      },
      {
        id: 'ext-var-tt-102',
        channelSkuId: 'TTS_SKU_10293849',
        channelSellerSku: 'AUR-WHT-STD-TT',
        title: 'Arctic White / Standard Audio',
        specOptions: { 'Color': 'Arctic White', 'Edition': 'Standard Audio' },
        price: 299.00,
        currency: 'MYR',
        stock: 40,
        imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-103',
        mappedJwSku: 'AUR-WHT-STD',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Arctic White / Standard Audio',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 40,
        mappedJwPrice: 299.00,
        mappedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Hazim (Lead Ops)'
      },
      {
        id: 'ext-var-tt-103',
        channelSkuId: 'TTS_SKU_10293850',
        channelSellerSku: 'AUR-PINE-ANC-TT',
        title: 'Forest Pine / Studio Master ANC',
        specOptions: { 'Color': 'Forest Pine', 'Edition': 'Studio Master ANC' },
        price: 349.00,
        currency: 'MYR',
        stock: 25,
        imageUrl: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-01',
          variantId: 'var-105',
          sku: 'AUR-PINE-ANC',
          productName: 'Aurora Studio Pro Wireless Headphones',
          variantTitle: 'Forest Pine / Studio Master ANC',
          imageUrl: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=400&auto=format&fit=crop&q=80',
          stock: 25,
          price: 349.00,
          confidenceScore: 99,
          matchReason: 'SKU 前缀完全匹配且规格属性（Forest Pine / ANC）100% 一致'
        }
      }
    ]
  },
  {
    id: 'ext-prod-tt-02',
    connectionId: 'conn-lazada-01',
    platform: 'lazada',
    shopId: 'MY102938482',
    shopName: 'Aurora Audio LazMall Store',
    channelProductId: 'LZ_PRD_77492011',
    title: 'Apex Ergonomic Low-Profile Mechanical Keyboard RGB Hot-Swap',
    imageUrl: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Computers & Gaming > Keyboards',
    status: 'active',
    updatedAt: new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-tt-201',
        channelSkuId: 'TTS_SKU_44920188',
        channelSellerSku: 'APX-KB-RED-TT',
        title: 'Phantom Black / Cherry MX Red (Linear)',
        specOptions: { 'Color': 'Phantom Black', 'Switch': 'Cherry MX Red' },
        price: 199.00,
        currency: 'MYR',
        stock: 55,
        imageUrl: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-04',
          variantId: 'var-401',
          sku: 'APX-KB-RED',
          productName: 'Apex Ergonomic Mechanical Keyboard',
          variantTitle: 'Phantom Black / Cherry MX Red (Linear)',
          imageUrl: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=400&auto=format&fit=crop&q=80',
          stock: 55,
          price: 199.00,
          confidenceScore: 96,
          matchReason: '商品名称与轴体型号 (Cherry MX Red) 完全吻合'
        }
      },
      {
        id: 'ext-var-tt-202',
        channelSkuId: 'TTS_SKU_44920189',
        channelSellerSku: 'APX-KB-BLU-TT',
        title: 'Lunar White / Cherry MX Blue (Clicky)',
        specOptions: { 'Color': 'Lunar White', 'Switch': 'Cherry MX Blue' },
        price: 199.00,
        currency: 'MYR',
        stock: 35,
        imageUrl: 'https://images.unsplash.com/photo-1595225476474-87563907a212?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-04',
          variantId: 'var-403',
          sku: 'APX-KB-BLU',
          productName: 'Apex Ergonomic Mechanical Keyboard',
          variantTitle: 'Lunar White / Cherry MX Blue (Clicky)',
          imageUrl: 'https://images.unsplash.com/photo-1595225476474-87563907a212?w=400&auto=format&fit=crop&q=80',
          stock: 35,
          price: 199.00,
          confidenceScore: 95,
          matchReason: '商品名称与白青轴 (Lunar White / Blue) 智能比对吻合'
        }
      }
    ]
  },
  {
    id: 'ext-prod-tt-03',
    connectionId: 'conn-shopee-02',
    platform: 'shopee',
    shopId: '481029381',
    shopName: 'Aurora Audio Outlet Store',
    channelProductId: 'SP_OUTLET_33910284',
    title: 'SoundPulse Ultra Portable Wireless Waterproof Speaker IP67',
    imageUrl: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Electronics & Audio > Bluetooth Speakers',
    status: 'active',
    updatedAt: new Date(Date.now() - 12 * 60 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-tt-301',
        channelSkuId: 'TTS_SKU_22091847',
        channelSellerSku: 'SP-BLU-STD-TT',
        title: 'Ocean Blue / Standard IP67',
        specOptions: { 'Color': 'Ocean Blue', 'Waterproof': 'IP67' },
        price: 129.00,
        currency: 'MYR',
        stock: 80,
        imageUrl: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-03',
        mappedJwVariantId: 'var-301',
        mappedJwSku: 'SP-BLU-STD',
        mappedJwProductName: 'SoundPulse Portable Waterproof Speaker',
        mappedJwVariantTitle: 'Ocean Blue / Standard IP67',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 80,
        mappedJwPrice: 129.00,
        mappedAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Hazim (Lead Ops)'
      },
      {
        id: 'ext-var-tt-302',
        channelSkuId: 'TTS_SKU_22091848',
        channelSellerSku: 'SP-GRY-V2-TT',
        title: 'Matte Gray / Special Outdoor Edition',
        specOptions: { 'Color': 'Matte Gray', 'Edition': 'Special Outdoor' },
        price: 149.00,
        currency: 'MYR',
        stock: 15,
        imageUrl: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'conflict',
        conflictReason: '原关联的 JW SKU [SP-GRY-OLD] 已在本地商品库中停用或删除，无法核对库存，需重新指定有效规格'
      }
    ]
  },

  // 2. Shopee: Aurora Audio Flagship Mall (conn-shopee-01)
  {
    id: 'ext-prod-sp-01',
    connectionId: 'conn-shopee-01',
    platform: 'shopee',
    shopId: '293849102',
    shopName: 'Aurora Audio Flagship Mall',
    channelProductId: 'SP_ITEM_2938491028',
    title: '【Shopee Mall 官方正品】Aurora Studio Pro 旗舰降噪头戴式无线耳机 60h长续航',
    imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Audio > Over-Ear Headphones',
    status: 'active',
    updatedAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-sp-101',
        channelSkuId: 'SP_MOD_84920194',
        channelSellerSku: 'SHP-AUR-BLK-ANC',
        title: 'Midnight Black / Studio Master ANC',
        specOptions: { 'Color': 'Midnight Black', 'Type': 'ANC Flagship' },
        price: 349.00,
        currency: 'MYR',
        stock: 45,
        imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-102',
        mappedJwSku: 'AUR-BLK-ANC',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Midnight Black / Studio Master ANC',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 45,
        mappedJwPrice: 349.00,
        mappedAt: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Nurul (Ops Manager)'
      },
      {
        id: 'ext-var-sp-102',
        channelSkuId: 'SP_MOD_84920195',
        channelSellerSku: 'SHP-AUR-WHT-STD',
        title: 'Arctic White / Standard Audio',
        specOptions: { 'Color': 'Arctic White', 'Type': 'Standard' },
        price: 299.00,
        currency: 'MYR',
        stock: 40,
        imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-103',
        mappedJwSku: 'AUR-WHT-STD',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Arctic White / Standard Audio',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 40,
        mappedJwPrice: 299.00,
        mappedAt: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Nurul (Ops Manager)'
      }
    ]
  },
  {
    id: 'ext-prod-sp-02',
    connectionId: 'conn-shopee-01',
    platform: 'shopee',
    shopId: '293849102',
    shopName: 'Aurora Audio Flagship Mall',
    channelProductId: 'SP_ITEM_8839201948',
    title: 'Solis 实木模块化桌面增高架 双显示器实木置物支架 航空铝合金支脚',
    imageUrl: 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Home & Living > Desk Organizers',
    status: 'active',
    updatedAt: new Date(Date.now() - 48 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-sp-201',
        channelSkuId: 'SP_MOD_44920184',
        channelSellerSku: 'SHP-SLS-WAL-105',
        title: 'Solid Walnut / Aluminum Riser (105cm)',
        specOptions: { 'Wood': 'Solid Walnut', 'Length': '105cm' },
        price: 189.00,
        currency: 'MYR',
        stock: 25,
        imageUrl: 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-05',
          variantId: 'var-501',
          sku: 'SLS-DSK-WAL',
          productName: 'Solis Modular Desk Shelf',
          variantTitle: 'Solid Walnut / Aluminum Riser (105cm)',
          imageUrl: 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=400&auto=format&fit=crop&q=80',
          stock: 25,
          price: 189.00,
          confidenceScore: 94,
          matchReason: '商品材质与尺寸规格（Solid Walnut / 105cm）属性完全匹配'
        }
      },
      {
        id: 'ext-var-sp-202',
        channelSkuId: 'SP_MOD_44920185',
        channelSellerSku: 'SHP-SLS-OAK-105',
        title: 'Natural Oak / Aluminum Riser (105cm)',
        specOptions: { 'Wood': 'Natural Oak', 'Length': '105cm' },
        price: 189.00,
        currency: 'MYR',
        stock: 20,
        imageUrl: 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-05',
          variantId: 'var-502',
          sku: 'SLS-DSK-OAK',
          productName: 'Solis Modular Desk Shelf',
          variantTitle: 'Natural Oak / Aluminum Riser (105cm)',
          imageUrl: 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=400&auto=format&fit=crop&q=80',
          stock: 20,
          price: 189.00,
          confidenceScore: 94,
          matchReason: '橡木规格属性（Natural Oak / 105cm）对应 JW SKU'
        }
      }
    ]
  },
  {
    id: 'ext-prod-sp-03',
    connectionId: 'conn-shopee-01',
    platform: 'shopee',
    shopId: '293849102',
    shopName: 'Aurora Audio Flagship Mall',
    channelProductId: 'SP_ITEM_1928374650',
    title: 'Chronos 极简包豪斯钛金属石英腕表 蓝宝石防刮镜面 40mm',
    imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Watches > Men Watches',
    status: 'active',
    updatedAt: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-sp-301',
        channelSkuId: 'SP_MOD_11223344',
        channelSellerSku: 'SHP-CHR-TIT-BLK',
        title: 'Matte Titanium / Black Leather Strap (40mm)',
        specOptions: { 'Case': 'Matte Titanium', 'Strap': 'Black Leather' },
        price: 249.00,
        currency: 'MYR',
        stock: 30,
        imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-06',
          variantId: 'var-601',
          sku: 'CHR-TIT-BLK',
          productName: 'Chronos Minimalist Watch',
          variantTitle: 'Matte Titanium / Black Leather Strap (40mm)',
          imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80',
          stock: 30,
          price: 249.00,
          confidenceScore: 97,
          matchReason: 'SKU 前缀完全匹配且表带表壳规格属性完全一致'
        }
      },
      {
        id: 'ext-var-sp-302',
        channelSkuId: 'SP_MOD_11223345',
        channelSellerSku: 'SHP-CHR-TIT-MSH',
        title: 'Raw Titanium / Milanese Mesh Band (40mm)',
        specOptions: { 'Case': 'Raw Titanium', 'Strap': 'Milanese Mesh' },
        price: 269.00,
        currency: 'MYR',
        stock: 20,
        imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-06',
          variantId: 'var-602',
          sku: 'CHR-TIT-MSH',
          productName: 'Chronos Minimalist Watch',
          variantTitle: 'Raw Titanium / Milanese Mesh Band (40mm)',
          imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&auto=format&fit=crop&q=80',
          stock: 20,
          price: 269.00,
          confidenceScore: 97,
          matchReason: '米兰尼斯金属表带规格与 JW SKU 匹配'
        }
      }
    ]
  },

  // 3. Lazada: Aurora Audio LazMall Store (conn-lazada-01)
  {
    id: 'ext-prod-lz-01',
    connectionId: 'conn-lazada-01',
    platform: 'lazada',
    shopId: 'MY102938482',
    shopName: 'Aurora Audio LazMall Store',
    channelProductId: 'LZ_ITEM_98372819',
    title: 'Aurora Studio Pro ANC Wireless Over-Ear Headphones (LazMall Certified)',
    imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Audio > Over The Ear Headphones',
    status: 'active',
    updatedAt: new Date(Date.now() - 40 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-lz-101',
        channelSkuId: 'LZ_SKU_9948201',
        channelSellerSku: 'LZ-AUR-ANC-BLK',
        title: 'Midnight Black / Studio Master ANC',
        specOptions: { 'Color': 'Midnight Black', 'Edition': 'Studio Master ANC' },
        price: 349.00,
        currency: 'MYR',
        stock: 42,
        imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-102',
        mappedJwSku: 'AUR-BLK-ANC',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Midnight Black / Studio Master ANC',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 45,
        mappedJwPrice: 349.00,
        mappedAt: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Hazim (Lead Ops)'
      },
      {
        id: 'ext-var-lz-102',
        channelSkuId: 'LZ_SKU_9948202',
        channelSellerSku: 'LZ-AUR-ANC-WHT',
        title: 'Arctic White / Studio Master ANC',
        specOptions: { 'Color': 'Arctic White', 'Edition': 'Studio Master ANC' },
        price: 349.00,
        currency: 'MYR',
        stock: 35,
        imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-104',
        mappedJwSku: 'AUR-WHT-ANC',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Arctic White / Studio Master ANC',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 35,
        mappedJwPrice: 349.00,
        mappedAt: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Hazim (Lead Ops)'
      }
    ]
  },
  {
    id: 'ext-prod-lz-02',
    connectionId: 'conn-lazada-01',
    platform: 'lazada',
    shopId: 'MY102938482',
    shopName: 'Aurora Audio LazMall Store',
    channelProductId: 'LZ_ITEM_55920194',
    title: 'AeroFit Open-Ear Wireless Earbuds Bluetooth 5.4 Ergonomic Sport Hooks',
    imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Audio > In-Ear Earbuds',
    status: 'active',
    updatedAt: new Date(Date.now() - 75 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-lz-201',
        channelSkuId: 'LZ_SKU_5549201',
        channelSellerSku: 'LZ-AFT-CHR',
        title: 'Cosmic Charcoal / Standard Edition',
        specOptions: { 'Color': 'Cosmic Charcoal' },
        price: 149.00,
        currency: 'MYR',
        stock: 55,
        imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-02',
          variantId: 'var-201',
          sku: 'AFT-EAR-CHR',
          productName: 'AeroFit Open-Ear Wireless Earbuds',
          variantTitle: 'Cosmic Charcoal / Standard Edition',
          imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400&auto=format&fit=crop&q=80',
          stock: 60,
          price: 149.00,
          confidenceScore: 98,
          matchReason: '型号 (AeroFit) 及配色 (Cosmic Charcoal) 高度匹配'
        }
      },
      {
        id: 'ext-var-lz-202',
        channelSkuId: 'LZ_SKU_5549202',
        channelSellerSku: 'LZ-AFT-GLC',
        title: 'Glacier Silver / Standard Edition',
        specOptions: { 'Color': 'Glacier Silver' },
        price: 149.00,
        currency: 'MYR',
        stock: 40,
        imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-02',
          variantId: 'var-202',
          sku: 'AFT-EAR-GLC',
          productName: 'AeroFit Open-Ear Wireless Earbuds',
          variantTitle: 'Glacier Silver / Standard Edition',
          imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=400&auto=format&fit=crop&q=80',
          stock: 50,
          price: 149.00,
          confidenceScore: 98,
          matchReason: '型号及冰川银配色 (Glacier Silver) 完全匹配'
        }
      }
    ]
  },

  // 4. Lazada: Aurora Audio LazMall Store (conn-lazada-01) - Lifestyle Audio
  {
    id: 'ext-prod-sh-01',
    connectionId: 'conn-lazada-01',
    platform: 'lazada',
    shopId: 'MY102938482',
    shopName: 'Aurora Audio LazMall Store',
    channelProductId: 'LZ_PRD_8492019482',
    title: 'Aurora Studio Pro Headphones - LazMall Catalog Edition',
    imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Headphones',
    status: 'active',
    updatedAt: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-sh-101',
        channelSkuId: 'LZ_MOD_449201928471',
        channelSellerSku: 'LZ-AUR-BLK-ANC',
        title: 'Midnight Black / Studio Master ANC',
        specOptions: { 'Color': 'Midnight Black', 'Edition': 'Studio Master ANC' },
        price: 349.00,
        currency: 'MYR',
        stock: 45,
        imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-102',
        mappedJwSku: 'AUR-BLK-ANC',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Midnight Black / Studio Master ANC',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 45,
        mappedJwPrice: 349.00,
        mappedAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Nurul (Ops Manager)'
      },
      {
        id: 'ext-var-sh-102',
        channelSkuId: 'LZ_MOD_449201928472',
        channelSellerSku: 'LZ-AUR-WHT-ANC',
        title: 'Arctic White / Studio Master ANC',
        specOptions: { 'Color': 'Arctic White', 'Edition': 'Studio Master ANC' },
        price: 349.00,
        currency: 'MYR',
        stock: 35,
        imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'mapped',
        mappedJwProductId: 'prod-01',
        mappedJwVariantId: 'var-104',
        mappedJwSku: 'AUR-WHT-ANC',
        mappedJwProductName: 'Aurora Studio Pro Wireless Headphones',
        mappedJwVariantTitle: 'Arctic White / Studio Master ANC',
        mappedJwVariantImage: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=400&auto=format&fit=crop&q=80',
        mappedJwStock: 35,
        mappedJwPrice: 349.00,
        mappedAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(),
        mappedBy: 'Nurul (Ops Manager)'
      }
    ]
  },
  {
    id: 'ext-prod-sh-02',
    connectionId: 'conn-shopee-01',
    platform: 'shopee',
    shopId: '293849102',
    shopName: 'Aurora Audio Flagship Mall',
    channelProductId: 'SP_ITEM_110293847',
    title: 'Vantage Carbon Fiber Commuter Backpack 22L Waterproof Laptop Compartment',
    imageUrl: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400&auto=format&fit=crop&q=80',
    categoryName: 'Bags & Accessories',
    status: 'active',
    updatedAt: new Date(Date.now() - 150 * 60 * 1000).toISOString(),
    variants: [
      {
        id: 'ext-var-sh-201',
        channelSkuId: 'gid://shopify/ProductVariant/7729103849',
        channelSellerSku: 'SPF-VNTG-22L',
        title: 'Stealth Matte Carbon / 22L Daily Commuter',
        specOptions: { 'Color': 'Stealth Matte Carbon', 'Capacity': '22L' },
        price: 219.00,
        currency: 'MYR',
        stock: 35,
        imageUrl: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=200&auto=format&fit=crop&q=80',
        mappingStatus: 'unmapped',
        suggestedMatch: {
          productId: 'prod-08',
          variantId: 'var-801',
          sku: 'VNTG-BP-22L',
          productName: 'Vantage Carbon Fiber Backpack',
          variantTitle: 'Stealth Matte Carbon / 22L Daily Commuter',
          imageUrl: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400&auto=format&fit=crop&q=80',
          stock: 35,
          price: 219.00,
          confidenceScore: 97,
          matchReason: '背包型号与容量规格（22L / Carbon）完全对应'
        }
      }
    ]
  }
];
