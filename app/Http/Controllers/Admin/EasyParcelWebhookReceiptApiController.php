<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Shipping\ShippingPermission;
use App\Http\Controllers\Controller;
use App\Services\Admin\EasyParcelWebhookReceiptDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EasyParcelWebhookReceiptApiController extends Controller
{
    public function __invoke(Request $request, EasyParcelWebhookReceiptDataService $data): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::VIEW), 403);

        return response()->json(['data' => $data->collection()->values()]);
    }
}
