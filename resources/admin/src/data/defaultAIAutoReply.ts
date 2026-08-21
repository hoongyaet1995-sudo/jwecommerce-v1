import { AIAutoReplyMasterConfig } from '../types/aiAutoReply';

export const DEFAULT_AI_AUTO_REPLY_CONFIG: AIAutoReplyMasterConfig = {
  isAutoReplyGloballyEnabled: true,
  activeChannels: {
    storefrontWebChat: true,
    whatsapp: true,
    instagramDm: true,
    tiktokShopChat: true,
    shopeeChat: false,
    lazadaChat: false
  },
  personality: {
    botName: 'JW AI Concierge & Stylist',
    avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
    roleTitle: 'Official JW Studio E-Commerce Stylist & Customer Care Specialist',
    toneOfVoice: 'luxury_professional',
    customToneInstructions: 'Be warm, respectful, concise and authoritative about streetwear luxury fabrics (280GSM heavy cotton, drop-shoulder tailoring, French terry). Always offer solutions with clarity.',
    primaryLanguage: 'auto_detect',
    supportedLanguages: ['English', 'Bahasa Melayu', '中文 (简体/繁体)', 'Manglish'],
    brandMissionPrompt: `You are the official AI Customer Concierge for "JW Studio", a premier contemporary luxury streetwear and minimalist lifestyle brand based in Malaysia.
- Provide accurate assistance regarding products, order tracking, returns, shipping SLAs (Peninsular 1-2 days, East Malaysia 3-5 days), and official promotions.
- Maintain high brand polish: polite, sophisticated, helpful, and concise on mobile screens.
- Never invent prices, never offer unofficial discounts, and never mention competitor brands.
- If a customer is dissatisfied or has an urgent RMA dispute, politely offer escalation to our human supervisor via WhatsApp hotline (+0142244200) or ticket transfer.`,
    creativityTemperature: 0.3,
    maxOutputTokens: 280,
    greetingMessage: {
      en: 'Hello! Welcome to JW Studio. How may I assist your style journey or order today?',
      ms: 'Selamat datang ke JW Studio! Ada apa-apa yang boleh saya bantu mengenai pesanan atau koleksi kami?',
      zh: '您好！欢迎来到 JW Studio 官方精品店。请问有什么可以为您服务或查询订单吗？'
    },
    offlineMessage: {
      en: 'Our human team is currently offline (Operating hours: 9:00 AM - 10:00 PM MYT). I am your 24/7 AI Concierge, here to answer questions or check your orders!',
      ms: 'Pasukan kami kini di luar talian. Saya ialah Pembantu AI JW Studio yang sedia membantu 24/7!',
      zh: '人工客服当前为非工作时间（工作时间：9:00 AM - 10:00 PM）。我是 JW Studio 24小时智能顾问，随时为您解答疑问或查询订单！'
    },
    fallbackHandoffMessage: {
      en: 'I understand this requires specialized human attention. I am transferring this chat to a JW Studio Senior Support Specialist.',
      ms: 'Saya sedang menyambungkan perbualan ini kepada Pegawai Khidmat Pelanggan Kanan JW Studio.',
      zh: '我已为您转接至 JW Studio 人工高级客服专员，请稍候片刻。'
    },
    humanHandoffTriggers: {
      onFrustrationDetected: true,
      consecutiveFailureCount: 2,
      onRefundOrDispute: true,
      onVipCustomer: true,
      urgentKeywords: ['human', 'scam', 'police', 'lawyer', 'bank dispute', 'broken item', '真人', '人工', '投诉', 'agent', 'manager', 'rosak', 'refund urgent']
    }
  },
  knowledgeScope: {
    faqKnowledge: {
      isEnabled: true,
      retrievalDepth: 'semantic',
      items: [
        {
          id: 'faq-1',
          category: 'shipping',
          question: 'What are the delivery times and shipping rates within Malaysia?',
          answer: 'We provide free standard shipping across West Malaysia on orders over RM 150 (Flat RM 8 for below). Peninsular delivery takes 1-2 working days via Pos Laju or Ninja Van. East Malaysia (Sabah & Sarawak) takes 3-5 working days (Flat RM 15). Tracking numbers are sent via WhatsApp/SMS immediately upon packing.',
          keywords: ['shipping fee', 'postage', 'delivery time', 'how long', 'pos laju', 'ninja van', 'sabah', 'sarawak', 'west malaysia', 'postage cost'],
          isActive: true,
          lastUpdatedAt: '2026-08-15'
        },
        {
          id: 'faq-2',
          category: 'returns',
          question: 'What is the return and exchange policy?',
          answer: 'JW Studio offers a 14-day hassle-free exchange and return policy for unworn items with original tags and packaging attached. Size exchanges within West Malaysia enjoy one-time complimentary return courier pickup.',
          keywords: ['return', 'exchange', 'wrong size', 'refund', '14 days', 'tukar saiz', 'pulang barang', '退货', '换尺码'],
          isActive: true,
          lastUpdatedAt: '2026-08-16'
        },
        {
          id: 'faq-3',
          category: 'sizing',
          question: 'How do I choose the right size for JW Heavyweight Oversized Tees?',
          answer: 'Our 280GSM Heavyweight Luxury Tees are tailored with a modern drop-shoulder relaxed silhouette. For a standard streetwear oversized look, choose your true size. For a fitted classic silhouette, we recommend sizing down one size. S (160-170cm, 50-65kg), M (170-178cm, 65-78kg), L (175-185cm, 78-90kg), XL (180-192cm, 90kg+).',
          keywords: ['sizing', 'size chart', 'oversized fit', 'measurement', 'how to choose size', 'cm kg', 'berat badan', 'tinggi', '尺码推荐'],
          isActive: true,
          lastUpdatedAt: '2026-08-16'
        },
        {
          id: 'faq-4',
          category: 'payment',
          question: 'What payment methods do you accept?',
          answer: 'We accept FPX Online Banking (Maybank2u, CIMB Clicks, Public Bank, Hong Leong), Credit/Debit Cards (Visa & Mastercard), Touch \'n Go eWallet, GrabPay, Atome 3x Buy-Now-Pay-Later (0% interest), DuitNow QR, and Cash on Delivery (COD) via Pos Laju nationwide.',
          keywords: ['payment', 'fpx', 'tng', 'touch n go', 'duitnow', 'credit card', 'atome', 'installment', 'cod', 'cash on delivery', 'bayar masa sampai', '货到付款'],
          isActive: true,
          lastUpdatedAt: '2026-08-17'
        },
        {
          id: 'faq-5',
          category: 'store_info',
          question: 'What is the customer support contact and official showroom location?',
          answer: 'You can contact JW Studio Customer Care via WhatsApp at +0142244200 or email at joshua@jwaistudio.online. Our flagship showroom is located in Kuala Lumpur, open Tuesday to Sunday from 11:00 AM to 8:00 PM.',
          keywords: ['contact', 'whatsapp', 'telephone', 'phone number', 'email', 'showroom', 'location', 'address', 'kedai fizikal', 'hubungi kami', '联系方式'],
          isActive: true,
          lastUpdatedAt: '2026-08-17'
        }
      ]
    },
    orderLookup: {
      isEnabled: true,
      requiresVerification: true,
      verificationMethod: 'phone_last4',
      allowedFields: {
        orderStatus: true,
        trackingNumberAndCourier: true,
        estimatedDeliveryDate: true,
        itemSummary: true,
        shippingAddressMasked: true,
        paymentStatus: true
      },
      allowAddressChangeRequest: true,
      allowCancellationRequest: false
    },
    productCatalog: {
      isEnabled: true,
      canCheckLiveStock: true,
      canRecommendUpsells: true,
      canExplainSizingAndMaterials: true,
      includeOutOfStockAlternatives: true,
      maxRecommendations: 3,
      allowedCategories: ['T-Shirts', 'Hoodies', 'Pants', 'Accessories', 'Headwear']
    },
    promotionsAndDiscounts: {
      isEnabled: true,
      canDiscloseActiveVouchers: true,
      canCalculateEstimatedSavings: true,
      strictNoPriceBargaining: true,
      vouchers: [
        {
          id: 'v-1',
          code: 'JWVIP10',
          discountType: 'percentage',
          value: 10,
          minSpend: 200,
          description: '10% OFF on all orders exceeding RM 200 (Storewide)',
          expiresAt: '2026-12-31',
          isActive: true,
          publiclyDisclosable: true
        },
        {
          id: 'v-2',
          code: 'FREESHIPMY',
          discountType: 'free_shipping',
          value: 15,
          minSpend: 150,
          description: 'Free Shipping nationwide across West & East Malaysia',
          expiresAt: '2026-12-31',
          isActive: true,
          publiclyDisclosable: true
        },
        {
          id: 'v-3',
          code: 'WELCOME50',
          discountType: 'fixed_myr',
          value: 50,
          minSpend: 350,
          description: 'RM 50 instant rebate for new members spending RM 350+',
          expiresAt: '2026-09-30',
          isActive: true,
          publiclyDisclosable: true
        }
      ]
    },
    customKnowledgeDocs: [
      {
        id: 'doc-1',
        title: 'Heavyweight Cotton Garment Care & Washing Guide',
        category: 'Product Care',
        contentMarkdown: `### JW Studio Luxury Cotton Care Guide
- **Water Temperature:** Machine wash cold inside-out (under 30°C) to preserve fabric density.
- **Drying:** Air dry flat in shade. Do not tumble dry on high heat to prevent shrinkage.
- **Ironing:** Iron on low-to-medium heat on reverse side. Avoid direct heat on high-density puff prints.`,
        tags: ['cotton care', 'washing', 'shrinkage', 'puff print', 'garment care'],
        isActive: true,
        characterCount: 374,
        lastUpdatedAt: '2026-08-10'
      },
      {
        id: 'doc-2',
        title: 'Pos Laju & Ninja Van Weekend Pickup Schedule',
        category: 'Logistics',
        contentMarkdown: `### Warehouse Dispatch Timing
- Orders placed before **2:00 PM (Monday - Friday)** are packed and collected by courier on the same day.
- Saturday dispatch cutoff is **11:30 AM**. Orders placed on Sunday are picked up Monday morning.`,
        tags: ['cut off', 'dispatch', 'same day delivery', 'weekend delivery'],
        isActive: true,
        characterCount: 268,
        lastUpdatedAt: '2026-08-12'
      }
    ]
  },
  guardrails: {
    preventCompetitorMentions: {
      isEnabled: true,
      competitorBlacklist: [
        'Uniqlo',
        'Zara',
        'H&M',
        'Nike',
        'Adidas',
        'Stussy',
        'Supreme',
        'Fear of God',
        'Padini',
        'Shein',
        'Taobao'
      ],
      standardRefusalMessage: 'At JW Studio, our focus is exclusively on crafting proprietary luxury 280GSM heavy streetwear tailored for Malaysia. We cannot comment on or compare with third-party brands.'
    },
    strictPriceBargainingGuard: {
      isEnabled: true,
      enforcementMode: 'redirect_to_public_promotions',
      refusalResponse: 'All JW Studio prices are standardized to guarantee fair, premium direct-from-factory pricing. We cannot offer custom discounts, but you are welcome to use our active voucher code **JWVIP10** for 10% off orders over RM 200!'
    },
    antiHallucinationRules: {
      isEnabled: true,
      neverPromiseOutOfStock: true,
      strictStoreContextOnly: true,
      confidenceCutoffThreshold: 0.78,
      unrelatedQueryResponse: 'I specialize specifically in JW Studio fashion collections, orders, shipping, and styling advice. How can I help with your store experience today?'
    },
    piiAndDataMasking: {
      maskCreditCardNumbers: true,
      maskNationalIdOrIC: true,
      maskCustomerPersonalPhoneInLogs: true
    },
    toxicAndProfanityFilter: {
      isEnabled: true,
      escalateToSupervisorImmediately: true,
      blockedProfanityResponse: 'JW Studio maintains a respectful and safe community. Please refrain from using abusive language, and our supervisor will be glad to assist you.'
    },
    customForbiddenWords: [
      'fake',
      'cheap copy',
      'pirated',
      'scam shop',
      'counterfeit',
      'free gift without order',
      'free item please'
    ]
  },
  lastUpdatedBy: 'Admin (Joshua W.)',
  lastUpdatedAt: '2026-08-17 20:10 MYT'
};
