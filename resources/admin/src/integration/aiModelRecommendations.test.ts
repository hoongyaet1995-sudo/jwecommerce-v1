import { describe, expect, it } from 'vitest';
import { modelOptions } from './aiModelRecommendations';

describe('curated AI model options', () => {
  it('shows three recommendations but permits only models returned for the key', () => {
    const result = modelOptions('openai', 'text', [{ id: 'gpt-5.6-luna', text: true, image: false }]);
    expect(result.recommended).toHaveLength(3);
    expect(result.recommended.filter(model => model.available).map(model => model.id)).toEqual(['gpt-5.6-luna']);
    expect(result.recommended[0].label).toContain('Recommended');
  });
  it('keeps image recommendations separate and retains other available models without duplicates', () => {
    const result = modelOptions('openai', 'image', [
      { id: 'gpt-image-2', text: false, image: true },
      { id: 'gpt-image-other', text: false, image: true },
      { id: 'gpt-5.6-luna', text: true, image: false },
    ], 'gpt-image-other');
    expect(result.recommended).toHaveLength(3);
    expect(result.others.map(model => model.id)).toEqual(['gpt-image-other']);
    expect(modelOptions('gemini', 'text', []).recommended).toEqual([]);
  });
});
