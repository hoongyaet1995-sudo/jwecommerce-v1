<?php

namespace App\Services\Notifications;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Domain\Notifications\RenderedNotification;
use App\Jobs\SendOrderNotificationJob;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationDispatchService
{
    public function __construct(
        private readonly NotificationContextFactory $contextFactory,
        private readonly NotificationTemplateRenderer $renderer,
    ) {}

    /** @param array<string, mixed> $extra
     * @return array{rendered: RenderedNotification, statusName: string, emailEnabled: bool}
     */
    public function preview(Order $order, string $eventKey, array $extra = []): array
    {
        $template = $this->template($eventKey);

        return [
            'rendered' => $this->renderer->render($template, $this->orderContext($order, $template, $extra)),
            'statusName' => (string) $template->status_name,
            'emailEnabled' => (bool) $template->email_enabled,
        ];
    }

    public function retry(NotificationDispatch $dispatch, User $actor): NotificationDispatch
    {
        $queued = DB::transaction(function () use ($dispatch, $actor): NotificationDispatch {
            $failed = NotificationDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);

            if ($failed->state !== NotificationDispatch::STATE_FAILED) {
                throw ValidationException::withMessages([
                    'dispatch' => 'Only a failed notification delivery can be retried.',
                ]);
            }

            $failed->forceFill([
                'state' => NotificationDispatch::STATE_QUEUED,
                'queued_at' => now(),
                'failed_at' => null,
                'last_error_code' => null,
                'delivery_lease_token' => null,
                'delivery_lease_expires_at' => null,
                'requested_by' => $actor->id,
            ])->save();

            $this->queueAfterCommit($failed);

            return $failed;
        });

        return $queued->refresh();
    }

    public function resend(NotificationDispatch $dispatch, User $actor): NotificationDispatch
    {
        return DB::transaction(function () use ($dispatch, $actor): NotificationDispatch {
            $original = NotificationDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);

            if ($original->state !== NotificationDispatch::STATE_SENT) {
                throw ValidationException::withMessages([
                    'dispatch' => 'Only a sent notification delivery can be resent.',
                ]);
            }

            $resend = NotificationDispatch::query()->create([
                ...$this->frozenSnapshot($original),
                'source_type' => 'notification_dispatch',
                'source_id' => (string) $original->public_id,
                'idempotency_key' => null,
                'state' => NotificationDispatch::STATE_QUEUED,
                'source' => NotificationDispatch::SOURCE_MANUAL_RESEND,
                'attempt_count' => 0,
                'queued_at' => now(),
                'requested_by' => $actor->id,
                'resend_of_id' => $original->id,
            ]);

            $this->queueAfterCommit($resend);

            return $resend;
        });
    }

    /** @param array<string, mixed> $extra */
    public function sendCurrent(Order $order, string $eventKey, User $actor, array $extra = []): NotificationDispatch
    {
        $template = $this->template($eventKey);

        if (! $template->email_enabled) {
            throw ValidationException::withMessages([
                'eventKey' => 'Email is disabled for this notification template.',
            ]);
        }

        $context = $this->orderContext($order, $template, $extra);
        $rendered = $this->renderer->render($template, $context);

        return DB::transaction(function () use ($order, $template, $eventKey, $actor, $context, $rendered): NotificationDispatch {
            $dispatch = NotificationDispatch::query()->create($this->newSnapshot(
                template: $template,
                rendered: $rendered,
                variables: $context,
                recipientEmail: (string) $context['customer_email'],
                recipientName: (string) $context['customer_name'],
                eventKey: $eventKey,
                source: NotificationDispatch::SOURCE_ADMIN_CURRENT_TEMPLATE,
                actor: $actor,
                order: $order,
                sourceType: 'admin',
                sourceId: (string) $actor->id,
            ));

            $this->queueAfterCommit($dispatch);

            return $dispatch;
        });
    }

    public function test(NotificationTemplate $template, string $recipientEmail, User $actor): NotificationDispatch
    {
        $variables = $this->sampleVariables($template->event_key);
        $rendered = $this->renderer->render($template, $variables);

        return DB::transaction(function () use ($template, $recipientEmail, $actor, $variables, $rendered): NotificationDispatch {
            $dispatch = NotificationDispatch::query()->create($this->newSnapshot(
                template: $template,
                rendered: $rendered,
                variables: $variables,
                recipientEmail: $recipientEmail,
                recipientName: 'Notification Test',
                eventKey: $template->event_key,
                source: NotificationDispatch::SOURCE_TEST,
                actor: $actor,
                order: null,
                sourceType: 'notification_template',
                sourceId: (string) $template->id,
            ));

            $this->queueAfterCommit($dispatch);

            return $dispatch;
        });
    }

    public function validateTemplate(NotificationTemplate $template): void
    {
        $this->validateAvailableTokens($template);
        $this->renderer->render($template, $this->sampleVariables($template->event_key));
    }

    /** @return array<string, string> */
    public function sampleVariables(string $eventKey): array
    {
        $variables = [];

        foreach (NotificationEventCatalog::variablesFor($eventKey) as $token) {
            $name = trim($token, '{}');
            $variables[$name] = match ($name) {
                'order_url', 'tracking_url' => url('/order-tracking'),
                'customer_email', 'shop_email' => 'notification-test@example.com',
                'shop_whatsapp' => 'https://wa.me/60123456789',
                'order_number' => 'JW-TEST-1001',
                'order_total' => 'MYR 100.00',
                'order_date' => '2026-08-31',
                'order_items_summary' => 'Sample Product × 1',
                'customer_name' => 'Alex Customer',
                'customer_first_name' => 'Alex',
                'customer_phone', 'shop_phone' => '+60123456789',
                'payment_method' => 'bank transfer',
                'payment_reference' => 'PAY-TEST-1001',
                'shipping_address' => '10 Studio Road, Kuala Lumpur, 50000, MY',
                'carrier_name' => 'Sample Carrier',
                'tracking_number' => 'TRACK-TEST-1001',
                'payment_proof_reason' => 'The submitted proof could not be verified.',
                'status_name' => (string) NotificationEventCatalog::get($eventKey)['status_name'],
                'staff_note' => 'This is a notification test.',
                'shop_name' => 'JW Studio',
                default => 'Sample value',
            };
        }

        return $variables;
    }

    /** @param array<string, mixed> $extra
     * @return array<string, string>
     */
    private function orderContext(Order $order, NotificationTemplate $template, array $extra): array
    {
        $defaults = [
            'status_name' => (string) $template->status_name,
            'staff_note' => 'No additional notes.',
        ];

        if ($template->event_key === 'payment_proof_rejected') {
            $defaults['payment_proof_reason'] = 'The submitted proof could not be verified.';
        }

        return $this->contextFactory->forOrder($order, array_merge($defaults, $extra));
    }

    private function template(string $eventKey): NotificationTemplate
    {
        NotificationEventCatalog::get($eventKey);

        return NotificationTemplate::query()->where('event_key', $eventKey)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function frozenSnapshot(NotificationDispatch $dispatch): array
    {
        return [
            'order_id' => $dispatch->order_id,
            'notification_template_id' => $dispatch->notification_template_id,
            'channel' => $dispatch->channel,
            'event_key' => $dispatch->event_key,
            'recipient_email' => $dispatch->recipient_email,
            'recipient_name' => $dispatch->recipient_name,
            'rendered_subject' => $dispatch->rendered_subject,
            'rendered_preheader' => $dispatch->rendered_preheader,
            'rendered_heading' => $dispatch->rendered_heading,
            'rendered_body' => $dispatch->rendered_body,
            'rendered_cta_text' => $dispatch->rendered_cta_text,
            'rendered_cta_url' => $dispatch->rendered_cta_url,
            'template_version' => $dispatch->template_version,
            'variable_snapshot' => $dispatch->variable_snapshot,
        ];
    }

    /** @param array<string, string> $variables
     * @return array<string, mixed>
     */
    private function newSnapshot(
        NotificationTemplate $template,
        RenderedNotification $rendered,
        array $variables,
        string $recipientEmail,
        string $recipientName,
        string $eventKey,
        string $source,
        User $actor,
        ?Order $order,
        string $sourceType,
        string $sourceId,
    ): array {
        return [
            'order_id' => $order?->id,
            'notification_template_id' => $template->id,
            'channel' => 'email',
            'event_key' => $eventKey,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'idempotency_key' => null,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName !== '' ? $recipientName : null,
            'rendered_subject' => $rendered->subject,
            'rendered_preheader' => $rendered->preheader,
            'rendered_heading' => $rendered->heading,
            'rendered_body' => $rendered->body,
            'rendered_cta_text' => $rendered->ctaText,
            'rendered_cta_url' => $rendered->ctaUrl,
            'template_version' => $template->version,
            'variable_snapshot' => $variables,
            'state' => NotificationDispatch::STATE_QUEUED,
            'source' => $source,
            'attempt_count' => 0,
            'queued_at' => now(),
            'requested_by' => $actor->id,
        ];
    }

    private function queueAfterCommit(NotificationDispatch $dispatch): void
    {
        DB::afterCommit(static function () use ($dispatch): void {
            SendOrderNotificationJob::dispatch($dispatch->id)->afterCommit();
        });
    }

    private function validateAvailableTokens(NotificationTemplate $template): void
    {
        $allowed = NotificationEventCatalog::variablesFor($template->event_key);
        $fields = [
            'emailSubject' => (string) $template->email_subject,
            'emailPreheader' => (string) $template->email_preheader,
            'emailHeading' => (string) $template->email_heading,
            'emailBody' => (string) $template->email_body,
            'emailCtaText' => (string) $template->email_cta_text,
            'emailCtaUrl' => (string) $template->email_cta_url,
            'whatsappMessage' => (string) $template->whatsapp_message,
        ];

        foreach ($template->whatsapp_cta_buttons ?? [] as $index => $button) {
            $fields["whatsappCtaButtons.{$index}.value"] = (string) ($button['value'] ?? '');
        }

        $errors = [];
        foreach ($fields as $field => $value) {
            preg_match_all('/\{[a-z0-9_]+\}/', $value, $matches);
            foreach (array_unique($matches[0]) as $token) {
                if (! in_array($token, $allowed, true)) {
                    $errors[$field][] = "The {$token} variable is not available for this notification event.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
