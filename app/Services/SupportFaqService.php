<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;

class SupportFaqService
{
    public function read(): array
    {
        return StoreSetting::where('key', 'support_faq')->value('value') ?? ['version' => 0, 'faq' => null];
    }

    public function save(array $input): array
    {
        $data = validator($input, [
            'autoReplyEnabled' => 'sometimes|boolean',
            'personality' => 'sometimes|array:toneOfVoice,customToneInstructions,maxOutputTokens',
            'personality.toneOfVoice' => 'required_with:personality|in:luxury_professional,friendly_helpful,concise_efficient,energetic_streetwear,formal_corporate,custom',
            'personality.customToneInstructions' => 'nullable|string|max:2000',
            'personality.maxOutputTokens' => 'required_with:personality|integer|min:150|max:600',
            'primaryLanguage' => 'sometimes|in:auto_detect,en,ms,zh',
            'identity' => 'sometimes|array:botName,roleTitle',
            'identity.botName' => 'required_with:identity|string|max:100',
            'identity.roleTitle' => 'required_with:identity|string|max:255',
            'version' => 'required|integer|min:0', 'faq.isEnabled' => 'required|boolean',
            'faq.retrievalDepth' => 'required|in:strict,semantic,deep',
            'faq.categories' => 'required|array|max:100', 'faq.categories.*.id' => 'required|string|max:100|distinct|not_in:all', 'faq.categories.*.label' => 'required|string|max:60',
            'faq.deletedCategoryIds' => 'sometimes|array|max:500', 'faq.deletedCategoryIds.*' => 'string|max:100|not_in:others,all',
            'faq.items' => 'present|array|max:500', 'faq.items.*.id' => 'required|string|max:100|distinct', 'faq.items.*.category' => 'required|string|max:100',
            'faq.items.*.question' => 'required|string|max:1000', 'faq.items.*.answer' => 'required|string|max:10000',
            'faq.items.*.keywords' => 'present|array|max:100', 'faq.items.*.keywords.*' => 'string|max:200', 'faq.items.*.isActive' => 'required|boolean', 'faq.items.*.lastUpdatedAt' => 'required|string|max:100',
        ])->validate();
        $faq = $data['faq'];
        $deleted = $faq['deletedCategoryIds'] ?? [];
        $faq['categories'] = array_values(array_filter($faq['categories'], fn ($c) => $c['id'] !== 'others' && ! in_array($c['id'], $deleted, true)));
        array_unshift($faq['categories'], ['id' => 'others', 'label' => 'Others']);
        foreach ($faq['items'] as &$item) {
            if (! in_array($item['category'], array_column($faq['categories'], 'id'), true)) {
                $item['category'] = 'others';
            }
        }
        unset($item);

        return DB::transaction(function () use ($data, $faq) {
            DB::table('store_settings')->insertOrIgnore(['key' => 'support_faq', 'value' => json_encode(['version' => 0, 'faq' => null]), 'created_at' => now(), 'updated_at' => now()]);
            $row = StoreSetting::where('key', 'support_faq')->lockForUpdate()->firstOrFail();
            abort_if(($row->value['version'] ?? 0) !== $data['version'], 409, 'FAQs changed in another session. Reload before saving.');
            $result = ['version' => $data['version'] + 1, 'faq' => $faq, 'personality' => $data['personality'] ?? ($row->value['personality'] ?? null), 'primaryLanguage' => $data['primaryLanguage'] ?? ($row->value['primaryLanguage'] ?? 'auto_detect'), 'autoReplyEnabled' => $data['autoReplyEnabled'] ?? ($row->value['autoReplyEnabled'] ?? false), 'identity' => $data['identity'] ?? ($row->value['identity'] ?? null)];
            $row->update(['value' => $result]);

            return $result;
        });
    }

    public function publicFaq(): array
    {
        $settings = $this->read();
        $identity = $settings['identity'] ?? ['botName' => 'JW Studio Support', 'roleTitle' => 'Let’s find what you need'];
        $faq = $settings['faq'];
        if (! $faq || ! $faq['isEnabled']) {
            return ['primaryLanguage' => $settings['primaryLanguage'] ?? 'auto_detect', 'identity' => $identity, 'categories' => []];
        }

        return ['primaryLanguage' => $settings['primaryLanguage'] ?? 'auto_detect', 'identity' => $identity, 'categories' => array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['label'], 'items' => array_values(array_map(fn ($i) => ['id' => $i['id'], 'question' => $i['question'], 'answer' => $i['answer']], array_filter($faq['items'], fn ($i) => $i['isActive'] && $i['category'] === $c['id'])))], $faq['categories'])];
    }
}
