<?php

namespace App\Http\Controllers;

use App\Models\EnquiryQuotationVersion;
use App\Models\OrderPayment;
use App\Services\Payments\HostedPaymentGatewayManager;
use App\Services\Payments\PaymentProofService;
use App\Services\ProductEnquiryService;
use App\Services\QuotationCheckoutService;
use App\Services\WebsiteDesignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationCheckoutController extends Controller
{
    private function owner(Request $r, EnquiryQuotationVersion $v): void
    {
        abort_unless(app(ProductEnquiryService::class)->canView($r, $v->quotation->enquiry), 404);
        $r->attributes->set('supportEnquiryContext', $v->quotation->enquiry);
        $r->attributes->set('websiteRenderedTheme', app(WebsiteDesignService::class)->surface());
    }

    public function accept(Request $r, EnquiryQuotationVersion $quotationVersion, QuotationCheckoutService $s)
    {
        $this->owner($r, $quotationVersion);
        $s->accept($quotationVersion, $r->user('customer')?->id);

        return redirect()->route('quotations.checkout', $quotationVersion);
    }

    public function checkout(Request $r, EnquiryQuotationVersion $quotationVersion, QuotationCheckoutService $s)
    {
        $this->owner($r, $quotationVersion);
        $acceptance = $s->acceptance($quotationVersion);
        abort_unless($acceptance, 409, 'Please accept this quotation first.');
        if ($acceptance->order_id) {
            return redirect()->route('quotations.payment', $quotationVersion);
        }$s->eligibility($quotationVersion);

        return $this->view($quotationVersion, $acceptance, null, $s->methods($quotationVersion));
    }

    private function view($version, $acceptance, $order, $methods = [])
    {
        $view = $order ? 'storefront.quotations.checkout' : app(WebsiteDesignService::class)->view('checkout');
        $data = compact('version', 'acceptance', 'order', 'methods') + [
            'quotationCheckout' => true,
            'savedAddresses' => [],
            'checkoutEmail' => $version->document['customer']['email'],
        ];

        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $r, EnquiryQuotationVersion $quotationVersion, QuotationCheckoutService $s)
    {
        $this->owner($r, $quotationVersion);
        $data = $r->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:50', 'line_1' => 'nullable|string|max:255', 'line_2' => 'nullable|string|max:255', 'city' => 'nullable|string|max:255', 'state' => 'nullable|string|max:255', 'postal_code' => 'nullable|string|max:50', 'country' => 'nullable|string|size:2', 'note' => 'nullable|string|max:2000', 'payment_method' => 'required|string|max:50', 'bank_account_id' => 'nullable|string|max:100']);
        $order = $s->checkout($quotationVersion, $data);

        return redirect()->route('quotations.payment', $quotationVersion);
    }

    public function payment(Request $r, EnquiryQuotationVersion $quotationVersion, QuotationCheckoutService $s)
    {
        $this->owner($r, $quotationVersion);
        $a = $s->acceptance($quotationVersion);
        abort_unless($a?->order_id, 404);

        return $this->view($quotationVersion, $a, $a->order()->with('payments')->firstOrFail());
    }

    public function proof(Request $r, EnquiryQuotationVersion $quotationVersion, QuotationCheckoutService $s, PaymentProofService $proofs)
    {
        $this->owner($r, $quotationVersion);
        $a = $s->acceptance($quotationVersion);
        abort_unless($a?->order_id, 404);
        $o = $a->order()->firstOrFail();
        $p = $o->payments()->latest('id')->firstOrFail();
        abort_unless($o->status === 'pending_payment' && $p->gateway === 'bank_transfer' && $p->status === 'pending' && $p->proof_status !== 'verified' && data_get($p->configuration_snapshot, 'allowProofUpload'), 409);
        $data = $r->validate(['payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240']);
        $proofs->storeCheckoutUpload($p, $data['payment_proof']);

        return back()->with('status', 'Payment proof submitted for verification.');
    }

    public function pay(Request $r, EnquiryQuotationVersion $quotationVersion, QuotationCheckoutService $s, HostedPaymentGatewayManager $hosted)
    {
        $this->owner($r, $quotationVersion);
        $a = $s->acceptance($quotationVersion);
        abort_unless($a?->order_id, 404);
        $o = $a->order()->firstOrFail();
        abort_unless($o->status === 'pending_payment', 409);
        $p = $o->payments()->latest('id')->firstOrFail();
        abort_unless(in_array($p->gateway, ['stripe', 'paypal', 'billplz'], true) && $p->status === 'pending', 409);
        $r->session()->put('pending_payment_id', $p->id);
        $url = DB::transaction(function () use ($p) {
            $locked = OrderPayment::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $url = data_get($locked->payload, 'quotation_checkout_url');
            if ($url) {
                return $url;
            }if (data_get($locked->payload, 'quotation_starting')) {
                throw ValidationException::withMessages(['payment' => 'Payment initiation is being confirmed. Please contact our team before starting another payment.']);
            }$locked->payload = array_merge($locked->payload ?? [], ['quotation_starting' => now()->toIso8601String()]);
            $locked->save();

            return null;
        });
        if (! $url) {
            try {
                $url = $hosted->begin($p, $o);
                $p->refresh();
                $p->update(['payload' => array_merge($p->payload ?? [], ['quotation_checkout_url' => $url])]);
            } catch (\RuntimeException) {
                return back()->withErrors(['payment' => 'We could not confirm a payment link. Your order remains unpaid. Please contact our team before retrying.']);
            }
        }
        abort_unless(is_string($url) && str_starts_with($url,'https://'),502);

        return redirect()->away($url);
    }
}
