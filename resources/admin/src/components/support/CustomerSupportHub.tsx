import React, { useState, useMemo, useEffect, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  MessageSquareText,
  Sparkles,
  Inbox,
  PanelRight,
  PanelRightClose,
  Bot,
  Sliders
  , ArrowLeft, Sun, Moon
} from 'lucide-react';
import { ConversationListPanel } from './ConversationListPanel';
import { ChatWorkspacePanel } from './ChatWorkspacePanel';
import { CustomerContextPanel } from './CustomerContextPanel';
import { SupportChannel, ConversationStatus } from '../../types/support';
import { sectionFromPath } from '../../integration/adminRoutes';

export const CustomerSupportHub: React.FC = () => {
  const {
    supportConversations, supportLoading, supportError,
    selectedConversationId,
    setSelectedConversationId,
    setCurrentSection,
    theme, toggleTheme,
  } = useAdmin();

  // Filter States
  const [selectedChannel, setSelectedChannel] = useState<SupportChannel | 'all'>('all');
  const [statusFilter, setStatusFilter] = useState<ConversationStatus | 'all' | 'unread' | 'high_priority'>(()=>new URLSearchParams(window.location.search).get('filter')==='human'?'human_mode':'open');
  const [searchQuery, setSearchQuery] = useState('');
  const [isContextPanelOpen, setIsContextPanelOpen] = useState(false);
  const [isMobileListOpen, setIsMobileListOpen] = useState(true);

  // Active conversation object
  const activeConversation = useMemo(() => {
    return supportConversations.find(c => c.id === selectedConversationId) || null;
  }, [supportConversations, selectedConversationId]);

  const notificationSelectionApplied=useRef(false);
  useEffect(() => {
    if (supportLoading) return;
    if (!notificationSelectionApplied.current) {
      notificationSelectionApplied.current=true;
      const target=supportConversations.find(c=>c.id===new URLSearchParams(window.location.search).get('conversation'));
      if(target){setSelectedConversationId(target.id);setStatusFilter(target.status==='resolved'?'resolved':target.status==='open'?'open':'human_mode');return;}
    }
    const matches = supportConversations.filter(c => (selectedChannel==='all'||c.channel===selectedChannel) && (!searchQuery.trim()||[c.customer.name,c.customer.phone,c.customer.email,c.snippet,...c.messages.map(m=>m.content)].some(v=>v?.toLowerCase().includes(searchQuery.trim().toLowerCase()))) && (statusFilter === 'archived' ? c.isArchived : !c.isArchived && (statusFilter === 'resolved' ? c.status === 'resolved' : statusFilter === 'human_mode' ? ['waiting_human','human_mode'].includes(c.status) : c.status === 'open')));
    if (!matches.some(c => c.id === selectedConversationId)) setSelectedConversationId(matches[0]?.id || null);
  }, [supportConversations, supportLoading, selectedConversationId, statusFilter, selectedChannel, searchQuery, setSelectedConversationId]);

  // Overall statistics
  const stats = useMemo(() => {
    const total = supportConversations.length;
    const unread = supportConversations.reduce((acc, c) => acc + (c.unreadCount || 0), 0);
    const aiHandling = supportConversations.filter(c => c.status === 'open').length;
    const humanHandling = supportConversations.filter(c => ['waiting_human','human_mode'].includes(c.status)).length;
    const resolved = supportConversations.filter(c => c.status === 'resolved').length;
    const highPriority = supportConversations.filter(c => c.priority === 'urgent' || c.priority === 'high').length;
    const aiAutomationRate = total > 0 ? Math.round((aiHandling / total) * 100) : 75;

    return { total, unread, aiHandling, humanHandling, resolved, highPriority, aiAutomationRate };
  }, [supportConversations]);

  return (
    <div id="customer-support-hub-container" className="flex flex-col h-full min-h-0 overflow-hidden bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100">
      {/* Top Application Bar */}
      <header id="support-hub-header" className="flex-shrink-0 h-14 px-3 sm:px-6 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-2 sm:gap-4 z-20">
        <div className="flex items-center gap-2 sm:gap-3 min-w-0">
          <button id="btn-support-back" aria-label="Return to previous page" title="Return to previous page"
            onClick={() => {
              const returnPath = window.history.state?.supportReturnPath;
              if (typeof returnPath === 'string' && sectionFromPath(returnPath) && sectionFromPath(returnPath) !== 'support' && window.history.length > 1) {
                window.history.back();
                return;
              }
              const previous = document.referrer ? new URL(document.referrer) : null;
              if (previous?.origin === window.location.origin && window.history.length > 1) window.history.back();
              else window.location.assign('/admin');
            }}
            className="p-1.5 rounded-lg text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100 hover:bg-neutral-100 dark:hover:bg-neutral-800">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div className="hidden sm:block p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
            <MessageSquareText className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-sm sm:text-base font-semibold text-neutral-900 dark:text-neutral-50 leading-none truncate">
                Customer Support
              </h1>
            </div>
            <p role={supportError ? "alert" : "status"} className={"text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 " + (supportError ? "" : "hidden sm:block")}>
              {supportError || (supportLoading ? 'Loading conversations…' : 'Support inbox')}
            </p>
          </div>
        </div>

        {/* Header Right Controls */}
        <div className="flex items-center gap-1.5 sm:gap-2.5 flex-shrink-0">
          <div className="flex items-center p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700/80 shadow-2xs">
            <button id="btn-theme-light" aria-label="Light Mode" title="Switch to Light Mode" aria-pressed={theme === 'light'} onClick={() => { if (theme !== 'light') toggleTheme(); }}
              className={'p-1.5 px-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all '+(theme === 'light' ? 'bg-white text-amber-600 shadow-xs ring-1 ring-neutral-200/80' : 'text-neutral-500 hover:text-neutral-800')}>
              <Sun className="w-3.5 h-3.5" /><span className="hidden lg:inline text-[11px]">Light</span>
            </button>
            <button id="btn-theme-dark" aria-label="Dark Mode" title="Switch to Dark Mode" aria-pressed={theme === 'dark'} onClick={() => { if (theme !== 'dark') toggleTheme(); }}
              className={'p-1.5 px-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all '+(theme === 'dark' ? 'bg-neutral-950 text-indigo-300 shadow-xs ring-1 ring-neutral-700' : 'text-neutral-500 hover:text-neutral-800')}>
              <Moon className="w-3.5 h-3.5" /><span className="hidden lg:inline text-[11px]">Dark</span>
            </button>
          </div>

          {/* Context Panel Toggle */}
          <button
            id="toggle-context-panel-btn"
            aria-label="Customer details" aria-expanded={isContextPanelOpen} aria-controls="customer-context-panel"
            onClick={() => setIsContextPanelOpen(!isContextPanelOpen)}
            title={isContextPanelOpen ? 'Hide Customer Context' : 'Show Customer Context'}
            className={`px-2.5 py-1.5 rounded-lg border text-xs transition-colors flex items-center gap-1.5 ${
              isContextPanelOpen
                ? 'bg-neutral-100 dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200'
                : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
            }`}
          >
            {isContextPanelOpen ? (
              <>
                <PanelRightClose className="w-4 h-4" />
                <span className="text-xs font-medium hidden sm:inline">Customer details</span>
              </>
            ) : (
              <>
                <PanelRight className="w-4 h-4" />
                <span className="text-xs font-medium hidden sm:inline">Customer details</span>
              </>
            )}
          </button>
        </div>
      </header>

      {/* Main Omnichannel Inbox Workspace */}
      <main id="support-hub-main" className="flex-1 flex overflow-hidden">
        <div className="relative flex-1 flex w-full h-full min-h-0 overflow-hidden">
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
              setStatusFilter={(status) => {
                setStatusFilter(status);
                const matches = supportConversations.filter(c => status === 'archived' ? c.isArchived : !c.isArchived && (status === 'resolved' ? c.status === 'resolved' : status === 'human_mode' ? ['waiting_human','human_mode'].includes(c.status) : c.status === 'open'));
                if (!matches.some(c => c.id === selectedConversationId)) setSelectedConversationId(matches[0]?.id || null);
              }}
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
                  Choose a conversation to read and reply.
                </p>
              </div>
            )}
          </div>

          {/* Panel 3: Collapsible Customer Context & CRM Insights */}
          {isContextPanelOpen && activeConversation && (
            <div className="absolute inset-y-0 right-0 z-30 w-80 max-w-full xl:static xl:w-80 flex flex-shrink-0 flex-col border-l border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-y-auto shadow-xl xl:shadow-none">
              <CustomerContextPanel key={activeConversation.id} conversation={activeConversation} onClose={() => setIsContextPanelOpen(false)} />
            </div>
          )}
        </div>
      </main>
    </div>
  );
};


