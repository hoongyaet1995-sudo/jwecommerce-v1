<?php
namespace App\Services\Support;

use App\Models\SupportAttachment;

class SupportAttachmentAnalysisPolicy
{
    public const DEFAULTS = ['image'=>false,'document'=>false,'audio'=>false,'pdfPages'=>10,'audioSeconds'=>120,'retentionDays'=>30,'transcriptionModel'=>'gpt-4o-mini-transcribe'];

    public function settings(array $settings): array
    {
        $value = array_replace(self::DEFAULTS, array_intersect_key($settings['attachmentAnalysis'] ?? [], self::DEFAULTS));
        foreach (['image','document','audio'] as $kind) $value[$kind] = $value[$kind] === true;
        foreach (['pdfPages'=>[1,20],'audioSeconds'=>[15,300],'retentionDays'=>[7,90]] as $key=>$bounds) {
            $value[$key] = max($bounds[0], min($bounds[1], (int)$value[$key]));
        }
        return $value;
    }

    public function eligible(SupportAttachment $attachment, array $settings): bool
    {
        $conversation = $attachment->conversation;
        return $conversation && $conversation->status === 'open'
            && app(SupportAiPolicy::class)->enabled($settings, $conversation->channel)
            && ($this->settings($settings)[$attachment->kind] ?? false)
            && ($attachment->kind !== 'audio' || $conversation->channel === 'whatsapp')
            && $attachment->status === 'ready' && $attachment->message_id !== null
            && $attachment->merchant_key === $conversation->merchant_key;
    }
}
