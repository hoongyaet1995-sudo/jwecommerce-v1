<?php
namespace App\Domain\Accounts;

use App\Models\Customer;
use App\Models\EnquiryQuotationVersion;

/** Customer identity is authoritative; a matching enquiry email never confers access. */
class CustomerQuotationPayloadFactory
{
    public function for(Customer $customer): array
    {
        if (! $customer->user_id) return [];

        return EnquiryQuotationVersion::query()
            ->whereNotNull('sent_at')
            ->whereHas('quotation.enquiry', fn ($query) => $query->where('customer_user_id', $customer->user_id))
            ->with('quotation.enquiry')
            ->orderByDesc('sent_at')
            ->get()->map(fn ($version) => [
                'id' => $version->id,
                'number' => $version->document['number'] ?? $version->quotation->number,
                'revision' => $version->revision,
                'sentAt' => $version->sent_at->toIso8601String(),
                'expiresAt' => $version->expires_at?->toIso8601String(),
                'expired' => $version->expires_at?->isPast() ?? false,
                'url' => route('quotations.show', $version, false),
            ])->all();
    }
}
