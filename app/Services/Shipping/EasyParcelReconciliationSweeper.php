<?php

namespace App\Services\Shipping;

use App\Models\EasyParcelReconciliationTask;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingWebhookReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class EasyParcelReconciliationSweeper
{
    private const DISCOVERY_LIMIT = 100;

    public function __construct(
        private readonly EasyParcelReconciliationTaskService $tasks,
        private readonly EasyParcelReconciliationPublisher $publisher,
    ) {}

    public function sweep(): int
    {
        ShipmentQuote::query()
            ->where('provider', ShippingProviderConfigurationService::PROVIDER)
            ->where('environment', ShippingProviderConfigurationService::ENVIRONMENT)
            ->whereNotNull('booking_claim_key')
            ->whereDoesntHave('shipment')
            ->where(function ($query): void {
                $query->where('booking_state', 'uncertain')
                    ->orWhere(function ($inProgress): void {
                        $inProgress->where('booking_state', 'in_progress')
                            ->where('booking_started_at', '<=', now()->subMinutes(2));
                    });
            })
            ->orderBy('id')
            ->limit(self::DISCOVERY_LIMIT)
            ->get()
            ->each(fn (ShipmentQuote $quote) => $this->tasks->ensureBooking($quote));

        Shipment::query()
            ->where('source', ShippingProviderConfigurationService::PROVIDER)
            ->whereNotIn('status', ['delivered', 'returned', 'booking_failed', 'cancelled'])
            ->whereDoesntHave('failedBookingRecovery', function ($query): void {
                $query->whereIn('state', ['cancelling', 'awaiting_replacement', 'complete']);
            })
            ->where(function ($query): void {
                $query->whereNull('tracking_number')
                    ->orWhere('tracking_number', '')
                    ->orWhereNull('label_path')
                    ->orWhere('label_path', '');
            })
            ->orderBy('id')
            ->limit(self::DISCOVERY_LIMIT)
            ->get()
            ->each(function (Shipment $shipment): void {
                $task = $this->tasks->ensureShipmentDetails($shipment);
                if (blank($shipment->tracking_number)) {
                    $this->alertIfAwbOverdue($task, $shipment);
                }
            });

        ShippingWebhookReceipt::query()
            ->where('provider', ShippingProviderConfigurationService::PROVIDER)
            ->where('environment', ShippingProviderConfigurationService::ENVIRONMENT)
            ->whereIn('topic', [
                'shipment.created',
                'shipment.awb.update',
                'shipment.status.update',
                'shipment.tracking.update',
            ])
            ->whereIn('processing_state', ['received', 'queued', 'unmatched', 'retrying'])
            ->orderBy('id')
            ->limit(self::DISCOVERY_LIMIT)
            ->get()
            ->each(fn (ShippingWebhookReceipt $receipt) => $this->tasks->ensureWebhookReceipt($receipt));

        return $this->publisher->sweep();
    }

    private function alertIfAwbOverdue(EasyParcelReconciliationTask $task, Shipment $shipment): void
    {
        $threshold = max(15, (int) config('services.easyparcel.awb_alert_after_minutes', 60));
        if ($shipment->created_at === null || $shipment->created_at->gt(now()->subMinutes($threshold))) {
            return;
        }

        $alerted = DB::transaction(function () use ($task): bool {
            $locked = EasyParcelReconciliationTask::query()->lockForUpdate()->find($task->id);
            if (! $locked instanceof EasyParcelReconciliationTask || $locked->alerted_at !== null) {
                return false;
            }

            $locked->forceFill(['alerted_at' => now()])->save();

            return true;
        });

        if ($alerted) {
            Log::warning('EasyParcel shipment is still missing an AWB.', [
                'shipment_id' => $shipment->id,
                'order_id' => $shipment->order_id,
                'age_minutes' => $shipment->created_at->diffInMinutes(now()),
            ]);
        }
    }
}
