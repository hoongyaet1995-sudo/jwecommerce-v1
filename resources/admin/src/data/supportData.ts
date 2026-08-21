import {
  SupportConversation,
  CannedResponse,
  CopilotQueryMessage
} from '../types/support';

export const INITIAL_CANNED_RESPONSES: CannedResponse[] = [
  {
    id: 'can-1',
    shortcut: '/tracking',
    title: 'Malaysia Courier Tracking Link',
    category: 'Shipping',
    content: 'Hi! You can track your parcel anytime via our live tracking portal: https://aurora.store/track?awb={{trackingNumber}}. Courier SLA is typically 1-3 business days within Peninsular Malaysia and 3-5 days to Sabah/Sarawak.'
  },
  {
    id: 'can-2',
    shortcut: '/fpx',
    title: 'FPX & E-Wallet Payment Verification',
    category: 'Payment',
    content: 'Thank you for your payment via FPX / DuitNow QR. We have verified your transaction receipt. Your order is now being packed in our Shah Alam fulfilment hub.'
  },
  {
    id: 'can-3',
    shortcut: '/return',
    title: '14-Day Free Exchange & Return Policy',
    category: 'Returns',
    content: 'We offer a hassle-free 14-day return & size exchange guarantee. Please ensure tags are intact. Would you like our courier to arrange a doorstep pickup from your address?'
  },
  {
    id: 'can-4',
    shortcut: '/vip_discount',
    title: 'VIP 10% Loyalty Coupon',
    category: 'Discounts',
    content: 'As a valued VIP member, here is an exclusive 10% discount voucher for your next checkout: **JWVIP10**. Valid across all new arrivals!'
  },
  {
    id: 'can-5',
    shortcut: '/greeting_ms',
    title: 'Malay Language Greeting (Bahasa Melayu)',
    category: 'Greeting',
    content: 'Hai! Terima kasih kerana menghubungi JW Studio. Saya adalah pembantu khidmat pelanggan AI. Ada apa-apa yang boleh saya bantu anda hari ini?'
  }
];

export const INITIAL_SUPPORT_CONVERSATIONS: SupportConversation[] = [
  {
    id: 'conv-whatsapp-1',
    channel: 'whatsapp',
    title: 'Dato’ Kenneth Tan (Pos Laju Express Inquiry)',
    snippet: 'Is my order #ORD-MY-8891 arriving before Friday? Need it for a corporate dinner.',
    unreadCount: 2,
    isPinned: true,
    priority: 'urgent',
    status: 'open', // AI Handling with ready draft
    customer: {
      id: 'cust-ken-88',
      name: 'Dato’ Kenneth Tan',
      avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
      phone: '+60 12-889 2311',
      email: 'kenneth.tan@royalglen.my',
      location: 'Kuala Lumpur, Malaysia',
      countryCode: 'MY',
      language: 'English / Bahasa Melayu',
      timezone: 'GMT+8 (Asia/Kuala_Lumpur)',
      totalOrdersCount: 14,
      lifetimeValue: 12450.00,
      firstSeenAt: '2025-03-12',
      lastSeenAt: '2 mins ago',
      tags: ['VIP Diamond', 'Corporate Buyer', 'Fast Payer', 'KL Sentral'],
      leadScore: 96,
      leadStage: 'Active Customer',
      qualification: 'vip_buyer',
      recentOrderId: 'ORD-MY-8891',
      recentOrderTotal: 1890.00,
      recentOrderStatus: 'In Transit',
      recentOrderTracking: 'PL9281749102MY'
    },
    aiSummary: {
      intent: 'Order Delivery Expedited Inquiry',
      sentiment: 'neutral',
      keyIssue: 'Customer needs delivery before Friday 5 PM for an upcoming banquet in Bangsar.',
      suggestedAction: 'Confirm Pos Laju hub clearance and offer priority dispatch guarantee with SMS alert.',
      summaryMarkdown: 'Dato’ Kenneth placed **#ORD-MY-8891** (RM 1,890.00). Parcel cleared Shah Alam sort facility today at 08:30 AM. Estimated delivery is tomorrow afternoon.'
    },
    activeAiDraft: {
      id: 'draft-1',
      content: 'Good morning Dato’ Kenneth! We have checked your Pos Laju shipment (AWB: PL9281749102MY). It successfully departed our Shah Alam logistics hub this morning and is scheduled for out-for-delivery tomorrow (Thursday) before 3:00 PM. You will definitely receive it ahead of your Friday dinner. I have also marked your consignment as Priority Red.',
      suggestedAt: '1 min ago',
      confidence: 0.98,
      reasoning: 'Extracted order #ORD-MY-8891 tracking from WMS. Carrier scan timestamp confirmed on-time SLA. Personalized with polite Malaysian honorifics.',
      model: 'Gemini 2.5 Flash B2B Agent',
      sourceContext: 'WMS Dispatch API + Pos Laju Webhook status'
    },
    lastMessageAt: '2 mins ago',
    createdAt: '2026-08-17T08:45:00Z',
    messages: [
      {
        id: 'msg-w1',
        conversationId: 'conv-whatsapp-1',
        senderRole: 'customer',
        senderName: 'Dato’ Kenneth Tan',
        timestamp: '10:14 AM',
        content: 'Hi JW Studio team, I placed order #ORD-MY-8891 yesterday. Can you double check if it will reach Bangsar before Friday? I need the tailored blazer for our boardroom dinner.',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-w2',
        conversationId: 'conv-whatsapp-1',
        senderRole: 'customer',
        senderName: 'Dato’ Kenneth Tan',
        timestamp: '10:15 AM',
        content: '',
        type: 'voice',
        deliveryStatus: 'read',
        attachments: [
          {
            id: 'att-v1',
            name: 'Voice Note (0:18)',
            url: 'https://actions.google.com/sounds/v1/ambiences/coffee_shop.ogg',
            type: 'audio',
            durationSeconds: 18,
            transcription: '“Please make sure the packaging is pristine as this is a gift presentation set. Thank you!”'
          }
        ]
      },
      {
        id: 'msg-w3',
        conversationId: 'conv-whatsapp-1',
        senderRole: 'system',
        senderName: 'AI Dispatch Bot',
        timestamp: '10:15 AM',
        content: 'Order #ORD-MY-8891 automatically verified from OMS. Pos Laju Tracking: PL9281749102MY (Status: Sorted at Shah Alam Hub).',
        type: 'system_event',
        orderCard: {
          orderId: 'ORD-MY-8891',
          status: 'In Transit (Pos Laju Express)',
          totalAmount: 1890.00,
          itemsCount: 3,
          courierName: 'Pos Malaysia Express',
          trackingNumber: 'PL9281749102MY'
        }
      }
    ],
    internalNotes: [
      {
        id: 'note-1',
        authorName: 'Alex Wong (Lead Ops)',
        authorAvatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
        timestamp: '10:16 AM',
        content: 'Dato’ Kenneth is our Top 5% recurring client. Priority white-glove packaging tag already attached at bay 3.'
      }
    ],
    customMetadata: {
      sourceUrl: 'https://wa.me/60198892311',
      utmCampaign: 'whatsapp_direct_crm'
    }
  },
  {
    id: 'conv-web-1',
    channel: 'web_guest',
    title: 'Nurul Huda (Wholesale Boutique Query)',
    snippet: 'We are looking to stock 200 units of the Silk Hijab & Abaya series for our Penang branch...',
    unreadCount: 1,
    isPinned: true,
    priority: 'high',
    status: 'open', // AI Handling
    customer: {
      id: 'cust-web-nurul',
      name: 'Nurul Huda (Modest Luxe Boutique)',
      avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
      phone: '+60 17-345 8821',
      email: 'procurement@modestluxe.com.my',
      location: 'Georgetown, Penang',
      countryCode: 'MY',
      language: 'Bahasa Melayu / English',
      timezone: 'GMT+8',
      totalOrdersCount: 3,
      lifetimeValue: 8400.00,
      firstSeenAt: '2025-11-04',
      lastSeenAt: '8 mins ago',
      tags: ['B2B Wholesale', 'Penang Branch', 'Tier-2 Tier Pricing'],
      leadScore: 88,
      leadStage: 'Evaluation',
      qualification: 'wholesale_inquiry'
    },
    aiSummary: {
      intent: 'B2B Wholesale Quotation & Tier Pricing',
      sentiment: 'inquisitive',
      keyIssue: 'Requesting wholesale tier discount table for 200+ units minimum order quantity (MOQ).',
      suggestedAction: 'Send B2B Volume Pricing catalog and connect with Trade Sales specialist.',
      summaryMarkdown: 'Nurul represents **Modest Luxe Boutique (Penang)**. Seeking quotation for 200 units Silk Scarf & Abaya collection. Estimated pipeline value: **RM 18,000.00**.'
    },
    activeAiDraft: {
      id: 'draft-2',
      content: 'Salam Cik Nurul! Terima kasih atas minat terhadap JW Studio Silk Collection. Untuk pesanan borong (B2B Wholesale) melebihi 150 unit, kami menawarkan diskaun tier sebanyak 35% berserta percuma penghantaran berinsurans ke Pulau Pinang. Bolehkah kami kongsikan Wholesale Lookbook 2026 dan invois sebut harga (Proforma Invoice) melalui emel rasmi anda?',
      suggestedAt: '7 mins ago',
      confidence: 0.95,
      reasoning: 'Detected high-value commercial wholesale intent. Generated polite Malaysian bilingual business proposal.',
      model: 'Gemini 2.5 Flash B2B Agent',
      sourceContext: 'B2B Wholesale Pricing Matrix v4'
    },
    lastMessageAt: '8 mins ago',
    createdAt: '2026-08-17T08:30:00Z',
    messages: [
      {
        id: 'msg-w10',
        conversationId: 'conv-web-1',
        senderRole: 'customer',
        senderName: 'Nurul Huda',
        timestamp: '10:02 AM',
        content: 'Hi! We are expanding our boutique in Gurney Plaza, Penang. We want to place an order for 200 units from the premium Silk collection. Do you provide custom colorways and wholesale invoicing with SST exemption receipt?',
        type: 'text',
        deliveryStatus: 'delivered'
      }
    ],
    internalNotes: [],
    customMetadata: {
      sourceUrl: 'https://aurora.store/collections/silk-series',
      userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
      utmCampaign: 'google_b2b_search'
    }
  },
  {
    id: 'conv-ig-1',
    channel: 'instagram',
    title: '@chloe_lifestyle (Creator Partnership)',
    snippet: 'Loved the linen co-ord set! Would love to feature it for our upcoming Langkawi retreat reel...',
    unreadCount: 0,
    isPinned: false,
    priority: 'normal',
    status: 'human_mode', // Locked by teammate
    assignedAgent: {
      id: 'agent-sarah',
      name: 'Sarah Chen (PR & Collab)',
      avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
      isLocked: true
    },
    customer: {
      id: 'cust-ig-chloe',
      name: 'Chloe Lim (@chloe_lifestyle)',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
      handle: '@chloe_lifestyle',
      location: 'Petaling Jaya, Malaysia',
      countryCode: 'MY',
      language: 'English',
      totalOrdersCount: 2,
      lifetimeValue: 620.00,
      firstSeenAt: '2026-01-15',
      lastSeenAt: '15 mins ago',
      tags: ['KOL / Influencer (120k IG)', 'Fashion Creator', 'Langkawi Campaign'],
      leadScore: 82,
      leadStage: 'Discovery',
      qualification: 'hot_prospect'
    },
    aiSummary: {
      intent: 'Influencer Seeding & Affiliate Inquiry',
      sentiment: 'positive',
      keyIssue: 'Inquiring about PR seeding sample for upcoming travel vlog in exchange for Instagram Reels + TikTok tag.',
      suggestedAction: 'Approve Tier-A sampling kit and issue customized creator tracking code.',
      summaryMarkdown: 'Chloe Lim has 120k followers with 4.8% engagement rate. Requesting Linen Resort set in Sand Beige (Size M).'
    },
    lastMessageAt: '15 mins ago',
    createdAt: '2026-08-17T07:15:00Z',
    messages: [
      {
        id: 'msg-ig-1',
        conversationId: 'conv-ig-1',
        senderRole: 'customer',
        senderName: 'Chloe Lim',
        timestamp: '09:40 AM',
        content: 'Hey JW Studio team! ✨ Just saw your new Linen Summer release on IG story. I’m heading to St. Regis Langkawi next Tuesday and would love to style the co-ord set in a dedicated 4k Reel. Let me know if you do creator seedings!',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-ig-2',
        conversationId: 'conv-ig-1',
        senderRole: 'human_agent',
        senderName: 'Sarah Chen (PR & Collab)',
        timestamp: '09:48 AM',
        content: 'Hi Chloe! Super excited to collaborate with you! 🌴 Yes, we’d love to gift you the Linen Co-ord set in Sand Beige. What size fits you best? We can express ship it to PJ today.',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-ig-3',
        conversationId: 'conv-ig-1',
        senderRole: 'customer',
        senderName: 'Chloe Lim',
        timestamp: '09:50 AM',
        content: 'Size M would be perfect! Sending my shipping address in a moment. Thank you so much! 💕',
        type: 'text',
        deliveryStatus: 'read'
      }
    ],
    internalNotes: [
      {
        id: 'note-ig-1',
        authorName: 'Sarah Chen',
        authorAvatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
        timestamp: '09:52 AM',
        content: 'I have locked this conversation to finalize the creator agreement & tracking link. ETA to complete: 10 mins.'
      }
    ]
  },
  {
    id: 'conv-tiktok-1',
    channel: 'tiktok',
    title: 'Ahmad Faiz (Flash Sale Size Chart)',
    snippet: 'Is the Oversized Heavyweight Tee true to Asian sizing or oversized drop shoulder?',
    unreadCount: 0,
    isPinned: false,
    priority: 'normal',
    status: 'open',
    customer: {
      id: 'cust-tt-faiz',
      name: 'Ahmad Faiz (@faiz_kicks)',
      avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
      handle: '@faiz_kicks',
      location: 'Shah Alam, Selangor',
      countryCode: 'MY',
      language: 'Bahasa Melayu / English',
      totalOrdersCount: 1,
      lifetimeValue: 149.00,
      firstSeenAt: '2026-06-20',
      lastSeenAt: '25 mins ago',
      tags: ['TikTok Shop', 'Streetwear', 'Repeat Live Buyer'],
      leadScore: 65,
      leadStage: 'Checkout Ready',
      qualification: 'qualified_lead'
    },
    aiSummary: {
      intent: 'Size Recommendation & Fit Comparison',
      sentiment: 'neutral',
      keyIssue: 'Customer height 178cm, 74kg wanting relaxed streetwear silhouette.',
      suggestedAction: 'Recommend Size L for boxy drop-shoulder fit and send instant checkout link.',
      summaryMarkdown: 'Customer inquiring about 280 GSM Heavyweight Tee. High purchase intent from TikTok Live banner.'
    },
    lastMessageAt: '25 mins ago',
    createdAt: '2026-08-17T06:50:00Z',
    messages: [
      {
        id: 'msg-tt-1',
        conversationId: 'conv-tiktok-1',
        senderRole: 'customer',
        senderName: 'Ahmad Faiz',
        timestamp: '09:12 AM',
        content: 'Salam bro, nak tanya pasal Heavyweight Tee 280gsm tu. Tinggi saya 178cm, berat 74kg. Elok ambil size M ke L kalau nak fit santai drop shoulder?',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-tt-2',
        conversationId: 'conv-tiktok-1',
        senderRole: 'ai_agent',
        senderName: 'JW AI Assistant',
        timestamp: '09:13 AM',
        content: 'Salam Faiz! Untuk ketinggian 178cm dan berat 74kg, kami sangat cadangkan **Size L**! Potongan ini menampilkan boxy cut dengan drop-shoulder 2.5 inci yang selesa tanpa nampak gelebeh. Stock untuk warna Washed Charcoal & Vintage White masih ada di gudang.',
        type: 'text',
        deliveryStatus: 'read',
        productCard: {
          id: 'prod-hw-tee',
          name: 'JW Studio 280GSM Heavyweight Street Tee (Washed Charcoal)',
          price: 89.00,
          image: 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300&auto=format&fit=crop&q=80',
          stock: 42,
          sku: 'TEE-HVY-BLK-L'
        }
      }
    ],
    internalNotes: []
  },
  {
    id: 'conv-fb-1',
    channel: 'facebook',
    title: 'Grace Wong (Exchange Completed)',
    snippet: 'Thank you! The replacement size S received in good condition.',
    unreadCount: 0,
    isPinned: false,
    priority: 'low',
    status: 'resolved',
    customer: {
      id: 'cust-fb-grace',
      name: 'Grace Wong',
      avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&auto=format&fit=crop&q=80',
      email: 'grace.wong@gmail.com',
      location: 'Subang Jaya, Selangor',
      countryCode: 'MY',
      language: 'English',
      totalOrdersCount: 5,
      lifetimeValue: 1980.00,
      firstSeenAt: '2025-08-10',
      lastSeenAt: '1 hour ago',
      tags: ['Subang Jaya', 'Satisfied Customer', 'Loyal VIP'],
      leadScore: 92,
      leadStage: 'Active Customer',
      qualification: 'support_ticket'
    },
    aiSummary: {
      intent: 'Size Exchange Resolution',
      sentiment: 'positive',
      keyIssue: 'Completed doorstep return & swap via Ninja Van. Customer confirmed satisfaction.',
      suggestedAction: 'Ticket resolved. Automated post-support CSAT survey sent.',
      summaryMarkdown: 'Exchange ticket #EX-9921 closed successfully with 5/5 customer rating.'
    },
    lastMessageAt: '1 hour ago',
    createdAt: '2026-08-16T14:20:00Z',
    messages: [
      {
        id: 'msg-fb-1',
        conversationId: 'conv-fb-1',
        senderRole: 'customer',
        senderName: 'Grace Wong',
        timestamp: 'Yesterday 04:30 PM',
        content: 'Hi, the dress I received was a bit loose around the waist. Can I exchange M to S?',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-fb-2',
        conversationId: 'conv-fb-1',
        senderRole: 'human_agent',
        senderName: 'Alex Wong (Support)',
        timestamp: 'Yesterday 04:35 PM',
        content: 'Hi Grace! Sure thing, we have arranged a Ninja Van 1-to-1 swap at your Subang Jaya doorstep tomorrow morning.',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-fb-3',
        conversationId: 'conv-fb-1',
        senderRole: 'customer',
        senderName: 'Grace Wong',
        timestamp: '08:15 AM',
        content: 'Received the new Size S dress this morning! The fit is immaculate now. Thanks for the swift turnaround! ⭐⭐⭐⭐⭐',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-fb-4',
        conversationId: 'conv-fb-1',
        senderRole: 'system',
        senderName: 'System Bot',
        timestamp: '08:16 AM',
        content: 'Ticket marked as Resolved by Alex Wong. CSAT score: 5/5 stars.',
        type: 'system_event'
      }
    ],
    internalNotes: []
  },
  {
    id: 'conv-reddit-1',
    channel: 'reddit',
    title: 'u/TechNomadMY (Packaging Feedback)',
    snippet: 'Saw your brand mentioned in r/malaysia on sustainable honeycomb packaging. Where do you source...',
    unreadCount: 1,
    isPinned: false,
    priority: 'normal',
    status: 'open',
    customer: {
      id: 'cust-rd-nomad',
      name: 'u/TechNomadMY',
      avatar: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
      handle: 'u/TechNomadMY',
      location: 'Kuala Lumpur',
      countryCode: 'MY',
      language: 'English',
      totalOrdersCount: 0,
      lifetimeValue: 0,
      firstSeenAt: '2026-08-17',
      lastSeenAt: '40 mins ago',
      tags: ['Reddit Community', 'Eco-Packaging', 'Prospect'],
      leadScore: 50,
      leadStage: 'Discovery',
      qualification: 'general_guest'
    },
    aiSummary: {
      intent: 'Brand Sustainability Query',
      sentiment: 'neutral',
      keyIssue: 'Asking about biodegradable cassava mailers and FSC honeycomb sleeves.',
      suggestedAction: 'Provide eco-initiative link and 10% welcome coupon.',
      summaryMarkdown: 'Thread originating from r/malaysia weekly ecommerce discussions.'
    },
    lastMessageAt: '40 mins ago',
    createdAt: '2026-08-17T06:00:00Z',
    messages: [
      {
        id: 'msg-rd-1',
        conversationId: 'conv-reddit-1',
        senderRole: 'customer',
        senderName: 'u/TechNomadMY',
        timestamp: '08:22 AM',
        content: 'Hey guys, saw a post on r/malaysia praising your 100% plastic-free packaging. Are the garment bags truly home-compostable in tropical weather?',
        type: 'text',
        deliveryStatus: 'delivered'
      }
    ],
    internalNotes: []
  },
  {
    id: 'conv-x-1',
    channel: 'x',
    title: '@syazwan_dev (Next-Day Delivery Shoutout)',
    snippet: 'Ordered 4pm yesterday and arrived 10am in Cyberjaya today! Pos Malaysia / Flash was blazing fast 🔥',
    unreadCount: 0,
    isPinned: false,
    priority: 'low',
    status: 'resolved',
    customer: {
      id: 'cust-x-syazwan',
      name: 'Syazwan (@syazwan_dev)',
      avatar: 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=150&auto=format&fit=crop&q=80',
      handle: '@syazwan_dev',
      location: 'Cyberjaya, Selangor',
      countryCode: 'MY',
      language: 'English',
      totalOrdersCount: 4,
      lifetimeValue: 920.00,
      firstSeenAt: '2025-10-18',
      lastSeenAt: '2 hours ago',
      tags: ['Tech Community', 'Super Fast Reviewer'],
      leadScore: 78,
      leadStage: 'Active Customer',
      qualification: 'vip_buyer'
    },
    aiSummary: {
      intent: 'Customer Gratitude & Social Proof',
      sentiment: 'positive',
      keyIssue: 'Customer posted praise regarding automated same-day warehouse dispatch.',
      suggestedAction: 'Acknowledge tweet with official brand handle and gift 100 loyalty points.',
      summaryMarkdown: 'Social sentiment high positive (+95%). Retweeted on marketing feed.'
    },
    lastMessageAt: '2 hours ago',
    createdAt: '2026-08-17T05:10:00Z',
    messages: [
      {
        id: 'msg-x-1',
        conversationId: 'conv-x-1',
        senderRole: 'customer',
        senderName: 'Syazwan',
        timestamp: '07:15 AM',
        content: 'Ordered at 4:15 PM yesterday and the courier just knocked on my door in Cyberjaya at 10 AM! Hands down smoothest ecommerce logistics experience in MY 🔥 @JWStudioMY',
        type: 'text',
        deliveryStatus: 'read'
      },
      {
        id: 'msg-x-2',
        conversationId: 'conv-x-1',
        senderRole: 'ai_agent',
        senderName: 'JW AI Social Agent',
        timestamp: '07:18 AM',
        content: 'Thank you so much for the love Syazwan! 🚀 Our Shah Alam automated fulfillment robot team worked double shifts last night to clear express batches. We just credited +100 JW Points to your wallet!',
        type: 'text',
        deliveryStatus: 'read'
      }
    ],
    internalNotes: []
  }
];

export const INITIAL_INTERNAL_COPILOT_MESSAGES: CopilotQueryMessage[] = [
  {
    id: 'cop-1',
    role: 'user',
    content: 'What is our current daily revenue and top shipping courier performance across Malaysia?',
    timestamp: '09:30 AM'
  },
  {
    id: 'cop-2',
    role: 'assistant',
    content: `Here is the real-time operational executive summary for **August 17, 2026**:

### 📊 Financial & Orders Velocity
- **Today's Gross Merchandise Value (GMV)**: **RM 48,290.00** (*+18.4% vs last Monday*)
- **Total Orders Placed**: **314 Orders** across Web Storefront (54%), TikTok Shop (28%), Shopee (12%), and Lazada (6%).
- **Average Order Value (AOV)**: **RM 153.78**

### 🚚 Courier SLA & Delivery Performance
- **Pos Malaysia Express**: **98.4% On-Time Delivery** (Average transit time: 1.2 days in Klang Valley, 2.8 days East Malaysia).
- **Ninja Van Malaysia**: **96.8% On-Time** with 99.1% COD collection reconciliation rate.
- **J&T Express**: **95.2% On-Time** with peak volume handling in Johor Bahru.

### 💡 AI Strategic Recommendations
1. **Stock Warning**: *Washed Charcoal 280GSM Tee (Size L)* is at 42 units ATP. Restock trigger recommended before Friday flash sale.
2. **Pending High-Value Ticket**: Dato’ Kenneth Tan requested VIP dinner delivery validation for order **#ORD-MY-8891** (RM 1,890.00).`,
    timestamp: '09:30 AM',
    reasoningSteps: [
      'Querying live OMS database transactions for 2026-08-17',
      'Aggregating multi-channel GMV (Shopify, TikTok Shop, Shopee API)',
      'Calculating carrier SLA latency from WMS webhooks',
      'Cross-referencing high priority VIP tickets from support queue'
    ],
    suggestedFollowUps: [
      'Show me pending COD uncollected amounts by courier',
      'List all customer tickets with sentiment "frustrated"',
      'Draft an email announcement for weekend Free Shipping campaign',
      'Which products have the highest return/exchange rate this month?'
    ],
    dataReferences: [
      {
        type: 'sales_metric',
        title: 'Today’s GMV',
        value: 'RM 48,290',
        change: '+18.4% WoW'
      },
      {
        type: 'courier_sla',
        title: 'Pos Laju SLA',
        value: '98.4%',
        change: 'On-Time Delivery'
      },
      {
        type: 'inventory_alert',
        title: 'Heavyweight Tee L',
        value: '42 left',
        change: 'Reorder Point Met'
      }
    ]
  }
];
