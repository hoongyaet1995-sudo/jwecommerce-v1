import {VisualChoiceSelect} from './VisualChoiceSelect';
import React from 'react';
import type {PageSection} from '../../types';
import {ctaPresetPatch} from '../../cms/ctaPresets';
const inputClass='w-full mt-1 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-900 dark:text-white';
export function CtaStripSettings({section,onChange}:{section:PageSection;onChange:(patch:Partial<PageSection>)=>void}) {
 return <div className="space-y-3 pb-4 border-b border-neutral-100 dark:border-neutral-800">
  <label className="block text-xs text-neutral-500">Layout preset<VisualChoiceSelect aria-label="Layout preset" value={section.ctaPreset??'default'} className={inputClass} onChange={event=>onChange(ctaPresetPatch(section,event.target.value as NonNullable<PageSection['ctaPreset']>))}><option value="saas-closing">SaaS Closing CTA</option><option value="default">Classic — centered call to action</option><option value="image-banner">Image Banner — background photograph and left text</option><option value="editorial-band">Editorial CTA Band — headline and lateral action</option><option value="industrial-outline">Industrial Outline — title and two actions</option><option value="help-panel">Help Panel — inset support callout</option></VisualChoiceSelect></label>
  {section.ctaPreset==='image-banner' && <><p className="text-xs text-neutral-500">Full-width background inside the page margin. Select the image to adjust crop, fill, overlay and foreground. Choosing this preset sets text colors to white.</p><label className="block text-xs text-neutral-500">Banner height<VisualChoiceSelect aria-label="Banner height" className={inputClass} value={section.ctaBannerHeight??'normal'} onChange={event=>onChange({ctaBannerHeight:event.target.value as PageSection['ctaBannerHeight']})}><option value="compact">Compact</option><option value="normal">Normal</option><option value="spacious">Spacious</option></VisualChoiceSelect></label></>}
 </div>;
}
