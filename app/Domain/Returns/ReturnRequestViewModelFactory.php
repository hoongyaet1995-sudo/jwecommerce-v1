<?php

namespace App\Domain\Returns;

use App\Models\ReturnRequest;

class ReturnRequestViewModelFactory
{
    public function make(?ReturnRequest $r): ?array
    {
        if (! $r) {
            return null;
        } $r->loadMissing(['item', 'evidence', 'events', 'replacementOrder']);

        return ['id' => (string) $r->id, 'number' => $r->number, 'status' => $r->status, 'resolution' => $r->resolution, 'quantity' => $r->quantity, 'reason' => $r->reason, 'details' => $r->details, 'eligibleUntil' => $r->eligible_until?->toIso8601String(), 'receivedQuantity' => $r->received_quantity, 'condition' => $r->condition, 'warehouseNote' => $r->warehouse_note, 'rejectionReason' => $r->rejection_reason, 'replacementOrderId' => $r->replacement_order_id ? (string) $r->replacement_order_id : null, 'replacementOrderNumber' => $r->replacementOrder?->number, 'refundedAmount' => $r->resolvedRefund?->amount ?? null, 'externalRefundReference' => $r->external_refund_reference, 'manualRefundNote' => $r->manual_refund_note, 'createdAt' => $r->created_at?->toIso8601String(), 'item' => ['id' => (string) $r->item->id, 'title' => $r->item->title, 'variantTitle' => $r->item->variant_title, 'sku' => $r->item->sku, 'image' => $r->item->image_url, 'unitPrice' => $r->item->unit_price / 100], 'evidence' => $r->evidence->map(fn ($e) => ['id' => (string) $e->id, 'name' => $e->original_name, 'mimeType' => $e->mime_type])->values(), 'events' => $r->events->sortBy('id')->map(fn ($e) => ['id' => (string) $e->id, 'type' => $e->event_type, 'actorType' => $e->actor_type, 'note' => $e->note, 'at' => $e->created_at?->toIso8601String()])->values()];
    }
}
