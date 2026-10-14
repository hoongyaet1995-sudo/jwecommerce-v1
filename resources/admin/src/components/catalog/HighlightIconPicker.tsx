import { AudioLines, Battery, Usb, Bluetooth, Wifi, Cpu, Monitor, Headphones, Zap, ShieldCheck, Package, Truck, Sparkles, Leaf, Droplets, Award, Check, Gem, Gift } from 'lucide-react';

const choices = [
  ['check', 'Check', Check], ['audio-lines', 'Audio', AudioLines], ['battery', 'Battery', Battery],
  ['usb', 'USB', Usb], ['bluetooth', 'Bluetooth', Bluetooth], ['wifi', 'Wi-Fi', Wifi],
  ['cpu', 'Processor', Cpu], ['monitor', 'Display', Monitor], ['headphones', 'Headphones', Headphones],
  ['zap', 'Power', Zap], ['shield-check', 'Protection', ShieldCheck], ['package', 'Package', Package],
  ['truck', 'Delivery', Truck], ['sparkles', 'Sparkles', Sparkles], ['leaf', 'Leaf', Leaf],
  ['gem', 'Gem', Gem], ['gift', 'Gift', Gift], ['droplets', 'Water', Droplets], ['award', 'Award', Award],
] as const;

export function HighlightIconPicker({ value, onChange }: { value?: string; onChange: (icon: string) => void }) {
  const current = choices.find(([id]) => id === value) ?? choices[0];
  const Icon = current[2];
  return <label className="flex items-center gap-2 text-xs shrink-0">
    <Icon className="w-5 h-5" aria-hidden="true" />
    <span className="sr-only">Highlight icon</span>
    <select aria-label="Highlight icon" value={current[0]} onChange={e => onChange(e.target.value)} className="px-2 py-1.5 rounded-lg border bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white">
      {choices.map(([id, label]) => <option key={id} value={id}>{label}</option>)}
    </select>
  </label>;
}
