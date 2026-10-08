import React from 'react';
import {ArrowRight} from 'lucide-react';
import type {PageSection} from '../../types';
import {MotionButton} from './motion/CmsMotionWrapper';
export function EditorialSectionAction({section,isEditingMode}:{section:PageSection;isEditingMode:boolean}) {
  if(!section.buttonText || !section.buttonLink) return null;
  const link=section.buttonStyle==='text-link';
  return <MotionButton><a href={section.buttonLink} data-editorial-action={link?'text-link':'button'} onClick={e=>isEditingMode && e.preventDefault()}
    style={link?{color:section.customColors?.primaryBtnBg}:{backgroundColor:section.customColors?.primaryBtnBg,color:section.customColors?.primaryBtnText}}
    className={link?'inline-flex items-center gap-3 text-sm font-semibold text-indigo-600 underline underline-offset-4 transition-opacity hover:opacity-80':'inline-flex items-center gap-3 px-6 py-3 rounded-lg bg-indigo-600 text-white text-sm font-bold transition-opacity hover:opacity-90'}>
    {section.buttonText}<ArrowRight className="w-4 h-4" />
  </a></MotionButton>;
}
