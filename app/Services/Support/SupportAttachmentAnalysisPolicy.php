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

    public function conversationEligible(\App\Models\SupportConversation $conversation, array $settings): bool
    {
        if(app(EnquiryReception::class)->enquiry($conversation))return app(EnquiryReception::class)->eligible($conversation,$settings);
        return $conversation->status==='open' && app(SupportAiPolicy::class)->enabled($settings,$conversation->channel)
            && $conversation->notes()->where('content','like','[Archive] %')->latest('id')->value('content')!=='[Archive] true';
    }

    public function eligible(SupportAttachment $attachment, array $settings): bool
    {
        $conversation = $attachment->conversation;
        return $conversation && $this->conversationEligible($conversation,$settings)
            && ($this->settings($settings)[$attachment->kind] ?? false)
            && ($attachment->kind !== 'audio' || $conversation->channel === 'whatsapp')
            && $attachment->status === 'ready' && $attachment->message_id !== null
            && $attachment->merchant_key === $conversation->merchant_key;
    }
}
