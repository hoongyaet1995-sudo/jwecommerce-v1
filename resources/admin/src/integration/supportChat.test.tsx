// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useSupportChat } from './supportChat';
import type { SupportConversation } from '../types/support';

const conversation = { id: 'real-thread', customer: { name: 'Guest' }, unreadCount: 0, messages: [], internalNotes: [], status: 'open' } as unknown as SupportConversation;
const response = (payload: unknown, ok = true) => ({ ok, json: async () => payload }) as Response;
let state: ReturnType<typeof useSupportChat>, root: Root;
const mockFetch = vi.fn();
function Harness() { state = useSupportChat(false); return null; }
beforeEach(async () => {
  vi.stubGlobal('fetch', mockFetch); mockFetch.mockReset();
  (globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;
  document.head.innerHTML = '<meta name="csrf-token" content="test-csrf">';
  root = createRoot(document.createElement('div'));
  await act(async () => root.render(<Harness />));
});
afterEach(async () => { await act(async () => root.unmount()); vi.unstubAllGlobals(); });

describe('real Support state ownership', () => {
  it('loads only server conversations and merges incremental updates without losing unchanged threads', async () => {
    mockFetch.mockResolvedValueOnce(response({ conversations: [conversation], syncedAt: '2026-10-02T00:00:00Z' }));
    await act(async () => { await state.refreshSupport(); });
    expect(state.supportConversations[0].customer.name).toBe('Guest');
    mockFetch.mockResolvedValueOnce(response({ conversations: [], syncedAt: '2026-10-02T00:00:04Z' }));
    await act(async () => { await state.refreshSupport(); });
    expect(state.supportConversations).toHaveLength(1);
    expect(mockFetch.mock.calls[1][0]).toContain('?since=');
  });
  it('preserves drafts on failure and reuses message UUID on a retry', async () => {
    mockFetch.mockResolvedValueOnce(response({ conversations: [conversation] }));
    await act(async () => { await state.refreshSupport(); state.setSupportDraft(conversation.id, 'My reply'); });
    mockFetch.mockResolvedValueOnce(response({ message: 'Save failed' }, false));
    let saved = true;
    await act(async () => { saved = await state.sendMessageToConversation(conversation.id, 'My reply'); });
    expect(saved).toBe(false); expect(state.supportDrafts[conversation.id]).toBe('My reply');
    expect(state.supportError).toBe('Save failed');
    const first = JSON.parse(mockFetch.mock.calls[1][1].body);
    mockFetch.mockResolvedValueOnce(response({ conversation }));
    await act(async () => { saved = await state.sendMessageToConversation(conversation.id, 'My reply'); });
    expect(saved).toBe(true);
    const retry = JSON.parse(mockFetch.mock.calls[2][1].body);
    expect(retry.client_message_id).toBe(first.client_message_id);
    expect(mockFetch.mock.calls[2][1].headers['X-CSRF-TOKEN']).toBe('test-csrf');
  });
  it('ignores a stale poll response that arrives after a saved mutation', async () => {
    mockFetch.mockResolvedValueOnce(response({ conversations: [conversation] }));
    await act(async () => { await state.refreshSupport(); });
    let finish: (value: Response) => void = () => {};
    mockFetch.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
    const poll = state.refreshSupport();
    const updated = { ...conversation, snippet: 'Saved reply' };
    mockFetch.mockResolvedValueOnce(response({ conversation: updated }));
    await act(async () => { await state.sendMessageToConversation(conversation.id, 'Saved reply'); });
    await act(async () => { finish(response({ conversations: [conversation] })); await poll; });
    expect(state.supportConversations[0].snippet).toBe('Saved reply');
  });
});
