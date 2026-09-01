<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderNotificationJob;
use App\Models\Customer;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationTemplateDefaults;
use App\Services\Notifications\OrderNotificationMilestoneService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;
use Throwable;

class OrderNotificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_MARKERS = [
        'TASK9_RAW_BODY_MARKER',
        'task9-recipient@example.test',
        'TASK9_SMTP_SECRET',
        'https://api.easyparcel.test/private',
        'TASK9_EASYPARCEL_TOKEN',
        'TASK9_PROVIDER_MESSAGE_ID',
    ];

    private string $securityLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        app(NotificationTemplateDefaults::class)->synchronize();
        $this->securityLogPath = storage_path('logs/order-notification-security-test.log');
        @unlink($this->securityLogPath);

        config([
            'logging.default' => 'order-notification-security-test',
            'logging.channels.order-notification-security-test' => [
                'driver' => 'single',
                'path' => $this->securityLogPath,
                'level' => 'debug',
            ],
        ]);
        Log::getFacadeRoot()->forgetChannel('order-notification-security-test');
    }

    protected function tearDown(): void
    {
        @unlink($this->securityLogPath);
        parent::tearDown();
    }

    public function test_public_admin_shell_and_authorized_dtos_exclude_private_dispatch_data(): void
    {
        $order = $this->order('NON-EXPOSURE');
        NotificationDispatch::factory()->failed()->create([
            'order_id' => $order->id,
            'recipient_email' => self::PRIVATE_MARKERS[1],
            'rendered_subject' => 'Safe bounded subject',
            'rendered_body' => implode(' ', self::PRIVATE_MARKERS),
            'rendered_cta_url' => self::PRIVATE_MARKERS[3],
            'variable_snapshot' => [
                'smtp_secret' => self::PRIVATE_MARKERS[2],
                'easyparcel_token' => self::PRIVATE_MARKERS[4],
            ],
            'provider_message_id' => self::PRIVATE_MARKERS[5],
            'last_error_code' => 'transport',
        ]);
        $admin = $this->superAdmin();

        $responses = [
            $this->get('/order-tracking')->assertOk(),
            $this->actingAs($admin)->get('/admin/orders')->assertOk(),
            $this->actingAs($admin)->getJson('/admin/api/orders')->assertOk(),
            $this->actingAs($admin)
                ->getJson("/admin/api/orders/{$order->id}/notification-dispatches")
                ->assertOk()
                ->assertJsonPath('data.0.subject', 'Safe bounded subject')
                ->assertJsonPath('data.0.errorCode', 'transport'),
        ];

        foreach ($responses as $response) {
            $this->assertSafeResponse($this->decodedResponseBody($response->getContent()));
        }
    }

    public function test_unauthorized_notification_routes_do_not_allow_template_order_or_dispatch_enumeration(): void
    {
        $order = $this->order('ENUMERATION');
        $failed = NotificationDispatch::factory()->failed()->create(['order_id' => $order->id]);
        $sent = NotificationDispatch::factory()->sent()->create(['order_id' => $order->id]);
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $missingOrderId = $order->id + 999999;
        $missingDispatchId = '00000000-0000-4000-8000-000000000000';

        $this->getJson('/admin/api/notification-templates')->assertUnauthorized();
        $this->getJson("/admin/api/orders/{$order->id}/notification-dispatches")->assertUnauthorized();
        $this->getJson("/admin/api/orders/{$missingOrderId}/notification-dispatches")->assertUnauthorized();
        $this->postJson("/admin/api/notification-dispatches/{$failed->public_id}/retry")->assertUnauthorized();
        $this->postJson("/admin/api/notification-dispatches/{$missingDispatchId}/retry")->assertUnauthorized();

        $forbiddenResponses = [
            $this->actingAs($staff)->getJson('/admin/api/notification-templates'),
            $this->actingAs($staff)->putJson('/admin/api/notification-templates/order_created', []),
            $this->actingAs($staff)->putJson('/admin/api/notification-templates/not-a-real-event', []),
            $this->actingAs($staff)->postJson('/admin/api/notification-templates/reset'),
            $this->actingAs($staff)->postJson('/admin/api/notification-templates/order_created/test', [
                'recipientEmail' => 'enumeration@example.test',
            ]),
            $this->actingAs($staff)->postJson('/admin/api/notification-templates/not-a-real-event/test', [
                'recipientEmail' => 'enumeration@example.test',
            ]),
            $this->actingAs($staff)->getJson("/admin/api/orders/{$order->id}/notification-dispatches"),
            $this->actingAs($staff)->getJson("/admin/api/orders/{$missingOrderId}/notification-dispatches"),
            $this->actingAs($staff)->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
                'eventKey' => 'order_created',
            ]),
            $this->actingAs($staff)->postJson("/admin/api/orders/{$missingOrderId}/notifications/preview", [
                'eventKey' => 'order_created',
            ]),
            $this->actingAs($staff)->postJson("/admin/api/orders/{$order->id}/notifications/send-current", [
                'eventKey' => 'order_created',
            ]),
            $this->actingAs($staff)->postJson("/admin/api/orders/{$missingOrderId}/notifications/send-current", [
                'eventKey' => 'order_created',
            ]),
            $this->actingAs($staff)->postJson("/admin/api/notification-dispatches/{$failed->public_id}/retry"),
            $this->actingAs($staff)->postJson("/admin/api/notification-dispatches/{$missingDispatchId}/retry"),
            $this->actingAs($staff)->postJson("/admin/api/notification-dispatches/{$sent->public_id}/resend"),
            $this->actingAs($staff)->postJson("/admin/api/notification-dispatches/{$missingDispatchId}/resend"),
        ];

        foreach ($forbiddenResponses as $response) {
            $response->assertForbidden();
        }
    }

    public function test_reported_queue_and_mail_failures_expose_only_bounded_notification_errors(): void
    {
        $privateFailure = implode(' ', self::PRIVATE_MARKERS);
        $order = $this->order('LOG-BOUNDARY');
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException($privateFailure));
        $this->app->instance(Dispatcher::class, $dispatcher);

        DB::transaction(fn (): ?NotificationDispatch => app(OrderNotificationMilestoneService::class)->record(
            $order,
            'order_created',
            'order',
            $order->id,
        ));

        $dispatch = $order->notificationDispatches()->sole();
        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->state);
        $this->assertSame('queue', $dispatch->last_error_code);

        $mailDispatch = NotificationDispatch::factory()->create(['order_id' => $order->id]);

        try {
            (new SendOrderNotificationJob($mailDispatch->id))->handle(
                new SecurityThrowingMailer(new TransportException($privateFailure)),
            );
            $this->fail('The mail failure was swallowed.');
        } catch (Throwable $reported) {
            report($reported);
        }

        $log = is_file($this->securityLogPath)
            ? (string) file_get_contents($this->securityLogPath)
            : '';

        $this->assertStringContainsString('notification', strtolower($log));

        foreach (self::PRIVATE_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, $log);
        }
    }

    public function test_manual_queue_publication_failures_remain_safe_failed_and_auditable(): void
    {
        $privateFailure = implode(' ', self::PRIVATE_MARKERS);
        $admin = $this->superAdmin();
        $order = $this->order('MANUAL-QUEUE');

        $failed = NotificationDispatch::factory()->failed()->create(['order_id' => $order->id]);
        $this->bindThrowingDispatcher($privateFailure);
        $retry = $this->actingAs($admin)
            ->postJson("/admin/api/notification-dispatches/{$failed->public_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.state', NotificationDispatch::STATE_FAILED)
            ->assertJsonPath('data.errorCode', 'queue');
        $this->assertSafeResponse($retry->getContent());
        $this->assertSafeQueueFailure($failed->refresh());

        $sent = NotificationDispatch::factory()->sent()->create(['order_id' => $order->id]);
        $this->bindThrowingDispatcher($privateFailure);
        $resend = $this->actingAs($admin)
            ->postJson("/admin/api/notification-dispatches/{$sent->public_id}/resend")
            ->assertCreated()
            ->assertJsonPath('data.state', NotificationDispatch::STATE_FAILED)
            ->assertJsonPath('data.errorCode', 'queue');
        $this->assertSafeResponse($resend->getContent());
        $this->assertSafeQueueFailure(
            NotificationDispatch::query()->where('public_id', $resend->json('data.id'))->firstOrFail(),
        );

        $this->bindThrowingDispatcher($privateFailure);
        $sendCurrent = $this->actingAs($admin)
            ->postJson("/admin/api/orders/{$order->id}/notifications/send-current", [
                'eventKey' => 'order_created',
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', NotificationDispatch::STATE_FAILED)
            ->assertJsonPath('data.errorCode', 'queue');
        $this->assertSafeResponse($sendCurrent->getContent());
        $this->assertSafeQueueFailure(
            NotificationDispatch::query()->where('public_id', $sendCurrent->json('data.id'))->firstOrFail(),
        );

        $this->bindThrowingDispatcher($privateFailure);
        $testSend = $this->actingAs($admin)
            ->postJson('/admin/api/notification-templates/order_created/test', [
                'recipientEmail' => 'manual-queue@example.test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', NotificationDispatch::STATE_FAILED)
            ->assertJsonPath('data.errorCode', 'queue');
        $this->assertSafeResponse($testSend->getContent());
        $this->assertSafeQueueFailure(
            NotificationDispatch::query()->where('public_id', $testSend->json('data.id'))->firstOrFail(),
        );

        $log = is_file($this->securityLogPath)
            ? (string) file_get_contents($this->securityLogPath)
            : '';
        $this->assertStringContainsString('notification', strtolower($log));
        $this->assertSafeResponse($log);
    }

    private function bindThrowingDispatcher(string $privateFailure): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException($privateFailure));
        $this->app->instance(Dispatcher::class, $dispatcher);
    }

    private function assertSafeQueueFailure(NotificationDispatch $dispatch): void
    {
        $this->assertSame(NotificationDispatch::STATE_FAILED, $dispatch->state);
        $this->assertSame('queue', $dispatch->last_error_code);
        $this->assertNotNull($dispatch->failed_at);
    }

    private function assertSafeResponse(string $serialized): void
    {
        foreach (self::PRIVATE_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, $serialized);
            $this->assertStringNotContainsString(json_encode($marker, JSON_THROW_ON_ERROR), $serialized);
        }
    }

    private function decodedResponseBody(string $body): string
    {
        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return html_entity_decode($body, ENT_QUOTES | ENT_HTML5);
        }
    }

    private function order(string $suffix): Order
    {
        $customer = Customer::query()->create([
            'email' => strtolower($suffix).'@example.test',
            'first_name' => 'Task 9',
            'last_name' => $suffix,
        ]);
        $order = Order::query()->create([
            'number' => 'JW-TASK9-'.$suffix,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $order->items()->create([
            'sku' => 'TASK9-'.$suffix,
            'title' => 'Task 9 Notification Product',
            'variant_title' => 'Acceptance',
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);
        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Task 9',
            'last_name' => $suffix,
            'line_1' => '1 Test Fixture Road',
            'city' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'MY',
        ]);
        $order->payments()->create([
            'gateway' => 'bank_transfer',
            'status' => 'pending',
            'reference' => 'TASK9-'.$suffix,
        ]);

        return $order;
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }
}

final class SecurityThrowingMailer implements MailerContract
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
