export const SOCIAL_PLATFORMS = [
  { key: 'facebook', label: 'Facebook', usernameHint: 'yourpage' },
  { key: 'instagram', label: 'Instagram', usernameHint: '@yourbrand' },
  { key: 'tiktok', label: 'TikTok', usernameHint: '@yourbrand' },
  { key: 'youtube', label: 'YouTube', usernameHint: '@yourchannel' },
  { key: 'linkedin', label: 'LinkedIn', usernameHint: 'yourprofile' },
  { key: 'x', label: 'X', usernameHint: '@yourbrand' },
  { key: 'whatsapp', label: 'WhatsApp', usernameHint: '60123456789' },
] as const;

export type SocialPlatformKey = (typeof SOCIAL_PLATFORMS)[number]['key'];

export type SocialPlatformConfiguration = {
  enabled: boolean;
  value: string;
};

export type SocialIconsConfiguration = {
  iconStyle: 'brand' | 'dark' | 'white';
  iconType: 'square' | 'circle' | 'plain';
  customColor: string | null;
  iconSize: number;
  spacing: number;
  alignment: 'left' | 'center' | 'right';
  platforms: Record<SocialPlatformKey, SocialPlatformConfiguration>;
};

type TXeniboxEditorWindow = Window & {
  xeniboxEmailEditorConfig?: {
    socialIconBaseUrl?: string;
    socialIconColorUrl?: string;
  };
};

const SOCIAL_MARKER_SELECTOR = '[data-xenibox-social-icons="1"]';

export function createDefaultSocialIconsConfiguration(): SocialIconsConfiguration {
  return {
    iconStyle: 'brand',
    iconType: 'square',
    customColor: null,
    iconSize: 32,
    spacing: 10,
    alignment: 'center',
    platforms: SOCIAL_PLATFORMS.reduce(
      (platforms, platform) => ({
        ...platforms,
        [platform.key]: { enabled: true, value: '' },
      }),
      {} as Record<SocialPlatformKey, SocialPlatformConfiguration>
    ),
  };
}

export function createDefaultSocialIconsHtml() {
  return buildSocialIconsHtml(createDefaultSocialIconsConfiguration());
}

export function parseSocialIconsHtml(contents: string | null | undefined): SocialIconsConfiguration | null {
  if (!contents || contents.indexOf('data-xenibox-social-icons="1"') === -1) {
    return null;
  }

  const template = document.createElement('template');
  template.innerHTML = contents;
  const marker = template.content.querySelector<HTMLElement>(SOCIAL_MARKER_SELECTOR);

  if (!marker) {
    return null;
  }

  const defaults = createDefaultSocialIconsConfiguration();
  const iconStyle = ['brand', 'dark', 'white'].includes(marker.dataset.iconStyle ?? '')
    ? (marker.dataset.iconStyle as SocialIconsConfiguration['iconStyle'])
    : defaults.iconStyle;
  const iconType = ['square', 'circle', 'plain'].includes(marker.dataset.iconType ?? '')
    ? (marker.dataset.iconType as SocialIconsConfiguration['iconType'])
    : defaults.iconType;
  const customColor = /^#[0-9A-Fa-f]{6}$/.test(marker.dataset.customColor ?? '')
    ? (marker.dataset.customColor as string).toUpperCase()
    : null;
  const alignment = ['left', 'center', 'right'].includes(marker.dataset.alignment ?? '')
    ? (marker.dataset.alignment as SocialIconsConfiguration['alignment'])
    : defaults.alignment;
  const iconSize = clampNumber(marker.dataset.iconSize, 20, 48, defaults.iconSize);
  const spacing = clampNumber(marker.dataset.spacing, 0, 24, defaults.spacing);
  const platforms = { ...defaults.platforms };

  SOCIAL_PLATFORMS.forEach((platform) => {
    platforms[platform.key] = {
      enabled: marker.dataset[`${platform.key}Enabled`] !== '0',
      value: marker.dataset[`${platform.key}Value`] ?? '',
    };
  });

  return { iconStyle, iconType, customColor, iconSize, spacing, alignment, platforms };
}

export function buildSocialIconsHtml(configuration: SocialIconsConfiguration) {
  const iconBaseUrl = getSocialIconBaseUrl();
  const marginStyle = configuration.alignment === 'left'
    ? 'margin-right:auto;'
    : configuration.alignment === 'right'
      ? 'margin-left:auto;'
      : 'margin-left:auto;margin-right:auto;';
  const markerAttributes = [
    'data-xenibox-social-icons="1"',
    `data-icon-style="${configuration.iconStyle}"`,
    `data-icon-type="${configuration.iconType}"`,
    `data-custom-color="${configuration.customColor ?? ''}"`,
    `data-icon-size="${configuration.iconSize}"`,
    `data-spacing="${configuration.spacing}"`,
    `data-alignment="${configuration.alignment}"`,
    ...SOCIAL_PLATFORMS.flatMap((platform) => {
      const platformConfiguration = configuration.platforms[platform.key];
      return [
        `data-${platform.key}-enabled="${platformConfiguration.enabled ? '1' : '0'}"`,
        `data-${platform.key}-value="${escapeHtmlAttribute(platformConfiguration.value)}"`,
      ];
    }),
  ].join(' ');
  const cells = SOCIAL_PLATFORMS.filter((platform) => configuration.platforms[platform.key].enabled)
    .map((platform) => {
      const platformConfiguration = configuration.platforms[platform.key];
      const profileUrl = createSocialProfileUrl(platform.key, platformConfiguration.value);
      const imageUrl = getSocialIconImageUrl(iconBaseUrl, platform.key, configuration);
      const image = `<img src="${escapeHtmlAttribute(imageUrl)}" width="${configuration.iconSize}" height="${configuration.iconSize}" alt="${platform.label}" style="display:block;width:${configuration.iconSize}px;height:${configuration.iconSize}px;border:0;outline:none;text-decoration:none;">`;
      const content = profileUrl === ''
        ? image
        : `<a href="${escapeHtmlAttribute(profileUrl)}" target="_blank" title="${platform.label}" style="display:inline-block;text-decoration:none;">${image}</a>`;
      const sidePadding = configuration.spacing / 2;

      return `<td align="center" valign="middle" style="padding-left:${sidePadding}px;padding-right:${sidePadding}px;">${content}</td>`;
    })
    .join('');

  return `<table ${markerAttributes} role="presentation" align="${configuration.alignment}" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;${marginStyle}"><tbody><tr>${cells}</tr></tbody></table>`;
}

export function createSocialProfileUrl(platform: SocialPlatformKey, value: string) {
  const cleanValue = value.trim();

  if (cleanValue === '') {
    return '';
  }

  if (/^https?:\/\//i.test(cleanValue)) {
    return cleanValue;
  }

  const username = cleanValue.replace(/^@/, '').replace(/^\/+|\/+$/g, '');

  switch (platform) {
    case 'facebook':
      return `https://www.facebook.com/${encodeURIComponent(username)}`;
    case 'instagram':
      return `https://www.instagram.com/${encodeURIComponent(username)}`;
    case 'tiktok':
      return `https://www.tiktok.com/@${encodeURIComponent(username)}`;
    case 'youtube':
      return `https://www.youtube.com/@${encodeURIComponent(username)}`;
    case 'linkedin':
      return `https://www.linkedin.com/in/${encodeURIComponent(username)}`;
    case 'x':
      return `https://x.com/${encodeURIComponent(username)}`;
    case 'whatsapp': {
      const phoneNumber = cleanValue.replace(/[^0-9]/g, '');
      return phoneNumber === '' ? '' : `https://wa.me/${phoneNumber}`;
    }
  }
}

function getSocialIconBaseUrl() {
  const configuration = (window as TXeniboxEditorWindow).xeniboxEmailEditorConfig;
  return (configuration?.socialIconBaseUrl ?? '/assets/images/social').replace(/\/$/, '');
}

function getSocialIconFileName(platform: SocialPlatformKey, iconType: SocialIconsConfiguration['iconType']) {
  if (iconType === 'circle') {
    return `${platform}-circle.png`;
  }

  if (iconType === 'plain') {
    return `${platform}-plain.png`;
  }

  return `${platform}.png`;
}

function getSocialIconImageUrl(
  iconBaseUrl: string,
  platform: SocialPlatformKey,
  configuration: SocialIconsConfiguration
) {
  if (configuration.iconType === 'plain' && configuration.customColor) {
    const color = configuration.customColor.replace('#', '').toUpperCase();
    return `${getSocialIconColorUrl()}/${platform}/${color}.png`;
  }

  return `${iconBaseUrl}/${configuration.iconStyle}/${getSocialIconFileName(platform, configuration.iconType)}`;
}

function getSocialIconColorUrl() {
  const configuration = (window as TXeniboxEditorWindow).xeniboxEmailEditorConfig;
  return (configuration?.socialIconColorUrl ?? '/assets/social-icon').replace(/\/$/, '');
}

function clampNumber(value: string | undefined, minimum: number, maximum: number, fallback: number) {
  const number = Number(value);
  return Number.isFinite(number) ? Math.min(maximum, Math.max(minimum, number)) : fallback;
}

function escapeHtmlAttribute(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
