<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;

class SupportFaqService
{
    public function read(): array
    {
        $settings = StoreSetting::where('key', 'support_faq')->value('value') ?? ['version' => 0, 'faq' => null];
        $settings['aiChannels'] = array_replace(\App\Services\Support\SupportAiPolicy::DEFAULTS, $settings['aiChannels'] ?? []);
        $settings['productCatalog'] ??= ['isEnabled'=>true,'canCheckLiveStock'=>true,'canExplainSizingAndMaterials'=>true,'canRecommendUpsells'=>true,'maxRecommendations'=>3];
        $settings['orderLookup'] ??= ['isEnabled'=>false,'allowedFields'=>['orderStatus'=>true,'trackingNumberAndCourier'=>true,'estimatedDeliveryDate'=>false,'itemSummary'=>false,'shippingAddressMasked'=>false,'paymentStatus'=>false]];
        $settings['discountKnowledge'] ??= ['isEnabled'=>false,'activeIds'=>[]];
        $settings['greetingMessage'] ??= $this->defaultGreeting();
        $settings['personality'] = array_replace($this->defaultPersonality(), $settings['personality'] ?? []);
        $settings['personality']['humanHandoffTriggers'] = array_replace($this->defaultPersonality()['humanHandoffTriggers'], $settings['personality']['humanHandoffTriggers'] ?? []);
        unset($settings['personality']['humanHandoffTriggers']['onVipCustomer']);

        return $settings;
    }

    public function save(array $input): array
    {
        $data = validator($input, [
            'orderLookup'=>'sometimes|array:isEnabled,allowedFields',
            'orderLookup.isEnabled'=>'required_with:orderLookup|boolean',
            'orderLookup.allowedFields'=>'required_with:orderLookup|array:orderStatus,trackingNumberAndCourier,estimatedDeliveryDate,itemSummary,shippingAddressMasked,paymentStatus',
            'orderLookup.allowedFields.*'=>'boolean',
            'discountKnowledge' => 'sometimes|array:isEnabled,activeIds',
            'discountKnowledge.isEnabled' => 'required_with:discountKnowledge|boolean',
            'discountKnowledge.activeIds' => 'present_with:discountKnowledge|array|max:500',
            'discountKnowledge.activeIds.*' => 'string|distinct|exists:coupons,id',
            'productCatalog' => 'sometimes|array:isEnabled,canCheckLiveStock,canExplainSizingAndMaterials,canRecommendUpsells,maxRecommendations',
            'productCatalog.isEnabled' => 'required_with:productCatalog|boolean',
            'productCatalog.canCheckLiveStock' => 'required_with:productCatalog|boolean',
            'productCatalog.canExplainSizingAndMaterials' => 'required_with:productCatalog|boolean',
            'productCatalog.canRecommendUpsells' => 'required_with:productCatalog|boolean',
            'productCatalog.maxRecommendations' => 'required_with:productCatalog|integer|min:1|max:5',
            'autoReplyEnabled' => 'sometimes|boolean',
            'aiChannels'=>'sometimes|array:storefront,facebook,instagram,whatsapp,tiktok',
            'aiChannels.*'=>'boolean',
            'greetingMessage' => 'sometimes|array:en,ms,zh',
            'greetingMessage.en' => 'required_with:greetingMessage|string|max:2000',
            'greetingMessage.ms' => 'required_with:greetingMessage|string|max:2000',
            'greetingMessage.zh' => 'required_with:greetingMessage|string|max:2000',
            'personality' => 'sometimes|array:creativityTemperature,toneOfVoice,customToneInstructions,maxOutputTokens,brandMissionPrompt,unknownAnswerMessage,serviceUnavailableMessage,humanHandoffTriggers,fallbackHandoffMessage',
            'personality.humanHandoffTriggers' => 'sometimes|array:onExplicitRequest,onFrustrationDetected,onRefundOrDispute,onServiceUnavailable,consecutiveFailureCount,urgentKeywords',
            'personality.humanHandoffTriggers.onExplicitRequest' => 'required_with:personality.humanHandoffTriggers|boolean',
            'personality.humanHandoffTriggers.onFrustrationDetected' => 'required_with:personality.humanHandoffTriggers|boolean',
            'personality.humanHandoffTriggers.onRefundOrDispute' => 'required_with:personality.humanHandoffTriggers|boolean',
            'personality.humanHandoffTriggers.onServiceUnavailable' => 'required_with:personality.humanHandoffTriggers|boolean',
            'personality.humanHandoffTriggers.consecutiveFailureCount' => 'required_with:personality.humanHandoffTriggers|integer|min:0|max:5',
            'personality.humanHandoffTriggers.urgentKeywords' => 'present_with:personality.humanHandoffTriggers|array|max:30',
            'personality.humanHandoffTriggers.urgentKeywords.*' => 'string|max:80|distinct',
            'personality.fallbackHandoffMessage' => 'sometimes|array:en,ms,zh',
            'personality.fallbackHandoffMessage.en' => 'required_with:personality.fallbackHandoffMessage|string|max:1000',
            'personality.fallbackHandoffMessage.ms' => 'required_with:personality.fallbackHandoffMessage|string|max:1000',
            'personality.fallbackHandoffMessage.zh' => 'required_with:personality.fallbackHandoffMessage|string|max:1000',
            'personality.toneOfVoice' => 'required_with:personality|in:luxury_professional,friendly_helpful,concise_efficient,energetic_streetwear,formal_corporate,custom',
            'personality.creativityTemperature' => 'sometimes|numeric|min:0|max:1',
            'personality.brandMissionPrompt' => 'nullable|string|max:4000',
            'personality.unknownAnswerMessage' => 'sometimes|array:en,ms,zh',
            'personality.serviceUnavailableMessage' => 'sometimes|array:en,ms,zh',
            'personality.unknownAnswerMessage.en' => 'required_with:personality.unknownAnswerMessage|string|max:2000',
            'personality.unknownAnswerMessage.ms' => 'required_with:personality.unknownAnswerMessage|string|max:2000',
            'personality.unknownAnswerMessage.zh' => 'required_with:personality.unknownAnswerMessage|string|max:2000',
            'personality.serviceUnavailableMessage.en' => 'required_with:personality.serviceUnavailableMessage|string|max:2000',
            'personality.serviceUnavailableMessage.ms' => 'required_with:personality.serviceUnavailableMessage|string|max:2000',
            'personality.serviceUnavailableMessage.zh' => 'required_with:personality.serviceUnavailableMessage|string|max:2000',
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
        if (isset($data['personality']['creativityTemperature']) && \Illuminate\Support\Facades\Schema::hasTable('ai_connections')) {
            $connection = \App\Models\AiConnection::where('enabled', true)->where('support_enabled', true)->first();
            $model = $connection?->text_model ?? '';
            $unsupported = ($connection?->provider === 'openai' && preg_match('/^o[1-9]|^gpt-5(?:-|$)|^gpt-6-astra|^gpt-6\\.1-sol/', $model))
                || ($connection?->provider === 'anthropic' && preg_match('/claude-(?:opus-4-[7-9]|sonnet-5|mythos)/', $model));
            if ($unsupported) {
                throw \Illuminate\Validation\ValidationException::withMessages(['personality.creativityTemperature' => 'The selected support model does not support custom temperature. Select a compatible model first.']);
            }
        }
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
            $result = ['aiChannels'=>array_replace(\App\Services\Support\SupportAiPolicy::DEFAULTS,$row->value['aiChannels'] ?? [],$data['aiChannels'] ?? []),'orderLookup'=>$data['orderLookup'] ?? ($row->value['orderLookup'] ?? ['isEnabled'=>false,'allowedFields'=>[]]), 'discountKnowledge'=>$data['discountKnowledge'] ?? ($row->value['discountKnowledge'] ?? ['isEnabled'=>false,'activeIds'=>[]]), 'productCatalog' => $data['productCatalog'] ?? ($row->value['productCatalog'] ?? ['isEnabled'=>true,'canCheckLiveStock'=>true,'canExplainSizingAndMaterials'=>true,'canRecommendUpsells'=>true,'maxRecommendations'=>3]), 'greetingMessage' => $data['greetingMessage'] ?? ($row->value['greetingMessage'] ?? $this->defaultGreeting()), 'version' => $data['version'] + 1, 'faq' => $faq, 'personality' => array_replace($this->defaultPersonality(), $row->value['personality'] ?? [], $data['personality'] ?? []), 'primaryLanguage' => $data['primaryLanguage'] ?? ($row->value['primaryLanguage'] ?? 'auto_detect'), 'autoReplyEnabled' => $data['autoReplyEnabled'] ?? ($row->value['autoReplyEnabled'] ?? false), 'identity' => $data['identity'] ?? ($row->value['identity'] ?? null)];
            $row->update(['value' => $result]);

            return $result;
        });
    }

    public function defaultPersonality(): array
    {
        return [
            'humanHandoffTriggers'=>['onExplicitRequest'=>true,'onFrustrationDetected'=>true,'onRefundOrDispute'=>true,'onServiceUnavailable'=>true,'consecutiveFailureCount'=>2,'urgentKeywords'=>[]],
            'fallbackHandoffMessage'=>['en'=>'Your request has been submitted. Please wait for our support team to take over.','ms'=>'Permintaan anda telah dihantar. Sila tunggu pasukan khidmat pelanggan.','zh'=>'已提交人工请求，请等待客服接手。'],
            'toneOfVoice' => 'friendly_helpful', 'customToneInstructions' => '', 'maxOutputTokens' => 350, 'brandMissionPrompt' => '',
            'unknownAnswerMessage' => ['en' => "I couldn't find a confirmed answer in our FAQ. Please leave your question here for our support team.", 'ms' => 'Jawapan yang disahkan belum tersedia dalam FAQ kami. Sila tinggalkan soalan untuk pasukan sokongan.', 'zh' => '暂时无法从 FAQ 确认答案。请留下问题，我们的客服团队会协助你。'],
            'serviceUnavailableMessage' => ['en' => 'Automatic assistance is temporarily unavailable. Your message has been saved for our support team.', 'ms' => 'Bantuan automatik tidak tersedia buat sementara. Mesej anda telah disimpan untuk pasukan sokongan.', 'zh' => '自动客服暂时无法使用。你的消息已保存，客服团队会协助你。'],
        ];
    }

    public function defaultGreeting(): array
    {
        return [
            'en' => 'Hello! Welcome to JW Studio. How can we help you today?',
            'ms' => 'Selamat datang ke JW Studio! Bagaimana kami boleh membantu anda hari ini?',
            'zh' => '您好！欢迎来到 JW Studio。今天有什么可以帮您？',
        ];
    }

    public function publicFaq(): array
    {
        $settings = $this->read();
        $identity = $settings['identity'] ?? ['botName' => 'JW Studio Support', 'roleTitle' => 'Let’s find what you need'];
        $faq = $settings['faq'];
        if (! $faq || ! $faq['isEnabled']) {
            return ['greetingMessage' => $settings['greetingMessage'] ?? $this->defaultGreeting(), 'primaryLanguage' => $settings['primaryLanguage'] ?? 'auto_detect', 'identity' => $identity, 'categories' => []];
        }

        return ['greetingMessage' => $settings['greetingMessage'] ?? $this->defaultGreeting(), 'primaryLanguage' => $settings['primaryLanguage'] ?? 'auto_detect', 'identity' => $identity, 'categories' => array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['label'], 'items' => array_values(array_map(fn ($i) => ['id' => $i['id'], 'question' => $i['question'], 'answer' => $i['answer']], array_filter($faq['items'], fn ($i) => $i['isActive'] && $i['category'] === $c['id'])))], $faq['categories'])];
    }
}
