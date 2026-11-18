// Share pending reads only. Settled results and failures are never cached.
const pending = new Map<string, Promise<unknown>>();
export function invalidateSettingsRead(key: string): void { pending.delete(key); }
export function inFlightSettingsRead<T>(key: string, load: () => Promise<T>): Promise<T> {
  const existing = pending.get(key);
  if (existing) return existing as Promise<T>;
  const request = load();
  pending.set(key, request);
  const clear = () => { if (pending.get(key) === request) pending.delete(key); };
  void request.then(clear, clear);
  return request;
}
