import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  SupportChannel,
  ConversationStatus,
  SupportConversation
} from '../../types/support';
import {
  Search,
  Pin,
  Bot,
  UserCheck,
  CheckCircle2,
  AlertCircle,
  MessageSquare,
  Sparkles,
  Phone,
  Globe,
  Facebook,
  Instagram,
  Flame,
  Clock,
  Filter,
  Check
} from 'lucide-react';

interface ConversationListPanelProps {
  selectedChannel: SupportChannel | 'all';
  setSelectedChannel: (channel: SupportChannel | 'all') => void;
  statusFilter: ConversationStatus | 'all' | 'unread' | 'high_priority';
  setStatusFilter: (status: ConversationStatus | 'all' | 'unread' | 'high_priority') => void;
  searchQuery: string;
  setSearchQuery: (query: string) => void;
  onSelectConversation: (id: string) => void;
  stats: {
    total: number;
    unread: number;
    aiHandling: number;
    humanHandling: number;
    resolved: number;
    highPriority: number;
  };
}

// Channel display metadata
export const CHANNEL_META: Record<SupportChannel, { label: string; bg: string; text: string; icon: any }> = {
  web_guest: { label: 'Web Guest', bg: 'bg-blue-50 dark:bg-blue-950/60', text: 'text-blue-600 dark:text-blue-400', icon: Globe },
  whatsapp: { label: 'WhatsApp', bg: 'bg-emerald-50 dark:bg-emerald-950/60', text: 'text-emerald-600 dark:text-emerald-400', icon: Phone },
  facebook: { label: 'Facebook', bg: 'bg-indigo-50 dark:bg-indigo-950/60', text: 'text-indigo-600 dark:text-indigo-400', icon: Facebook },
  instagram: { label: 'Instagram', bg: 'bg-pink-50 dark:bg-pink-950/60', text: 'text-pink-600 dark:text-pink-400', icon: Instagram },
  tiktok: { label: 'TikTok', bg: 'bg-neutral-100 dark:bg-neutral-800', text: 'text-neutral-900 dark:text-neutral-100', icon: Sparkles },
  reddit: { label: 'Reddit', bg: 'bg-orange-50 dark:bg-orange-950/60', text: 'text-orange-600 dark:text-orange-400', icon: MessageSquare },
  x: { label: 'X (Twitter)', bg: 'bg-neutral-100 dark:bg-neutral-800', text: 'text-neutral-900 dark:text-neutral-200', icon: MessageSquare }
};

export const ConversationListPanel: React.FC<ConversationListPanelProps> = ({
  selectedChannel,
  setSelectedChannel,
  statusFilter,
  setStatusFilter,
  searchQuery,
  setSearchQuery,
  onSelectConversation,
  stats
}) => {
  const {
    supportConversations,
    selectedConversationId,
    togglePinConversation,
    markConversationAsRead
  } = useAdmin();

  // Filter conversations
  const filteredList = supportConversations.filter(c => {
    // Channel filter
    if (selectedChannel !== 'all' && c.channel !== selectedChannel) return false;

    // Status filter
    if (statusFilter === 'unread' && (!c.unreadCount || c.unreadCount === 0)) return false;
    if (statusFilter === 'high_priority' && c.priority !== 'urgent' && c.priority !== 'high') return false;
    if (statusFilter === 'open' && c.status !== 'open') return false;
    if (statusFilter === 'human_mode' && c.status !== 'human_mode') return false;
    if (statusFilter === 'resolved' && c.status !== 'resolved') return false;

    // Search query (customer name, phone, email, snippet, intent)
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchName = c.customer.name.toLowerCase().includes(q);
      const matchPhone = c.customer.phone?.toLowerCase().includes(q);
      const matchEmail = c.customer.email?.toLowerCase().includes(q);
      const matchSnippet = c.snippet.toLowerCase().includes(q);
      const matchIntent = c.aiSummary.intent.toLowerCase().includes(q);
      if (!matchName && !matchPhone && !matchEmail && !matchSnippet && !matchIntent) {
        return false;
      }
    }

    return true;
  });

  // Sort pinned items to top, then by unread, then by status
  const sortedList = [...filteredList].sort((a, b) => {
    if (a.isPinned && !b.isPinned) return -1;
    if (!a.isPinned && b.isPinned) return 1;
    if ((b.unreadCount || 0) > (a.unreadCount || 0)) return 1;
    if ((a.unreadCount || 0) > (b.unreadCount || 0)) return -1;
    return 0;
  });

  return (
    <div id="conversation-queue-panel" className="flex flex-col h-full overflow-hidden">
      {/* Top Search & Filter Bar */}
      <div className="p-3 border-b border-neutral-200 dark:border-neutral-800 space-y-2.5">
        {/* Search Input */}
        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
          <input
            id="support-inbox-search"
            type="text"
            placeholder="Search name, phone, order, intent..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700/80 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          {searchQuery && (
            <button
              onClick={() => setSearchQuery('')}
              className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-neutral-400 hover:text-neutral-600"
            >
              ✕
            </button>
          )}
        </div>

        {/* Channel Horizontal Scroll Filters */}
        <div className="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1 text-[11px]">
          <button
            onClick={() => setSelectedChannel('all')}
            className={`px-2.5 py-1 rounded-full whitespace-nowrap font-medium transition-colors ${
              selectedChannel === 'all'
                ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900 shadow-xs'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
            }`}
          >
            All Channels
          </button>
          {(Object.keys(CHANNEL_META) as SupportChannel[]).map((ch) => {
            const meta = CHANNEL_META[ch];
            const isSelected = selectedChannel === ch;
            const Icon = meta.icon;
            return (
              <button
                key={ch}
                onClick={() => setSelectedChannel(ch)}
                className={`flex items-center gap-1 px-2.5 py-1 rounded-full whitespace-nowrap font-medium transition-colors ${
                  isSelected
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200'
                }`}
              >
                <Icon className="w-3 h-3" />
                <span>{meta.label}</span>
              </button>
            );
          })}
        </div>

        {/* Status Tab Filters */}
        <div className="flex items-center justify-between gap-1 text-[11px] font-medium border-t border-neutral-100 dark:border-neutral-800/60 pt-2">
          <button
            onClick={() => setStatusFilter('all')}
            className={`px-2 py-0.5 rounded transition-colors ${
              statusFilter === 'all'
                ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 font-semibold'
                : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300'
            }`}
          >
            All ({stats.total})
          </button>

          <button
            onClick={() => setStatusFilter('unread')}
            className={`flex items-center gap-1 px-2 py-0.5 rounded transition-colors ${
              statusFilter === 'unread'
                ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 font-semibold'
                : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300'
            }`}
          >
            <span>Unread</span>
            {stats.unread > 0 && (
              <span className="px-1 py-0.2 text-[9px] bg-amber-500 text-white rounded-full font-bold">
                {stats.unread}
              </span>
            )}
          </button>

          <button
            onClick={() => setStatusFilter('open')}
            className={`px-2 py-0.5 rounded transition-colors ${
              statusFilter === 'open'
                ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 font-semibold'
                : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300'
            }`}
          >
            AI Active ({stats.aiHandling})
          </button>

          <button
            onClick={() => setStatusFilter('human_mode')}
            className={`px-2 py-0.5 rounded transition-colors ${
              statusFilter === 'human_mode'
                ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 font-semibold'
                : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300'
            }`}
          >
            Human ({stats.humanHandling})
          </button>

          <button
            onClick={() => setStatusFilter('resolved')}
            className={`px-2 py-0.5 rounded transition-colors ${
              statusFilter === 'resolved'
                ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 font-semibold'
                : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300'
            }`}
          >
            Resolved ({stats.resolved})
          </button>
        </div>
      </div>

      {/* Conversation List Stream */}
      <div id="conversation-queue-list" className="flex-1 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800/60">
        {sortedList.length === 0 ? (
          <div className="p-8 text-center text-neutral-400">
            <MessageSquare className="w-8 h-8 mx-auto stroke-[1.2] mb-2 text-neutral-300 dark:text-neutral-700" />
            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-400">No conversations found</p>
            <p className="text-[11px] text-neutral-400 mt-0.5">Try resetting search or channel filters</p>
          </div>
        ) : (
          sortedList.map((conv) => {
            const isSelected = conv.id === selectedConversationId;
            const channelMeta = CHANNEL_META[conv.channel] || CHANNEL_META.web_guest;
            const ChannelIcon = channelMeta.icon;

            return (
              <div
                key={conv.id}
                id={`conversation-item-${conv.id}`}
                onClick={() => {
                  onSelectConversation(conv.id);
                  if (conv.unreadCount > 0) markConversationAsRead(conv.id);
                }}
                className={`p-3 cursor-pointer transition-all relative ${
                  isSelected
                    ? 'bg-indigo-50/60 dark:bg-indigo-950/40 border-l-3 border-indigo-600 dark:border-indigo-500'
                    : 'hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40'
                }`}
              >
                <div className="flex items-start gap-2.5">
                  {/* Customer Avatar & Channel Badge */}
                  <div className="relative flex-shrink-0">
                    <img
                      src={conv.customer.avatar}
                      alt={conv.customer.name}
                      className="w-9 h-9 rounded-full object-cover ring-1 ring-neutral-200 dark:ring-neutral-700"
                    />
                    <div
                      className={`absolute -bottom-1 -right-1 p-0.5 rounded-full border border-white dark:border-neutral-900 ${channelMeta.bg} ${channelMeta.text}`}
                      title={channelMeta.label}
                    >
                      <ChannelIcon className="w-2.5 h-2.5" />
                    </div>
                  </div>

                  {/* Conversation Header & Details */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-1">
                      <div className="flex items-center gap-1.5 min-w-0">
                        <span className={`text-xs font-medium truncate ${isSelected ? 'text-indigo-900 dark:text-indigo-100 font-semibold' : 'text-neutral-900 dark:text-neutral-100'}`}>
                          {conv.customer.name}
                        </span>
                        {conv.isPinned && (
                          <Pin className="w-3 h-3 text-amber-500 flex-shrink-0 fill-amber-500" />
                        )}
                      </div>
                      <span className="text-[10px] text-neutral-400 dark:text-neutral-500 flex-shrink-0">
                        {conv.lastMessageAt}
                      </span>
                    </div>

                    {/* Intent / Topic line */}
                    <div className="flex items-center gap-1.5 mt-0.5 text-[11px]">
                      <span className="text-neutral-500 dark:text-neutral-400 font-medium truncate">
                        {conv.aiSummary.intent}
                      </span>
                    </div>

                    {/* Last Snippet */}
                    <p className="text-[11px] text-neutral-600 dark:text-neutral-400 truncate mt-0.5">
                      {conv.snippet}
                    </p>

                    {/* Bottom Status Tags & AI Draft Indication */}
                    <div className="flex items-center justify-between gap-1.5 mt-2">
                      <div className="flex items-center gap-1 overflow-hidden">
                        {/* Conversation State Badge */}
                        {conv.status === 'open' && (
                          <span className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                            <Bot className="w-2.5 h-2.5" />
                            AI Triage
                          </span>
                        )}
                        {conv.status === 'human_mode' && (
                          <span className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-blue-50 text-blue-700 dark:bg-blue-950/80 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60">
                            <UserCheck className="w-2.5 h-2.5" />
                            Human {conv.assignedAgent ? `(${conv.assignedAgent.name.split(' ')[0]})` : ''}
                          </span>
                        )}
                        {conv.status === 'resolved' && (
                          <span className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                            <Check className="w-2.5 h-2.5" />
                            Resolved
                          </span>
                        )}

                        {/* AI Draft ready indicator */}
                        {conv.activeAiDraft && conv.status !== 'resolved' && (
                          <span className="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-50 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60">
                            <Sparkles className="w-2.5 h-2.5" />
                            Draft Ready
                          </span>
                        )}
                      </div>

                      {/* Unread Counter Badge */}
                      {conv.unreadCount > 0 && (
                        <span className="px-1.5 py-0.2 text-[10px] font-bold rounded-full bg-amber-500 text-white flex-shrink-0">
                          {conv.unreadCount}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};
