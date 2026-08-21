import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  MessageSquareText,
  Sparkles,
  Inbox,
  PanelRight,
  PanelRightClose,
  Bot,
  Sliders
} from 'lucide-react';
import { ConversationListPanel } from './ConversationListPanel';
import { ChatWorkspacePanel } from './ChatWorkspacePanel';
import { CustomerContextPanel } from './CustomerContextPanel';
import { SupportChannel, ConversationStatus } from '../../types/support';

export const CustomerSupportHub: React.FC = () => {
  const {
    supportConversations,
    selectedConversationId,
    setSelectedConversationId,
    setCurrentSection,
  } = useAdmin();

  // Filter States
  const [selectedChannel, setSelectedChannel] = useState<SupportChannel | 'all'>('all');
  const [statusFilter, setStatusFilter] = useState<ConversationStatus | 'all' | 'unread' | 'high_priority'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [isContextPanelOpen, setIsContextPanelOpen] = useState(true);
  const [isMobileListOpen, setIsMobileListOpen] = useState(true);

  // Active conversation object
  const activeConversation = useMemo(() => {
    return supportConversations.find(c => c.id === selectedConversationId) || supportConversations[0] || null;
  }, [supportConversations, selectedConversationId]);

  // Overall statistics
  const stats = useMemo(() => {
    const total = supportConversations.length;
    const unread = supportConversations.reduce((acc, c) => acc + (c.unreadCount || 0), 0);
    const aiHandling = supportConversations.filter(c => c.status === 'open').length;
    const humanHandling = supportConversations.filter(c => c.status === 'human_mode').length;
    const resolved = supportConversations.filter(c => c.status === 'resolved').length;
    const highPriority = supportConversations.filter(c => c.priority === 'urgent' || c.priority === 'high').length;
    const aiAutomationRate = total > 0 ? Math.round((aiHandling / total) * 100) : 75;

    return { total, unread, aiHandling, humanHandling, resolved, highPriority, aiAutomationRate };
  }, [supportConversations]);

  return (
    <div id="customer-support-hub-container" className="flex flex-col h-[calc(100vh-4rem)] max-h-[calc(100vh-4rem)] overflow-hidden bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100">
      {/* Top Application Bar */}
      <header id="support-hub-header" className="flex-shrink-0 h-14 px-4 sm:px-6 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-4 z-20">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
            <MessageSquareText className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-base font-semibold text-neutral-900 dark:text-neutral-50 leading-none">
                Customer Support
              </h1>
              <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-indigo-100/80 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 rounded-full border border-indigo-200 dark:border-indigo-800">
                <Sparkles className="w-3 h-3" />
                Omnichannel Inbox
              </span>
            </div>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 hidden sm:block">
              Multi-channel customer inbox, WhatsApp, social tickets & AI triage assist
            </p>
          </div>
        </div>

        {/* Header Right Controls */}
        <div className="flex items-center gap-2.5">
          <button
            onClick={() => setCurrentSection('ai-auto-reply')}
            className="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-colors"
          >
            <Bot className="w-3.5 h-3.5" />
            <span>AI Auto-Reply Studio</span>
          </button>

          {/* Context Panel Toggle */}
          <button
            id="toggle-context-panel-btn"
            onClick={() => setIsContextPanelOpen(!isContextPanelOpen)}
            title={isContextPanelOpen ? 'Hide Customer Context' : 'Show Customer Context'}
            className={`px-2.5 py-1.5 rounded-lg border text-xs transition-colors hidden xl:flex items-center gap-1.5 ${
              isContextPanelOpen
                ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200'
                : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
            }`}
          >
            {isContextPanelOpen ? (
              <>
                <PanelRightClose className="w-4 h-4" />
                <span className="text-xs font-medium">Context</span>
              </>
            ) : (
              <>
                <PanelRight className="w-4 h-4" />
                <span className="text-xs font-medium">Context</span>
              </>
            )}
          </button>
        </div>
      </header>

      {/* Main Omnichannel Inbox Workspace */}
      <main id="support-hub-main" className="flex-1 flex overflow-hidden">
        <div className="flex-1 flex w-full h-full overflow-hidden">
          {/* Panel 1: Conversation Queue List */}
          <div
            className={`w-full md:w-80 lg:w-96 flex-shrink-0 flex flex-col border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 ${
              isMobileListOpen ? 'flex' : 'hidden md:flex'
            }`}
          >
            <ConversationListPanel
              selectedChannel={selectedChannel}
              setSelectedChannel={setSelectedChannel}
              statusFilter={statusFilter}
              setStatusFilter={setStatusFilter}
              searchQuery={searchQuery}
              setSearchQuery={setSearchQuery}
              onSelectConversation={(id) => {
                setSelectedConversationId(id);
                setIsMobileListOpen(false);
              }}
              stats={stats}
            />
          </div>

          {/* Panel 2: Live Conversation & AI Workspace */}
          <div
            className={`flex-1 flex flex-col bg-neutral-50 dark:bg-neutral-950 overflow-hidden ${
              !isMobileListOpen ? 'flex' : 'hidden md:flex'
            }`}
          >
            {activeConversation ? (
              <ChatWorkspacePanel
                conversation={activeConversation}
                onBackToList={() => setIsMobileListOpen(true)}
                isContextOpen={isContextPanelOpen}
                onToggleContext={() => setIsContextPanelOpen(!isContextPanelOpen)}
              />
            ) : (
              <div className="flex-1 flex flex-col items-center justify-center p-8 text-center text-neutral-400">
                <Inbox className="w-12 h-12 stroke-[1.2] mb-3 text-neutral-300 dark:text-neutral-700" />
                <p className="text-sm font-medium text-neutral-600 dark:text-neutral-400">
                  No conversation selected
                </p>
                <p className="text-xs text-neutral-400 dark:text-neutral-600 mt-1 max-w-xs">
                  Choose a customer inquiry from the left queue to review channel messages and AI drafts.
                </p>
              </div>
            )}
          </div>

          {/* Panel 3: Collapsible Customer Context & CRM Insights */}
          {isContextPanelOpen && activeConversation && (
            <div className="hidden xl:flex w-84 2xl:w-96 flex-shrink-0 flex-col border-l border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-y-auto">
              <CustomerContextPanel conversation={activeConversation} />
            </div>
          )}
        </div>
      </main>
    </div>
  );
};
