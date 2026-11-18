import {ChevronDown} from 'lucide-react';
import React, {useEffect, useRef} from 'react';

type Props = {
  label: string;
  value: string;
  options: Array<[string, string]>;
  disabled?: boolean;
  onChange: (value: string) => void;
  icon: (index: number) => React.ReactNode;
};

export function ImageChoiceDropdown({label, value, options, disabled, onChange, icon}: Props) {
  const root = useRef<HTMLDetailsElement>(null);
  const trigger = useRef<HTMLElement>(null);
  const current = Math.max(0, options.findIndex(([key]) => key === value));
  useEffect(() => {
    const closeOutside = (event: PointerEvent) => {
      if (event.target instanceof Node && !root.current?.contains(event.target)) root.current?.removeAttribute('open');
    };
    document.addEventListener('pointerdown', closeOutside);
    return () => document.removeEventListener('pointerdown', closeOutside);
  }, []);
  const row = 'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left';
  const close = () => {
    root.current?.removeAttribute('open');
    trigger.current?.focus();
  };
  return (
    <div className="grid grid-cols-[minmax(5.5rem,0.8fr)_minmax(0,1.2fr)] items-start gap-2 text-sm text-neutral-700 dark:text-neutral-200">
      <span className="pt-1.5">{label}</span>
      {disabled ? <button type="button" disabled aria-label={label} title="Follows this section layout" className={row+' bg-neutral-100 opacity-50 dark:bg-neutral-800'}>{icon(current)}{options[current][1]}</button> :
      <details data-image-choice={label} ref={root} className="group/image-choice min-w-0" onKeyDown={event=>{
        if (event.key==='Escape' && root.current?.open) {event.stopPropagation();close();return;}
        if (!['ArrowDown','ArrowUp'].includes(event.key)) return;
        event.preventDefault();
        if (!root.current) return;
        root.current.open = true;
        const choices = Array.from(root.current.querySelectorAll('button'));
        const index = choices.indexOf(document.activeElement as HTMLButtonElement);
        choices[index < 0 ? current : (index + choices.length + (event.key==='ArrowDown'?1:-1)) % choices.length]?.focus();
      }}>
        <summary ref={element=>{trigger.current=element;}} aria-label={label+': '+options[current][1]} className={row+' cursor-pointer list-none bg-neutral-100 dark:bg-neutral-800'}>
          {icon(current)}<span className="min-w-0 flex-1 truncate">{options[current][1]}</span><ChevronDown aria-hidden size={14} strokeWidth={2} className="shrink-0 text-neutral-500 transition-transform group-open/image-choice:rotate-180 dark:text-neutral-400"/>
        </summary>
        <div className="mt-1 rounded-md border border-neutral-300 bg-white p-1 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
          {options.map(([key, text], index)=><button key={key} type="button" data-choice-value={key} aria-pressed={index===current} className={row+' hover:bg-neutral-100 focus-visible:bg-neutral-100 dark:hover:bg-neutral-800 dark:focus-visible:bg-neutral-800'} onClick={()=>{onChange(key);close();}}>
            {icon(index)}<span className="flex-1">{text}</span>{index===current&&<span aria-hidden>✓</span>}
          </button>)}
        </div>
      </details>}
    </div>
  );
}
