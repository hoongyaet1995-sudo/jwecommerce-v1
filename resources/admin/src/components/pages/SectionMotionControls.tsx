import React from 'react';
import type {PageSection, SectionMotionPreset} from '../../types';
import {ImageChoiceDropdown} from './ImageChoiceDropdown';
import {MOTION_PRESET_DETAILS} from './motion/CmsMotionWrapper';

const presets = Object.keys(MOTION_PRESET_DETAILS) as SectionMotionPreset[];
const entranceOptions: Array<[string, string]> = presets.map(key => [key, MOTION_PRESET_DETAILS[key].label]);
const paceOptions: Array<[string, string]> = [
  ['', 'Follow page / layout'],
  ['smooth', 'Smooth · 0.8s'],
  ['relaxed', 'Relaxed · 1.1s'],
];

const drawings: Record<string, React.ReactNode> = {
  'subtle-lift': <><rect x="2" y="4" width="9" height="7" rx="1"/><path d="M2 15h9m4 0V4m-2 2 2-2 2 2"/></>,
  'fade-in': <><rect x="2" y="5" width="10" height="9" rx="1" strokeDasharray="1 2"/><path d="M7 3h9v9"/></>,
  'smooth-zoom': <><rect x="6" y="6" width="6" height="6" rx="1"/><path d="M2 6V2h4m6 0h4v4m0 6v4h-4m-6 0H2v-4"/></>,
  'dynamic-pop': <path d="m1 14 4-7 4 5 4-9 4 5"/>,
  none: <rect x="4" y="4" width="10" height="10" rx="1"/>,
  '': <><rect x="3" y="2" width="12" height="14" rx="2"/><path d="M6 6h6m-6 3h6m-6 3h3"/></>,
  smooth: <><circle cx="9" cy="9" r="7"/><path d="M9 5v4h4"/></>,
  relaxed: <><circle cx="9" cy="9" r="7"/><path d="M9 5v4l-3 3"/></>,
};

function MotionIcon({value}: {value: string}) {
  return <svg aria-hidden="true" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor"
    strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"
    className="shrink-0 text-neutral-500 dark:text-neutral-300">{drawings[value]}</svg>;
}

type Props = {
  section: PageSection;
  onChange: (section: PageSection) => void;
  onReplay?: () => void;
  onApplyAll?: (preset: SectionMotionPreset) => void;
};

const actionClass = 'rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs hover:bg-neutral-100 disabled:opacity-50 dark:border-neutral-700 dark:hover:bg-neutral-800';

export function SectionMotionControls({section, onChange, onReplay, onApplyAll}: Props) {
  const preset = section.motionPreset || 'subtle-lift';
  return <div className="space-y-2.5">
    <ImageChoiceDropdown overlay label="Entrance" options={entranceOptions} value={preset}
      icon={index => <MotionIcon value={entranceOptions[index][0]}/>}
      onChange={value => onChange({...section, motionPreset: value as SectionMotionPreset})}/>
    <ImageChoiceDropdown overlay label="Pace" options={paceOptions} value={section.motionPace ?? ''}
      icon={index => <MotionIcon value={paceOptions[index][0]}/>}
      onChange={value => {
        const next = {...section};
        if (value) next.motionPace = value as PageSection['motionPace'];
        else delete next.motionPace;
        onChange(next);
      }}/>
    {(onReplay || onApplyAll) && <div className="flex gap-2">
      {onReplay && <button type="button" className={actionClass} disabled={preset === 'none'} onClick={onReplay}>Replay</button>}
      {onApplyAll && <button type="button" className={actionClass} onClick={() => onApplyAll(preset)}
        title="Apply this motion preset to all sections on the current page">Apply All</button>}
    </div>}
  </div>;
}
