<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductEnquiry;
use App\Services\EnquiryQuotationService;
use Illuminate\Http\Request;

class EnquiryQuotationController extends Controller
{
    public function show(ProductEnquiry $enquiry, EnquiryQuotationService $s)
    {
        return response()->json(['data' => $s->view($enquiry)])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $r, ProductEnquiry $enquiry, EnquiryQuotationService $s)
    {
        $data = $r->validate(['contextHash' => 'required|string|size:64', 'lockVersion' => 'required|integer|min:0', 'validUntil' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:2000', 'items' => 'required|array|min:1|max:100', 'items.*' => 'required|array:name,description,qty,price', 'items.*.name' => 'required|string|max:160', 'items.*.description' => 'nullable|string|max:220', 'items.*.qty' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'], 'items.*.price' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,3})?$/D']]);

        return response()->json(['data' => $s->save($enquiry, $data, $r->user()->id)])->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $r, ProductEnquiry $enquiry, EnquiryQuotationService $s)
    {
        $data = $r->validate(['lockVersion' => 'required|integer|min:1']);

        return response()->json(['data' => $s->send($enquiry, $data['lockVersion'], $r->user()->id)])->header('Cache-Control', 'private, no-store');
    }

    public function revise(Request $r, ProductEnquiry $enquiry, EnquiryQuotationService $s)
    {
        $data = $r->validate(['lockVersion' => 'required|integer|min:1']);

        return response()->json(['data' => $s->revise($enquiry, $data['lockVersion'], $r->user()->id)])->header('Cache-Control','private, no-store');
    }
}
