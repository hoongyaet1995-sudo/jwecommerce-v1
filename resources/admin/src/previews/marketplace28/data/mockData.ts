import { AdminUser, Category, Product } from '../types';

export const INITIAL_ADMIN_USER: AdminUser = {
  id: 'usr-admin-01',
  name: 'Alexander Sterling',
  email: 'alexander.s@auroracommerce.io',
  avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
  role: 'super_admin',
  twoFactorEnabled: true,
};
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
