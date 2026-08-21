import { 
  AdminUser, 
  HeaderMenuL1, 
  FooterMenuL1, 
  Category, 
  Collection, 
  Product, 
  Customer, 
  Order,
  CmsPage,
  StaffUser,
  StaffPermissionOption,
  ShopGeneralSettings,
  SocialMediaLinks
} from '../types';
import { DEFAULT_JW_STUDIO_LOGO } from './defaultLogo';

export const INITIAL_ADMIN_USER: AdminUser = {
  id: 'usr-admin-01',
  name: 'Alexander Sterling',
  email: 'alexander.s@auroracommerce.io',
  avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
  role: 'super_admin',
  twoFactorEnabled: true,
};

export const INITIAL_HEADER_MENUS: HeaderMenuL1[] = [
  {
    id: 'l1-tech',
    title: 'Electronics & Audio',
    url: '/categories/electronics',
    linkType: 'category',
    targetId: 'cat-electronics',
    icon: 'Laptop',
    badge: 'Popular',
    isHighlighted: false,
    displayStyle: 'megamenu',
    isActive: true,
    sortOrder: 1,
    columns: [
      {
        id: 'l2-headphones',
        title: 'Premium Audio',
        url: '/categories/audio',
        linkType: 'category',
        targetId: 'cat-audio',
        icon: 'Headphones',
        badge: 'Top Rated',
        isActive: true,
        sortOrder: 1,
        items: [
          { id: 'l3-anc', title: 'Noise Cancelling Headphones', url: '/categories/anc-headphones', linkType: 'category', badge: 'HOT', isActive: true, sortOrder: 1 },
          { id: 'l3-tws', title: 'True Wireless Earbuds', url: '/categories/wireless-earbuds', linkType: 'category', isActive: true, sortOrder: 2 },
          { id: 'l3-dac', title: 'Hi-Fi DACs & Amplifiers', url: '/categories/dacs-amps', linkType: 'category', isActive: true, sortOrder: 3 },
          { id: 'l3-studio', title: 'Studio Reference Monitors', url: '/categories/studio-monitors', linkType: 'category', isActive: true, sortOrder: 4 },
        ]
      },
      {
        id: 'l2-wearables',
        title: 'Wearables & Smart',
        url: '/categories/wearables',
        linkType: 'category',
        targetId: 'cat-wearables',
        icon: 'Watch',
        isActive: true,
        sortOrder: 2,
        items: [
          { id: 'l3-smartwatch', title: 'GPS Smartwatches', url: '/categories/smartwatches', linkType: 'category', isActive: true, sortOrder: 1 },
          { id: 'l3-rings', title: 'Smart Health Rings', url: '/categories/smart-rings', linkType: 'category', badge: 'NEW', isActive: true, sortOrder: 2 },
          { id: 'l3-bands', title: 'Fitness Trackers', url: '/categories/fitness-bands', linkType: 'category', isActive: true, sortOrder: 3 },
        ]
      },
      {
        id: 'l2-workspace',
        title: 'Desk & Workspace',
        url: '/categories/workspace',
        linkType: 'category',
        icon: 'Layers',
        isActive: true,
        sortOrder: 3,
        items: [
          { id: 'l3-keyboards', title: 'Custom Mechanical Keyboards', url: '/categories/keyboards', linkType: 'category', isActive: true, sortOrder: 1 },
          { id: 'l3-mats', title: 'Merino Wool Desk Mats', url: '/categories/desk-mats', linkType: 'category', isActive: true, sortOrder: 2 },
          { id: 'l3-chargers', title: 'Magnetic Wireless Docks', url: '/categories/chargers', linkType: 'category', isActive: true, sortOrder: 3 },
        ]
      }
    ]
  },
  {
    id: 'l1-apparel',
    title: 'Apparel & Lifestyle',
    url: '/categories/apparel',
    linkType: 'category',
    targetId: 'cat-apparel',
    icon: 'Shirt',
    displayStyle: 'megamenu',
    isActive: true,
    sortOrder: 2,
    columns: [
      {
        id: 'l2-menswear',
        title: 'Men’s Minimalist',
        url: '/categories/mens',
        linkType: 'category',
        isActive: true,
        sortOrder: 1,
        items: [
          { id: 'l3-outerwear', title: 'Technical Outerwear & Parkas', url: '/categories/outerwear', linkType: 'category', isActive: true, sortOrder: 1 },
          { id: 'l3-merino', title: 'Merino Wool Sweaters', url: '/categories/sweaters', linkType: 'category', isActive: true, sortOrder: 2 },
          { id: 'l3-pants', title: 'Commuter Trousers', url: '/categories/trousers', linkType: 'category', isActive: true, sortOrder: 3 },
        ]
      },
      {
        id: 'l2-gear',
        title: 'Bags & Carry Gear',
        url: '/categories/bags',
        linkType: 'category',
        isActive: true,
        sortOrder: 2,
        items: [
          { id: 'l3-backpacks', title: 'Weatherproof Everyday Packs', url: '/categories/backpacks', linkType: 'category', badge: 'Best Seller', isActive: true, sortOrder: 1 },
          { id: 'l3-duffle', title: 'Modular Travel Duffles', url: '/categories/duffles', linkType: 'category', isActive: true, sortOrder: 2 },
          { id: 'l3-pouches', title: 'Tech Accessories Pouches', url: '/categories/pouches', linkType: 'category', isActive: true, sortOrder: 3 },
        ]
      }
    ]
  },
  {
    id: 'l1-collections',
    title: 'Collections',
    url: '/collections',
    linkType: 'collection',
    icon: 'Sparkles',
    displayStyle: 'dropdown',
    isActive: true,
    sortOrder: 3,
    columns: [
      {
        id: 'l2-curated',
        title: 'Featured Curations',
        url: '/collections/all',
        linkType: 'collection',
        isActive: true,
        sortOrder: 1,
        items: [
          { id: 'l3-col-summer', title: 'Summer Tech Essentials 2026', url: '/collections/summer-tech', linkType: 'collection', targetId: 'col-summer', badge: 'SALE', isActive: true, sortOrder: 1 },
          { id: 'l3-col-travel', title: 'Nomad Workstation Kit', url: '/collections/nomad-kit', linkType: 'collection', targetId: 'col-nomad', isActive: true, sortOrder: 2 },
          { id: 'l3-col-eco', title: 'Circular & Eco-Crafted', url: '/collections/eco-crafted', linkType: 'collection', isActive: true, sortOrder: 3 },
        ]
      }
    ]
  },
  {
    id: 'l1-clearance',
    title: 'Archive Clearance',
    url: '/collections/clearance',
    linkType: 'collection',
    targetId: 'col-clearance',
    icon: 'Tag',
    badge: '30% OFF',
    isHighlighted: true,
    displayStyle: 'simple_link',
    isActive: true,
    sortOrder: 4,
    columns: []
  }
];

export const INITIAL_FOOTER_MENUS: FooterMenuL1[] = [
  {
    id: 'ft-support',
    sectionTitle: 'Customer Care & Support',
    isActive: true,
    sortOrder: 1,
    links: [
      { id: 'ftl-track', title: 'Track Order & Shipping', url: '/orders/track', linkType: 'page', badge: 'Live', isActive: true, sortOrder: 1 },
      { id: 'ftl-returns', title: '30-Day Hassle-Free Returns', url: '/returns', linkType: 'page', isActive: true, sortOrder: 2 },
      { id: 'ftl-warranty', title: '2-Year Global Warranty', url: '/warranty', linkType: 'page', isActive: true, sortOrder: 3 },
      { id: 'ftl-contact', title: 'Contact Concierge', url: '/contact', linkType: 'page', isActive: true, sortOrder: 4 },
    ]
  },
  {
    id: 'ft-about',
    sectionTitle: 'About & Brand Story',
    isActive: true,
    sortOrder: 2,
    links: [
      { id: 'ftl-story', title: 'Our Design Philosophy', url: '/about/philosophy', linkType: 'page', isActive: true, sortOrder: 1 },
      { id: 'ftl-sustainability', title: 'Sustainability Report 2026', url: '/about/sustainability', linkType: 'page', isActive: true, sortOrder: 2 },
      { id: 'ftl-press', title: 'Press Kit & Media Inquiries', url: '/press', linkType: 'page', isActive: true, sortOrder: 3 },
      { id: 'ftl-careers', title: 'Careers (We are hiring!)', url: 'https://careers.auroracommerce.io', linkType: 'custom_url', isExternal: true, badge: '5 Open', isActive: true, sortOrder: 4 },
    ]
  },
  {
    id: 'ft-legal',
    sectionTitle: 'Legal & Privacy',
    isActive: true,
    sortOrder: 3,
    links: [
      { id: 'ftl-privacy', title: 'Privacy Policy (GDPR / CCPA)', url: '/legal/privacy', linkType: 'page', isActive: true, sortOrder: 1 },
      { id: 'ftl-terms', title: 'Terms of Service', url: '/legal/terms', linkType: 'page', isActive: true, sortOrder: 2 },
      { id: 'ftl-cookie', title: 'Cookie Settings & Consent', url: '/legal/cookies', linkType: 'page', isActive: true, sortOrder: 3 },
    ]
  },
  {
    id: 'ft-developer',
    sectionTitle: 'Developers & Partners',
    isActive: true,
    sortOrder: 4,
    links: [
      { id: 'ftl-api', title: 'Universal Ecommerce API Docs', url: '/docs/api', linkType: 'page', badge: 'v2.4', isActive: true, sortOrder: 1 },
      { id: 'ftl-affiliates', title: 'Affiliate Partner Program', url: '/affiliates', linkType: 'page', isActive: true, sortOrder: 2 },
      { id: 'ftl-wholesale', title: 'B2B Wholesale Inquiries', url: '/wholesale', linkType: 'page', isActive: true, sortOrder: 3 },
    ]
  }
];

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

export const INITIAL_COLLECTIONS: Collection[] = [
  {
    id: 'col-summer',
    title: 'Summer Tech Essentials 2026',
    slug: 'summer-tech-essentials',
    description: 'Curated audio gear and portable power accessories for outdoor travel.',
    coverImage: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
    type: 'dynamic',
    matchType: 'all',
    dynamicRules: [
      { id: 'r-1', field: 'price', operator: 'greater_than', value: '49' },
      { id: 'r-2', field: 'tag', operator: 'equals', value: 'summer' }
    ],
    status: 'active',
    productCount: 12,
    createdAt: '2026-06-01'
  },
  {
    id: 'col-nomad',
    title: 'Nomad Workstation Kit',
    slug: 'nomad-kit',
    description: 'Lightweight mechanical keyboards, wireless mice, and padded laptop sleeves.',
    coverImage: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&auto=format&fit=crop&q=80',
    type: 'manual',
    manualProductIds: ['prod-01', 'prod-02', 'prod-03'],
    status: 'active',
    productCount: 3,
    createdAt: '2026-06-15'
  },
  {
    id: 'col-clearance',
    title: 'Archive Clearance Flash Sale',
    slug: 'clearance-flash-sale',
    description: 'Limited remaining stock from previous seasonal collections at up to 40% discount.',
    coverImage: 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800&auto=format&fit=crop&q=80',
    type: 'dynamic',
    matchType: 'any',
    dynamicRules: [
      { id: 'r-3', field: 'tag', operator: 'equals', value: 'clearance' },
      { id: 'r-4', field: 'inventory', operator: 'less_than', value: '20' }
    ],
    status: 'active',
    productCount: 8,
    createdAt: '2026-07-01'
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
        values: ['Midnight Black', 'Arctic White', 'Forest Pine']
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
        enabled: true
      }
    ],
    seoTitle: 'Aurora Studio Pro Headphones | Lossless Audio ANC',
    seoDescription: 'High resolution wireless headphones with active noise cancellation and ergonomic fit.',
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
        values: ['Anodized Space Gray', 'E-Coat Cream']
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
  }
];

export const INITIAL_CUSTOMERS: Customer[] = [
  {
    id: 'cust-01',
    name: 'Elena Rostova',
    email: 'elena.rostova@techlux.co',
    phone: '+1 (415) 892-3401',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
    totalOrders: 6,
    totalSpent: 1840.50,
    currency: 'USD',
    registeredAt: '2025-11-14',
    lastOrderAt: '2026-08-12',
    status: 'vip',
    tags: ['VIP', 'Repeat Buyer', 'Audio Enthusiast'],
    notes: 'Preferred courier: FedEx Priority. Often requests signature waiver.',
    addresses: [
      {
        id: 'addr-01',
        isDefault: true,
        type: 'shipping',
        firstName: 'Elena',
        lastName: 'Rostova',
        company: 'Apex Design Collective',
        address1: '450 Mission Street, Suite 1400',
        city: 'San Francisco',
        province: 'CA',
        country: 'United States',
        postalCode: '94105',
        phone: '+1 (415) 892-3401'
      }
    ]
  },
  {
    id: 'cust-02',
    name: 'Marcus Vance',
    email: 'm.vance@vancemedia.studio',
    phone: '+1 (212) 555-0198',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    totalOrders: 3,
    totalSpent: 735.00,
    currency: 'USD',
    registeredAt: '2026-01-20',
    lastOrderAt: '2026-08-08',
    status: 'active',
    tags: ['Tech Enthusiast'],
    addresses: [
      {
        id: 'addr-02',
        isDefault: true,
        type: 'shipping',
        firstName: 'Marcus',
        lastName: 'Vance',
        address1: '72 Franklin Ave, Apt 4B',
        city: 'Brooklyn',
        province: 'NY',
        country: 'United States',
        postalCode: '11205',
        phone: '+1 (212) 555-0198'
      }
    ]
  },
  {
    id: 'cust-03',
    name: 'Sophia Lindqvist',
    email: 'sophia@scandicnorth.se',
    phone: '+46 8 123 4567',
    avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&auto=format&fit=crop&q=80',
    totalOrders: 2,
    totalSpent: 488.00,
    currency: 'USD',
    registeredAt: '2026-03-05',
    lastOrderAt: '2026-07-29',
    status: 'active',
    tags: ['International', 'Design Professional'],
    addresses: [
      {
        id: 'addr-03',
        isDefault: true,
        type: 'shipping',
        firstName: 'Sophia',
        lastName: 'Lindqvist',
        address1: 'Storgatan 18',
        city: 'Stockholm',
        province: 'Stockholm',
        country: 'Sweden',
        postalCode: '114 55',
        phone: '+46 8 123 4567'
      }
    ]
  },
  {
    id: 'cust-04',
    name: 'David Chen',
    email: 'david.chen@pacificbio.org',
    phone: '+1 (206) 430-8821',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
    totalOrders: 1,
    totalSpent: 165.00,
    currency: 'USD',
    registeredAt: '2026-08-01',
    lastOrderAt: '2026-08-01',
    status: 'active',
    tags: ['First Time Buyer'],
    addresses: [
      {
        id: 'addr-04',
        isDefault: true,
        type: 'shipping',
        firstName: 'David',
        lastName: 'Chen',
        address1: '1200 5th Ave, Floor 8',
        city: 'Seattle',
        province: 'WA',
        country: 'United States',
        postalCode: '98101',
        phone: '+1 (206) 430-8821'
      }
    ]
  }
];

export const INITIAL_ORDERS: Order[] = [
  {
    id: 'ord-1001',
    orderNumber: 'ORD-9842',
    createdAt: '2026-08-14T14:32:00Z',
    customerId: 'cust-01',
    customerName: 'Elena Rostova',
    customerEmail: 'elena.rostova@techlux.co',
    customerPhone: '+1 (415) 892-3401',
    paymentGateway: 'Stripe',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'processing',
    currency: 'USD',
    items: [
      {
        id: 'item-01',
        productId: 'prod-01',
        variantId: 'var-102',
        title: 'Aurora Studio Pro Wireless Headphones',
        variantTitle: 'Midnight Black / Studio Master ANC',
        sku: 'AUR-BLK-ANC',
        price: 349,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=150&auto=format&fit=crop&q=80',
        weightGrams: 325
      },
      {
        id: 'item-02',
        productId: 'prod-02',
        variantId: 'var-201',
        title: 'Nexus 75% Custom Mechanical Keyboard',
        variantTitle: 'Linear Quartz 45g / Anodized Space Gray',
        sku: 'NEX-LIN-GRY',
        price: 189,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=150&auto=format&fit=crop&q=80',
        weightGrams: 1420
      }
    ],
    subtotal: 538,
    taxAmount: 48.42,
    shippingAmount: 0, // Free expedited
    discountAmount: 25.00,
    totalAmount: 561.42,
    trackingNumber: 'TRK-99214088US',
    trackingCarrier: 'FedEx Express Priority',
    shippingAddress: {
      id: 'ord-addr-01',
      isDefault: true,
      type: 'shipping',
      firstName: 'Elena',
      lastName: 'Rostova',
      company: 'Apex Design Collective',
      address1: '450 Mission Street, Suite 1400',
      city: 'San Francisco',
      province: 'CA',
      country: 'United States',
      postalCode: '94105',
      phone: '+1 (415) 892-3401'
    },
    billingAddress: {
      id: 'ord-baddr-01',
      isDefault: true,
      type: 'billing',
      firstName: 'Elena',
      lastName: 'Rostova',
      company: 'Apex Design Collective',
      address1: '450 Mission Street, Suite 1400',
      city: 'San Francisco',
      province: 'CA',
      country: 'United States',
      postalCode: '94105',
      phone: '+1 (415) 892-3401'
    },
    customerNote: 'Please leave package with front reception desk if after 5 PM.',
    internalNote: 'High value client - flagged for VIP handling.',
    timeline: [
      {
        id: 'ev-1',
        timestamp: '2026-08-14T14:32:00Z',
        type: 'created',
        title: 'Order Created',
        description: 'Customer placed order via Web Storefront (Checkout v2.4)'
      },
      {
        id: 'ev-2',
        timestamp: '2026-08-14T14:32:15Z',
        type: 'paid',
        title: 'Payment Succeeded',
        description: 'Stripe charge ch_3P7x82 captured $561.42 successfully'
      },
      {
        id: 'ev-3',
        timestamp: '2026-08-14T15:10:00Z',
        type: 'fulfillment_updated',
        title: 'Moved to Processing',
        description: 'Allocated to Warehouse Bay Area Facility A-3'
      }
    ]
  },
  {
    id: 'ord-1002',
    orderNumber: 'ORD-9841',
    createdAt: '2026-08-13T10:15:00Z',
    customerId: 'cust-02',
    customerName: 'Marcus Vance',
    customerEmail: 'm.vance@vancemedia.studio',
    customerPhone: '+1 (212) 555-0198',
    paymentGateway: 'Apple Pay',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'shipped',
    currency: 'USD',
    items: [
      {
        id: 'item-03',
        productId: 'prod-03',
        title: 'Vanguard Cordura 35L Weekender Pack',
        variantTitle: 'Default Obsidian Black',
        sku: 'VAN-35L-OBS',
        price: 165,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=150&auto=format&fit=crop&q=80',
        weightGrams: 1200
      }
    ],
    subtotal: 165,
    taxAmount: 14.85,
    shippingAmount: 15.00,
    discountAmount: 0,
    totalAmount: 194.85,
    trackingNumber: 'UPS-1Z9999999999999999',
    trackingCarrier: 'UPS Ground Air',
    shippingAddress: {
      id: 'ord-addr-02',
      isDefault: true,
      type: 'shipping',
      firstName: 'Marcus',
      lastName: 'Vance',
      address1: '72 Franklin Ave, Apt 4B',
      city: 'Brooklyn',
      province: 'NY',
      country: 'United States',
      postalCode: '11205',
      phone: '+1 (212) 555-0198'
    },
    billingAddress: {
      id: 'ord-baddr-02',
      isDefault: true,
      type: 'billing',
      firstName: 'Marcus',
      lastName: 'Vance',
      address1: '72 Franklin Ave, Apt 4B',
      city: 'Brooklyn',
      province: 'NY',
      country: 'United States',
      postalCode: '11205',
      phone: '+1 (212) 555-0198'
    },
    timeline: [
      {
        id: 'ev-10',
        timestamp: '2026-08-13T10:15:00Z',
        type: 'created',
        title: 'Order Created',
        description: 'Customer checked out via Apple Pay'
      },
      {
        id: 'ev-11',
        timestamp: '2026-08-13T16:45:00Z',
        type: 'tracking_added',
        title: 'Tracking Dispatched',
        description: 'Assigned tracking UPS-1Z9999999999999999 via UPS Ground'
      }
    ]
  },
  {
    id: 'ord-1003',
    orderNumber: 'ORD-9840',
    createdAt: '2026-08-12T08:20:00Z',
    customerId: 'cust-03',
    customerName: 'Sophia Lindqvist',
    customerEmail: 'sophia@scandicnorth.se',
    paymentGateway: 'Credit Card',
    paymentGatewayStatus: 'pending',
    fulfillmentStatus: 'unfulfilled',
    currency: 'USD',
    items: [
      {
        id: 'item-04',
        productId: 'prod-01',
        variantId: 'var-104',
        title: 'Aurora Studio Pro Wireless Headphones',
        variantTitle: 'Arctic White / Studio Master ANC',
        sku: 'AUR-WHT-ANC',
        price: 349,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=150&auto=format&fit=crop&q=80'
      }
    ],
    subtotal: 349,
    taxAmount: 0,
    shippingAmount: 35.00,
    discountAmount: 0,
    totalAmount: 384.00,
    shippingAddress: {
      id: 'ord-addr-03',
      isDefault: true,
      type: 'shipping',
      firstName: 'Sophia',
      lastName: 'Lindqvist',
      address1: 'Storgatan 18',
      city: 'Stockholm',
      province: 'Stockholm',
      country: 'Sweden',
      postalCode: '114 55',
      phone: '+46 8 123 4567'
    },
    billingAddress: {
      id: 'ord-baddr-03',
      isDefault: true,
      type: 'billing',
      firstName: 'Sophia',
      lastName: 'Lindqvist',
      address1: 'Storgatan 18',
      city: 'Stockholm',
      province: 'Stockholm',
      country: 'Sweden',
      postalCode: '114 55',
      phone: '+46 8 123 4567'
    },
    timeline: [
      {
        id: 'ev-20',
        timestamp: '2026-08-12T08:20:00Z',
        type: 'created',
        title: 'Order Placed',
        description: 'Payment 3DS verification awaiting customer confirmation'
      }
    ]
  },
  {
    id: 'ord-1004',
    orderNumber: 'ORD-9839',
    createdAt: '2026-08-10T19:40:00Z',
    customerId: 'cust-04',
    customerName: 'David Chen',
    customerEmail: 'david.chen@pacificbio.org',
    paymentGateway: 'Stripe',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'delivered',
    currency: 'USD',
    items: [
      {
        id: 'item-05',
        productId: 'prod-03',
        title: 'Vanguard Cordura 35L Weekender Pack',
        variantTitle: 'Default Obsidian Black',
        sku: 'VAN-35L-OBS',
        price: 165,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=150&auto=format&fit=crop&q=80'
      }
    ],
    subtotal: 165,
    taxAmount: 15.20,
    shippingAmount: 0,
    discountAmount: 0,
    totalAmount: 180.20,
    trackingNumber: 'FDX-773829102938',
    trackingCarrier: 'FedEx Home Delivery',
    shippingAddress: {
      id: 'ord-addr-04',
      isDefault: true,
      type: 'shipping',
      firstName: 'David',
      lastName: 'Chen',
      address1: '1200 5th Ave, Floor 8',
      city: 'Seattle',
      province: 'WA',
      country: 'United States',
      postalCode: '98101',
      phone: '+1 (206) 430-8821'
    },
    billingAddress: {
      id: 'ord-baddr-04',
      isDefault: true,
      type: 'billing',
      firstName: 'David',
      lastName: 'Chen',
      address1: '1200 5th Ave, Floor 8',
      city: 'Seattle',
      province: 'WA',
      country: 'United States',
      postalCode: '98101',
      phone: '+1 (206) 430-8821'
    },
    timeline: [
      {
        id: 'ev-30',
        timestamp: '2026-08-10T19:40:00Z',
        type: 'created',
        title: 'Order Placed',
        description: 'Paid via Visa ending in 4242'
      },
      {
        id: 'ev-31',
        timestamp: '2026-08-12T11:00:00Z',
        type: 'fulfillment_updated',
        title: 'Delivered',
        description: 'Delivered at front door by FedEx'
      }
    ]
  }
];

export const INITIAL_PAGES: CmsPage[] = [
  {
    id: 'page-brand-story',
    title: 'Brand Story & Heritage',
    slug: 'brand-story',
    description: 'The artisanal ethos, heritage craftsmanship, and acoustic engineering behind Aurora.',
    status: 'published',
    template: 'fullwidth',
    heroImage: 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=1200&auto=format&fit=crop&q=80',
    seoTitle: 'Brand Story & Heritage | Aurora Commerce',
    seoDescription: 'Discover our journey of combining Scandinavian minimalist design with audiophile precision.',
    createdAt: '2026-01-15',
    updatedAt: '2026-08-14',
    sections: [
      {
        id: 'sec-hero-1',
        type: 'hero_banner',
        title: 'Crafted for Pure Acoustic Resonance',
        subtitle: 'THE AURORA DESIGN MANIFESTO',
        content: 'Born in Stockholm, engineered for perfectionists worldwide. Every curve, driver, and stitch is calculated to elevate how you experience sound.',
        mediaUrl: 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=1600&auto=format&fit=crop&q=80',
        mediaType: 'image',
        buttonText: 'Explore Collection',
        buttonLink: '/collections/flagship',
        secondaryButtonText: 'Watch Short Film',
        secondaryButtonLink: '#brand-video',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'spacious',
        overlayOpacity: 55,
        isVisible: true
      },
      {
        id: 'sec-quote-1',
        type: 'rich_text',
        title: 'An Obsession with Tactile Perfection',
        subtitle: 'PHILOSOPHY & VISION',
        content: 'We believe audio equipment shouldn\'t simply disappear into the background—it should be an heirloom object of industrial art that sparks tactile joy every single day.',
        quote: '“True luxury is not excess; it is the absolute removal of everything unnecessary until only timeless resonance remains.”',
        authorName: 'Elin Lindqvist',
        authorRole: 'Head of Industrial Design',
        authorAvatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200&auto=format&fit=crop&q=80',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-900',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true
      },
      {
        id: 'sec-split-1',
        type: 'media_text_split',
        title: 'Precision Machined Aerograde Titanium',
        subtitle: 'MATERIALS & ACOUSTICS',
        content: 'Our bespoke headband gimbals and acoustic earcups are CNC-milled from monolithic blocks of aircraft-grade titanium alloy. This provides unrivaled resonance damping and featherweight structural rigidity.',
        mediaUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1000&auto=format&fit=crop&q=80',
        mediaType: 'image',
        mediaPosition: 'right',
        mediaAspectRatio: '4:3',
        buttonText: 'Read Engineering Whitepaper',
        buttonLink: '/specs/titanium',
        textAlignment: 'left',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true
      },
      {
        id: 'sec-features-1',
        type: 'feature_cards',
        title: 'The 4 Pillars of Aurora Craft',
        subtitle: 'UNCOMPROMISING METRICS',
        content: 'Each device undergoes 142 distinct stress tests and anechoic acoustic calibration steps before leaving our workshop.',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-900',
        textColor: 'light',
        paddingY: 'normal',
        columnsCount: 4,
        isVisible: true,
        items: [
          {
            id: 'feat-1',
            title: 'Anechoic Calibrated',
            description: 'Individual frequency response tuning within ±0.5dB tolerance curve.',
            icon: 'Sparkles',
            badge: '0.01% THD'
          },
          {
            id: 'feat-2',
            title: 'Full-Grain Lambskin',
            description: 'Ethically sourced Italian lambskin earpads filled with memory foam.',
            icon: 'ShieldCheck',
            badge: 'Comfort'
          },
          {
            id: 'feat-3',
            title: '40-Hour Battery',
            description: 'Next-gen solid-state lithium cells with 15-minute quick flash recharge.',
            icon: 'Zap',
            badge: 'Fast Charge'
          },
          {
            id: 'feat-4',
            title: 'Lifetime Support',
            description: 'Fully modular architecture designed to be repaired and serviced for decades.',
            icon: 'Globe',
            badge: 'Eco Repair'
          }
        ]
      },
      {
        id: 'sec-cta-1',
        type: 'cta_strip',
        title: 'Experience Aurora in Person',
        subtitle: 'FLAGSHIP SHOWROOMS & STUDIOS',
        content: 'Book a private 45-minute acoustic listening session in our London, Tokyo, or New York listening lounges.',
        buttonText: 'Book Listening Session',
        buttonLink: '/pages/showrooms',
        secondaryButtonText: 'View Store Locations',
        secondaryButtonLink: '/pages/locations',
        textAlignment: 'center',
        backgroundColor: 'bg-gradient-indigo',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true
      }
    ]
  },
  {
    id: 'page-summer-lookbook',
    title: 'Summer 2026 Lookbook',
    slug: 'summer-lookbook-2026',
    description: 'Visual editorial gallery showcasing the Nordic Summer acoustic and apparel series.',
    status: 'published',
    template: 'standard',
    heroImage: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1200&auto=format&fit=crop&q=80',
    seoTitle: 'Summer 2026 Visual Lookbook | Aurora',
    seoDescription: 'Explore the summer aesthetic: minimalist travel gear, wireless studio monitors, and breathable linen audio pouches.',
    createdAt: '2026-05-20',
    updatedAt: '2026-08-10',
    sections: [
      {
        id: 'sec-lb-hero',
        type: 'hero_banner',
        title: 'Summer 2026: Midsummer Soundscapes',
        subtitle: 'EDITORIAL LOOKBOOK',
        content: 'Embrace radiant Nordic daylight and high-fidelity sound on the go.',
        mediaUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1600&auto=format&fit=crop&q=80',
        mediaType: 'image',
        buttonText: 'Shop the Lookbook',
        buttonLink: '/collections/summer-2026',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'spacious',
        overlayOpacity: 45,
        isVisible: true
      },
      {
        id: 'sec-lb-gallery',
        type: 'media_gallery',
        title: 'Curated Visual Moments',
        subtitle: 'STYLE IN MOTION',
        content: 'From seaside retreat studios to urban morning commutes, sound that travels effortlessly.',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-900',
        textColor: 'light',
        paddingY: 'normal',
        columnsCount: 3,
        isVisible: true,
        items: [
          {
            id: 'g-1',
            title: 'Midnight Sun Session',
            description: 'Aurora One Wireless in Frosted Platinum',
            imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
            linkUrl: '/products/aurora-one-wireless'
          },
          {
            id: 'g-2',
            title: 'Archipelago Field Test',
            description: 'IP67 Waterproof Outdoor Speaker',
            imageUrl: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=800&auto=format&fit=crop&q=80',
            linkUrl: '/products/aurora-roam-speaker'
          },
          {
            id: 'g-3',
            title: 'Minimalist Workspace',
            description: 'Desk Reference Monitor System',
            imageUrl: 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=800&auto=format&fit=crop&q=80',
            linkUrl: '/products/aurora-studio-pro'
          }
        ]
      },
      {
        id: 'sec-lb-products',
        type: 'product_grid',
        title: 'Featured in This Issue',
        subtitle: 'DIRECT FROM THE EDITORIAL',
        content: 'Click any signature item below to view detailed SKU specifications and variant options.',
        productIds: ['prod-01', 'prod-02', 'prod-03'],
        columnsCount: 3,
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true
      }
    ]
  },
  {
    id: 'page-faq-warranty',
    title: 'Customer Care & FAQ',
    slug: 'faq-warranty',
    description: 'Frequently asked questions regarding worldwide express shipping, 5-year warranty, and returns.',
    status: 'published',
    template: 'standard',
    seoTitle: 'Support, Warranty & FAQ | Aurora',
    seoDescription: 'Read our international warranty terms, care instructions, and answers to common questions.',
    createdAt: '2026-02-10',
    updatedAt: '2026-08-01',
    sections: [
      {
        id: 'sec-faq-hero',
        type: 'hero_banner',
        title: 'How Can We Assist You?',
        subtitle: 'SUPPORT & SERVICE DESK',
        content: 'Instant answers to shipping questions, driver burn-in guides, and our 5-year global hardware guarantee.',
        mediaUrl: 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=1600&auto=format&fit=crop&q=80',
        mediaType: 'image',
        buttonText: 'Contact Concierge',
        buttonLink: '/contact',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'normal',
        overlayOpacity: 60,
        isVisible: true
      },
      {
        id: 'sec-faq-items',
        type: 'faq_accordion',
        title: 'Frequently Answered Questions',
        subtitle: 'POLICIES & GUIDES',
        content: 'Find transparent details about our manufacturing, logistics, and customer promise.',
        textAlignment: 'left',
        backgroundColor: 'bg-neutral-900',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true,
        items: [
          {
            id: 'faq-1',
            title: 'What does the 5-Year Global Warranty cover?',
            description: 'Every Aurora acoustic product is guaranteed against manufacturing defects for five full years from the date of delivery. If drivers, switches, or battery modules degrade abnormally, we repair or replace them free of charge with prepaid shipping both ways.'
          },
          {
            id: 'faq-2',
            title: 'What are the international shipping times and customs fees?',
            description: 'We ship DDP (Delivery Duty Paid) via FedEx International Priority across 120 countries. All customs tariffs and VAT taxes are included at checkout. Most shipments arrive within 2–4 business days.'
          },
          {
            id: 'faq-3',
            title: 'How does the 30-day home listening trial work?',
            description: 'Try any Aurora headphones or studio monitors in your own acoustic environment for 30 days. If you are not completely enchanted, return them in original packaging for a 100% refund, including return shipping.'
          },
          {
            id: 'faq-4',
            title: 'Are replacement parts and earpads readily available?',
            description: 'Yes. In alignment with our circular sustainability pledge, magnetic replacement earpads, cables, and battery packs are stocked permanently and can be ordered directly from our store.'
          }
        ]
      }
    ]
  },
  {
    id: 'page-flagship-experience',
    title: 'Aurora Studio Pro Flagship Launch',
    slug: 'flagship-experience',
    description: 'High-impact product showcase with interactive before/after acoustic comparison, flash deal timer, and review testimonials.',
    status: 'published',
    template: 'fullwidth',
    heroImage: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1200&auto=format&fit=crop&q=80',
    seoTitle: 'Aurora Studio Pro Flagship Experience',
    seoDescription: 'Experience the new standard in studio monitoring, ANC isolation, and titanium acoustic engineering.',
    createdAt: '2026-06-01',
    updatedAt: '2026-08-16',
    sections: [
      {
        id: 'sec-flagship-hero',
        type: 'split_hero',
        title: 'Sound Refined to the Microscopic Detail',
        subtitle: 'THE 2026 FLAGSHIP RELEASE',
        content: 'Engineered with custom 45mm beryllium drivers and dual feedback hybrid ANC chips, delivering crystalline fidelity.',
        mediaUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1200&auto=format&fit=crop&q=80',
        mediaType: 'image',
        buttonText: 'Order Flagship Edition',
        buttonLink: '/products/aurora-studio-pro',
        secondaryButtonText: 'View Technical Specs',
        secondaryButtonLink: '#specs',
        textAlignment: 'left',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'spacious',
        cardBadge: 'Reference Edition',
        cardTitle: 'Aurora Studio Pro X',
        cardSubtitle: '45mm Beryllium • Hybrid ANC',
        cardPrice: '$349',
        cardExtra: 'Ships within 24 Hours',
        isVisible: true
      },
      {
        id: 'sec-flagship-deal',
        type: 'featured_deal',
        title: 'Collector Launch Edition Bundle',
        subtitle: 'LIMITED TIME RESERVATION',
        content: 'Includes custom CNC machined aluminum desk stand, balanced 4.4mm silver braided cable, and 5-Year VIP Concierge warranty.',
        mediaUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
        buttonText: 'Claim Special Bundle ($349)',
        buttonLink: '/products/aurora-studio-pro',
        dealEndDate: '2026-09-30T23:59:59',
        discountPercent: 20,
        dealBadge: 'Launch Privilege',
        textAlignment: 'left',
        backgroundColor: 'bg-neutral-900',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true
      },
      {
        id: 'sec-flagship-beforeafter',
        type: 'before_after',
        title: 'Active Noise Cancellation in Action',
        subtitle: 'INTERACTIVE ACOUSTIC SIMULATION',
        content: 'Slide to experience the difference between standard ambient cabin noise and Aurora pure isolation.',
        beforeImageUrl: 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?w=1000&auto=format&fit=crop&q=80',
        afterImageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1000&auto=format&fit=crop&q=80',
        beforeLabel: 'Standard Ambience (82dB)',
        afterLabel: 'Aurora ANC Active (Pure Silence)',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true
      },
      {
        id: 'sec-flagship-comparison',
        type: 'comparison_matrix',
        title: 'How Aurora Compares to Mainstream Audio',
        subtitle: 'UNCOMPROMISED BENCHMARK',
        content: 'Compare our precision-machined materials and bespoke driver acoustic specs side by side.',
        comparisonOurBrandName: 'Aurora Studio Pro',
        comparisonCompetitorName: 'Leading Plastic ANC Brands',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-900',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true,
        items: [
          {
            id: 'cmp-1',
            title: 'Chassis & Gimbal Material',
            description: 'Monolithic Aircraft Titanium vs Moulded ABS Plastic',
            badge: 'Grade 5 Titanium'
          },
          {
            id: 'cmp-2',
            title: 'Driver Diaphragm',
            description: '45mm Beryllium Foil vs Standard Mylar',
            badge: 'Lossless Highs'
          },
          {
            id: 'cmp-3',
            title: 'Battery & Repairability',
            description: 'Magnetic User-Swappable vs Glued Permanent Cell',
            badge: 'Sustainable'
          },
          {
            id: 'cmp-4',
            title: 'Warranty Support',
            description: '5-Year Worldwide Express vs Standard 1-Year Limited',
            badge: '5-Year Protection'
          }
        ]
      },
      {
        id: 'sec-flagship-testimonials',
        type: 'testimonials_slider',
        title: 'Endorsed by Audiophile Producers',
        subtitle: 'CRITICAL ACCLAIM',
        content: 'Here is what award-winning audio mastering engineers say about Aurora.',
        textAlignment: 'center',
        backgroundColor: 'bg-neutral-950',
        textColor: 'light',
        paddingY: 'normal',
        isVisible: true,
        items: [
          {
            id: 't-1',
            title: 'The cleanest high-frequency extension I have ever tested.',
            description: 'In 20 years of mastering platinum albums, no wireless headset has matched the transparency of the Aurora 45mm drivers.',
            author: 'Lukas Meyer',
            authorRole: 'Grammy-Nominated Audio Engineer',
            authorAvatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
            rating: 5,
            badge: 'Verified Audio Pro'
          },
          {
            id: 't-2',
            title: 'Incredible noise isolation for long transatlantic flights.',
            description: 'The titanium build feels indestructible and the lambskin memory foam earpads remain comfortable through 14-hour flights.',
            author: 'Maya Lin',
            authorRole: 'Industrial Architect',
            authorAvatar: 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=200&auto=format&fit=crop&q=80',
            rating: 5,
            badge: 'Verified Backer'
          }
        ]
      }
    ]
  }
];

// ---------------------------
// Settings & Staff RBAC Mock Data
// ---------------------------

export const STAFF_PERMISSION_OPTIONS: StaffPermissionOption[] = [
  {
    key: 'configuration',
    label: 'Configuration',
    category: 'core',
    description: 'System configurations, store credentials, and root business parameters'
  },
  {
    key: 'customers',
    label: 'Customers Control',
    category: 'sales',
    description: 'Manage customer accounts, VIP tiers, contact directories, and activity histories'
  },
  {
    key: 'orders',
    label: 'Orders Control',
    category: 'sales',
    description: 'Process incoming orders, manage fulfillment stages, shipping labels, and refunds'
  },
  {
    key: 'category',
    label: 'Category Setting',
    category: 'catalog',
    description: 'Manage taxonomy trees, parent-child categories, and storefront navigation links'
  },
  {
    key: 'products',
    label: 'Products Management',
    category: 'catalog',
    description: 'Create & edit items, configure multi-axis variant SKU matrix, stock, and pricing'
  },
  {
    key: 'promotions',
    label: 'Promotions Setting',
    category: 'marketing',
    description: 'Discount codes, flash sales, bundle coupons, and cart rule pricing'
  },
  {
    key: 'Rewards',
    label: 'Rewards Point Setting',
    category: 'marketing',
    description: 'Customer loyalty point earning rates, milestone redemption rules, and tiers'
  },
  {
    key: 'Layout',
    label: 'Layout Setting',
    category: 'system',
    description: 'Visual CMS page builder, header mega menus, and footer navigation blocks'
  },
  {
    key: 'dashboard',
    label: 'Analytics',
    category: 'core',
    description: 'Executive revenue metrics, sales trends, inventory turnover, and conversion rates'
  },
  {
    key: 'Sale Report',
    label: 'Sale Reports',
    category: 'sales',
    description: 'Downloadable financial statements, GST/SST audits, and product velocity reports'
  },
  {
    key: 'Module Integration',
    label: 'Module Integration',
    category: 'system',
    description: 'Webhooks, payment gateways, ERP connectors, and third-party API sync'
  },
  {
    key: 'Tax',
    label: 'Tax Setting',
    category: 'system',
    description: 'SST 6%/8% tax rules, regional tax zones, and fiscal calculation rules'
  },
  {
    key: 'Marketplace',
    label: 'Marketplace Setting',
    category: 'sales',
    description: 'TikTok Shop, Shopee, Lazada, and Amazon omni-channel synchronization'
  },
  {
    key: 'lhdn',
    label: 'LHDN / E-Invoice Setting',
    category: 'system',
    description: 'Malaysia LHDN MyInvois e-invoicing compliance, validation, and QR dispatch'
  }
];

export const INITIAL_STAFF_USERS: StaffUser[] = [
  {
    id: 'staff-1',
    username: 'Airene',
    fullName: 'Airene Wong',
    email: 'airene.sales@auroracommerce.io',
    phone: '+60 12-345 6789',
    position: 'Airene sale',
    permissions: ['customers', 'orders', 'category', 'products', 'promotions', 'Sale Report', 'Marketplace'],
    isActive: true,
    lastLoginAt: '2026-08-14 16:45:20',
    createdAt: '2026-01-15',
    avatarColor: 'bg-emerald-500',
    notes: 'Senior retail sales lead handling client orders & marketplace sync'
  },
  {
    id: 'staff-2',
    username: 'admin',
    fullName: 'Alexander Sterling (Root Admin)',
    email: 'admin@auroracommerce.io',
    phone: '+60 11-888 9999',
    position: 'Admin',
    permissions: [
      'dashboard',
      'configuration',
      'customers',
      'orders',
      'category',
      'products',
      'promotions',
      'Rewards',
      'Layout',
      'Sale Report',
      'Module Integration',
      'Tax',
      'lhdn'
    ],
    isActive: true,
    lastLoginAt: '2026-08-15 08:30:12',
    createdAt: '2025-11-01',
    avatarColor: 'bg-indigo-600',
    notes: 'Global platform super administrator with unrestricted permissions'
  },
  {
    id: 'staff-3',
    username: 'ayu',
    fullName: 'Ayu Kartika',
    email: 'ayu.support@auroracommerce.io',
    phone: '+60 17-987 6543',
    position: 'sales team',
    permissions: ['dashboard', 'customers', 'orders', 'category', 'products', 'promotions', 'Layout', 'Sale Report'],
    isActive: true,
    lastLoginAt: '2026-08-15 09:12:44',
    createdAt: '2026-02-20',
    avatarColor: 'bg-cyan-600',
    notes: 'Operations & sales support representative'
  },
  {
    id: 'staff-4',
    username: 'edtan',
    fullName: 'Edward Tan (Head of Finance)',
    email: 'edtan.finance@auroracommerce.io',
    phone: '+60 16-222 3344',
    position: 'ACCOUNT',
    permissions: [
      'dashboard',
      'configuration',
      'customers',
      'orders',
      'category',
      'products',
      'promotions',
      'Rewards',
      'Layout',
      'Sale Report',
      'Module Integration',
      'Tax',
      'lhdn'
    ],
    isActive: true,
    lastLoginAt: '2026-08-13 14:02:11',
    createdAt: '2025-12-10',
    avatarColor: 'bg-amber-600',
    notes: 'Accounts department lead managing invoice audits & LHDN compliance'
  },
  {
    id: 'staff-5',
    username: 'jemels',
    fullName: 'Jemels Hoong (Store Manager)',
    email: 'jemels.h@auroracommerce.io',
    phone: '+60 19-333 4455',
    position: 'Admin',
    permissions: [
      'dashboard',
      'configuration',
      'customers',
      'orders',
      'category',
      'products',
      'promotions',
      'Rewards',
      'Layout',
      'Sale Report',
      'Module Integration',
      'Tax',
      'lhdn',
      'Marketplace'
    ],
    isActive: true,
    lastLoginAt: '2026-08-15 01:22:05',
    createdAt: '2025-10-05',
    avatarColor: 'bg-purple-600',
    notes: 'Principal store manager with full omnichannel authority'
  }
];

export const INITIAL_SOCIAL_LINKS: SocialMediaLinks = {
  facebook: 'https://www.facebook.com/profile.php?id=61578497026372',
  instagram: 'https://instagram.com/your-profile',
  tiktok: 'https://tiktok.com/@your-profile',
  youtube: 'https://youtube.com/@your-channel',
  linkedin: 'https://linkedin.com/company/your-company',
  twitter: 'https://x.com/your-handle',
  whatsapp: 'https://wa.me/60123456789',
  xiaohongshu: 'https://www.xiaohongshu.com/user/profile/...'
};

export const INITIAL_SHOP_SETTINGS: ShopGeneralSettings = {
  shopName: 'JW Studio',
  shopLogo: DEFAULT_JW_STUDIO_LOGO,
  tagline: 'Enterprise AI & Omnichannel Commerce Studio',
  businessRegNo: '202601008899 (1456789-W)',
  sstNumber: 'W10-2401-32000456',
  email: 'joshua@jwaistudio.online',
  phone: '+0142244200',
  whatsapp: '+0142244200',
  address: 'Level 28, Menara Horizon, 8 Jalan Bangsar Utama',
  city: 'Kuala Lumpur',
  state: 'Wilayah Persekutuan',
  country: 'Malaysia',
  postcode: '59000',
  currency: 'MYR (RM)',
  timezone: 'Asia/Kuala_Lumpur (GMT+8)',
  maintenanceMode: false,
  orderPrefix: 'JWS-',
  enablePoints: true,
  pointsRate: 1,
  pointsRedeemRate: 100,
  socialLinks: INITIAL_SOCIAL_LINKS
};


