import React, { useState, useRef, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  SupportConversation,
  ChatMessage,
  MessageType,
  MessageAttachment,
  CannedResponse
} from '../../types/support';
import { CHANNEL_META } from './ConversationListPanel';
import {
  Send,
  Bot,
  UserCheck,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  Paperclip,
  Image as ImageIcon,
  Mic,
  MicOff,
  ShoppingBag,
  Package,
  RotateCcw,
  ArrowLeft,
  Pin,
  MoreVertical,
  Check,
  CheckCheck,
  AlertTriangle,
  Play,
  Pause,
  Volume2,
  FileText,
  Trash2,
  ExternalLink,
  ChevronDown,
  Info,
  Maximize2,
  Zap,
  HelpCircle
} from 'lucide-react';

interface ChatWorkspacePanelProps {
  conversation: SupportConversation;
  onBackToList: () => void;
  isContextOpen: boolean;
  onToggleContext: () => void;
}

export const ChatWorkspacePanel: React.FC<ChatWorkspacePanelProps> = ({
  conversation,
  onBackToList,
  isContextOpen,
  onToggleContext
}) => {
  const {
    user,
    sendMessageToConversation,
    takeOverConversation,
    handBackToAI,
    resolveConversation,
    reopenConversation,
    approveAIDraft,
    discardAIDraft,
    generateAIDraft,
    togglePinConversation,
    retryFailedMessage,
    cannedResponses,
    products,
    orders
  } = useAdmin();

  // Composer States
  const [inputText, setInputText] = useState('');
  const [isRecordingVoice, setIsRecordingVoice] = useState(false);
  const [voiceDuration, setVoiceDuration] = useState(0);
  const [showCannedMenu, setShowCannedMenu] = useState(false);
  const [showAttachMenu, setShowAttachMenu] = useState(false);
  const [simulateFailure, setSimulateFailure] = useState(false);
  const [playingVoiceId, setPlayingVoiceId] = useState<string | null>(null);
  const [selectedImagePreview, setSelectedImagePreview] = useState<string | null>(null);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Auto-scroll on new message
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [conversation.messages, conversation.isAiTyping, conversation.activeAiDraft]);

  // Voice recording timer simulation
  useEffect(() => {
    let interval: any;
    if (isRecordingVoice) {
      interval = setInterval(() => {
        setVoiceDuration(prev => prev + 1);
      }, 1000);
    } else {
      setVoiceDuration(0);
    }
    return () => clearInterval(interval);
  }, [isRecordingVoice]);

  // Channel metadata
  const channelMeta = CHANNEL_META[conversation.channel] || CHANNEL_META.web_guest;
  const ChannelIcon = channelMeta.icon;

  // Send message handler
  const handleSendMessage = () => {
    if (!inputText.trim()) return;
    const textToSend = simulateFailure ? `${inputText} [sim-fail]` : inputText;
    sendMessageToConversation(conversation.id, textToSend, 'text');
    setInputText('');
    setShowCannedMenu(false);
    if (simulateFailure) setSimulateFailure(false);
  };

  // Voice note simulator completion
  const handleFinishVoiceRecord = () => {
    setIsRecordingVoice(false);
    const audioSample = {
      id: `att-voice-${Date.now()}`,
      type: 'audio' as const,
      url: 'https://example.com/voice-sample.mp3',
      name: 'voice-note.mp3',
      size: '240 KB',
      durationSeconds: voiceDuration || 8,
      transcript: 'Customer support voice dispatch: Audio note processed with speech-to-text accuracy.',
      transcriptConfidence: 0.97
    };

    sendMessageToConversation(
      conversation.id,
      'Voice note sent',
      'voice',
      [audioSample],
      { role: 'human_agent' }
    );
  };

  // Handle attaching sample product card
  const handleAttachProduct = () => {
    const sampleProd = products[0] || {
      id: 'prod-demo',
      name: 'Aurora 280GSM Heavyweight Tee',
      price: 89.0,
      images: ['https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300&auto=format&fit=crop&q=80']
    };

    sendMessageToConversation(
      conversation.id,
      `Shared product recommendation: ${sampleProd.name}`,
      'product_card',
      undefined,
      {
        role: 'human_agent',
        productCard: {
          id: sampleProd.id,
          name: sampleProd.name,
          price: sampleProd.price,
          currency: 'MYR',
          thumbnail: sampleProd.images?.[0] || 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300&auto=format&fit=crop&q=80',
          sku: 'AUR-TEE-01',
          stockStatus: 'in_stock'
        }
      }
    );
    setShowAttachMenu(false);
  };

  // Handle attaching sample order status card
  const handleAttachOrder = () => {
    const sampleOrder = orders[0] || {
      id: 'ord-my-9081',
      orderNumber: 'AUR-MY-89210',
      totalAmount: 189.0,
      fulfillmentStatus: 'shipped'
    };

    sendMessageToConversation(
      conversation.id,
      `Live Order Status verification: #${sampleOrder.orderNumber}`,
      'order_card',
      undefined,
      {
        role: 'human_agent',
        orderCard: {
          orderNumber: sampleOrder.orderNumber || 'AUR-MY-89210',
          status: 'Shipped (In Transit)',
          total: `RM ${sampleOrder.totalAmount?.toFixed(2) || '189.00'}`,
          courierName: 'Pos Malaysia Express',
          trackingNumber: 'ER920194821MY',
          destination: 'Petaling Jaya, Selangor'
        }
      }
    );
    setShowAttachMenu(false);
  };

  // Apply canned response
  const handleApplyCanned = (canned: CannedResponse) => {
    let replaced = canned.content
      .replace('{{customer_name}}', conversation.customer.name)
      .replace('{{order_number}}', 'AUR-MY-89210')
      .replace('{{agent_name}}', user?.name || 'Customer Support Specialist');

    setInputText(replaced);
    setShowCannedMenu(false);
    textareaRef.current?.focus();
  };

  return (
    <div id="chat-workspace-container" className="flex flex-col h-full overflow-hidden bg-neutral-50 dark:bg-neutral-950">
      {/* 1. Header Toolbar */}
      <div
        id="chat-workspace-header"
        className="flex-shrink-0 h-16 px-4 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3 z-10"
      >
        {/* Left: Back (Mobile) & Customer Summary */}
        <div className="flex items-center gap-3 min-w-0">
          <button
            onClick={onBackToList}
            className="md:hidden p-1.5 -ml-1 text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>

          {/* Customer Avatar & Status */}
          <div className="relative flex-shrink-0">
            <img
              src={conversation.customer.avatar}
              alt={conversation.customer.name}
              className="w-10 h-10 rounded-full object-cover ring-2 ring-neutral-100 dark:ring-neutral-800"
            />
            <div
              className={`absolute -bottom-1 -right-1 p-0.5 rounded-full border border-white dark:border-neutral-900 ${channelMeta.bg} ${channelMeta.text}`}
              title={channelMeta.label}
            >
              <ChannelIcon className="w-3 h-3" />
            </div>
          </div>

          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h2 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">
                {conversation.customer.name}
              </h2>
              {conversation.customer.qualification && (
                <span className="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                  {conversation.customer.qualification}
                </span>
              )}
            </div>

            <div className="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
              <span>{channelMeta.label}</span>
              <span>•</span>
              <span className="font-mono">{conversation.customer.phone || conversation.customer.email || 'Web Session'}</span>
              <span>•</span>
              <span className="text-indigo-600 dark:text-indigo-400 font-medium">
                Intent: {conversation.aiSummary.intent}
              </span>
            </div>
          </div>
        </div>

        {/* Right: State Machine Actions (AI Mode / Human Takeover / Resolve) */}
        <div className="flex items-center gap-2 flex-shrink-0">
          {/* Status Indicator & Takeover Controls */}
          {conversation.status === 'open' && (
            <div className="flex items-center gap-1.5">
              <span className="hidden lg:inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                <Bot className="w-3.5 h-3.5" />
                AI Active
              </span>

              <button
                id="btn-take-over-chat"
                onClick={() => takeOverConversation(conversation.id)}
                className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-xs"
              >
                <UserCheck className="w-3.5 h-3.5" />
                <span>Take Over</span>
              </button>
            </div>
          )}

          {conversation.status === 'human_mode' && (
            <div className="flex items-center gap-1.5">
              <span className="hidden lg:inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                <UserCheck className="w-3.5 h-3.5" />
                {conversation.assignedAgent?.name || 'Human Specialist'}
              </span>

              <button
                id="btn-hand-back-ai"
                onClick={() => handBackToAI(conversation.id)}
                className="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-lg transition-colors"
                title="Return conversation to automated AI handling"
              >
                <Bot className="w-3.5 h-3.5 text-indigo-500" />
                <span className="hidden sm:inline">Hand to AI</span>
              </button>
            </div>
          )}

          {/* Resolve / Reopen Button */}
          {conversation.status !== 'resolved' ? (
            <button
              id="btn-resolve-chat"
              onClick={() => resolveConversation(conversation.id)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg transition-colors"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Resolve</span>
            </button>
          ) : (
            <button
              id="btn-reopen-chat"
              onClick={() => reopenConversation(conversation.id)}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-700 rounded-lg transition-colors"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Reopen</span>
            </button>
          )}

          {/* Pin Button */}
          <button
            onClick={() => togglePinConversation(conversation.id)}
            title={conversation.isPinned ? 'Unpin thread' : 'Pin thread to top'}
            className={`p-1.5 rounded-lg border transition-colors ${
              conversation.isPinned
                ? 'bg-amber-50 dark:bg-amber-950/60 border-amber-300 dark:border-amber-700 text-amber-600 dark:text-amber-400'
                : 'bg-white dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200'
            }`}
          >
            <Pin className={`w-4 h-4 ${conversation.isPinned ? 'fill-amber-500' : ''}`} />
          </button>
        </div>
      </div>

      {/* 2. Message History Stream */}
      <div
        id="chat-message-stream"
        className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4"
      >
        {/* Thread Start Notice */}
        <div className="flex items-center justify-center my-2">
          <div className="flex items-center gap-2 px-3 py-1 rounded-full bg-neutral-200/60 dark:bg-neutral-800/60 text-neutral-500 text-[11px]">
            <ChannelIcon className="w-3 h-3" />
            <span>Connected via {channelMeta.label} • End-to-End Encrypted Gateway</span>
          </div>
        </div>

        {/* Message Bubble Mapping */}
        {conversation.messages.map((msg) => {
          const isCustomer = msg.senderRole === 'customer';
          const isAI = msg.senderRole === 'ai_agent';
          const isHuman = msg.senderRole === 'human_agent';
          const isSystem = msg.senderRole === 'system';

          // System Event line
          if (isSystem) {
            return (
              <div key={msg.id} className="flex justify-center my-3">
                <div className="flex items-center gap-1.5 px-3 py-1 rounded-md bg-neutral-100 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-400 text-xs border border-neutral-200 dark:border-neutral-700">
                  <Info className="w-3.5 h-3.5 text-neutral-400" />
                  <span>{msg.content}</span>
                  <span className="text-[10px] text-neutral-400">({msg.timestamp})</span>
                </div>
              </div>
            );
          }

          return (
            <div
              key={msg.id}
              id={`chat-message-${msg.id}`}
              className={`flex items-start gap-2.5 max-w-2xl ${
                isCustomer ? 'mr-auto' : 'ml-auto flex-row-reverse'
              }`}
            >
              {/* Avatar */}
              <img
                src={
                  isCustomer
                    ? conversation.customer.avatar
                    : isHuman
                    ? (msg.senderAvatar || user?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80')
                    : 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=150&auto=format&fit=crop&q=80'
                }
                alt={msg.senderName}
                className="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-neutral-200 dark:ring-neutral-800"
              />

              {/* Message Payload */}
              <div className={`space-y-1.5 ${isCustomer ? 'items-start' : 'items-end'}`}>
                {/* Header Tag */}
                <div
                  className={`flex items-center gap-1.5 text-[11px] ${
                    isCustomer ? 'text-neutral-500' : 'justify-end text-neutral-400'
                  }`}
                >
                  <span className="font-medium text-neutral-700 dark:text-neutral-300">
                    {msg.senderName}
                  </span>
                  {isAI && (
                    <span className="inline-flex items-center gap-0.5 px-1.5 py-0.2 text-[9px] font-semibold rounded bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                      <Sparkles className="w-2.5 h-2.5" />
                      Gemini 2.5
                    </span>
                  )}
                  {isHuman && (
                    <span className="inline-flex items-center gap-0.5 px-1.5 py-0.2 text-[9px] font-semibold rounded bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                      <UserCheck className="w-2.5 h-2.5" />
                      Specialist
                    </span>
                  )}
                  <span>•</span>
                  <span>{msg.timestamp}</span>
                </div>

                {/* Bubble Surface */}
                <div
                  className={`p-3.5 rounded-2xl text-xs leading-relaxed shadow-xs ${
                    isCustomer
                      ? 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-tl-sm'
                      : isAI
                      ? 'bg-indigo-600 text-white rounded-tr-sm'
                      : 'bg-neutral-900 dark:bg-neutral-800 text-white rounded-tr-sm border border-neutral-800 dark:border-neutral-700'
                  }`}
                >
                  {/* Text Content */}
                  {msg.content && (
                    <p className="whitespace-pre-wrap">{msg.content}</p>
                  )}

                  {/* Voice Note Attachment Player */}
                  {msg.type === 'voice' && msg.attachments && msg.attachments[0] && (
                    <div className="mt-2 p-2.5 rounded-xl bg-black/10 dark:bg-white/10 space-y-2">
                      <div className="flex items-center gap-2.5">
                        <button
                          onClick={() => setPlayingVoiceId(playingVoiceId === msg.id ? null : msg.id)}
                          className="p-2 rounded-full bg-white text-indigo-600 dark:bg-neutral-900 dark:text-indigo-400 shadow-xs hover:scale-105 transition-transform"
                        >
                          {playingVoiceId === msg.id ? (
                            <Pause className="w-3.5 h-3.5 fill-current" />
                          ) : (
                            <Play className="w-3.5 h-3.5 fill-current ml-0.5" />
                          )}
                        </button>
                        {/* Audio Waveform Simulator */}
                        <div className="flex-1 flex items-center gap-0.5 h-6 px-1">
                          {[40, 70, 90, 60, 30, 80, 100, 50, 75, 45, 85, 95, 60, 40, 70, 80, 30, 90, 65, 50].map((h, i) => (
                            <div
                              key={i}
                              style={{ height: `${h}%` }}
                              className={`w-1 rounded-full transition-all ${
                                playingVoiceId === msg.id ? 'bg-indigo-300 animate-pulse' : 'bg-white/60 dark:bg-white/40'
                              }`}
                            />
                          ))}
                        </div>
                        <span className="text-[11px] font-mono opacity-80">
                          0:0{msg.attachments[0].durationSeconds || 8}
                        </span>
                      </div>

                      {/* Speech to text transcript */}
                      {msg.attachments[0].transcript && (
                        <div className="pt-2 border-t border-white/10 text-[11px] opacity-90 italic">
                          "{msg.attachments[0].transcript}"
                        </div>
                      )}
                    </div>
                  )}

                  {/* Image Attachment Gallery */}
                  {msg.type === 'image' && msg.attachments && (
                    <div className="mt-2 grid grid-cols-2 gap-2">
                      {msg.attachments.map((att) => (
                        <div
                          key={att.id}
                          className="relative rounded-lg overflow-hidden border border-black/10 cursor-pointer group"
                          onClick={() => setSelectedImagePreview(att.url)}
                        >
                          <img
                            src={att.url}
                            alt={att.name}
                            className="w-full h-32 object-cover group-hover:scale-105 transition-transform"
                          />
                          <div className="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                            <Maximize2 className="w-4 h-4 text-white" />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Product Recommendation Card */}
                  {msg.productCard && (
                    <div className="mt-2.5 p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-neutral-100 flex items-center gap-3">
                      <img
                        src={msg.productCard.thumbnail}
                        alt={msg.productCard.name}
                        className="w-12 h-12 rounded-lg object-cover ring-1 ring-neutral-200 dark:ring-neutral-700"
                      />
                      <div className="flex-1 min-w-0">
                        <div className="font-semibold text-xs truncate">
                          {msg.productCard.name}
                        </div>
                        <div className="text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">
                          {msg.productCard.currency} {msg.productCard.price.toFixed(2)}
                        </div>
                        <span className="text-[10px] text-emerald-600 font-medium">In Stock • Selangor Hub</span>
                      </div>
                    </div>
                  )}

                  {/* Live Order Card */}
                  {msg.orderCard && (
                    <div className="mt-2.5 p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-neutral-100 space-y-1.5">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold">{msg.orderCard.orderNumber}</span>
                        <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border border-blue-200">
                          {msg.orderCard.status}
                        </span>
                      </div>
                      <div className="text-[11px] text-neutral-500">
                        Courier: <span className="font-medium text-neutral-800 dark:text-neutral-200">{msg.orderCard.courierName}</span>
                      </div>
                      <div className="text-[11px] text-neutral-500 font-mono">
                        AWB: {msg.orderCard.trackingNumber}
                      </div>
                    </div>
                  )}
                </div>

                {/* Delivery Status / Failed Retry */}
                {!isCustomer && (
                  <div className="flex items-center justify-end gap-1 text-[10px] text-neutral-400 pr-1">
                    {msg.deliveryStatus === 'delivered' && (
                      <span className="flex items-center gap-0.5 text-neutral-400">
                        <CheckCheck className="w-3 h-3 text-indigo-500" />
                        <span>Delivered</span>
                      </span>
                    )}
                    {msg.deliveryStatus === 'failed' && (
                      <div className="flex items-center gap-1.5 text-red-500 font-medium">
                        <AlertTriangle className="w-3 h-3 text-red-500" />
                        <span>Delivery Failed</span>
                        <button
                          onClick={() => retryFailedMessage(conversation.id, msg.id)}
                          className="px-1.5 py-0.5 rounded bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300 hover:bg-red-200 transition-colors font-semibold"
                        >
                          Retry
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
          );
        })}

        {/* AI Typing Indicator */}
        {conversation.isAiTyping && (
          <div className="flex items-start gap-2.5 max-w-md">
            <div className="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-950 flex items-center justify-center text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-300 dark:ring-indigo-800">
              <Bot className="w-4 h-4" />
            </div>
            <div className="p-3 rounded-2xl rounded-tl-sm bg-white dark:bg-neutral-900 border border-indigo-200 dark:border-indigo-800/80 shadow-xs flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400">
              <Sparkles className="w-3.5 h-3.5 animate-spin" />
              <span>Gemini 2.5 Flash analyzing context & drafting response...</span>
            </div>
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      {/* 3. AI Recommended Draft Box (Docked right above composer) */}
      {conversation.activeAiDraft && conversation.status !== 'resolved' && (
        <div
          id="ai-draft-recommendation-box"
          className="flex-shrink-0 mx-4 mb-3 p-3.5 rounded-xl bg-gradient-to-r from-indigo-50 via-purple-50 to-indigo-50 dark:from-indigo-950/60 dark:via-purple-950/40 dark:to-indigo-950/60 border border-indigo-200 dark:border-indigo-800/80 shadow-sm space-y-2.5 z-10"
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="p-1 rounded-md bg-indigo-600 text-white">
                <Sparkles className="w-3.5 h-3.5" />
              </div>
              <span className="text-xs font-semibold text-indigo-900 dark:text-indigo-200">
                AI Suggested Response
              </span>
              <span className="px-2 py-0.2 text-[10px] font-bold rounded-full bg-indigo-200/80 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-300">
                {Math.round(conversation.activeAiDraft.confidence * 100)}% Match
              </span>
              <span className="text-[10px] text-neutral-500 hidden sm:inline">
                via {conversation.activeAiDraft.model}
              </span>
            </div>

            <div className="flex items-center gap-1">
              <button
                onClick={() => generateAIDraft(conversation.id)}
                title="Regenerate Draft with Gemini"
                className="p-1 text-neutral-500 hover:text-indigo-600 rounded"
              >
                <RotateCcw className="w-3.5 h-3.5" />
              </button>
              <button
                onClick={() => discardAIDraft(conversation.id)}
                title="Discard suggestion"
                className="p-1 text-neutral-500 hover:text-red-500 rounded"
              >
                <Trash2 className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <p className="text-xs text-neutral-800 dark:text-neutral-200 leading-relaxed bg-white/80 dark:bg-neutral-900/80 p-2.5 rounded-lg border border-indigo-100 dark:border-indigo-900/60 font-sans">
            {conversation.activeAiDraft.content}
          </p>

          <div className="flex items-center justify-between pt-1">
            <span className="text-[10px] text-neutral-500 truncate max-w-xs">
              Context: {conversation.activeAiDraft.reasoning}
            </span>

            <div className="flex items-center gap-2">
              <button
                onClick={() => {
                  setInputText(conversation.activeAiDraft!.content);
                  discardAIDraft(conversation.id);
                  textareaRef.current?.focus();
                }}
                className="px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-white dark:bg-neutral-900 border border-indigo-200 dark:border-indigo-800 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950 transition-colors"
              >
                Edit in Composer
              </button>
              <button
                id="btn-approve-send-ai-draft"
                onClick={() => approveAIDraft(conversation.id)}
                className="flex items-center gap-1 px-3 py-1 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-xs transition-colors"
              >
                <Check className="w-3.5 h-3.5" />
                <span>Approve & Send</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 4. Rich Message Composer */}
      <div
        id="chat-composer-container"
        className="flex-shrink-0 p-4 bg-white dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-800 space-y-2.5"
      >
        {/* Quick Canned Response Pills & Simulator Controls */}
        <div className="flex items-center justify-between gap-2 text-xs">
          <div className="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
            <span className="text-[11px] text-neutral-400 font-medium whitespace-nowrap">Quick Responses:</span>
            {cannedResponses.slice(0, 3).map((canned) => (
              <button
                key={canned.id}
                onClick={() => handleApplyCanned(canned)}
                className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 text-[11px] whitespace-nowrap transition-colors"
              >
                {canned.title}
              </button>
            ))}
            <button
              onClick={() => setShowCannedMenu(!showCannedMenu)}
              className="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap"
            >
              All Canned ({cannedResponses.length})
            </button>
          </div>

          {/* Test Carrier failure simulator toggle */}
          <label className="hidden sm:flex items-center gap-1.5 text-[11px] text-neutral-500 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={simulateFailure}
              onChange={(e) => setSimulateFailure(e.target.checked)}
              className="rounded text-indigo-600 focus:ring-0 w-3 h-3"
            />
            <span>Simulate Gateway Fail</span>
          </label>
        </div>

        {/* Canned Response Dropdown Popover */}
        {showCannedMenu && (
          <div className="p-3 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 space-y-2 max-h-48 overflow-y-auto">
            <div className="flex items-center justify-between text-xs font-semibold text-neutral-700 dark:text-neutral-300">
              <span>Select Enterprise Canned Template</span>
              <button onClick={() => setShowCannedMenu(false)} className="text-neutral-400 hover:text-neutral-600">✕</button>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              {cannedResponses.map((canned) => (
                <div
                  key={canned.id}
                  onClick={() => handleApplyCanned(canned)}
                  className="p-2 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-700/60 hover:border-indigo-500 cursor-pointer transition-colors"
                >
                  <div className="text-xs font-medium text-neutral-900 dark:text-neutral-100">{canned.title}</div>
                  <div className="text-[10px] text-neutral-500 truncate mt-0.5">{canned.content}</div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Active Voice Recording Bar */}
        {isRecordingVoice ? (
          <div className="flex items-center justify-between p-3 rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping" />
              <span className="text-xs font-semibold">Recording Voice Note ({voiceDuration}s)</span>
              <span className="text-[11px] opacity-80">Waveform streaming live...</span>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setIsRecordingVoice(false)}
                className="px-2.5 py-1 text-xs text-neutral-600 dark:text-neutral-300 bg-white dark:bg-neutral-900 rounded-md border border-neutral-200 dark:border-neutral-700"
              >
                Cancel
              </button>
              <button
                onClick={handleFinishVoiceRecord}
                className="flex items-center gap-1 px-3 py-1 text-xs font-semibold bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors"
              >
                <Check className="w-3.5 h-3.5" />
                <span>Send Audio</span>
              </button>
            </div>
          </div>
        ) : (
          /* Main Textarea Input & Action Triggers */
          <div className="relative flex items-end gap-2 bg-neutral-50 dark:bg-neutral-800/80 rounded-xl border border-neutral-200 dark:border-neutral-700/80 p-2 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
            {/* Attachment Popover Trigger */}
            <div className="relative">
              <button
                type="button"
                onClick={() => setShowAttachMenu(!showAttachMenu)}
                className="p-2 text-neutral-500 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-neutral-200/60 dark:hover:bg-neutral-700/60 transition-colors"
                title="Attach product, order or file"
              >
                <Paperclip className="w-4 h-4" />
              </button>

              {/* Attach Dropdown Menu */}
              {showAttachMenu && (
                <div className="absolute bottom-12 left-0 w-52 p-1.5 bg-white dark:bg-neutral-900 rounded-xl shadow-xl border border-neutral-200 dark:border-neutral-800 space-y-1 z-30">
                  <button
                    onClick={handleAttachProduct}
                    className="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 rounded-lg transition-colors"
                  >
                    <ShoppingBag className="w-3.5 h-3.5 text-indigo-600" />
                    <span>Recommend Product SKU</span>
                  </button>

                  <button
                    onClick={handleAttachOrder}
                    className="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 rounded-lg transition-colors"
                  >
                    <Package className="w-3.5 h-3.5 text-blue-600" />
                    <span>Share Order Status / AWB</span>
                  </button>

                  <button
                    onClick={() => {
                      sendMessageToConversation(
                        conversation.id,
                        'Shared product catalog attachment',
                        'image',
                        [{
                          id: `att-img-${Date.now()}`,
                          type: 'image',
                          url: 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&auto=format&fit=crop&q=80',
                          name: 'catalog-spec.jpg',
                          size: '1.2 MB'
                        }]
                      );
                      setShowAttachMenu(false);
                    }}
                    className="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 rounded-lg transition-colors"
                  >
                    <ImageIcon className="w-3.5 h-3.5 text-pink-600" />
                    <span>Upload Image / Photo</span>
                  </button>
                </div>
              )}
            </div>

            {/* Voice record trigger */}
            <button
              type="button"
              onClick={() => setIsRecordingVoice(true)}
              className="p-2 text-neutral-500 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-neutral-200/60 dark:hover:bg-neutral-700/60 transition-colors"
              title="Record voice dispatch"
            >
              <Mic className="w-4 h-4" />
            </button>

            {/* Textarea */}
            <textarea
              ref={textareaRef}
              rows={2}
              placeholder={`Reply to ${conversation.customer.name} via ${channelMeta.label}... (Press Enter to send, Shift+Enter for new line)`}
              value={inputText}
              onChange={(e) => setInputText(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault();
                  handleSendMessage();
                }
              }}
              className="flex-1 bg-transparent border-0 resize-none text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:ring-0 p-1.5 max-h-32"
            />

            {/* Send Action Button */}
            <button
              id="btn-send-chat-message"
              type="button"
              onClick={handleSendMessage}
              disabled={!inputText.trim()}
              className="p-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:hover:bg-indigo-600 text-white transition-colors flex-shrink-0 shadow-xs"
            >
              <Send className="w-4 h-4" />
            </button>
          </div>
        )}
      </div>

      {/* Full image preview modal if clicked */}
      {selectedImagePreview && (
        <div
          className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
          onClick={() => setSelectedImagePreview(null)}
        >
          <div className="relative max-w-3xl max-h-[85vh] overflow-hidden rounded-2xl">
            <img src={selectedImagePreview} alt="Preview" className="w-full h-full object-contain" />
            <button
              onClick={() => setSelectedImagePreview(null)}
              className="absolute top-4 right-4 p-2 rounded-full bg-black/60 text-white hover:bg-black/90"
            >
              ✕
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
