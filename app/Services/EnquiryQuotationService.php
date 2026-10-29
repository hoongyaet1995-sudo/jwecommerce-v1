<?php

namespace App\Services;

use App\Models\EnquiryQuotation;
use App\Models\EnquiryQuotationVersion;
use App\Models\ProductEnquiry;
use App\Models\SupportConversation;
use App\Support\CurrencySettings;
use App\Support\ShopBrand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnquiryQuotationService
{
    private function contextHash(array $document): string
    {
        return hash('sha256', json_encode($document['currency']));
    }

    public function initial(ProductEnquiry $e): array
    {
        $currency = app(CurrencySettings::class)->default();
        $dp = max(0, min(3, (int) $currency['decimalPlaces']));

        return ['merchant' => app(ShopBrand::class)->name(), 'customer' => ['name' => $e->name, 'email' => $e->email], 'enquiryRef' => $e->reference, 'title' => $e->product_title, 'requirements' => $e->requirements, 'currency' => ['code' => $currency['code'], 'decimals' => $dp, 'left' => $currency['symbolLeft'], 'right' => $currency['symbolRight']], 'validUntil' => now('Asia/Kuala_Lumpur')->addDays(14)->toDateString(), 'notes' => '', 'items' => [['name' => $e->product_title, 'description' => $e->product_snapshot['variant'] ?? '', 'qty' => '1', 'price' => '', 'amountMinor' => 0]], 'totalMinor' => 0];
    }

    public function view(ProductEnquiry $e): array
    {
        $q = EnquiryQuotation::where('product_enquiry_id', $e->id)->with('versions')->first();
        $document = $q?->document ?? $this->initial($e);
        $accepted=$q?\App\Models\QuotationAcceptance::where('enquiry_quotation_id',$q->id)->first():null;

        return ['accepted'=>(bool)$accepted,'orderNumber'=>$accepted?->order?->number,'enquiryId' => $e->id, 'contextHash' => $this->contextHash($document), 'number' => $q?->number, 'status' => $q?->status ?? 'draft', 'revision' => $q?->revision ?? 1, 'lockVersion' => $q?->lock_version ?? 0, 'document' => $document, 'versions' => $q ? $q->versions->map(fn ($v) => ['id' => $v->id, 'revision' => $v->revision, 'sentAt' => $v->sent_at->toIso8601String(), 'expired' => $v->expires_at->isPast(), 'url' => route('quotations.show', $v, false)])->all() : []];
    }

    private function scaled(string $value, int $dp, string $key): int
    {
        if (! preg_match('/^\d{1,8}(?:\.\d{1,'.max(1, $dp).'})?$/D', $value) || ($dp === 0 && str_contains($value, '.'))) {
            throw ValidationException::withMessages([$key => 'Enter a non-negative amount with at most '.$dp.' decimal places.']);
        }
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * (10 ** $dp) + (int) str_pad($fraction, $dp, '0');
    }

    private function document(array $context, array $input): array
    {
        $dp = $context['currency']['decimals'];
        $items = [];
        $total = 0;
        foreach ($input['items'] as $i => $item) {
            $qty = $this->scaled((string) $item['qty'], 2, "items.$i.qty");
            $price = $this->scaled((string) $item['price'], $dp, "items.$i.price");
            if ($qty <= 0 || $qty > 100000000 || $price > 10000000 * (10 ** $dp)) {
                throw ValidationException::withMessages(["items.$i.qty" => 'Quantity or price exceeds the supported range.']);
            }
            $line = intdiv($qty * $price + 50, 100);
            $total += $line;
            if ($total > 99999999999) {
                throw ValidationException::withMessages(['items' => 'Quotation total is too large.']);
            }
            $items[] = ['name' => trim($item['name']), 'description' => trim($item['description'] ?? ''), 'qty' => (string) $item['qty'], 'price' => (string) $item['price'], 'amountMinor' => $line];
        }

        return array_replace($context, ['validUntil' => $input['validUntil'], 'notes' => trim($input['notes'] ?? ''), 'items' => $items, 'totalMinor' => $total]);
    }

    public function save(ProductEnquiry $e, array $input, int $actor): array
    {
        DB::transaction(function () use ($e, $input, $actor) {
            ProductEnquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $q = EnquiryQuotation::where('product_enquiry_id', $e->id)->lockForUpdate()->first();
            abort_if($q&&\App\Models\QuotationAcceptance::where('enquiry_quotation_id',$q->id)->exists(),409,'This quotation was accepted. Its agreed scope is locked.');
            abort_unless(($q?->lock_version ?? 0) === $input['lockVersion'], 409, 'This quotation changed. Reload before saving.');
            abort_if($q && $q->status !== 'draft', 409, 'Create a revision to change a sent quotation.');
            $context = $q?->document ?? $this->initial($e);
            abort_unless(hash_equals($this->contextHash($context), $input['contextHash']), 409, 'Quotation currency settings changed. Reload and confirm the prices before saving.');
            $doc = $this->document($context, $input);
            if (! $q) {
                $q = EnquiryQuotation::create(['id' => (string) Str::uuid(), 'product_enquiry_id' => $e->id, 'number' => 'QT-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)), 'document' => $doc]);
            } else {
                $q->document = $doc;
                $q->lock_version++;
                $q->save();
            }
            $e->events()->create(['actor_id' => $actor, 'type' => 'quotation_saved', 'content' => 'Quotation '.$q->number.' v'.$q->revision.' draft saved.']);
        }, 3);

        return $this->view($e);
    }

    public function send(ProductEnquiry $e, int $lock, int $actor): array
    {
        DB::transaction(function () use ($e, $lock, $actor) {
            $row = ProductEnquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $q = EnquiryQuotation::where('product_enquiry_id', $e->id)->lockForUpdate()->firstOrFail();
            if ($q->status === 'sent') {
                return;
            }
            abort_if(\App\Models\QuotationAcceptance::where('enquiry_quotation_id',$q->id)->exists(),409,'This quotation was accepted. Its agreed scope is locked.');
            abort_unless($q->lock_version === $lock, 409, 'This quotation changed. Reload before sending.');
            if ($q->document['validUntil'] < now('Asia/Kuala_Lumpur')->toDateString()) {
                throw ValidationException::withMessages(['validUntil' => 'The quotation has expired. Choose a new valid-until date.']);
            }
            $v = EnquiryQuotationVersion::create(['id' => (string) Str::uuid(), 'enquiry_quotation_id' => $q->id, 'revision' => $q->revision, 'document' => array_replace($q->document, ['number' => $q->number]), 'sent_by' => $actor, 'sent_at' => now(), 'expires_at' => Carbon::parse($q->document['validUntil'], 'Asia/Kuala_Lumpur')->endOfDay()->utc()]);
            $q->status = 'sent';
            $q->lock_version++;
            $q->save();
            $row->events()->create(['actor_id' => $actor, 'type' => 'quotation_sent', 'content' => 'Quotation '.$q->number.' v'.$q->revision.' published to the customer enquiry.']);
            try { $versionId=\App\Models\EnquiryQuotationVersion::where('enquiry_quotation_id',$q->id)->where('revision',$q->revision)->value('id');if($versionId)app(\App\Services\Marketing\MarketingAttribution::class)->enquiryStage($row->id,'quotation_sent',$versionId); } catch (\Throwable) { \Illuminate\Support\Facades\Log::warning('Optional quotation analytics unavailable'); }

            if ($row->support_conversation_id) {
                $this->deliver($v, SupportConversation::findOrFail($row->support_conversation_id));
            }
        }, 3);

        return $this->view($e);
    }

    public function revise(ProductEnquiry $e, int $lock, int $actor): array
    {
        DB::transaction(function () use ($e, $lock, $actor) {
            ProductEnquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $q = EnquiryQuotation::where('product_enquiry_id', $e->id)->lockForUpdate()->firstOrFail();
            abort_if(\App\Models\QuotationAcceptance::where('enquiry_quotation_id',$q->id)->exists(),409,'This quotation was accepted. Its agreed scope is locked.');
            abort_unless($q->status === 'sent' && $q->lock_version === $lock, 409, 'This quotation changed. Reload before creating a revision.');
            $q->status = 'draft';
            $q->revision++;
            $q->lock_version++;
            $q->save();
            $e->events()->create(['actor_id' => $actor, 'type' => 'quotation_revised', 'content' => 'Quotation '.$q->number.' v'.$q->revision.' draft created; sent versions retained.']);
        }, 3);

        return $this->view($e);
    }

    public function deliver(EnquiryQuotationVersion $version, SupportConversation $conversation): void
    {
        $v = EnquiryQuotationVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
        if ($v->support_message_id) {
            return;
        }
        $c = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
        $url = route('quotations.show', $v, false);
        $m = $c->messages()->create(['client_message_id' => $v->id, 'sender_role' => 'human_agent', 'staff_user_id' => $v->sent_by, 'content' => 'Your quotation '.$v->document['number'].' · v'.$v->revision.' is ready. Please review the items, scope and valid-until date.', 'actions' => [['type' => 'view_quotation', 'label' => 'View quotation', 'url' => $url]]]);
        $v->support_message_id = $m->id;
        $v->save();
        if ($c->status === 'resolved') {
            $c->status = 'waiting_human';
            $c->notes()->create(['staff_user_id' => $v->sent_by, 'content' => '[Control] resolved → waiting_human (quotation published)']);
        }$c->last_message_at = now();
        $c->save();
    }

    public function deliverPending(ProductEnquiry $e, SupportConversation $c): void
    {
        if (! Schema::hasTable('enquiry_quotation_versions')) {
            return;
        }
        $q = EnquiryQuotation::where('product_enquiry_id', $e->id)->first();
        if (! $q) {
            return;
        }
        foreach ($q->versions()->whereNull('support_message_id')->reorder('revision')->get() as $v) {
            $this->deliver($v,$c);
        }
    }
}
