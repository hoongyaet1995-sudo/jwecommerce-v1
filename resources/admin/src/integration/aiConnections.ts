import { useEffect, useRef, useState } from 'react';

export type AIProvider = 'openai' | 'gemini' | 'anthropic' | 'compatible';
export const providerLabels: Record<AIProvider, string> = { openai: 'OpenAI', gemini: 'Google Gemini', anthropic: 'Anthropic', compatible: 'OpenAI-compatible' };
export interface AIConnection {
  provider: AIProvider; label: string; has_key: boolean; base_url: string | null;
  enabled: boolean; support_enabled: boolean; builder_enabled: boolean; search_enabled?: boolean;
  text_model: string | null; image_model: string | null;
  models: { id: string; text: boolean; image: boolean }[];
  verified_at: string | null; last_error: string | null; version: number;
}
export async function aiRequest(path = '', method = 'GET', body?: unknown) {
  const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
  const response = await fetch('/admin/api/integration/ai' + path, {
    method, credentials: 'same-origin', cache: 'no-store',
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await response.json().catch(() => null);
  if (!response.ok || !data) {
    const validation = data?.errors ? Object.values(data.errors).flat()[0] : null;
    throw new Error(typeof validation === 'string' ? validation : data?.message || 'Unable to load AI settings. Reload and try again.');
  }
  return data as { connections?: AIConnection[]; connection?: AIConnection; compatible_base_urls?: string[] };
}
export function useAIConnections() {
  const [connections, setConnections] = useState<AIConnection[]>([]);
  const [endpoints, setEndpoints] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const live = useRef(true);
  async function reload() {
    try {
      const data = await aiRequest();
      if (live.current) { setConnections(data.connections || []); setEndpoints(data.compatible_base_urls || []); setError(''); }
    } catch (failure) { if (live.current) setError((failure as Error).message); }
    finally { if (live.current) setLoading(false); }
  }
  useEffect(() => { live.current = true; void reload(); return () => { live.current = false; }; }, []);
  return { connections, endpoints, loading, error, reload };
}

export async function verifyAISettings(provider: AIProvider, version: number, draft?: Record<string, unknown>, onSaved?: () => void) {
  if (draft) {
    const saved = await aiRequest('/' + provider, 'PUT', { ...draft, enabled: false, version });
    if (!saved.connection) throw new Error('Unable to confirm saved settings. Reload before verifying.');
    version = saved.connection.version;
    onSaved?.();
  }
  return aiRequest('/' + provider + '/verify', 'POST', { version });
}
