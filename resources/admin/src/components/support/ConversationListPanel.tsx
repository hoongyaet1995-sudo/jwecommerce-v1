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
  web_guest: { label: 'Storefront', bg: 'bg-blue-50 dark:bg-blue-950/60', text: 'text-blue-600 dark:text-blue-400', icon: Globe },
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
    if (statusFilter === 'archived' ? !c.isArchived : c.isArchived) return false;
    // Channel filter
    if (selectedChannel !== 'all' && c.channel !== selectedChannel) return false;

    // Status filter
    if (statusFilter === 'unread' && (!c.unreadCount || c.unreadCount === 0)) return false;
    if (statusFilter === 'high_priority' && c.priority !== 'urgent' && c.priority !== 'high') return false;
    if (statusFilter === 'open' && c.status !== 'open') return false;
    if (statusFilter === 'human_mode' && !['waiting_human','human_mode'].includes(c.status)) return false;
    if (statusFilter === 'resolved' && c.status !== 'resolved') return false;

    // Search query (customer name, phone, email, snippet, intent)
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchName = c.customer.name.toLowerCase().includes(q);
      const matchPhone = c.customer.phone?.toLowerCase().includes(q);
      const matchEmail = c.customer.email?.toLowerCase().includes(q);
      const matchSnippet = c.snippet.toLowerCase().includes(q);
      const matchMessage = c.messages.some(message => message.content.toLowerCase().includes(q));
      if (!matchName && !matchPhone && !matchEmail && !matchSnippet && !matchMessage) {
        return false;
      }
    }

    return true;
  });

  // Latest message first across the currently selected queue.
  const sortedList = [...filteredList].sort((a, b) => {
    const newest = (value: string) => Number.isFinite(Date.parse(value)) ? Date.parse(value) : 0;
    return newest(b.lastMessageTimestamp || b.createdAt) - newest(a.lastMessageTimestamp || a.createdAt) || a.id.localeCompare(b.id);
  });

  return (
    <div id="conversation-queue-panel" className="flex flex-col h-full overflow-hidden">
      {/* Top Search & Filter Bar */}
      <div className="p-3 border-b border-neutral-200 dark:border-neutral-800 space-y-2.5">
        {/* Search Input */}
        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
          <input
            id="support-inbox-search" aria-label="Search conversations"
            type="text"
            placeholder="Search customer or message…"
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

        <div className="flex items-center gap-1 text-[11px] font-medium border-t border-neutral-100 dark:border-neutral-800/60 pt-2">
          {(['open','human_mode','resolved','archived'] as const).map(status => <button key={status} onClick={() => setStatusFilter(status)} aria-pressed={statusFilter === status}
            className={'flex-1 px-2 py-1.5 rounded transition-colors '+(statusFilter === status ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 font-semibold' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300')}>
            {status === 'open' ? 'Pending' : status === 'human_mode' ? 'Human' : status === 'archived' ? 'Archived' : 'Resolved'} {supportConversations.some(c => c.unreadCount > 0 && (status === 'archived' ? c.isArchived : !c.isArchived && (status === 'open' ? c.status === 'open' : status === 'human_mode' ? ['waiting_human','human_mode'].includes(c.status) : c.status === 'resolved'))) && <span aria-label='Unread' className='inline-block w-1.5 h-1.5 rounded-full bg-red-500 ml-1' />}
          </button>)}
        </div>
      </div>

      {/* Conversation List Stream */}
      <div id="conversation-queue-list" className="flex-1 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800/60">
        {sortedList.length === 0 ? (
          <div className="p-8 text-center text-neutral-400">
            <MessageSquare className="w-8 h-8 mx-auto stroke-[1.2] mb-2 text-neutral-300 dark:text-neutral-700" />
            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-400">No conversations found</p>
            <p className="text-[11px] text-neutral-400 mt-0.5">Try another search or conversation filter</p>
          </div>
        ) : (
          sortedList.map((conv) => {
            const isSelected = conv.id === selectedConversationId;
            const channelMeta = CHANNEL_META[conv.channel] || CHANNEL_META.web_guest;
            const ChannelIcon = channelMeta.icon;

            return (
              <button type="button"
                aria-label={'Open conversation with ' + conv.customer.name}
                aria-pressed={isSelected}
                key={conv.id}
                id={`conversation-item-${conv.id}`}
                onClick={() => {
                  onSelectConversation(conv.id);
                  if (conv.unreadCount > 0) markConversationAsRead(conv.id);
                }}
                className={`w-full text-left p-3 cursor-pointer transition-all relative ${
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
                      </div>
                      <div className="flex items-center gap-2 flex-shrink-0">
                        <span className="text-[10px] text-neutral-400 dark:text-neutral-500">{conv.lastMessageAt}</span>
                        {conv.unreadCount > 0 && <span aria-label="Unread" title="Unread messages" className="w-2 h-2 rounded-full bg-red-500 flex-shrink-0" />}
                      </div>
                    </div>

                    {/* Intent / Topic line */}
                    <div className="flex items-center gap-1.5 mt-0.5 text-[11px]">
                      <span className="text-neutral-500 dark:text-neutral-400 font-medium truncate">
                        {channelMeta.label}{conv.pageName ? ` · ${conv.pageName}` : ''}
                      </span>
                      {['waiting_human','human_mode'].includes(conv.status) && <span className="text-[10px] text-indigo-600 dark:text-indigo-400">{conv.status==='waiting_human'?'Waiting for human':'Human handling'}</span>}
                    </div>

                    {/* Last Snippet */}
                    <p className="text-[11px] text-neutral-600 dark:text-neutral-400 truncate mt-0.5">
                      {conv.snippet}
                    </p>

                  </div>
                </div>
              </button>
            );
          })
        )}
      </div>
    </div>
  );
};

