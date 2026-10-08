import React from 'react';
import type {PageSection} from '../../types';
import {isManifesto,richTextPresetPatch} from '../../cms/richTextPresets';
export function RichTextSettings({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
  return <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
    <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">Layout preset</label>
    <select aria-label="Layout preset" value={section.richTextPreset??'default'} onChange={event=>onChange(richTextPresetPatch(section,event.target.value as NonNullable<PageSection['richTextPreset']>))} className="w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white">
      <option value="default">Editorial Text &amp; Quote — original layout</option>
      <option value="manifesto">Manifesto — centered statement</option>
    </select>
    <p className="text-xs text-neutral-500 dark:text-neutral-400">{isManifesto(section)?'Centered layout with a readable text width. Separate paragraphs with a blank line. Quote content is preserved for the original layout.':'Original editorial layout with an optional quote.'}</p>
    {!isManifesto(section) && <>
      <label className="block">Quote<textarea aria-label="Quote" value={section.quote??''} onChange={event=>onChange({quote:event.target.value})} rows={3} className="mt-1 w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"/></label>
      <label className="block">Quote author<input aria-label="Quote author" value={section.authorName??''} onChange={event=>onChange({authorName:event.target.value})} className="mt-1 w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"/></label>
      <label className="block">Author role<input aria-label="Author role" value={section.authorRole??''} onChange={event=>onChange({authorRole:event.target.value})} className="mt-1 w-full px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white"/></label>
    </>}
  </div>;
}
