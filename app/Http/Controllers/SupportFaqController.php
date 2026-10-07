<?php

namespace App\Http\Controllers;

use App\Services\SupportFaqService;
use Illuminate\Http\Request;

class SupportFaqController extends Controller
{
    public function index(Request $request, SupportFaqService $service)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $settings=$service->read();
        $readiness=\Illuminate\Support\Facades\Schema::hasTable('ai_connections')?app(\App\Services\Support\SupportAttachmentAnalyzer::class)->readiness($settings):['image'=>false,'document'=>false,'audio'=>false,'provider'=>null,'model'=>null];
        return response()->json($settings + ['attachmentReadiness'=>$readiness,'discountRecords'=>app(\App\Services\Support\DiscountKnowledgeService::class)->records()])->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, SupportFaqService $service)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json($service->save($request->all()))->header('Cache-Control', 'no-store');
    }

    public function show(SupportFaqService $service)
    {
        return response()->json($service->publicFaq())->header('Cache-Control', 'no-store');
    }
}
