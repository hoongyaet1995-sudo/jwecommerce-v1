<?php

namespace App\Services;

use App\Models\EnquiryQuotation;
use App\Models\EnquiryQuotationVersion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductEnquiry;
use App\Models\QuotationAcceptance;
use App\Models\User;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationCheckoutService
{
    public function eligibility(EnquiryQuotationVersion $v): void
    {
        $doc = $v->document;
        if ($doc['currency']['decimals'] !== 2 || $doc['totalMinor'] <= 0 || $doc['totalMinor'] > 2147483647) {
            throw ValidationException::withMessages(['quotation' => 'This quotation needs a supported two-decimal currency and a positive payable total within the payment limit. Please contact our team.']);
        }
        $configured = collect(app(CurrencySettings::class)->all())->firstWhere('code', $doc['currency']['code']);
        if (! $configured || ! $configured['isEnabled'] || (int) $configured['decimalPlaces'] !== 2) {
            throw ValidationException::withMessages(['quotation' => 'This quotation currency is not currently available for payment. Please contact our team.']);
        }
    }

    public function methods(EnquiryQuotationVersion $v): array
    {
        $methods = app(PaymentMethodAvailabilityService::class)->checkoutMethods(['currency' => $v->document['currency']['code'], 'amount' => $v->document['totalMinor'], 'address' => []]);
        unset($methods['cod']);
        // Current offline bank configuration is MYR; never imply foreign-currency settlement.
        if ($v->document['currency']['code'] !== 'MYR') {
            unset($methods['bank_transfer']);
        }

        return $methods;
    }

    public function acceptance(EnquiryQuotationVersion $v): ?QuotationAcceptance
    {
        return QuotationAcceptance::where('enquiry_quotation_version_id', $v->id)->first();
    }

    public function accept(EnquiryQuotationVersion $v, ?int $actor): QuotationAcceptance
    {
        return DB::transaction(function () use ($v, $actor) {
            $q = $v->quotation;
            ProductEnquiry::whereKey($q->product_enquiry_id)->lockForUpdate()->firstOrFail();
            $q = EnquiryQuotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
            $existing = QuotationAcceptance::where('enquiry_quotation_id', $q->id)->first();
            if ($existing) {
                abort_unless($existing->enquiry_quotation_version_id === $v->id, 409, 'Another version of this quotation was already accepted.');

                return $existing;
            }
            abort_unless($q->versions()->first()?->id === $v->id, 409, 'A newer quotation is available. Return to your enquiry to view it.');
            if ($v->expires_at->isPast()) {
                throw ValidationException::withMessages(['quotation' => 'This quotation has expired. Please request an updated quotation.']);
            }
            $this->eligibility($v);
            if (! $this->methods($v)) {
                throw ValidationException::withMessages(['quotation' => 'No payment method is currently available for this quotation. Please contact our team.']);
            }
            $a = QuotationAcceptance::create(['enquiry_quotation_id' => $q->id, 'enquiry_quotation_version_id' => $v->id, 'accepted_by' => $actor, 'accepted_at' => now()]);
            $q->enquiry->events()->create(['actor_id' => null, 'type' => 'quotation_accepted', 'content' => 'Customer accepted '.$q->number.' v'.$v->revision.'. Manual fulfilment; no stock reserved.']);

            return $a;
        }, 3);
    }

    public function checkout(EnquiryQuotationVersion $v, array $data): Order
    {
        return DB::transaction(function () use ($v, $data) {
            $e = $v->quotation->enquiry;
            ProductEnquiry::whereKey($e->id)->lockForUpdate()->firstOrFail();
            $a = QuotationAcceptance::where('enquiry_quotation_version_id', $v->id)->lockForUpdate()->firstOrFail();
            if ($a->order_id) {
                return $a->order()->firstOrFail();
            }$this->eligibility($v);
            $method = $this->methods($v)[$data['payment_method']] ?? null;
            if (! $method) {
                throw ValidationException::withMessages(['payment_method' => 'This payment method is no longer available for the quotation.']);
            }
            $customer = $e->customer_user_id ? User::find($e->customer_user_id)?->customer : null;
            $doc = $v->document;
            $o = Order::create(['number' => 'JW-Q-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)), 'customer_id' => $customer?->id, 'email' => $doc['customer']['email'], 'currency' => $doc['currency']['code'], 'currency_exchange_rate' => 1, 'status' => 'pending_payment', 'shipping_method' => 'quoted_manual', 'shipping_label' => 'Manual fulfilment · as quoted', 'shipping_snapshot' => ['type' => 'quotation', 'quotation_version_id' => $v->id, 'fulfilment' => 'manual', 'inventory' => 'not_reserved'], 'customer_note' => $data['note'] ?? null, 'internal_note' => 'Quotation '.$doc['number'].' v'.$v->revision.' · Manual fulfilment. No automatic stock reservation.', 'subtotal' => $doc['totalMinor'], 'discount_amount' => 0, 'shipping_amount' => 0, 'payment_surcharge_amount' => 0, 'tax_amount' => 0, 'total' => $doc['totalMinor']]);
            OrderItem::create(['order_id' => $o->id, 'product_variant_id' => null, 'sku' => 'QUOTE-'.$doc['number'], 'title' => 'Quotation '.$doc['number'].' · v'.$v->revision, 'variant_title' => $doc['title'].' · Full agreed scope', 'quantity' => 1, 'unit_price' => $doc['totalMinor'], 'line_total' => $doc['totalMinor'], 'package_snapshot' => ['quotation_version_id' => $v->id, 'document' => $doc, 'fulfilment' => 'manual']]);
            $o->addresses()->create(['type' => 'shipping', 'first_name' => $data['name'], 'last_name' => '', 'phone' => $data['phone'] ?? null, 'line_1' => $data['line_1'] ?? 'As agreed in quotation', 'line_2' => $data['line_2'] ?? null, 'city' => $data['city'] ?? '', 'state' => $data['state'] ?? null, 'postal_code' => $data['postal_code'] ?? '', 'country' => $data['country'] ?? 'MY']);
            app(PaymentAttemptService::class)->createPendingPayment($o, $method, $o->currency, $data['bank_account_id'] ?? null);
            OrderStatusHistory::create(['order_id' => $o->id, 'to_status' => 'pending_payment', 'note' => 'Accepted quotation; awaiting verified payment. Manual fulfilment, no stock movement.']);
            $a->order_id = $o->id;
            $a->save();
            $e->events()->create(['type' => 'quotation_order_created', 'content' => 'Quotation converted to order '.$o->number.' (pending payment).']);

            return $o;
        }, 3);
    }
}
