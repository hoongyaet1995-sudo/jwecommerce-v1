import type { ShopGeneralSettings, LaravelAdminBootstrap } from '../types';

export async function loadShopBranding(): Promise<Partial<ShopGeneralSettings>> {
  const response = await fetch('/admin/api/shop-branding', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  if (!response.ok) throw new Error('Could not load the saved shop profile. Please reload before saving.');
  return (await response.json()).data;
}

async function rasterize(source: string): Promise<{logoPng: string; iconPng: string}> {
  const image = new Image();
  image.crossOrigin = 'anonymous';
  await new Promise<void>((resolve, reject) => {
    image.onload = () => resolve();
    image.onerror = () => reject(new Error('Cannot read this image. For an external URL, download the image and upload it here.'));
    image.src = source;
  });
  if (!image.naturalWidth || !image.naturalHeight || image.naturalWidth * image.naturalHeight > 40000000) throw new Error('Please use an image smaller than 40 megapixels.');
  const render = (size: number, square: boolean) => {
    const scale = Math.min(size / image.naturalWidth, size / image.naturalHeight, 1);
    const width = Math.max(1, Math.round(image.naturalWidth * scale));
    const height = Math.max(1, Math.round(image.naturalHeight * scale));
    const canvas = document.createElement('canvas');
    canvas.width = square ? size : width;
    canvas.height = square ? size : height;
    canvas.getContext('2d')!.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
    return canvas.toDataURL('image/png');
  };
  try { return { logoPng: render(1024, false), iconPng: render(256, true) }; }
  catch { throw new Error('This image URL does not permit conversion. Please upload the image file instead.'); }
}

export async function persistShopBranding(bootstrap: LaravelAdminBootstrap, profile: ShopGeneralSettings, previousLogo: string): Promise<Partial<ShopGeneralSettings>> {
  const images = profile.shopLogo && profile.shopLogo !== previousLogo ? await rasterize(profile.shopLogo) : {};
  const response = await fetch('/admin/api/shop-branding', {
    method: 'PUT', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify({ profile: { ...profile, shopLogo: undefined }, ...images }),
  });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Could not save the shop profile.');
  if (payload.data.faviconUrl) {
    let icon = document.querySelector<HTMLLinkElement>('#shop-favicon');
    if (!icon) { icon = document.createElement('link'); icon.id = 'shop-favicon'; icon.rel = 'icon'; document.head.appendChild(icon); }
    icon.type = 'image/x-icon'; icon.href = payload.data.faviconUrl;
  }
  return payload.data;
}
