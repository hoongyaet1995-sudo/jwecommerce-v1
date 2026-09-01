<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\NotificationDeliveryException;
use App\Jobs\SendOrderNotificationJob;
use App\Mail\OrderStatusNotificationMail;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;
use Throwable;

class SendOrderNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_the_frozen_snapshot_and_marks_dispatch_sent(): void
    {
        Mail::fake();
        $dispatch = NotificationDispatch::factory()->create([
            'recipient_email' => 'customer@example.test',
            'recipient_name' => 'Amina Buyer',
            'rendered_subject' => 'Frozen order subject',
        ]);

        $this->jobAtAttempt($dispatch, 1)->handle(app(MailerContract::class));

        Mail::assertSent(OrderStatusNotificationMail::class, function (OrderStatusNotificationMail $mail) use ($dispatch): bool {
            return $mail->dispatch->is($dispatch)
                && $mail->hasTo('customer@example.test', 'Amina Buyer');
        });
        $dispatch->refresh();
        $this->assertSame('sent', $dispatch->state);
        $this->assertSame(1, $dispatch->attempt_count);
        $this->assertNotNull($dispatch->sent_at);
        $this->assertNull($dispatch->last_error_code);
        $this->assertNull($dispatch->delivery_lease_token);
        $this->assertNull($dispatch->delivery_lease_expires_at);
    }

    public function test_two_immediate_delivery_claims_have_only_one_owner(): void
    {
        $dispatch = NotificationDispatch::factory()->create();

        $first = NotificationDispatch::claimForDelivery($dispatch->id, 1, $dispatch->queue_generation);
        $second = NotificationDispatch::claimForDelivery($dispatch->id, 1, $dispatch->queue_generation);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(NotificationDispatch::STATE_SENDING, $first->state);
        $this->assertNotNull($first->delivery_lease_token);
        $this->assertTrue($first->delivery_lease_expires_at->isFuture());
        $this->assertSame(1, $dispatch->refresh()->attempt_count);
    }

    public function test_fresh_sending_claim_cannot_send(): void
    {
        Mail::fake();
        $dispatch = NotificationDispatch::factory()->create([
            'state' => NotificationDispatch::STATE_SENDING,
            'attempt_count' => 1,
            'queue_attempt' => 1,
            'delivery_lease_token' => '0ad9805a-3ccd-4bb0-9404-3d8860e96530',
            'delivery_lease_expires_at' => now()->addMinute(),
        ]);

        $this->jobAtAttempt($dispatch, 1)->handle(app(MailerContract::class));

        Mail::assertNothingSent();
        $dispatch->refresh();
        $this->assertSame(NotificationDispatch::STATE_SENDING, $dispatch->state);
        $this->assertSame(1, $dispatch->attempt_count);
        $this->assertSame('0ad9805a-3ccd-4bb0-9404-3d8860e96530', $dispatch->delivery_lease_token);
    }

    public function test_stale_sending_claim_is_reclaimed_and_sent_once(): void
    {
        Mail::fake();
        $dispatch = NotificationDispatch::factory()->create([
            'state' => NotificationDispatch::STATE_SENDING,
            'attempt_count' => 1,
            'queue_attempt' => 1,
            'delivery_lease_token' => '7397c1a4-6a0e-4698-8f89-7798925dd628',
            'delivery_lease_expires_at' => now()->subSecond(),
        ]);

        $this->jobAtAttempt($dispatch, 2)->handle(app(MailerContract::class));

        Mail::assertSent(OrderStatusNotificationMail::class, 1);
        $dispatch->refresh();
        $this->assertSame(NotificationDispatch::STATE_SENT, $dispatch->state);
        $this->assertSame(2, $dispatch->attempt_count);
        $this->assertNull($dispatch->delivery_lease_token);
        $this->assertNull($dispatch->delivery_lease_expires_at);
    }

    public function test_sent_dispatch_is_an_idempotent_no_op(): void
    {
        Mail::fake();
        $dispatch = NotificationDispatch::factory()->sent()->create([
            'attempt_count' => 2,
        ]);

        $this->jobAtAttempt($dispatch, 3)->handle(app(MailerContract::class));

        Mail::assertNothingSent();
        $this->assertSame(2, $dispatch->refresh()->attempt_count);
    }

    public function test_intermediate_failure_returns_to_queue_state_and_attempt_two_can_claim_before_the_old_lease_expiry(): void
    {
        $order = Order::query()->create([
            'number' => 'JW-NOTIFY-FAILURE',
            'email' => 'customer@example.test',
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $dispatch = NotificationDispatch::factory()->create(['order_id' => $order->id]);
        $error = new TransportException('SMTP authentication failed with private-password');

        $job = $this->jobAtAttempt($dispatch, 1);
        try {
            $job->handle(new ThrowingMailer($error));
            $this->fail('The mail transport failure was swallowed.');
        } catch (NotificationDeliveryException $caught) {
            $this->assertSame('transport', $caught->safeCode);
            $this->assertSame('Order notification delivery failed [transport].', $caught->getMessage());
            $this->assertNull($caught->getPrevious());
            $this->assertStringNotContainsString('private-password', $caught->getMessage());
        }

        $dispatch->refresh();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
        $this->assertSame(NotificationDispatch::STATE_QUEUED, $dispatch->state);
        $this->assertSame(1, $dispatch->attempt_count);
        $this->assertNull($dispatch->last_error_code);
        $this->assertNull($dispatch->failed_at);
        $this->assertNull($dispatch->delivery_lease_token);
        $this->assertNull($dispatch->delivery_lease_expires_at);
        $this->assertStringNotContainsString('private-password', json_encode($dispatch->getAttributes(), JSON_THROW_ON_ERROR));

        Mail::fake();
        $this->jobAtAttempt($dispatch, 1)->handle(app(MailerContract::class));
        Mail::assertNothingSent();
        $this->jobAtAttempt($dispatch, 2)->handle(app(MailerContract::class));
        $dispatch->refresh();
        Mail::assertSent(OrderStatusNotificationMail::class, 1);
        $this->assertSame(NotificationDispatch::STATE_SENT, $dispatch->state);
        $this->assertSame(2, $dispatch->attempt_count);
        $this->assertNull($dispatch->last_error_code);
        $this->assertNull($dispatch->failed_at);
        $this->assertNull($dispatch->delivery_lease_token);
        $this->assertNull($dispatch->delivery_lease_expires_at);
    }

    public function test_timeout_and_unknown_failures_receive_bounded_safe_codes(): void
    {
        foreach ([
            'timeout' => new RuntimeException('Connection timed out while contacting mail host'),
            'unknown' => new RuntimeException('internal private detail'),
        ] as $expectedCode => $error) {
            $dispatch = NotificationDispatch::factory()->create();

            $job = $this->jobAtAttempt($dispatch, 1);

            try {
                $job->handle(new ThrowingMailer($error));
                $this->fail('The mail failure was swallowed.');
            } catch (NotificationDeliveryException $caught) {
                $this->assertSame($expectedCode, $caught->safeCode);
                $this->assertNull($caught->getPrevious());
                $this->assertStringNotContainsString($error->getMessage(), $caught->getMessage());

                $job->failed($caught);
            }

            $this->assertSame($expectedCode, $dispatch->refresh()->last_error_code);
            $this->assertStringNotContainsString($error->getMessage(), json_encode($dispatch->getAttributes(), JSON_THROW_ON_ERROR));
        }
    }

    public function test_terminal_failure_recovery_is_idempotent_and_a_failed_row_cannot_be_claimed_without_manual_requeue(): void
    {
        $dispatch = NotificationDispatch::factory()->create([
            'state' => NotificationDispatch::STATE_QUEUED,
            'attempt_count' => 4,
        ]);
        $job = new SendOrderNotificationJob($dispatch->id, $dispatch->queue_generation);

        $job->failed(new RuntimeException('hard timeout with private host detail'));
        $firstFailedAt = $dispatch->refresh()->failed_at?->toISOString();
        $job->failed(new RuntimeException('later duplicate callback'));

        $dispatch->refresh();
        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->state);
        $this->assertSame('timeout', $dispatch->last_error_code);
        $this->assertSame($firstFailedAt, $dispatch->failed_at?->toISOString());
        $this->assertNull(NotificationDispatch::claimForDelivery($dispatch->id, 1, $dispatch->queue_generation));
        $this->assertSame(4, $dispatch->refresh()->attempt_count);
    }

    public function test_hard_timeout_failed_callback_recovers_an_orphaned_sending_claim(): void
    {
        $dispatch = NotificationDispatch::factory()->create([
            'state' => NotificationDispatch::STATE_SENDING,
            'attempt_count' => 4,
            'delivery_lease_token' => 'e5a004d3-0026-45d0-a3e2-0d276f308265',
            'delivery_lease_expires_at' => now()->addMinute(),
        ]);

        (new SendOrderNotificationJob($dispatch->id, $dispatch->queue_generation))->failed(
            new RuntimeException('Job timed out after the hard timeout.'),
        );

        $dispatch->refresh();
        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->state);
        $this->assertSame('timeout', $dispatch->last_error_code);
        $this->assertNotNull($dispatch->failed_at);
        $this->assertNull($dispatch->delivery_lease_token);
        $this->assertNull($dispatch->delivery_lease_expires_at);
    }

    public function test_job_has_four_attempts_and_bounded_backoff(): void
    {
        $job = new SendOrderNotificationJob(123);

        $this->assertSame(4, $job->tries);
        $this->assertSame(45, $job->timeout);
        $this->assertFalse($job->failOnTimeout);
        $this->assertSame([60, 300, 900], $job->backoff());
    }

    public function test_timeout_attempts_one_through_four_advance_by_queue_attempt_without_waiting_for_the_lease(): void
    {
        $dispatch = NotificationDispatch::factory()->create();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $claimed = NotificationDispatch::claimForDelivery($dispatch->id, $attempt, $dispatch->queue_generation);
            $this->assertNotNull($claimed, "Laravel attempt {$attempt} must own the lifecycle.");
            $this->assertSame($attempt, $claimed->queue_attempt);
            $this->assertSame($attempt, $claimed->attempt_count);
            $this->assertTrue($claimed->delivery_lease_expires_at->isFuture());

            $duplicate = NotificationDispatch::claimForDelivery($dispatch->id, $attempt, $dispatch->queue_generation);
            $this->assertNull($duplicate, "A concurrent duplicate of attempt {$attempt} must not overlap.");
        }

        $job = $this->jobAtAttempt($dispatch, 4);
        $job->failed(new RuntimeException('Job timed out after attempt four.'));
        $dispatch->refresh();

        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->state);
        $this->assertSame(4, $dispatch->queue_attempt);
        $this->assertSame(4, $dispatch->attempt_count);
        $this->assertSame('timeout', $dispatch->last_error_code);
        $this->assertNull(NotificationDispatch::claimForDelivery($dispatch->id, 5, $dispatch->queue_generation));
    }

    public function test_infrastructure_retry_cannot_execute_a_terminal_failed_dispatch(): void
    {
        Mail::fake();
        $dispatch = NotificationDispatch::factory()->failed()->create([
            'attempt_count' => 4,
            'queue_attempt' => 4,
        ]);

        $this->jobAtAttempt($dispatch, 1)->handle(app(MailerContract::class));

        Mail::assertNothingSent();
        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->refresh()->state);
        $this->assertSame(4, $dispatch->attempt_count);
        $this->assertSame(4, $dispatch->queue_attempt);
    }

    public function test_old_serialized_job_cannot_claim_or_fail_a_new_admin_retry_lifecycle(): void
    {
        Queue::fake();
        Mail::fake();
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $dispatch = NotificationDispatch::factory()->failed()->create([
            'attempt_count' => 4,
            'queue_attempt' => 4,
        ]);
        $oldGeneration = $dispatch->queue_generation;
        $oldSerializedJob = $this->jobAtAttempt($dispatch, 5, $oldGeneration);

        $retried = app(NotificationDispatchService::class)->retry($dispatch, $actor);

        $this->assertNotSame($oldGeneration, $retried->queue_generation);
        $this->assertSame(NotificationDispatch::STATE_QUEUED, $retried->state);
        $this->assertSame(0, $retried->attempt_count);
        $this->assertNull($retried->queue_attempt);
        Queue::assertPushed(
            SendOrderNotificationJob::class,
            fn (SendOrderNotificationJob $job): bool => $job->dispatchId === $retried->id
                && $job->queueGeneration === $retried->queue_generation,
        );

        $oldSerializedJob->handle(app(MailerContract::class));
        $oldSerializedJob->failed(new RuntimeException('late failure from stale serialized job'));

        Mail::assertNothingSent();
        $retried->refresh();
        $this->assertSame(NotificationDispatch::STATE_QUEUED, $retried->state);
        $this->assertSame(0, $retried->attempt_count);
        $this->assertNull($retried->queue_attempt);
        $this->assertNull($retried->failed_at);
        $this->assertNull($retried->last_error_code);

        $this->jobAtAttempt($retried, 1, $retried->queue_generation)->handle(app(MailerContract::class));
        Mail::assertSent(OrderStatusNotificationMail::class, 1);
        $this->assertSame(NotificationDispatch::STATE_SENT, $retried->refresh()->state);
    }

    public function test_true_pre_b1_serialized_job_fails_closed_and_admin_retry_starts_a_safe_new_lifecycle(): void
    {
        Queue::fake();
        Mail::fake();
        $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $dispatch = NotificationDispatch::factory()->create([
            'queue_generation' => null,
            'queue_attempt' => null,
        ]);
        $legacyPayload = $this->serializedJobWithoutQueueGeneration($dispatch->id);
        $oldJob = unserialize($legacyPayload, ['allowed_classes' => [SendOrderNotificationJob::class]]);

        $this->assertInstanceOf(SendOrderNotificationJob::class, $oldJob);
        $this->assertTrue($oldJob->failOnTimeout, 'The pre-b1 serialized top-level timeout flag cannot be retrofitted.');
        $this->assertNull($oldJob->queueGeneration);

        try {
            $oldJob->handle(app(MailerContract::class));
            $this->fail('A pre-b1 job without a generation must fail closed.');
        } catch (NotificationDeliveryException $error) {
            $this->assertSame('queue', $error->safeCode);
        }

        Mail::assertNothingSent();
        $dispatch->refresh();
        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->state);
        $this->assertSame('queue', $dispatch->last_error_code);
        $this->assertNotNull($dispatch->failed_at);
        $firstFailedAt = $dispatch->failed_at?->toISOString();

        $oldJob->failed(new RuntimeException('private legacy worker failure'));
        $oldJob->failed(new RuntimeException('duplicate private legacy worker failure'));
        $dispatch->refresh();
        $this->assertSame('queue', $dispatch->last_error_code);
        $this->assertSame($firstFailedAt, $dispatch->failed_at?->toISOString());

        $retried = app(NotificationDispatchService::class)->retry($dispatch, $actor);
        $this->assertNotNull($retried->queue_generation);
        $this->assertSame(NotificationDispatch::STATE_QUEUED, $retried->state);
        Queue::assertPushed(
            SendOrderNotificationJob::class,
            fn (SendOrderNotificationJob $job): bool => $job->dispatchId === $retried->id
                && $job->queueGeneration === $retried->queue_generation,
        );

        try {
            $oldJob->handle(app(MailerContract::class));
            $this->fail('The old serialized job must not claim the Admin Retry lifecycle.');
        } catch (NotificationDeliveryException $error) {
            $this->assertSame('queue', $error->safeCode);
        }
        $oldJob->failed(new RuntimeException('late old lifecycle callback'));

        $retried->refresh();
        $this->assertSame(NotificationDispatch::STATE_QUEUED, $retried->state);
        $this->assertSame(0, $retried->attempt_count);
        $this->assertNull($retried->queue_attempt);
        $this->assertNull($retried->failed_at);
        $this->assertNull($retried->last_error_code);
        Mail::assertNothingSent();

        $this->jobAtAttempt($retried, 1, $retried->queue_generation)->handle(app(MailerContract::class));
        Mail::assertSent(OrderStatusNotificationMail::class, 1);
        $this->assertSame(NotificationDispatch::STATE_SENT, $retried->refresh()->state);
    }

    public function test_mail_html_uses_only_the_frozen_snapshot_and_contains_no_private_provider_links(): void
    {
        StoreSetting::query()->create([
            'key' => 'shop_name',
            'value' => ['data' => 'Current Changed Brand'],
        ]);
        $template = NotificationTemplate::factory()->create([
            'email_heading' => 'Current template heading',
            'email_body' => 'Current template body',
        ]);
        $dispatch = NotificationDispatch::factory()->create([
            'notification_template_id' => $template->id,
            'rendered_subject' => 'Shipped via J&T',
            'rendered_preheader' => 'Frozen preheader',
            'rendered_heading' => 'Frozen <Order> Heading',
            'rendered_body' => "Track safely at /order-tracking\n<strong>escaped</strong>",
            'rendered_cta_text' => 'Track Order',
            'rendered_cta_url' => url('/order-tracking'),
            'variable_snapshot' => [
                'shop_name' => '<img src=x onerror=alert(1)> Frozen JW Brand',
                'tracking_url' => url('/order-tracking'),
                'order_url' => url('/order-tracking'),
            ],
        ]);
        $template->update([
            'email_heading' => 'Changed EasyParcel heading',
            'email_body' => 'https://easyparcel.com/private?token=secret label_path proof-storage/private.png',
        ]);

        $html = (new OrderStatusNotificationMail($dispatch))->render();
        $mail = new OrderStatusNotificationMail($dispatch);

        $this->assertStringContainsString('Frozen JW Brand', $html);
        $this->assertSame('Shipped via J&T', $mail->envelope()->subject);
        $this->assertStringNotContainsString('Current Changed Brand', $html);
        $this->assertStringContainsString('Frozen preheader', $html);
        $this->assertStringContainsString('Frozen &lt;Order&gt; Heading', $html);
        $this->assertStringContainsString('/order-tracking', $html);
        $this->assertStringContainsString('&lt;strong&gt;escaped&lt;/strong&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringNotContainsString('Changed EasyParcel heading', $html);
        $this->assertStringNotContainsString('easyparcel.com', $html);
        $this->assertStringNotContainsString('label_path', $html);
        $this->assertStringNotContainsString('token', $html);
        $this->assertStringNotContainsString('proof-storage', $html);
    }

    public function test_mail_tracking_anchor_uses_safe_owned_fallback_for_missing_or_corrupt_snapshots(): void
    {
        foreach ([
            'missing' => ['shop_name' => 'JW Studio'],
            'corrupt' => [
                'shop_name' => 'JW Studio',
                'tracking_url' => 'javascript:alert(1)\" onclick=\"steal()//tracking.easyparcel.com',
            ],
        ] as $snapshot) {
            $dispatch = NotificationDispatch::factory()->create([
                'variable_snapshot' => $snapshot,
                'rendered_cta_text' => '',
                'rendered_cta_url' => '',
            ]);

            $html = (new OrderStatusNotificationMail($dispatch))->render();
            $expectedHref = 'href="'.e(url('/order-tracking')).'"';

            $this->assertStringContainsString($expectedHref, $html);
            $this->assertStringContainsString(e(url('/order-tracking')), $html);
            $this->assertStringNotContainsString('href=""', $html);
            $this->assertStringNotContainsString('javascript:', $html);
            $this->assertStringNotContainsString('onclick=', $html);
            $this->assertStringNotContainsString('easyparcel.com', $html);
        }
    }

    public function test_mail_decodes_legacy_snapshot_entities_once_then_escapes_html_contexts(): void
    {
        $dispatch = NotificationDispatch::factory()->create([
            'snapshot_version' => NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED,
            'rendered_subject' => 'J&amp;T &amp; O&#039;Brien &lt;b&gt;safe&lt;/b&gt;',
            'rendered_preheader' => 'J&amp;T preheader',
            'rendered_heading' => 'Hello O&#039;Brien &lt;script&gt;bad&lt;/script&gt;',
            'rendered_body' => 'Legacy &lt;img src=x onerror=alert(1)&gt; &amp; safe',
            'rendered_cta_text' => 'Track J&amp;T',
            'rendered_cta_url' => 'https://example.test/track?a=1&amp;b=2',
            'variable_snapshot' => [
                'shop_name' => 'J&amp;T &lt;script&gt;bad&lt;/script&gt;',
                'tracking_url' => e(url('/order-tracking')),
            ],
        ]);
        $mail = new OrderStatusNotificationMail($dispatch);
        $html = $mail->render();

        $this->assertSame("J&T & O'Brien <b>safe</b>", $mail->envelope()->subject);
        $this->assertStringContainsString('J&amp;T preheader', $html);
        $this->assertStringContainsString('J&amp;T &lt;script&gt;bad&lt;/script&gt;', $html);
        $this->assertStringContainsString('Legacy &lt;img src=x onerror=alert(1)&gt; &amp; safe', $html);
        $this->assertStringContainsString('href="https://example.test/track?a=1&amp;b=2"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<script>bad</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    private function jobAtAttempt(
        NotificationDispatch $dispatch,
        int $attempt,
        ?string $queueGeneration = null,
    ): SendOrderNotificationJob {
        $queueJob = Mockery::mock(QueueJobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn($attempt);

        return (new SendOrderNotificationJob(
            $dispatch->id,
            $queueGeneration ?? $dispatch->queue_generation,
        ))->setJob($queueJob);
    }

    private function serializedJobWithoutQueueGeneration(int $dispatchId): string
    {
        $placeholder = 'pre-b1-generation-placeholder';
        $job = new SendOrderNotificationJob($dispatchId, $placeholder);
        $job->failOnTimeout = true;
        $serialized = serialize($job);
        $property = serialize('queueGeneration').serialize($placeholder);
        $withoutGeneration = str_replace($property, '', $serialized, $removed);
        $this->assertSame(1, $removed, 'The current serialized shape must contain queueGeneration before simulating pre-b1.');

        $legacyPayload = preg_replace_callback(
            '/^(O:\d+:"[^"]+":)(\d+)(:\{)/',
            static fn (array $matches): string => $matches[1].((int) $matches[2] - 1).$matches[3],
            $withoutGeneration,
            1,
            $adjusted,
        );
        $this->assertSame(1, $adjusted);
        $this->assertIsString($legacyPayload);

        return $legacyPayload;
    }
}

final class ThrowingMailer implements MailerContract
{
    public function __construct(private readonly Throwable $error) {}

    public function to($users): PendingMail
    {
        return (new PendingMail($this))->to($users);
    }

    public function bcc($users): PendingMail
    {
        return (new PendingMail($this))->bcc($users);
    }

    public function raw($text, $callback): never
    {
        throw $this->error;
    }

    public function send($view, array $data = [], $callback = null): never
    {
        throw $this->error;
    }

    public function sendNow($mailable, array $data = [], $callback = null): never
    {
        throw $this->error;
    }
}
