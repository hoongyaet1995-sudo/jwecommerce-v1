<?php

namespace App\Services\Support;

use App\Models\ProductEnquiry;
use App\Models\SupportConversation;
use App\Models\SupportEnquiryIntake;
use Illuminate\Support\Facades\Schema;

/** Server-owned enquiry scope; client flags never confer access. */
class EnquiryReception
{
    public const FIELDS = ['product', 'quantity', 'purpose', 'location', 'timing', 'requirements'];

    public function settings(array $settings): array
    {
        return array_replace_recursive(['isEnabled' => false, 'websiteFaq' => true, 'productFaq' => true, 'collectDetails' => true, 'summarize' => true, 'autoHandoff' => true, 'fields' => ['product' => 'required', 'quantity' => 'required', 'purpose' => 'optional', 'location' => 'required', 'timing' => 'optional', 'requirements' => 'optional']], $settings['enquiryAccess'] ?? []);
    }

    public function enquiry(SupportConversation $c): ?ProductEnquiry
    {
        return Schema::hasTable('product_enquiries') ? ProductEnquiry::where('support_conversation_id', $c->id)->first() : null;
    }

    public function eligible(SupportConversation $c, array $settings): bool
    {
        if (! in_array($c->status, ['open', 'waiting_human'], true) || ! $this->enquiry($c) || ! $this->settings($settings)['isEnabled'] || ! app(SupportAiPolicy::class)->enabled($settings, $c->channel)) {
            return false;
        }
        if ($c->notes()->where('content', 'like', '[Archive] %')->latest('id')->value('content') === '[Archive] true') {
            return false;
        }

        return Schema::hasTable('support_enquiry_intakes') && ! SupportEnquiryIntake::where('conversation_id', $c->id)->where('ai_suspended', true)->exists();
    }

    public function card(SupportConversation $c): ?array
    {
        $e = $this->enquiry($c);
        if (! $e) {
            return null;
        }$s = $e->product_snapshot ?? [];
        $handle = $s['handle'] ?? '';
        $image = $s['image'] ?? null;
        $safeImage = is_string($image) && ((str_starts_with($image, '/') && ! str_starts_with($image, '//') && ! str_contains($image, '\\')) || str_starts_with($image, 'https://')) && ! preg_match('/[\x00-\x20]/', $image);

        return ['reference' => $e->reference, 'title' => $e->product_title, 'variant' => $s['variant'] ?? null, 'sku' => $s['sku'] ?? null, 'image' => $safeImage ? $image : null, 'url' => preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]*\z/D', $handle) ? '/products/'.$handle : null];
    }

    public function scope(array $settings): array
    {
        $p = $this->settings($settings);
        $settings['faq']['isEnabled'] = ($settings['faq']['isEnabled'] ?? false) && $p['websiteFaq'];
        $settings['productCatalog']['isEnabled'] = ($settings['productCatalog']['isEnabled'] ?? false) && $p['productFaq'];
        // An enquiry is not permission to disclose private orders or broaden sales tools.
        $settings['orderLookup']['isEnabled'] = false;
        $settings['discountKnowledge']['isEnabled'] = false;

        return $settings;
    }
}
