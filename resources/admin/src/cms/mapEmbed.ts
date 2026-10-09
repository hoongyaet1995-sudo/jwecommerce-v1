/** Accept Google Maps Copy HTML as data; never insert the supplied markup. */
export function safeMapEmbed(value?: string): string | undefined {
  try {
    const input = (value ?? '').trim();
    const source = input.startsWith('<')
      ? input.match(/<iframe\b[^>]*?\s+src\s*=\s*(["'])(.*?)\1/is)?.[2]
      : input;
    if (!source) return undefined;
    const url = new URL(source.replace(/&amp;/gi, '&'));
    return url.protocol === 'https:' && !url.username && !url.password &&
      ['www.google.com', 'maps.google.com'].includes(url.hostname) &&
      (url.pathname === '/maps/embed' || url.pathname.startsWith('/maps/embed/'))
      ? url.href : undefined;
  } catch { return undefined; }
}
