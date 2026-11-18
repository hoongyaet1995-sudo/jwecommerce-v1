import React, {useEffect, useState} from 'react';
import {Upload, X} from 'lucide-react';
import {imageTargets, imageTargetSection} from '../../cms/imageTargets';
import {SectionImageSettings} from './SectionImageSettings';
import {uploadMediaAsset} from '../../integration/pageData';
import {readAdminBootstrap} from '../../integration/adminBootstrap';
import type {PageSection} from '../../types';

type Props = {
  canvasRef: React.RefObject<HTMLDivElement | null>;
  sections: PageSection[];
  pageId: string;
  activeSectionId: string | null;
  onSelect: (sectionId: string, slot: string) => void;
  onPatch: (pageId: string, sectionId: string, slot: string, patch: Partial<PageSection>) => void;
};

export function BuilderImageToolbar({canvasRef, sections, pageId, activeSectionId, onSelect, onPatch}: Props) {
  const [target, setTarget] = useState<{sectionId:string; slot:string; x:number; y:number} | null>(null);

  const [url, setUrl] = useState('');
  const [error, setError] = useState('');
  const [uploading, setUploading] = useState(false);
  const fileInput = React.useRef<HTMLInputElement>(null);

  useEffect(() => setTarget(null), [pageId]);
  useEffect(() => {
    if (target && target.sectionId !== activeSectionId) setTarget(null);
  }, [activeSectionId, target]);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const close = () => setTarget(null);
    const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') close(); };
    const open = (event: MouseEvent) => {
      close();
      const element = event.target;
      if (!(element instanceof Element) || element.closest('form,[data-cms-source],[contenteditable="true"],[data-cms-text]')) return;
      const image = element.closest<HTMLElement>('[data-image-slot]');
      const slot = image?.dataset.imageSlot;
      const root = image?.closest('section[data-cms-editor]');
      const section = sections.find(candidate => root?.id === 'section-' + candidate.id);
      const imageTarget = section && imageTargets(section).find(candidate => candidate.id === slot);
      if (!slot || !section || !imageTarget) return;
      setUrl(imageTarget.url);
      setError('');
      event.preventDefault();
      onSelect(section.id, slot);
      setTarget({sectionId: section.id, slot, x: event.clientX, y: event.clientY});
    };
    canvas.addEventListener('contextmenu', open);
    canvas.addEventListener('wheel', close, true);
    canvas.addEventListener('pointerdown', close);
    window.addEventListener('resize', close);
    window.addEventListener('keydown', onKey);
    return () => {
      canvas.removeEventListener('contextmenu', open);
      canvas.removeEventListener('wheel', close, true);
      canvas.removeEventListener('pointerdown', close);
      window.removeEventListener('resize', close);
      window.removeEventListener('keydown', onKey);
    };
  }, [canvasRef, sections, onSelect]);

  const section = sections.find(candidate => candidate.id === target?.sectionId);
  const image = section && imageTargets(section).find(candidate => candidate.id === target?.slot);
  useEffect(() => { setUrl(image?.url??''); }, [image?.url, target?.slot]);
  if (!target || !section || !image) return null;
  const settings = imageTargetSection(section, image);
  const patch = (value: Partial<PageSection>) => onPatch(pageId, target.sectionId, target.slot, value);
  const upload = async (file?: File) => {
    if (!file || uploading) return;
    setUploading(true);
    setError('');
    try {
      patch({mediaUrl: await uploadMediaAsset(readAdminBootstrap(), file)});
    } catch (failure) {
      setError(failure instanceof Error ? failure.message : 'Unable to upload this image.');
    } finally {
      setUploading(false);
    }
  };
  const width = Math.min(324, window.innerWidth - 16);
  const control = 'shrink-0 rounded border border-neutral-600 bg-neutral-800 px-2 py-1.5 text-white disabled:opacity-50';
  return (
    <div role="dialog" aria-label={`Image tools: ${image.label}`}
      className="fixed z-[100] max-h-[min(640px,calc(100dvh-16px))] overflow-y-auto rounded-xl border border-neutral-700 bg-neutral-900 text-sm text-white shadow-2xl dark"
      style={{width, left: Math.max(8, Math.min(target.x, window.innerWidth - width - 8)), top: Math.max(8, Math.min(target.y - 46, window.innerHeight - Math.min(640, window.innerHeight - 16) - 8))}}>
      <header className="sticky top-0 z-10 flex items-center justify-between border-b border-neutral-700 bg-neutral-900 px-4 py-2">
        <strong>{image.label}</strong>
        <button type="button" className={control} aria-label="Close image tools" onClick={() => setTarget(null)}><X size={14} aria-hidden /></button>
      </header>
      <div className="space-y-3 p-4">
        <div className="relative grid h-36 place-items-center overflow-hidden rounded-lg bg-neutral-800">
          {image.url && <img src={image.url} alt="" referrerPolicy="no-referrer" className="absolute h-full w-full object-contain"/>}
          <input ref={fileInput} type="file" accept="image/*" hidden onChange={event=>{
            const file = event.currentTarget.files?.[0];
            event.currentTarget.value = '';
            void upload(file);
          }}/>
          <button type="button" className={control+' relative shadow-lg'} disabled={uploading} onClick={()=>fileInput.current?.click()}><Upload size={14} aria-hidden className="mr-1 inline"/>{uploading?'Uploading…':'Upload'}</button>
        </div>
        <form className="flex gap-2" onSubmit={event=>{
          event.preventDefault();
          if (url.trim()) patch({mediaUrl:url.trim()});
        }}>
          <input aria-label="Image URL" placeholder="Image URL" className={control+' min-w-0 flex-1 shrink'} value={url} onChange={event=>setUrl(event.target.value)}/>
          <button type="submit" className={control} disabled={!url.trim() || uploading}>Replace</button>
        </form>
        {error && <p role="alert" className="text-red-300">{error}</p>}
        <SectionImageSettings section={settings} geometryLocked={image.geometryLocked} mode="design" assetControl={null} onChange={patch}/>
      </div>
    </div>
  );
}
