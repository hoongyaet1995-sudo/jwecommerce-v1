import type { AIConnection, AIProvider } from './aiConnections';

const openai = {
  text: [
    { id: 'gpt-5.6-luna', label: 'GPT-5.6 Luna — Cost Saving (Recommended)' },
    { id: 'gpt-5.6-terra', label: 'GPT-5.6 Terra — Balanced Quality & Cost' },
    { id: 'gpt-5.6-sol', label: 'GPT-5.6 Sol — Premium Quality' },
  ],
  image: [
    { id: 'gpt-image-2', label: 'GPT Image 2 — General Image Generation' },
    { id: 'gpt-image-2.5-flare', label: 'GPT Image 2.5 Flare — Fast, High Quality' },
    { id: 'gpt-image-2.5-sunburst', label: 'GPT Image 2.5 Sunburst — Precise Editing' },
  ],
};

export function modelOptions(provider: AIProvider, kind: 'text' | 'image', models: AIConnection['models']) {
  const available = models.filter(model => model[kind]);
  const recommended = provider === 'openai'
    ? openai[kind].filter(model => available.some(item => item.id === model.id))
    : [];
  return {
    recommended,
    others: available.filter(model => !recommended.some(item => item.id === model.id)),
  };
}
