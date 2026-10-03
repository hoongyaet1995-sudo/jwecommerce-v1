import React, { useState, useRef, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Bot,
  Send,
  Sparkles,
  ShieldCheck,
  ShieldAlert,
  HelpCircle,
  Package,
  Tag,
  Ban,
  RefreshCw,
  Zap,
  Info,
  CheckCircle2,
  Terminal,
  Clock,
  User,
  Sliders,
  DollarSign
} from 'lucide-react';

interface SimulatedMessage {
  id: string;
  sender: 'user' | 'assistant' | 'system';
  text: string;
  timestamp: string;
  metadata?: {
    intent?: string;
    guardrailTriggered?: string | null;
    retrievedSource?: string | null;
    confidenceScore?: number;
    latencyMs?: number;
  };
}

const PRESET_SCENARIOS = [
  {
    label: '📦 Check Order Status',
    query: 'Hi, where is my order #JW-1002? Phone last 4 digits are 9912.',
    category: 'Order Query'
  },
  {
    label: '💰 Haggle / Bargain Price',
    query: 'Can you give me 30% special cash discount if I buy 2 heavy tees now?',
    category: 'Price Bargaining'
  },
  {
    label: '⚔️ Competitor Comparison',
    query: 'Why should I buy your RM 189 shirt instead of Uniqlo Airism or Zara?',
    category: 'Competitor Blacklist'
  },
  {
    label: '🔄 Return Policy Query',
    query: 'How many days do I have to return if the sizing does not fit?',
    category: 'FAQ Retrieval'
  },
  {
    label: '🤖 Off-topic LLM Test',
    query: 'Can you write a poem about artificial intelligence and give me python code?',
    category: 'Anti-Hallucination'
  },
  {
    label: '🎟️ Inquire Active Vouchers',
    query: 'Do you have any promotion vouchers or first-order discount codes today?',
    category: 'Promotions'
  }
];

export const AISimulatorTab: React.FC = () => {
  const { aiAutoReplyConfig } = useAdmin();
  const { personality, knowledgeScope, guardrails } = aiAutoReplyConfig;

  const [messages, setMessages] = useState<SimulatedMessage[]>([
    {
      id: 'init-1',
      sender: 'assistant',
      text: personality.greetingMessage.en || 'Welcome to JW Studio. How may I assist your style curation today?',
      timestamp: 'Just now',
      metadata: {
        intent: 'GREETING_INITIALIZED',
        confidenceScore: 100,
        latencyMs: 12
      }
    }
  ]);

  const [inputQuery, setInputQuery] = useState('');
  const [isTyping, setIsTyping] = useState(false);
  const [lastAuditLog, setLastAuditLog] = useState<{
    intent: string;
    guardrail: string | null;
    source: string | null;
    confidence: number;
    latency: number;
    appliedPrompt: string;
  }>({
    intent: 'STORE_GREETING',
    guardrail: null,
    source: 'Personality Config (en)',
    confidence: 100,
    latency: 18,
    appliedPrompt: personality.brandMissionPrompt.slice(0, 140) + '...'
  });

  const chatEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  const handleSendMessage = (textToSend?: string) => {
    const query = textToSend || inputQuery;
    if (!query.trim()) return;

    const userMsg: SimulatedMessage = {
      id: 'msg-' + Date.now(),
      sender: 'user',
      text: query,
      timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };

    setMessages(prev => [...prev, userMsg]);
    if (!textToSend) setInputQuery('');
    setIsTyping(true);

    const startTime = performance.now();

    setTimeout(() => {
      let replyText = '';
      let detectedIntent = 'GENERAL_INQUIRY';
      let triggeredGuardrail: string | null = null;
      let retrievedSource: string | null = null;
      let confidence = 96;

      const lower = query.toLowerCase();

      // 1. Off-topic Anti-Hallucination check
      if (
        guardrails.antiHallucinationRules.strictStoreContextOnly &&
        guardrails.antiHallucinationRules.refuseNonEcommerceQueries &&
        (lower.includes('poem') || lower.includes('python') || lower.includes('code') || lower.includes('capital of') || lower.includes('essay'))
      ) {
        replyText = guardrails.antiHallucinationRules.nonEcommerceRefusalMessage;
        detectedIntent = 'OFF_TOPIC_REJECTION';
        triggeredGuardrail = 'ANTI_HALLUCINATION_STORE_ONLY';
        retrievedSource = 'Guardrail: Strict Store Context';
        confidence = 99;
      }
      // 2. Competitor Check
      else if (
        guardrails.competitorMentions.blockOrNeutralize &&
        guardrails.competitorMentions.blacklistedCompetitors.some(c => lower.includes(c.toLowerCase()))
      ) {
        const matchedComp = guardrails.competitorMentions.blacklistedCompetitors.find(c => lower.includes(c.toLowerCase()));
        if (guardrails.competitorMentions.neutralizeStrategy === 'redirect_to_store_advantages') {
          replyText = `While we respect other brands in the market, JW Studio specializes in custom-milled 280GSM ultra-dense luxury cotton and artisanal drop-shoulder tailoring designed to outlast ordinary fast-fashion pieces. Our garments retain their structural drape through over 50+ wash cycles.`;
        } else {
          replyText = guardrails.competitorMentions.customCannedResponse;
        }
        detectedIntent = 'COMPETITOR_COMPARISON';
        triggeredGuardrail = `COMPETITOR_BLACKLIST (${matchedComp})`;
        retrievedSource = 'Guardrail: Competitor Neutralizer';
        confidence = 98;
      }
      // 3. Price Bargaining Check
      else if (
        guardrails.priceBargaining.blockUnauthorizedDiscounts &&
        (lower.includes('discount') || lower.includes('cheaper') || lower.includes('bargain') || lower.includes('haggle') || lower.includes('less price') || lower.includes('best price'))
      ) {
        if (guardrails.priceBargaining.suggestOfficialVouchersOnly && knowledgeScope.promotionsAndDiscounts.vouchers.length > 0) {
          const activeVouchers = knowledgeScope.promotionsAndDiscounts.vouchers
            .filter(v => v.publiclyDisclosable)
            .map(v => `• **${v.code}**: ${v.description}`)
            .join('\n');
          replyText = `Our prices reflect our bespoke heavyweight craftsmanship and premium custom wash treatments. While we do not offer manual price deductions, you are welcome to apply our active seasonal vouchers at checkout:\n\n${activeVouchers}`;
        } else {
          replyText = guardrails.priceBargaining.refusalTemplate;
        }
        detectedIntent = 'PRICE_BARGAINING';
        triggeredGuardrail = 'BLOCK_UNAUTHORIZED_DISCOUNTS';
        retrievedSource = 'Promotions Scope & Pricing Guardrail';
        confidence = 97;
      }
      // 4. Order Tracking Query
      else if (
        knowledgeScope.orderLookup.isEnabled &&
        (lower.includes('order') || lower.includes('tracking') || lower.includes('jw-') || lower.includes('where is my package'))
      ) {
        replyText = `Thank you for confirming your order details. Order **#JW-1002** has been dispatched with **Pos Laju** (Tracking: **ER82910291MY**). It is currently in transit to Selangor with an estimated arrival tomorrow by 4:00 PM.`;
        detectedIntent = 'ORDER_STATUS_LOOKUP';
        retrievedSource = 'OMS Live Database (#JW-1002)';
        confidence = 99;
      }
      // 5. FAQ RAG Knowledge Matching
      else {
        const matchedFaq = knowledgeScope.faqKnowledge.items.find(f =>
          f.isActive && f.keywords.some(k => lower.includes(k))
        );

        if (matchedFaq) {
          replyText = matchedFaq.answer;
          detectedIntent = `FAQ_GROUNDED (${matchedFaq.category.toUpperCase()})`;
          retrievedSource = `FAQ Node: "${matchedFaq.question.slice(0, 30)}..."`;
          confidence = 95;
        } else {
          // Default Brand AI Response
          replyText = `At JW Studio, our collections feature 280GSM heavy comb cotton with drop-shoulder silhouette cuts. If you require personalized sizing advice or measurements, feel free to share your height and weight!`;
          detectedIntent = 'PRODUCT_CURATION';
          retrievedSource = 'Master System Instructions';
          confidence = 91;
        }
      }

      const endTime = performance.now();
      const latencyMs = Math.round(endTime - startTime + Math.random() * 80 + 120);

      const botMsg: SimulatedMessage = {
        id: 'bot-' + Date.now(),
        sender: 'assistant',
        text: replyText,
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        metadata: {
          intent: detectedIntent,
          guardrailTriggered: triggeredGuardrail,
          retrievedSource,
          confidenceScore: confidence,
          latencyMs
        }
      };

      setLastAuditLog({
        intent: detectedIntent,
        guardrail: triggeredGuardrail,
        source: retrievedSource,
        confidence,
        latency: latencyMs,
        appliedPrompt: personality.brandMissionPrompt.slice(0, 140) + '...'
      });

      setMessages(prev => [...prev, botMsg]);
      setIsTyping(false);
    }, 450);
  };

  const handleResetChat = () => {
    setMessages([
      {
        id: 'init-reset',
        sender: 'assistant',
        text: personality.greetingMessage.en || 'Welcome to JW Studio. How may I assist your style curation today?',
        timestamp: 'Just now',
        metadata: {
          intent: 'GREETING_INITIALIZED',
          confidenceScore: 100,
          latencyMs: 10
        }
      }
    ]);
  };

  return (
    <div id="ai-simulator-tab-container" className="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-in fade-in duration-150">
      {/* Left Chat Window (7 Cols) */}
      <div className="lg:col-span-7 flex flex-col h-[680px] bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-xs overflow-hidden">
        {/* Chat Header */}
        <div className="p-4 bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="relative">
              <div className="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                <Bot className="w-5 h-5" />
              </div>
              <span className="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white dark:border-neutral-900" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-xs font-bold text-neutral-900 dark:text-white">
                  {personality.botName}
                </h3>
                <span className="text-[10px] px-1.5 py-0.5 bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold rounded-sm">
                  Live Preview
                </span>
              </div>
            </div>
          </div>

          <button
            onClick={handleResetChat}
            className="p-2 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-lg transition-colors"
            title="Reset Simulation Memory"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>

        {/* Quick Scenario Chips */}
        <div className="p-2.5 bg-neutral-100/60 dark:bg-neutral-800/40 border-b border-neutral-200 dark:border-neutral-800 flex items-center gap-1.5 overflow-x-auto text-[11px]">
          <span className="text-neutral-400 whitespace-nowrap pl-1 font-semibold flex items-center gap-1">
            <Zap className="w-3 h-3 text-amber-500" /> Test Scenarios:
          </span>
          {PRESET_SCENARIOS.map((sc, i) => (
            <button
              key={i}
              onClick={() => handleSendMessage(sc.query)}
              className="px-2.5 py-1 bg-white dark:bg-neutral-900 hover:bg-indigo-50 dark:hover:bg-indigo-950 hover:text-indigo-600 dark:hover:text-indigo-400 border border-neutral-200 dark:border-neutral-700 rounded-lg whitespace-nowrap text-neutral-700 dark:text-neutral-300 font-medium transition-colors"
            >
              {sc.label}
            </button>
          ))}
        </div>

        {/* Message Stream */}
        <div className="flex-1 p-4 overflow-y-auto space-y-4 bg-neutral-50/40 dark:bg-neutral-950/30">
          {messages.map(msg => {
            const isBot = msg.sender === 'assistant';
            return (
              <div
                key={msg.id}
                className={`flex gap-3 ${isBot ? 'justify-start' : 'justify-end'}`}
              >
                {isBot && (
                  <div className="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                    <Bot className="w-4 h-4" />
                  </div>
                )}

                <div className={`max-w-[82%] space-y-1.5 ${isBot ? 'items-start' : 'items-end'}`}>
                  <div
                    className={`p-3.5 rounded-2xl text-xs leading-relaxed ${
                      isBot
                        ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white border border-neutral-200 dark:border-neutral-700 shadow-xs'
                        : 'bg-indigo-600 text-white shadow-xs rounded-br-xs'
                    }`}
                  >
                    <p className="whitespace-pre-wrap">{msg.text}</p>
                  </div>

                  {/* Metadata Chips on Bot message */}
                  {isBot && msg.metadata && (
                    <div className="flex flex-wrap items-center gap-1.5 text-[10px] pl-1 text-neutral-400">
                      <span>{msg.timestamp}</span>
                      {msg.metadata.guardrailTriggered && (
                        <span className="px-1.5 py-0.2 rounded-sm bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 font-bold flex items-center gap-1">
                          <ShieldAlert className="w-2.5 h-2.5" />
                          {msg.metadata.guardrailTriggered}
                        </span>
                      )}
                      {msg.metadata.retrievedSource && (
                        <span className="px-1.5 py-0.2 rounded-sm bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700">
                          {msg.metadata.retrievedSource}
                        </span>
                      )}
                      <span className="font-mono">{msg.metadata.latencyMs}ms</span>
                    </div>
                  )}
                </div>

                {!isBot && (
                  <div className="w-7 h-7 rounded-lg bg-neutral-800 text-white flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                    <User className="w-4 h-4" />
                  </div>
                )}
              </div>
            );
          })}

          {isTyping && (
            <div className="flex gap-3 justify-start items-center">
              <div className="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 text-xs font-bold">
                <Bot className="w-4 h-4" />
              </div>
              <div className="bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 px-4 py-2.5 rounded-2xl text-xs text-neutral-400 flex items-center gap-1.5">
                <span className="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse" />
                <span className="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse delay-75" />
                <span className="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse delay-150" />
                <span className="text-[11px] ml-1">Evaluating guardrails & knowledge base...</span>
              </div>
            </div>
          )}

          <div ref={chatEndRef} />
        </div>

        {/* Input Bar */}
        <div className="p-3 bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-800 flex items-center gap-2">
          <input
            type="text"
            value={inputQuery}
            onChange={e => setInputQuery(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleSendMessage()}
            placeholder="Type customer message to test AI auto-reply..."
            className="flex-1 text-xs px-3.5 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
          />
          <button
            onClick={() => handleSendMessage()}
            className="p-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition-colors shadow-xs"
          >
            <Send className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Right Execution Inspector & Guardrail Audit (5 Cols) */}
      <div className="lg:col-span-5 space-y-4">
        {/* Active Guardrail Status Box */}
        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl p-5 shadow-xs">
          <div className="flex items-center gap-2 pb-3 border-b border-neutral-100 dark:border-neutral-800">
            <ShieldCheck className="w-4 h-4 text-emerald-600" />
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Real-time Policy Guard Inspector
            </h3>
          </div>

          <div className="space-y-3 pt-3 text-xs">
            <div className="flex items-center justify-between p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
              <span className="text-neutral-500">Detected Intent</span>
              <span className="font-mono font-bold text-neutral-900 dark:text-white">{lastAuditLog.intent}</span>
            </div>

            <div className="flex items-center justify-between p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
              <span className="text-neutral-500">Safety Guardrail</span>
              {lastAuditLog.guardrail ? (
                <span className="font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800">
                  {lastAuditLog.guardrail}
                </span>
              ) : (
                <span className="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                  <CheckCircle2 className="w-3.5 h-3.5" /> No Violations
                </span>
              )}
            </div>

            <div className="flex items-center justify-between p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
              <span className="text-neutral-500">RAG Grounded Source</span>
              <span className="text-neutral-700 dark:text-neutral-300 font-medium truncate max-w-[200px]" title={lastAuditLog.source || 'None'}>
                {lastAuditLog.source || 'General Context'}
              </span>
            </div>

            <div className="grid grid-cols-2 gap-2 pt-1">
              <div className="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 text-center">
                <span className="text-[10px] text-neutral-400 block">RAG Confidence</span>
                <span className="text-base font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">
                  {lastAuditLog.confidence}%
                </span>
              </div>

              <div className="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 text-center">
                <span className="text-[10px] text-neutral-400 block">Inference Latency</span>
                <span className="text-base font-extrabold text-neutral-900 dark:text-white font-mono">
                  {lastAuditLog.latency}ms
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Active Knowledge Boundary Snapshot */}
        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl p-5 shadow-xs">
          <div className="flex items-center gap-2 pb-3 border-b border-neutral-100 dark:border-neutral-800">
            <Terminal className="w-4 h-4 text-indigo-600" />
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Active Knowledge Scope Checklist
            </h3>
          </div>

          <div className="space-y-2 pt-3 text-xs">
            <div className="flex items-center justify-between">
              <span className="text-neutral-600 dark:text-neutral-400 flex items-center gap-1.5">
                <HelpCircle className="w-3.5 h-3.5 text-blue-500" /> FAQ Scope
              </span>
              <span className="font-semibold text-neutral-900 dark:text-white">
                {knowledgeScope.faqKnowledge.isEnabled ? `${knowledgeScope.faqKnowledge.items.length} FAQs` : 'Disabled'}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-neutral-600 dark:text-neutral-400 flex items-center gap-1.5">
                <Package className="w-3.5 h-3.5 text-amber-500" /> Order Lookup (OMS)
              </span>
              <span className="font-semibold text-neutral-900 dark:text-white">
                {knowledgeScope.orderLookup.isEnabled ? 'Active (Auth Required)' : 'Disabled'}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-neutral-600 dark:text-neutral-400 flex items-center gap-1.5">
                <DollarSign className="w-3.5 h-3.5 text-emerald-500" /> Bargaining Blocker
              </span>
              <span className="font-semibold text-emerald-600">Enforced</span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-neutral-600 dark:text-neutral-400 flex items-center gap-1.5">
                <Ban className="w-3.5 h-3.5 text-rose-500" /> Blacklisted Competitors
              </span>
              <span className="font-semibold text-neutral-900 dark:text-white">
                {guardrails.competitorMentions.blacklistedCompetitors.length} Brands
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
