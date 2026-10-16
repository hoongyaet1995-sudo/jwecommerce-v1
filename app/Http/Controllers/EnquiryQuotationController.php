<?php

namespace App\Http\Controllers;

use App\Models\EnquiryQuotation;
use App\Models\EnquiryQuotationVersion;
use App\Models\ProductEnquiry;
use App\Services\EnquiryQuotationService;
use App\Services\ProductEnquiryService;
use App\Services\ProductEnquirySupportService;
use App\Services\WebsiteDesignService;
use Illuminate\Http\Request;

class EnquiryQuotationController extends Controller
{
    private function render(Request $r, ProductEnquiry $enquiry, array $document, ?EnquiryQuotationVersion $version = null, ?array $state = null)
    {
        $r->attributes->set('supportEnquiryContext', $enquiry);
        $theme = app(WebsiteDesignService::class)->surface();
        $r->attributes->set('websiteRenderedTheme', $theme);

        return response()->view('storefront.quotations.document', compact('enquiry', 'document', 'version', 'state'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function show(Request $r, EnquiryQuotationVersion $quotationVersion, ProductEnquiryService $access)
    {
        $e = $quotationVersion->quotation->enquiry;
        abort_unless($access->canView($r, $e), 404);

        return $this->render($r, $e, $quotationVersion->document, $quotationVersion);
    }

    public function preview(Request $r, ProductEnquiry $enquiry, EnquiryQuotationService $s)
    {
        $state = $s->view($enquiry);
        $q = EnquiryQuotation::where('product_enquiry_id', $enquiry->id)->first();
        abort_unless($q, 404);
        $version = $q->status === 'sent' ? $q->versions()->first() : null;

        return $this->render($r, $enquiry, $version?->document ?? array_replace($state['document'], ['number' => $state['number']]), $version, $state);
    }

    public function previewVersion(Request $r, EnquiryQuotationVersion $quotationVersion)
    {
        return $this->render($r, $quotationVersion->quotation->enquiry, $quotationVersion->document, $quotationVersion, ['status' => 'sent']);
    }

    public function send(Request $r, ProductEnquiry $enquiry, EnquiryQuotationService $s)
    {
        $data = $r->validate(['lockVersion' => 'required|integer|min:1']);
        $s->send($enquiry, $data['lockVersion'], $r->user()->id);

        return redirect()->route('admin.quotations.preview', $enquiry);
    }

    public function support(Request $r, EnquiryQuotationVersion $quotationVersion, ProductEnquirySupportService $s)
    {
        $s->connect($r,$quotationVersion->quotation->enquiry);

        return redirect()->route('quotations.show',['quotationVersion' => $quotationVersion, 'support' => 1]);
    }
}
