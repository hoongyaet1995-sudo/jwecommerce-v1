<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @return never */
function failHarness(string $code): void
{
    fwrite(STDERR, json_encode(['ok' => false, 'code' => $code], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}

/** @param array<string, mixed> $payload */
function passHarness(array $payload): void
{
    fwrite(STDOUT, json_encode(['ok' => true, ...$payload], JSON_THROW_ON_ERROR).PHP_EOL);
}

/** @return array<string, int> */
function smokeCounts(): array
{
    return [
        'users' => DB::table('users')->count(),
        'customers' => DB::table('customers')->count(),
        'orders' => DB::table('orders')->count(),
        'notification_dispatches' => DB::table('notification_dispatches')->count(),
        'jobs' => DB::table('jobs')->count(),
        'failed_jobs' => DB::table('failed_jobs')->count(),
    ];
}

/** @return array<string, mixed> */
function readSmokeState(string $statePath, string $token): array
{
    if (! is_file($statePath)) {
        failHarness('state_missing');
    }

    $state = json_decode((string) file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($state) || ! hash_equals((string) ($state['token'] ?? ''), $token)) {
        failHarness('state_mismatch');
    }

    return $state;
}

$mode = $argv[1] ?? '';
$token = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_TOKEN');
$statePath = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_STATE_PATH');

if (
    app()->environment() !== 'testing'
    || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'jw_ecommerce_testing'
    || config('queue.default') !== 'database'
    || config('mail.default') !== 'array'
) {
    failHarness('unsafe_runtime');
}

if ($mode === 'probe') {
    passHarness([
        'mode' => 'probe',
        'environment' => app()->environment(),
        'connection' => config('database.default'),
        'database' => config('database.connections.mysql.database'),
        'queue' => config('queue.default'),
        'mailer' => config('mail.default'),
    ]);
    exit(0);
}

if (! preg_match('/^[a-z0-9]{12,40}$/', $token) || $statePath === '') {
    failHarness('invalid_fixture_identity');
}

try {
    if ($mode === 'setup') {
        if (is_file($statePath)) {
            failHarness('state_already_exists');
        }

        app(NotificationTemplateDefaults::class)->synchronize();
        $baselineCounts = smokeCounts();
        if ($baselineCounts['jobs'] !== 0) {
            failHarness('preexisting_jobs_refused');
        }

        $password = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_ADMIN_PASSWORD');
        if (strlen($password) < 16) {
            failHarness('invalid_fixture_password');
        }

        $admin = User::query()->create([
            'name' => 'Task 9 Smoke Admin',
            'email' => "task9-admin-{$token}@example.test",
            'password' => $password,
            'is_admin' => true,
            'is_super_admin' => true,
        ]);
        $customer = Customer::query()->create([
            'email' => "task9-customer-{$token}@example.test",
            'first_name' => 'Task Nine',
            'last_name' => 'Smoke',
            'phone' => '+601100000009',
            'status' => 'active',
        ]);
        $order = Order::query()->create([
            'number' => 'JW-TASK9-'.strtoupper(substr($token, 0, 12)),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 12900,
            'discount_amount' => 0,
            'shipping_amount' => 1000,
            'payment_surcharge_amount' => 0,
            'tax_amount' => 0,
            'total' => 13900,
        ]);
        $order->items()->create([
            'sku' => 'TASK9-'.strtoupper(substr($token, 0, 8)),
            'title' => 'Task 9 Smoke Product',
            'variant_title' => 'Dedicated Fixture',
            'unit_price' => 12900,
            'quantity' => 1,
            'line_total' => 12900,
        ]);
        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Task Nine',
            'last_name' => 'Smoke',
            'phone' => '+601100000009',
            'line_1' => '9 Local Smoke Lane',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'MY',
        ]);
        $order->payments()->create([
            'gateway' => 'simulated_card',
            'provider' => 'local_smoke',
            'environment' => 'testing',
            'status' => 'pending',
            'reference' => 'TASK9-PAY-'.strtoupper(substr($token, 0, 8)),
            'amount' => 13900,
            'currency' => 'MYR',
        ]);

        $privateMarker = "https://private.easyparcel.test/{$token}?token=TASK9_PRIVATE_TOKEN";
        foreach ([
            [NotificationDispatch::STATE_FAILED, "TASK9 FAILED {$token}"],
            [NotificationDispatch::STATE_SENT, "TASK9 SENT {$token}"],
        ] as [$dispatchState, $subject]) {
            NotificationDispatch::query()->create([
                'order_id' => $order->id,
                'notification_template_id' => NotificationTemplate::query()->where('event_key', 'order_created')->value('id'),
                'channel' => 'email',
                'event_key' => 'order_created',
                'source_type' => 'task9_smoke',
                'source_id' => $token,
                'recipient_email' => $customer->email,
                'recipient_name' => 'Task Nine Smoke',
                'rendered_subject' => $subject,
                'rendered_preheader' => 'Dedicated smoke dispatch.',
                'rendered_heading' => 'Smoke Fixture',
                'rendered_body' => "Private fixture body {$token}.",
                'rendered_cta_text' => 'View Order',
                'rendered_cta_url' => url('/order-tracking'),
                'template_version' => 1,
                'variable_snapshot' => ['fixture_token' => $token, 'private_provider_url' => $privateMarker],
                'state' => $dispatchState,
                'source' => NotificationDispatch::SOURCE_AUTOMATIC,
                'attempt_count' => $dispatchState === NotificationDispatch::STATE_FAILED ? 2 : 1,
                'queued_at' => now()->subMinutes(4),
                'sent_at' => $dispatchState === NotificationDispatch::STATE_SENT ? now()->subMinutes(3) : null,
                'failed_at' => $dispatchState === NotificationDispatch::STATE_FAILED ? now()->subMinutes(3) : null,
                'last_error_code' => $dispatchState === NotificationDispatch::STATE_FAILED ? 'transport' : null,
                'provider_message_id' => "TASK9_PROVIDER_PRIVATE_{$dispatchState}_{$token}",
            ]);
        }

        $state = [
            'token' => $token,
            'admin_id' => $admin->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'order_number' => $order->number,
            'baseline_counts' => $baselineCounts,
        ];
        $directory = dirname($statePath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            failHarness('state_directory_failed');
        }
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));

        passHarness([
            'mode' => 'setup',
            'database' => config('database.connections.mysql.database'),
            'orderNumber' => $order->number,
            'baselineCounts' => $baselineCounts,
            'fixtureCounts' => ['users' => 1, 'customers' => 1, 'orders' => 1, 'dispatches' => 2, 'jobs' => 0],
        ]);
        exit(0);
    }

    $state = readSmokeState($statePath, $token);
    if ($mode === 'cleanup') {
        DB::transaction(static function () use ($state): void {
            NotificationDispatch::query()->where('order_id', $state['order_id'])->delete();
            DB::table('sessions')->where('user_id', $state['admin_id'])->delete();
            Order::query()->whereKey($state['order_id'])->delete();
            Customer::query()->whereKey($state['customer_id'])->delete();
            User::query()->whereKey($state['admin_id'])->delete();
        });

        $finalCounts = smokeCounts();
        $countsRestored = $finalCounts === $state['baseline_counts'];
        @unlink($statePath);
        passHarness([
            'mode' => 'cleanup',
            'database' => config('database.connections.mysql.database'),
            'countsRestored' => $countsRestored,
            'finalCounts' => $finalCounts,
        ]);
        exit($countsRestored ? 0 : 1);
    }

    failHarness('unsupported_mode');
} catch (Throwable) {
    failHarness('harness_exception');
}
