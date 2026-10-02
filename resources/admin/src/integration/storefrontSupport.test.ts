// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, expect, it, vi } from 'vitest';

afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); document.body.innerHTML = ''; });

it('keeps an empty support welcome at the top when polling', async () => {
  vi.useFakeTimers();
  const component = readFileSync(resolve('../views/components/storefront/support-chat.blade.php'), 'utf8');
  document.body.innerHTML = component;
  const stream = document.querySelector<HTMLElement>('#sf-support-stream')!;
  Object.defineProperty(stream, 'scrollHeight', { value: 500 });
  Object.defineProperty(stream, 'clientHeight', { value: 450 });
  const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ conversation: null }) });
  vi.stubGlobal('fetch', fetchMock);
  vi.stubGlobal('matchMedia', () => ({ matches: false }));
  const script = component.match(/<script>([\s\S]*?)<\/script>/)![1];
  new Function(script)();
  document.querySelector<HTMLButtonElement>('#sf-support-launcher')!.click();
  await vi.advanceTimersByTimeAsync(0);
  expect(fetchMock).toHaveBeenCalledTimes(1);
  expect(stream.scrollTop).toBe(0);
  await vi.advanceTimersByTimeAsync(4000);
  expect(fetchMock).toHaveBeenCalledTimes(2);
  expect(stream.scrollTop).toBe(0);
  document.querySelector<HTMLButtonElement>('#sf-support-close')!.click();
});
