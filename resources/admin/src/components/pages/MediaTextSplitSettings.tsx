import {VisualChoiceSelect} from './VisualChoiceSelect';
import React from 'react';
import type {PageSection} from '../../types';
import {isEditorialMediaTextSplit,mediaTextSplitPresetPatch} from '../../cms/mediaTextSplitPresets';

const inputClass='w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 text-sm';
export function MediaTextSplitSettings({section,onChange,showIntroduction=true}: {section:PageSection;showIntroduction?:boolean;onChange:(patch:Partial<PageSection>)=>void}) {
  if(section.type!=='media_text_split') return null;
  const editorial=isEditorialMediaTextSplit(section);
  const story=section.layoutPreset==='editorial-story'||section.layoutPreset==='story-with-steps';
  return <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
    <label className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block">Layout Preset</label>
    <VisualChoiceSelect aria-label="Layout preset" className={inputClass} value={section.layoutPreset ?? 'default'} onChange={e=>onChange(mediaTextSplitPresetPatch(section,e.target.value as NonNullable<PageSection['layoutPreset']>))}><option value="corporate-section-story">Corporate Section Story — transparent artwork</option><option value="image-highlights">Image &amp; Highlights — image and four descriptions</option><option value="corporate-builder-overview">Corporate Builder Overview — image and icon labels</option><option value="corporate-marketplace">Corporate Marketplace — connected platform story</option><option value="corporate-courier">Corporate Courier — image, journey and logo marquee</option><option value="corporate-payments">Corporate Payments — centered image and logo marquee</option><option value="corporate-image-story">Corporate Image Story — embedded artwork</option><option value="corporate-feature">Corporate Feature Story</option><option value="corporate-support">Corporate Support Network</option><option value="saas-hero">SaaS Product Hero</option><option value="saas-showcase">SaaS Product Showcase</option><option value="saas-analytics">SaaS Analytics Showcase</option><option value="editorial-journal-rows">Editorial Journal — stacked image and narrative rows</option><option value="artisan-story">Artisan Story — photographs around a central narrative</option><option value="kids-discovery">Little Milestones — materials story and grouped icons</option><option value="tech-feature">Featured Device — introduction, image and features</option><option value="producer-story">Producer Story — photograph, copy and values</option><option value="flush-split">Flush Split — edge-to-edge image and copy</option><option value="default">Classic — balanced image and text</option><option value="editorial-hero">Editorial Hero — large heading and photograph</option><option value="editorial-story">Editorial Story — photograph and narrative</option><option value="editorial-story-cards">Editorial Story Cards — image and text pairs</option><option value="story-with-steps">Story with Steps — narrative and numbered steps</option><option value="editorial-cover">Editorial Cover — introduction and panoramic photograph</option></VisualChoiceSelect>
    {['corporate-feature','corporate-support','corporate-image-story'].includes(section.layoutPreset??'')&&<label className="block text-sm">Desktop image position<VisualChoiceSelect aria-label="Desktop image position" value={section.mediaPosition??'left'} onChange={e=>onChange({mediaPosition:e.target.value as 'left'|'right'})}><option value="left">Left</option><option value="right">Right</option></VisualChoiceSelect></label>}
    {showIntroduction&&section.layoutPreset==='editorial-cover'&&<label className="block text-sm text-neutral-500">Introduction<input aria-label="Cover introduction" className={inputClass} value={section.leadText??''} onChange={event=>onChange({leadText:event.target.value})}/></label>}
    {(editorial || section.layoutPreset?.startsWith('saas-')) && section.layoutPreset!=='editorial-cover' && <>
      <p className="text-xs text-neutral-500 dark:text-neutral-400">{story?'Narrative layout · square corners · mobile image first. Separate paragraphs with a blank line.':'Large heading · square corners · no image shadow. Mobile always shows text first. Use a new line in the title for an intentional line break.'}</p>
      <label className="block text-sm text-neutral-600 dark:text-neutral-400">Desktop image position
        <VisualChoiceSelect aria-label="Desktop image position" className={inputClass} value={section.mediaPosition ?? (story?'left':'right')} onChange={e=>onChange({mediaPosition:e.target.value as 'left'|'right'})}>
          <option value="right">Right</option><option value="left">Left</option>
        </VisualChoiceSelect>
      </label>
      <label className="block text-sm text-neutral-600 dark:text-neutral-400">CTA style
        <VisualChoiceSelect aria-label="CTA style" className={inputClass} value={section.buttonStyle??'button'} onChange={e=>onChange({buttonStyle:e.target.value as PageSection['buttonStyle']})}>
          <option value="button">Button</option><option value="text-link">Text link</option>
        </VisualChoiceSelect>
      </label>
      <p className="text-xs text-neutral-500 dark:text-neutral-400">CTA appears when both its text and destination are set.</p>
    </>}
  </div>;
}
