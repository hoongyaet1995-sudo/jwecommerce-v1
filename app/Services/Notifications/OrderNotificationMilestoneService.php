<?php

namespace App\Services\Notifications;

use App\Domain\Notifications\NotificationDeliveryException;
use App\Domain\Notifications\NotificationEventCatalog;
use App\Jobs\SendOrderNotificationJob;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrderNotificationMilestoneService
{
    public function __construct(
        private readonly NotificationContextFactory $contextFactory,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly NotificationTemplateDefaults $defaults,
    ) {}

    /** @param array<string, mixed> $extra */
    public function record(
        Order $order,
        string $eventKey,
        string $sourceType,
        string|int $sourceId,
        ?User $actor = null,
        array $extra = [],
    ): ?NotificationDispatch {
        $event = NotificationEventCatalog::get($eventKey);

        if (! $event['automatic_email']) {
            return null;
        }

        $template = NotificationTemplate::query()->where('event_key', $eventKey)->first();

        if ($template === null) {
            $this->defaults->synchronize();
            $template = NotificationTemplate::query()->where('event_key', $eventKey)->first();
        }

        if ($template === null || ! $template->email_enabled) {
            return null;
        }

        $context = $this->contextFactory->forOrder($order, array_merge([
            'status_name' => (string) $template->status_name,
            'staff_note' => 'No additional notes.',
        ], $extra));
        $rendered = $this->renderer->render($template, $context);
        $idempotencyKey = $this->automaticIdempotencyKey($order, $eventKey, $sourceType, $sourceId);

        $dispatch = NotificationDispatch::query()->firstOrCreate([
            'idempotency_key' => $idempotencyKey,
        ], [
            'order_id' => $order->id,
            'notification_template_id' => $template->id,
            'channel' => 'email',
            'event_key' => $eventKey,
            'source_type' => $sourceType,
            'source_id' => (string) $sourceId,
            'recipient_email' => $context['customer_email'],
            'recipient_name' => $context['customer_name'] ?: null,
            'rendered_subject' => $rendered->subject,
            'rendered_preheader' => $rendered->preheader,
            'rendered_heading' => $rendered->heading,
            'rendered_body' => $rendered->body,
            'rendered_cta_text' => $rendered->ctaText,
            'rendered_cta_url' => $rendered->ctaUrl,
            'template_version' => $template->version,
            'snapshot_version' => NotificationDispatch::SNAPSHOT_VERSION_PLAIN_TEXT,
            'variable_snapshot' => $rendered->variables,
            'state' => NotificationDispatch::STATE_QUEUED,
            'source' => NotificationDispatch::SOURCE_AUTOMATIC,
            'attempt_count' => 0,
            'queue_attempt' => null,
            'queue_generation' => (string) Str::uuid(),
            'queued_at' => now(),
            'requested_by' => $actor?->id,
        ]);

        if ($dispatch->wasRecentlyCreated) {
            DB::afterCommit(static function () use ($dispatch): void {
                try {
                    SendOrderNotificationJob::dispatch(
                        $dispatch->id,
                        $dispatch->queue_generation,
                    )->afterCommit();
                } catch (Throwable) {
                    $errorCode = 'queue';

                    try {
                        $updated = NotificationDispatch::query()
                            ->whereKey($dispatch->id)
                            ->where('state', NotificationDispatch::STATE_QUEUED)
                            ->update([
                                'state' => NotificationDispatch::STATE_FAILED,
                                'failed_at' => now(),
                                'last_error_code' => 'queue',
                                'delivery_lease_token' => null,
                                'delivery_lease_expires_at' => null,
                            ]);

                        if ($updated !== 1) {
                            $errorCode = 'unknown';
                        }
                    } catch (Throwable) {
                        $errorCode = 'unknown';
                    }

                    self::reportSafely(new NotificationDeliveryException($errorCode));
                }
            });
        }

        return $dispatch;
    }

    private static function reportSafely(NotificationDeliveryException $exception): void
    {
        try {
            report($exception);
        } catch (Throwable) {
            // The committed dispatch state is authoritative; reporting is best-effort only.
        }
    }

    private function automaticIdempotencyKey(
        Order $order,
        string $eventKey,
        string $sourceType,
        string|int $sourceId,
    ): string {
        $key = "automatic:email:order:{$order->id}:event:{$eventKey}";

        if ($eventKey === 'payment_proof_rejected') {
            $key .= ":source:{$sourceType}:{$sourceId}";
        }

        return $key;
    }
}
