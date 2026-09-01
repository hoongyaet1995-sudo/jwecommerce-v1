<?php

namespace App\Jobs;

use App\Domain\Notifications\NotificationDeliveryException;
use App\Mail\OrderStatusNotificationMail;
use App\Models\NotificationDispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 45;

    public bool $failOnTimeout = false;

    public readonly int $dispatchId;

    public ?string $queueGeneration = null;

    public function __construct(int $dispatchId, ?string $queueGeneration = null)
    {
        $this->dispatchId = $dispatchId;
        $this->queueGeneration = $queueGeneration;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(Mailer $mailer): void
    {
        $this->deliver($mailer, $this->attempts());
    }

    public function handleSynchronously(Mailer $mailer): void
    {
        $this->deliver($mailer, 1);
    }

    private function deliver(Mailer $mailer, int $attempt): void
    {
        if ($this->queueGeneration === null) {
            $exception = new NotificationDeliveryException('queue');
            $this->failed($exception);

            throw $exception;
        }

        $dispatch = NotificationDispatch::claimForDelivery(
            $this->dispatchId,
            $attempt,
            $this->queueGeneration,
        );

        if (! $dispatch instanceof NotificationDispatch) {
            return;
        }

        $leaseToken = (string) $dispatch->delivery_lease_token;

        try {
            $recipient = new Address(
                $dispatch->recipient_email,
                $dispatch->recipient_name ?: '',
            );
            $mailer->to($recipient)->send(new OrderStatusNotificationMail($dispatch));
            $this->finishClaim($dispatch, $leaseToken, [
                'state' => NotificationDispatch::STATE_SENT,
                'sent_at' => now(),
                'last_error_code' => null,
            ]);
        } catch (Throwable $error) {
            $safeErrorCode = $this->safeErrorCode($error);
            $this->finishClaim($dispatch, $leaseToken, [
                'state' => NotificationDispatch::STATE_QUEUED,
                'failed_at' => null,
                'last_error_code' => null,
            ]);

            throw new NotificationDeliveryException($safeErrorCode);
        }
    }

    public function failed(Throwable $error): void
    {
        NotificationDispatch::query()
            ->whereKey($this->dispatchId)
            ->when(
                $this->queueGeneration === null,
                static fn ($query) => $query->whereNull('queue_generation'),
                fn ($query) => $query->where('queue_generation', $this->queueGeneration),
            )
            ->whereIn('state', [
                NotificationDispatch::STATE_QUEUED,
                NotificationDispatch::STATE_SENDING,
            ])
            ->update([
                'state' => NotificationDispatch::STATE_FAILED,
                'failed_at' => now(),
                'last_error_code' => $this->safeErrorCode($error),
                'delivery_lease_token' => null,
                'delivery_lease_expires_at' => null,
            ]);
    }

    /** @param array<string, mixed> $attributes */
    private function finishClaim(NotificationDispatch $dispatch, string $leaseToken, array $attributes): void
    {
        NotificationDispatch::query()
            ->whereKey($dispatch->id)
            ->where('state', NotificationDispatch::STATE_SENDING)
            ->where('delivery_lease_token', $leaseToken)
            ->update(array_merge($attributes, [
                'delivery_lease_token' => null,
                'delivery_lease_expires_at' => null,
            ]));
    }

    private function safeErrorCode(Throwable $error): string
    {
        if ($error instanceof NotificationDeliveryException) {
            return $error->safeCode;
        }

        for ($current = $error; $current !== null; $current = $current->getPrevious()) {
            $description = strtolower($current::class.' '.$current->getMessage());

            if (str_contains($description, 'timeout') || str_contains($description, 'timed out')) {
                return 'timeout';
            }

            if ($current instanceof TransportExceptionInterface
                || str_contains($description, 'transport')
                || str_contains($description, 'smtp')
                || str_contains($description, 'authentication')
                || str_contains($description, 'authenticator')) {
                return 'transport';
            }
        }

        return 'unknown';
    }
}
