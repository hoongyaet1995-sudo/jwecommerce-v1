<?php

namespace App\Services\Support;

use App\Models\SupportConversation;

class SupportEscalationPolicy
{
    public function rules(array $settings = []): array
    {
        $defaults = app(\App\Services\SupportFaqService::class)->defaultPersonality()['humanHandoffTriggers'];
        return array_replace($defaults, $settings['personality']['humanHandoffTriggers'] ?? []);
    }

    public function requested(string $text, array $settings = []): bool
    {
        return $this->rules($settings)['onExplicitRequest'] && (bool) preg_match('/\b(human agent|live agent|real person|talk to (?:a |an )?(?:human|person|agent)|want (?:a |an )?human|ejen manusia)\b|转人工|找人工|人工客服|真人客服/iu', $text);
    }

    public function reason(SupportConversation $conversation, string $text, array $settings = []): ?string
    {
        $rules = $this->rules($settings);
        if ($rules['onRefundOrDispute'] && preg_match('/\b(refund dispute|payment dispute|change my paid order)\b|退款纠纷|付款纠纷|修改已付款订单/iu', $text)) return 'human_required';
        if ($rules['onFrustrationDetected'] && $conversation->messages()->where('sender_role', 'ai')->exists()
            && preg_match('/\b(not resolved|you misunderstood|didn.t help|still not solved)\b|没解决|没有解决|你误解|答非所问/iu', $text)) return 'unresolved';
        foreach ($rules['urgentKeywords'] as $keyword) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/iu', $text)) return 'custom_keyword';
        }
        return null;
    }

    public function enabledAction(array $action, SupportConversation $conversation, array $settings): bool
    {
        if (($action['type']??'') !== 'request_human') return true;
        $rules = $this->rules($settings);
        return match ($action['reason']??'') {
            'explicit_request' => $rules['onExplicitRequest'],
            'unresolved' => $rules['onFrustrationDetected'],
            'human_required' => $rules['onRefundOrDispute'],
            'service_unavailable' => $rules['onServiceUnavailable'],
            'custom_keyword' => count($rules['urgentKeywords']) > 0,
            'unanswered_twice' => $rules['consecutiveFailureCount'] > 0 && $this->failureStreak($conversation) >= $rules['consecutiveFailureCount'],
            default => false,
        };
    }

    private function failureStreak(SupportConversation $conversation): int
    {
        $count=0;
        foreach ($conversation->messages()->whereIn('sender_role',['ai','human_agent'])->latest('id')->limit(5)->get() as $message) {
            if ($message->sender_role !== 'ai' || !collect($message->actions??[])->contains(fn($a)=>($a['type']??'')==='unanswered')) break;
            $count++;
        }
        return $count;
    }

    public function actions(SupportConversation $conversation, bool $unanswered, ?string $reason, array $settings = []): array
    {
        $rules = $this->rules($settings);
        if ($reason === 'service_unavailable' && ! $rules['onServiceUnavailable']) return [];
        if ($reason) return [['type'=>'request_human', 'reason'=>$reason]];
        if (! $unanswered) return [];
        $threshold = (int) $rules['consecutiveFailureCount'];
        $streak = 1;
        foreach ($conversation->messages()->whereIn('sender_role', ['ai','human_agent'])->latest('id')->limit(5)->get() as $last) {
            if ($last->sender_role !== 'ai' || ! collect($last->actions ?? [])->contains(fn ($a) => ($a['type'] ?? '') === 'unanswered')) break;
            $streak++;
        }
        return array_merge([['type'=>'unanswered']], $threshold > 0 && $streak >= $threshold ? [['type'=>'request_human','reason'=>'unanswered_twice']] : []);
    }
}
