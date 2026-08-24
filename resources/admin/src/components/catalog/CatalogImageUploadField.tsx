import React, { useRef, useState } from 'react';
import { Image as ImageIcon, Upload } from 'lucide-react';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import { uploadMediaAsset } from '../../integration/pageData';

export const CatalogImageUploadField: React.FC<{ label: string; value?: string; onChange: (url: string) => void }> = ({ label, value = '', onChange }) => {
  const input = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const upload = async (file?: File) => {
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      setError('Only PNG, JPG, WEBP, GIF files up to 10MB are accepted.');
      return;
    }
    setUploading(true); setError(null);
    try { onChange(await uploadMediaAsset(readAdminBootstrap(), file)); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to upload image.'); } finally { setUploading(false); }
  };
  return <div className="space-y-2"><label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">{label}</label><div className="flex gap-2"><input type="text" placeholder="https://images.unsplash.com/..." value={value} onChange={event => onChange(event.target.value)} className="flex-1 px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"/><input ref={input} type="file" accept="image/png,image/jpeg,image/webp,image/gif" className="hidden" onChange={event => { void upload(event.target.files?.[0]); event.currentTarget.value = ''; }}/><button type="button" disabled={uploading} onClick={() => input.current?.click()} className="px-3 py-2 rounded-xl bg-neutral-800 dark:bg-neutral-700 text-white text-xs font-semibold hover:bg-neutral-700 disabled:opacity-50 flex items-center gap-1.5"><Upload className="w-3.5 h-3.5"/>{uploading ? 'Uploading…' : 'Upload'}</button></div>{value && <div className="h-20 rounded-xl overflow-hidden border border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800"><img src={value} alt="Selected catalog media" className="w-full h-full object-cover"/></div>}{error && <p className="text-[11px] text-rose-500 flex items-center gap-1"><ImageIcon className="w-3.5 h-3.5"/>{error}</p>}</div>;
};
