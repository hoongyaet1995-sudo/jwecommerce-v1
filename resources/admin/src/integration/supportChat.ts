import { useCallback, useEffect, useRef, useState } from 'react';
import type { SupportConversation } from '../types/support';

export async function supportRequest(path = '', method = 'GET', body?: unknown, signal?: AbortSignal) {
  const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
  const response = await fetch('/admin/api/support/conversations' + path, {
    method, credentials: 'same-origin', signal,
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok || !payload) throw new Error(payload?.message || 'Unable to connect to Support. Please try again.');
  return payload as { syncedAt?: string; conversations?: SupportConversation[]; conversation?: SupportConversation };
}

export function useSupportChat(active: boolean) {
  const [supportConversations, setConversations] = useState<SupportConversation[]>([]);
  const [selectedConversationId, setSelectedConversationId] = useState<string | null>(null);
  const [supportDrafts, setDrafts] = useState<Record<string, string>>({});
  const [supportLoading, setLoading] = useState(true);
  const [supportError, setError] = useState('');
  const [supportBusy, setBusy] = useState(false);
  const revision = useRef(0), busy = useRef(false);
  const syncedAt = useRef('');
  const retries = useRef<Record<string, { content: string; id: string }>>({});
  const setSupportDraft = (id: string, text: string) => setDrafts(previous => ({ ...previous, [id]: text }));
  const refreshSupport = useCallback(async (signal?: AbortSignal) => {
    const version = revision.current;
    try {
      const incremental = !!syncedAt.current;
      const data = await supportRequest(incremental ? '?since=' + encodeURIComponent(syncedAt.current) : '', 'GET', undefined, signal);
      if (version !== revision.current || busy.current || signal?.aborted) return;
      setConversations(previous => {
        if (!incremental) return data.conversations || [];
        const merged = new Map(previous.map(c => [c.id, c]));
        for (const conversation of data.conversations || []) merged.set(conversation.id, conversation);
        return [...merged.values()];
      });
      setSelectedConversationId(current => current || data.conversations?.find(c => c.status !== 'resolved')?.id || null);
      syncedAt.current = data.syncedAt || '';
      setError('');
    } catch (error) {
      if (!signal?.aborted) setError(error instanceof Error ? error.message : 'Unable to load conversations.');
    } finally { if (!signal?.aborted) setLoading(false); }
  }, []);
  useEffect(() => {
    if (!active) return;
    const controller = new AbortController();
    let timer: ReturnType<typeof setTimeout>;
    const poll = async () => {
      if (!document.hidden) await refreshSupport(controller.signal);
      if (!controller.signal.aborted) timer = setTimeout(poll, 4000);
    };
    void poll();
    return () => { controller.abort(); clearTimeout(timer); };
  }, [active, refreshSupport]);
  const mutate = async (id: string, action: string, data: unknown, method = 'POST') => {
    if (busy.current) return false;
    busy.current = true; setBusy(true); revision.current++;
    try {
      const result = await supportRequest(`/${id}/${action}`, method, data);
      if (result.conversation) setConversations(previous => previous.map(c => c.id === id ? result.conversation! : c));
      setError(''); return true;
    } catch (error) { setError(error instanceof Error ? error.message : 'Unable to save Support changes.'); return false; }
    finally { revision.current++; busy.current = false; setBusy(false); }
  };
  const sendMessageToConversation = async (id: string, content: string) => {
    if (!retries.current[id] || retries.current[id].content !== content) retries.current[id] = { content, id: crypto.randomUUID() };
    const ok = await mutate(id, 'messages', { content, client_message_id: retries.current[id].id });
    if (ok) delete retries.current[id];
    return ok;
  };
  const markConversationAsRead = (id: string) => {
    const conversation = supportConversations.find(c => c.id === id);
    if (!conversation?.unreadCount) return;
    void mutate(id, 'read', { through_message_id: Number(conversation.messages.at(-1)?.id || 0) });
  };
  return {
    supportConversations, supportDrafts, setSupportDraft, selectedConversationId, setSelectedConversationId,
    supportLoading, supportError, supportBusy, refreshSupport, sendMessageToConversation,
    resolveConversation: (id: string) => { void mutate(id, 'status', { status: 'resolved' }, 'PATCH'); },
    reopenConversation: (id: string) => { void mutate(id, 'status', { status: 'open' }, 'PATCH'); },
    markConversationAsRead,
    addStaffNote: (id: string, content: string) => mutate(id, 'notes', { content }),
  };
}
