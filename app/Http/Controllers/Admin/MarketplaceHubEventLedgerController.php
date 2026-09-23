<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class MarketplaceHubEventLedgerController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $events = DB::table('marketplace_hub_event_inbox as e')
            ->join('marketplace_stores as s', 's.id', '=', 'e.store_id')
            ->leftJoin('marketplace_orders as o', function ($join) {
                $join->on('o.store_id', '=', 'e.store_id')
                    ->on('o.external_order_id', '=', 'e.external_order_id');
            })
            ->leftJoin('marketplace_order_inventory as i', 'i.order_id', '=', 'o.id')
            ->select('e.hub_event_id', 'e.event_type', 'e.external_order_id', 'e.status',
                'e.created_at', 'e.processed_at', 's.platform', 's.shop_name',
                'o.id as order_id', 'i.status as inventory_status', 'i.error_code')
            ->selectSub(function ($query) {
                $query->from('marketplace_inventory_items as item')->selectRaw('count(*)')
                    ->whereColumn('item.order_id', 'o.id')->whereIn('item.status', ['skipped_unmapped','skipped_historical']);
            }, 'skipped_items')
            ->orderByDesc('e.id')->limit(100)->get()->map(fn ($event) => [
                'id' => $event->hub_event_id,
                'type' => $event->event_type,
                'externalOrderId' => $event->external_order_id,
                'platform' => $event->platform,
                'storeName' => $event->shop_name,
                'status' => $event->status === 'failed' ? 'failed'
                    : ($event->status !== 'completed' ? 'processing'
                        : ($event->inventory_status === 'exception' || $event->skipped_items > 0 ? 'warning' : 'success')),
                'deliveryStatus' => $event->status,
                'orderId' => $event->order_id,
                'inventoryStatus' => $event->inventory_status,
                'attentionCode' => $event->error_code ?: ($event->skipped_items > 0 ? 'inventory_line_skipped' : null),
                'receivedAt' => CarbonImmutable::parse($event->created_at, 'UTC')->toIso8601String(),
                'processedAt' => $event->processed_at === null ? null
                    : CarbonImmutable::parse($event->processed_at, 'UTC')->toIso8601String(),
            ]);

        return response()->json(['events' => $events])->header('Cache-Control', 'private, no-store');
    }
}
