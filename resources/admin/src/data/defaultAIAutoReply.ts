import { AIAutoReplyMasterConfig } from '../types/aiAutoReply';

export const DEFAULT_AI_AUTO_REPLY_CONFIG: AIAutoReplyMasterConfig = {
  isAutoReplyGloballyEnabled: true,
  customerMemory: {enabled:false,retentionDays:30},
  replyCadence: {enabled:false,collectionSeconds:2,intervalSeconds:1,maxSegments:3},
  attachmentAnalysis: {image:false,document:false,audio:false,pdfPages:10,audioSeconds:120,retentionDays:30,transcriptionModel:'gpt-4o-mini-transcribe'},
  aiChannels: {storefront:true,facebook:true,instagram:true,whatsapp:true,tiktok:false},
  activeChannels: {
    storefrontWebChat: true,
    whatsapp: true,
    instagramDm: true,
    tiktokShopChat: true,
    shopeeChat: false,
    lazadaChat: false
  },
  personality: {
    isEnabled: false,
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
    greetingAliases: ['hi','hello','hey','halo','hallo','hai','helo','你好','您好','good morning','good afternoon','good evening'],
    unknownAnswerMessage: {en:"I couldn't find a confirmed answer in our FAQ. Please leave your question here for our support team.",ms:'Jawapan yang disahkan belum tersedia dalam FAQ kami. Sila tinggalkan soalan untuk pasukan sokongan.',zh:'暂时无法从 FAQ 确认答案。请留下问题，我们的客服团队会协助你。'},
    queryUnavailableMessage: {en:'I could not complete one of the requested checks. Please ask our support team for help.',ms:'Saya tidak dapat menyelesaikan salah satu semakan yang diminta. Sila minta bantuan pasukan sokongan.',zh:'有一项查询暂时无法完成，请联系客服协助确认。'},
    serviceUnavailableMessage: {en:'Automatic assistance is temporarily unavailable. Your message has been saved for our support team.',ms:'Bantuan automatik tidak tersedia buat sementara. Mesej anda telah disimpan untuk pasukan sokongan.',zh:'自动客服暂时无法使用。你的消息已保存，客服团队会协助你。'},
    offlineMessage: {
      en: 'Our human team is currently offline (Operating hours: 9:00 AM - 10:00 PM MYT). I am your 24/7 AI Concierge, here to answer questions or check your orders!',
      ms: 'Pasukan kami kini di luar talian. Saya ialah Pembantu AI JW Studio yang sedia membantu 24/7!',
      zh: '人工客服当前为非工作时间（工作时间：9:00 AM - 10:00 PM）。我是 JW Studio 24小时智能顾问，随时为您解答疑问或查询订单！'
    },
    fallbackHandoffMessage: {
      en: 'Your request has been submitted. Please wait for our support team to take over.',
      ms: 'Permintaan anda telah dihantar. Sila tunggu pasukan khidmat pelanggan.',
      zh: '已提交人工请求，请等待客服接手。'
    },
    humanHandoffTriggers: {
      onFrustrationDetected: true,
      consecutiveFailureCount: 2,
      onRefundOrDispute: true,
      onExplicitRequest: true,
      onServiceUnavailable: true,
      urgentKeywords: []
    }
  },
  knowledgeScope: {
    responsePolicy: {partialAnswers:true,clarificationEnabled:true},
    faqKnowledge: {
      isEnabled: true,
      retrievalDepth: 'semantic',
      items: []
    },
    orderLookup: {
      isEnabled: true,
      provideTrackingEntry: true,
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
      includeStorePolicies: true,
      browseAliases: ['what do you sell','what are you selling','what exactly you are selling with','what products do you sell','你们卖什么','你们有什么商品','apa yang anda jual'],
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
    customKnowledgeDocs: []
  },
  guardrails: {
    conversation:{enabled:true,strength:'balanced',repeatLimit:3,cooldownMinutes:10},
    competitorMentions: {
      blockOrNeutralize: true,
      blacklistedCompetitors: [
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
      neutralizeStrategy: 'redirect_to_store_advantages',
      customCannedResponse: 'At JW Studio, our focus is exclusively on crafting proprietary luxury 280GSM heavy streetwear tailored for Malaysia. We cannot comment on or compare with third-party brands.'
    },
    priceBargaining: {
      blockUnauthorizedDiscounts: true,
      suggestOfficialVouchersOnly: true,
      refusalMessages: {ms:'Saya boleh berkongsi harga yang disenaraikan, tetapi tidak boleh merundingkan harga atau menjanjikan diskaun tanpa kebenaran.',zh:'我可以提供商品标价，但不能议价或承诺未经授权的折扣。'},
      refusalTemplate: 'All JW Studio prices are standardized to guarantee fair, premium direct-from-factory pricing. We cannot offer manual discounts, but you are welcome to apply active promotional codes at checkout.'
    },
    antiHallucinationRules: {
      strictStoreContextOnly: true,
      confidenceThresholdCutoff: 75,
      refuseNonEcommerceQueries: true,
      nonEcommerceRefusalMessage: 'I specialize specifically in JW Studio fashion collections, orders, shipping, and styling advice. How can I help with your store experience today?'
    },
    piiDataMasking: {
      maskCreditCards: true,
      maskNationalIds: true,
      maskFullAddresses: true,
      maskPhoneNumbers: true
    },
    profanityAndHarassment: {
      profanityFilterLevel: 'strict',
      banAfterAbuseCount: 3,
      customForbiddenPhrases: [
        'fake',
        'cheap copy',
        'pirated',
        'scam shop',
        'counterfeit',
        'free gift without order',
        'free item please'
      ]
    }
  },
  lastUpdatedBy: 'Admin (Joshua W.)',
  lastUpdatedAt: '2026-08-17 20:10 MYT'
};
