<?php

namespace App\Jobs;

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

    public function __construct(public readonly int $dispatchId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(Mailer $mailer): void
    {
        $dispatch = NotificationDispatch::query()->findOrFail($this->dispatchId);

        if ($dispatch->state === NotificationDispatch::STATE_SENT) {
            return;
        }

        $dispatch->forceFill([
            'state' => NotificationDispatch::STATE_SENDING,
            'attempt_count' => $dispatch->attempt_count + 1,
        ])->save();

        try {
            $recipient = new Address(
                $dispatch->recipient_email,
                $dispatch->recipient_name ?: '',
            );
            $mailer->to($recipient)->send(new OrderStatusNotificationMail($dispatch));
            $dispatch->forceFill([
                'state' => NotificationDispatch::STATE_SENT,
                'sent_at' => now(),
                'last_error_code' => null,
            ])->save();
        } catch (Throwable $error) {
            $dispatch->forceFill([
                'state' => NotificationDispatch::STATE_FAILED,
                'failed_at' => now(),
                'last_error_code' => $this->safeErrorCode($error),
            ])->save();

            throw $error;
        }
    }

    private function safeErrorCode(Throwable $error): string
    {
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
