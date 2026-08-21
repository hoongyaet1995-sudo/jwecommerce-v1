import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { SocialMediaLinks } from '../../types';
import { INITIAL_SOCIAL_LINKS } from '../../data/mockData';
import {
  Info,
  Facebook,
  Instagram,
  Youtube,
  Linkedin,
  Twitter,
  ExternalLink,
  Copy,
  CheckCircle2,
  Save,
  RotateCcw,
  Sparkles,
  Share2,
  Globe,
  MessageCircle,
  BookOpen,
  ArrowUpRight
} from 'lucide-react';

// Custom / Stylized TikTok Icon
const TikTokIcon: React.FC<{ className?: string }> = ({ className = "w-4 h-4" }) => (
  <svg className={className} viewBox="0 0 24 24" fill="currentColor">
    <path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-5.201 1.743l-.004-.007a2.894 2.894 0 0 1 2.31-4.646c.307 0 .604.05.882.14v-3.54a6.34 6.34 0 0 0-.882-.062C5.973 9.28 3 12.253 3 15.759 3 19.265 5.973 22 9.479 22c3.506 0 6.479-2.735 6.479-6.241V8.678a8.212 8.212 0 0 0 4.631 1.408V6.641a4.83 4.83 0 0 1-1-.055v.1z" />
  </svg>
);

// Custom / Stylized Xiaohongshu (RED) Icon
const XiaohongshuIcon: React.FC<{ className?: string }> = ({ className = "w-4 h-4" }) => (
  <svg className={className} viewBox="0 0 24 24" fill="currentColor">
    <path d="M19.5 3h-15A2.5 2.5 0 0 0 2 5.5v13A2.5 2.5 0 0 0 4.5 21h15a2.5 2.5 0 0 0 2.5-2.5v-13A2.5 2.5 0 0 0 19.5 3zm-3.8 13.7c-.5.4-1.2.6-2 .6-.6 0-1.1-.1-1.6-.4l-.4-.2c-.3-.2-.5-.4-.7-.7-.3.5-.7.9-1.2 1.1-.5.3-1.1.4-1.7.4-.9 0-1.6-.3-2.1-.8-.5-.5-.8-1.3-.8-2.2 0-1 .4-1.9 1.1-2.5.7-.6 1.7-.9 2.8-.9h1.7v-.9c0-.6-.1-1-.4-1.3-.3-.3-.8-.4-1.4-.4-.5 0-.9.1-1.3.3-.4.2-.6.5-.7.9l-1.9-.4c.2-.7.6-1.3 1.3-1.8.7-.5 1.5-.7 2.6-.7 1.3 0 2.3.3 3 1 .7.6 1.1 1.6 1.1 2.8v4.2c0 .6.1 1.1.3 1.4h-1.9zm-4.3-3.1h-1.6c-.6 0-1 .1-1.4.4-.3.3-.5.7-.5 1.2 0 .5.1.9.4 1.2.3.3.7.4 1.2.4.6 0 1.1-.2 1.4-.6.3-.4.5-.9.5-1.6v-1z" />
  </svg>
);

// Custom / Stylized WhatsApp Icon
const WhatsAppIcon: React.FC<{ className?: string }> = ({ className = "w-4 h-4" }) => (
  <svg className={className} viewBox="0 0 24 24" fill="currentColor">
    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c.01 4.54-3.69 8.23-8.22 8.23zm4.52-6.17c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.12.17 1.78 2.71 4.3 3.8.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.12-.22-.19-.47-.31z" />
  </svg>
);

// Custom / Stylized X (Twitter) Icon
const XTwitterIcon: React.FC<{ className?: string }> = ({ className = "w-4 h-4" }) => (
  <svg className={className} viewBox="0 0 24 24" fill="currentColor">
    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
  </svg>
);

export const SocialLinkSettingAdmin: React.FC = () => {
  const { shopSettings, saveShopSettings, addToast } = useAdmin();

  // Local state initialized from shopSettings.socialLinks or INITIAL_SOCIAL_LINKS
  const [socialLinks, setSocialLinks] = useState<SocialMediaLinks>({
    facebook: shopSettings.socialLinks?.facebook ?? INITIAL_SOCIAL_LINKS.facebook,
    instagram: shopSettings.socialLinks?.instagram ?? INITIAL_SOCIAL_LINKS.instagram,
    tiktok: shopSettings.socialLinks?.tiktok ?? INITIAL_SOCIAL_LINKS.tiktok,
    youtube: shopSettings.socialLinks?.youtube ?? INITIAL_SOCIAL_LINKS.youtube,
    linkedin: shopSettings.socialLinks?.linkedin ?? INITIAL_SOCIAL_LINKS.linkedin,
    twitter: shopSettings.socialLinks?.twitter ?? INITIAL_SOCIAL_LINKS.twitter,
    whatsapp: shopSettings.socialLinks?.whatsapp ?? INITIAL_SOCIAL_LINKS.whatsapp,
    xiaohongshu: shopSettings.socialLinks?.xiaohongshu ?? INITIAL_SOCIAL_LINKS.xiaohongshu,
  });

  const [copiedField, setCopiedField] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const handleInputChange = (field: keyof SocialMediaLinks, value: string) => {
    setSocialLinks(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSave = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    setIsSaving(true);

    const updatedSettings = {
      ...shopSettings,
      socialLinks: socialLinks
    };

    saveShopSettings(updatedSettings);

    setTimeout(() => {
      setIsSaving(false);
      addToast({
        type: 'success',
        title: 'Social Links Saved',
        message: 'Your official social media and channel URLs have been updated and synchronized with the storefront.'
      });
    }, 250);
  };

  const handleReset = () => {
    setSocialLinks({ ...INITIAL_SOCIAL_LINKS });
    addToast({
      type: 'info',
      title: 'Reset to Sample URLs',
      message: 'Restored default social media link templates.'
    });
  };

  const copyToClipboard = (text: string, fieldName: string) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    setCopiedField(fieldName);
    setTimeout(() => setCopiedField(null), 2000);
    addToast({
      type: 'success',
      title: 'Copied Link',
      message: `Copied ${fieldName} URL to clipboard.`
    });
  };

  const openLink = (url: string) => {
    if (!url) return;
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  // The 8 social fields defined in exact order and 2-column layout matching the user's uploaded screenshot
  const socialFields: {
    key: keyof SocialMediaLinks;
    label: string;
    placeholder: string;
    icon: React.ReactNode;
    brandColor: string;
    badge?: string;
  }[] = [
    {
      key: 'facebook',
      label: 'Facebook',
      placeholder: 'https://www.facebook.com/profile.php?id=61578497026372',
      icon: <Facebook className="w-4 h-4 text-blue-600 dark:text-blue-400" />,
      brandColor: 'hover:border-blue-500/50'
    },
    {
      key: 'instagram',
      label: 'Instagram',
      placeholder: 'https://instagram.com/your-profile',
      icon: <Instagram className="w-4 h-4 text-pink-600 dark:text-pink-400" />,
      brandColor: 'hover:border-pink-500/50'
    },
    {
      key: 'tiktok',
      label: 'TikTok',
      placeholder: 'https://tiktok.com/@your-profile',
      icon: <TikTokIcon className="w-4 h-4 text-neutral-900 dark:text-white" />,
      brandColor: 'hover:border-neutral-700/50'
    },
    {
      key: 'youtube',
      label: 'YouTube',
      placeholder: 'https://youtube.com/@your-channel',
      icon: <Youtube className="w-4 h-4 text-red-600 dark:text-red-400" />,
      brandColor: 'hover:border-red-500/50'
    },
    {
      key: 'linkedin',
      label: 'LinkedIn',
      placeholder: 'https://linkedin.com/company/your-company',
      icon: <Linkedin className="w-4 h-4 text-sky-700 dark:text-sky-400" />,
      brandColor: 'hover:border-sky-500/50'
    },
    {
      key: 'twitter',
      label: 'X (Twitter)',
      placeholder: 'https://x.com/your-handle',
      icon: <XTwitterIcon className="w-4 h-4 text-neutral-900 dark:text-white" />,
      brandColor: 'hover:border-neutral-700/50'
    },
    {
      key: 'whatsapp',
      label: 'WhatsApp',
      placeholder: 'https://wa.me/60123456789',
      icon: <WhatsAppIcon className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />,
      brandColor: 'hover:border-emerald-500/50'
    },
    {
      key: 'xiaohongshu',
      label: 'Xiaohongshu',
      placeholder: 'https://www.xiaohongshu.com/user/profile/...',
      icon: <XiaohongshuIcon className="w-4 h-4 text-rose-600 dark:text-rose-400" />,
      brandColor: 'hover:border-rose-500/50',
      badge: '小红书 / RED'
    }
  ];

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Main Social Media Links Card (Exact 1:1 Pixel Match to Screenshot) */}
      <div className="bg-[#fcfcfd] dark:bg-neutral-900 rounded-2xl border border-neutral-200/90 dark:border-neutral-800 p-6 sm:p-8 shadow-xs">
        {/* Card Header with Info Icon */}
        <div className="flex items-center justify-between pb-6 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center gap-2">
            <h2 className="text-base sm:text-lg font-bold text-neutral-900 dark:text-white tracking-tight">
              Social Media Links
            </h2>
            <div className="group relative cursor-pointer">
              <Info className="w-4 h-4 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition-colors" />
              <div className="absolute left-0 bottom-6 hidden group-hover:block z-20 w-72 p-2.5 bg-neutral-900 text-white text-[11px] rounded-lg shadow-xl leading-relaxed">
                Add your brand's official profile links. These will automatically appear in your website header, footer, mobile drawer, and checkout thank-you pages.
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className="hidden sm:inline-block text-xs text-neutral-400">
              {Object.values(socialLinks).filter(v => typeof v === 'string' && v.trim() !== '').length} active channels
            </span>
          </div>
        </div>

        {/* Form Grid (2 Columns on medium/large screens, 1 Column on mobile) */}
        <form onSubmit={handleSave} className="pt-6 space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
            {socialFields.map(field => {
              const value = socialLinks[field.key] || '';
              const isCopied = copiedField === field.key;

              return (
                <div key={field.key} className="space-y-1.5">
                  {/* Platform Icon & Label */}
                  <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-xs sm:text-[13px] font-semibold text-neutral-800 dark:text-neutral-200">
                      <span className="flex items-center justify-center flex-shrink-0">
                        {field.icon}
                      </span>
                      <span>{field.label}</span>
                      {field.badge && (
                        <span className="text-[10px] font-medium px-1.5 py-0.2 rounded bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                          {field.badge}
                        </span>
                      )}
                    </label>

                    {/* Quick Link Test / Copy Action */}
                    {value && (
                      <div className="flex items-center gap-1.5 text-[11px]">
                        <button
                          type="button"
                          onClick={() => copyToClipboard(value, field.label)}
                          className="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 p-1 rounded transition cursor-pointer"
                          title="Copy Link"
                        >
                          {isCopied ? (
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
                          ) : (
                            <Copy className="w-3.5 h-3.5" />
                          )}
                        </button>
                        <button
                          type="button"
                          onClick={() => openLink(value)}
                          className="inline-flex items-center gap-0.5 text-indigo-600 dark:text-indigo-400 hover:underline p-1 cursor-pointer"
                          title="Test / Open Link in new tab"
                        >
                          <ArrowUpRight className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    )}
                  </div>

                  {/* Input Box matching Screenshot with clean rounded-lg style */}
                  <div className="relative">
                    <input
                      id={`input-social-${field.key}`}
                      type="url"
                      placeholder={field.placeholder}
                      value={value}
                      onChange={e => handleInputChange(field.key, e.target.value)}
                      className={`w-full px-3.5 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800/90 text-xs sm:text-sm text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all shadow-2xs ${field.brandColor}`}
                    />
                  </div>
                </div>
              );
            })}
          </div>

          {/* Action Bar (Save / Reset / Status) */}
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-neutral-100 dark:border-neutral-800">
            <div className="flex items-center gap-2 text-xs text-neutral-500">
              <Sparkles className="w-4 h-4 text-amber-500" />
              <span>Links will be automatically formatted and linked across your storefront.</span>
            </div>

            <div className="flex items-center gap-3 w-full sm:w-auto">
              <button
                type="button"
                onClick={handleReset}
                className="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition cursor-pointer"
              >
                <RotateCcw className="w-3.5 h-3.5 text-neutral-400" />
                Reset Defaults
              </button>

              <button
                id="btn-save-social-links"
                type="submit"
                disabled={isSaving}
                className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-900 hover:bg-indigo-950 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white text-xs font-semibold shadow-md shadow-indigo-900/20 transition cursor-pointer"
              >
                <Save className="w-4 h-4" />
                {isSaving ? 'Saving...' : 'Save Social Links'}
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* Live Storefront Preview Widget */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Globe className="w-4 h-4 text-indigo-500" />
            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
              Live Storefront Preview (Footer & Header Icons)
            </h3>
          </div>
          <span className="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full border border-emerald-200/60">
            Realtime Synced
          </span>
        </div>

        <p className="text-xs text-neutral-500">
          This is how your social media channels will be rendered for visitors browsing your online store:
        </p>

        {/* Mock Storefront Footer Bar */}
        <div className="p-4 rounded-xl bg-neutral-900 text-white flex flex-col sm:flex-row items-center justify-between gap-4 border border-neutral-800">
          <div>
            <div className="text-xs font-bold text-neutral-200">Connect With {shopSettings.shopName || 'Aurora'}</div>
            <div className="text-[11px] text-neutral-400">Follow our channels for new releases & exclusive drops.</div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {socialFields.map(field => {
              const url = socialLinks[field.key];
              if (!url) return null;

              return (
                <button
                  key={field.key}
                  type="button"
                  onClick={() => openLink(url)}
                  title={`${field.label}: ${url}`}
                  className="w-8 h-8 rounded-lg bg-neutral-800 hover:bg-indigo-600 text-neutral-200 hover:text-white flex items-center justify-center transition-colors shadow-2xs cursor-pointer group"
                >
                  <span className="group-hover:scale-110 transition-transform">
                    {field.icon}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
};
