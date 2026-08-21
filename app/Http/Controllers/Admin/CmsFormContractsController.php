<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

final class CmsFormContractsController extends Controller
{
    public function __invoke(Page $page, PageRuntimePayloadFactory $payloads): JsonResponse
    {
        $payload = $payloads->forDraft($page);

        return response()->json(['data' => ['forms' => $payload['forms']]]);
    }
}
