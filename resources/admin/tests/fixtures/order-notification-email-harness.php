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

/** @return array<string, mixed> */
function assertSafeRuntime(): array
{
    $database = config('database.connections.mysql.database');
    $host = config('database.connections.mysql.host');
    $port = config('database.connections.mysql.port');
    $url = config('database.connections.mysql.url');
    $socket = config('database.connections.mysql.unix_socket');
    $queueConnection = config('queue.connections.database.connection');
    $sessionDriver = config('session.driver');
    $sessionConnection = config('session.connection');

    if (
        app()->environment() !== 'testing'
        || config('database.default') !== 'mysql'
        || $database !== 'jw_ecommerce_testing'
        || $host !== '127.0.0.1'
        || (string) $port !== '3306'
        || filled($url)
        || filled($socket)
        || config('queue.default') !== 'database'
        || $queueConnection !== 'mysql'
        || config('mail.default') !== 'array'
        || $sessionDriver !== 'database'
        || $sessionConnection !== 'mysql'
    ) {
        failHarness('unsafe_runtime_config');
    }

    try {
        $connectedDatabase = DB::connection('mysql')->selectOne('SELECT DATABASE() AS database_name');
    } catch (Throwable) {
        failHarness('database_probe_failed');
    }

    $actualDatabase = (string) ($connectedDatabase?->database_name ?? '');
    if ($actualDatabase !== 'jw_ecommerce_testing') {
        failHarness('unsafe_connected_database');
    }

    return [
        'environment' => app()->environment(),
        'connection' => config('database.default'),
        'database' => $database,
        'connectedDatabase' => $actualDatabase,
        'host' => $host,
        'port' => (int) $port,
        'urlPresent' => filled($url),
        'socketPresent' => filled($socket),
        'queue' => config('queue.default'),
        'queueConnection' => $queueConnection,
        'mailer' => config('mail.default'),
        'sessionDriver' => $sessionDriver,
        'sessionConnection' => $sessionConnection,
    ];
}

/** @return array<string, int> */
function smokeCounts(): array
{
    return [
        'users' => DB::table('users')->count(),
        'customers' => DB::table('customers')->count(),
        'orders' => DB::table('orders')->count(),
        'order_items' => DB::table('order_items')->count(),
        'order_addresses' => DB::table('order_addresses')->count(),
        'order_payments' => DB::table('order_payments')->count(),
        'notification_templates' => DB::table('notification_templates')->count(),
        'notification_dispatches' => DB::table('notification_dispatches')->count(),
        'jobs' => DB::table('jobs')->count(),
        'failed_jobs' => DB::table('failed_jobs')->count(),
        'sessions' => DB::table('sessions')->count(),
    ];
}

/** @param array<string, mixed> $payload */
function smokeHash(array $payload): string
{
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** @return list<array{id: int, eventKey: string, fingerprint: string}> */
function templateSnapshot(): array
{
    return NotificationTemplate::query()
        ->orderBy('event_key')
        ->get()
        ->map(static function (NotificationTemplate $template): array {
            $attributes = $template->getAttributes();
            unset($attributes['created_at'], $attributes['updated_at']);
            ksort($attributes);

            return [
                'id' => (int) $template->id,
                'eventKey' => (string) $template->event_key,
                'fingerprint' => smokeHash($attributes),
            ];
        })
        ->values()
        ->all();
}

/** @return array{adminEmail: string, customerEmail: string, orderNumber: string, dispatchSourceType: string, dispatchSourceId: string} */
function expectedOwnership(string $token): array
{
    return [
        'adminEmail' => "task9-admin-{$token}@example.test",
        'customerEmail' => "task9-customer-{$token}@example.test",
        'orderNumber' => 'JW-TASK9-'.strtoupper(substr($token, 0, 12)),
        'dispatchSourceType' => 'task9_smoke',
        'dispatchSourceId' => $token,
    ];
}

/** @return array<string, int> */
function businessCounts(): array
{
    $counts = smokeCounts();
    unset($counts['sessions']);

    return $counts;
}

/** @return array<string, mixed> */
function ownedBusinessSnapshot(string $token): array
{
    $ownership = expectedOwnership($token);
    $admin = User::query()->where('email', $ownership['adminEmail'])->first();
    $customer = Customer::query()->where('email', $ownership['customerEmail'])->first();
    $order = Order::query()->where('number', $ownership['orderNumber'])->first();

    $modelFingerprint = static function ($model): ?string {
        if ($model === null) {
            return null;
        }

        $attributes = $model->getAttributes();
        unset($attributes['created_at'], $attributes['updated_at'], $attributes['last_login_at']);
        ksort($attributes);

        return smokeHash($attributes);
    };
    $collectionFingerprint = static function ($models) use ($modelFingerprint): array {
        return $models->map($modelFingerprint)->values()->all();
    };

    return [
        'counts' => businessCounts(),
        'templates' => templateSnapshot(),
        'admin' => $modelFingerprint($admin),
        'customer' => $modelFingerprint($customer),
        'order' => $modelFingerprint($order),
        'items' => $order === null ? [] : $collectionFingerprint($order->items()->orderBy('id')->get()),
        'addresses' => $order === null ? [] : $collectionFingerprint($order->addresses()->orderBy('id')->get()),
        'payments' => $order === null ? [] : $collectionFingerprint($order->payments()->orderBy('id')->get()),
        'dispatches' => $collectionFingerprint(
            NotificationDispatch::query()
                ->where('source_type', $ownership['dispatchSourceType'])
                ->where('source_id', $ownership['dispatchSourceId'])
                ->orderBy('id')
                ->get(),
        ),
    ];
}

/** @return array{counts: array<string, int>, fingerprint: string} */
function businessEvidence(string $token): array
{
    $snapshot = ownedBusinessSnapshot($token);

    return [
        'counts' => $snapshot['counts'],
        'fingerprint' => smokeHash($snapshot),
    ];
}

/** @param array<string, mixed> $state
 * @return array<string, mixed>
 */
function signedSmokeState(array $state, string $token): array
{
    $state['signature'] = hash_hmac('sha256', smokeHash($state), $token);

    return $state;
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

    $signature = (string) ($state['signature'] ?? '');
    unset($state['signature']);
    if ($signature === '' || ! hash_equals(hash_hmac('sha256', smokeHash($state), $token), $signature)) {
        failHarness('state_signature_mismatch');
    }

    $state['signature'] = $signature;

    return $state;
}

$mode = $argv[1] ?? '';
$token = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_TOKEN');
$statePath = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_STATE_PATH');
$safeRuntime = assertSafeRuntime();

if ($mode === 'probe') {
    passHarness([
        'mode' => 'probe',
        ...$safeRuntime,
    ]);
    exit(0);
}

if ($mode === 'baseline') {
    $templates = templateSnapshot();
    passHarness([
        'mode' => 'baseline',
        'database' => $safeRuntime['database'],
        'counts' => smokeCounts(),
        'templateFingerprint' => smokeHash(['templates' => $templates]),
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

        $baselineCounts = smokeCounts();
        $baselineTemplates = templateSnapshot();
        if ($baselineCounts['jobs'] !== 0) {
            failHarness('preexisting_jobs_refused');
        }

        $password = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_ADMIN_PASSWORD');
        if (strlen($password) < 16) {
            failHarness('invalid_fixture_password');
        }

        $failurePoint = (string) getenv('ORDER_NOTIFICATION_ACCEPTANCE_FAIL_SETUP_AT');
        if (! in_array($failurePoint, ['', 'after_templates', 'after_fixture', 'after_state_write'], true)) {
            failHarness('invalid_failure_injection');
        }
        $ownership = expectedOwnership($token);

        $state = DB::transaction(static function () use (
            $baselineCounts,
            $baselineTemplates,
            $failurePoint,
            $ownership,
            $password,
            $statePath,
            $token,
        ): array {
            $ownedCollision = User::query()->where('email', $ownership['adminEmail'])->exists()
                || Customer::query()->where('email', $ownership['customerEmail'])->exists()
                || Order::query()->where('number', $ownership['orderNumber'])->exists()
                || NotificationDispatch::query()
                    ->where('source_type', $ownership['dispatchSourceType'])
                    ->where('source_id', $ownership['dispatchSourceId'])
                    ->exists();
            if ($ownedCollision) {
                throw new RuntimeException('fixture_identity_collision');
            }

            app(NotificationTemplateDefaults::class)->synchronize();
            if ($failurePoint === 'after_templates') {
                throw new RuntimeException('injected_setup_failure');
            }

            $admin = User::query()->create([
                'name' => 'Task 9 Smoke Admin',
                'email' => $ownership['adminEmail'],
                'password' => $password,
                'is_admin' => true,
                'is_super_admin' => true,
            ]);
            $customer = Customer::query()->create([
                'email' => $ownership['customerEmail'],
                'first_name' => 'Task Nine',
                'last_name' => 'Smoke',
                'phone' => '+601100000009',
                'status' => 'active',
            ]);
            $order = Order::query()->create([
                'number' => $ownership['orderNumber'],
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
                    'source_type' => $ownership['dispatchSourceType'],
                    'source_id' => $ownership['dispatchSourceId'],
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

            if ($failurePoint === 'after_fixture') {
                throw new RuntimeException('injected_setup_failure');
            }

            $postSetupTemplates = templateSnapshot();
            $baselineEventKeys = array_column($baselineTemplates, 'eventKey');
            $createdTemplates = array_values(array_filter(
                $postSetupTemplates,
                static fn (array $template): bool => ! in_array($template['eventKey'], $baselineEventKeys, true),
            ));
            $business = businessEvidence($token);
            $state = signedSmokeState([
                'token' => $token,
                'ownership' => $ownership,
                'admin_id' => $admin->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'baseline_counts' => $baselineCounts,
                'baseline_templates' => $baselineTemplates,
                'post_setup_templates' => $postSetupTemplates,
                'created_templates' => $createdTemplates,
                'setup_business_counts' => $business['counts'],
                'setup_business_fingerprint' => $business['fingerprint'],
            ], $token);
            $directory = dirname($statePath);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('state_directory_failed');
            }
            if (file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('state_write_failed');
            }
            if ($failurePoint === 'after_state_write') {
                throw new RuntimeException('injected_setup_failure');
            }

            return $state;
        });

        passHarness([
            'mode' => 'setup',
            'database' => config('database.connections.mysql.database'),
            'orderNumber' => $state['ownership']['orderNumber'],
            'baselineCounts' => $baselineCounts,
            'baselineTemplateCount' => count($baselineTemplates),
            'createdTemplateCount' => count($state['created_templates']),
            'fixtureCounts' => ['users' => 1, 'customers' => 1, 'orders' => 1, 'dispatches' => 2, 'jobs' => 0, 'failedJobs' => 0],
            'businessCounts' => $state['setup_business_counts'],
            'businessFingerprint' => $state['setup_business_fingerprint'],
        ]);
        exit(0);
    }

    $state = readSmokeState($statePath, $token);
    if ($mode === 'snapshot') {
        $business = businessEvidence($token);
        passHarness([
            'mode' => 'snapshot',
            'database' => $safeRuntime['database'],
            'counts' => $business['counts'],
            'businessFingerprint' => $business['fingerprint'],
        ]);
        exit(0);
    }

    if ($mode === 'cleanup') {
        $ownership = expectedOwnership($token);
        if ($state['ownership'] !== $ownership) {
            failHarness('state_ownership_mismatch');
        }

        $preCleanupBusiness = businessEvidence($token);
        $businessStateUnchanged = hash_equals(
            (string) $state['setup_business_fingerprint'],
            $preCleanupBusiness['fingerprint'],
        ) && $state['setup_business_counts'] === $preCleanupBusiness['counts'];
        if (templateSnapshot() !== $state['post_setup_templates']) {
            failHarness('template_ownership_mismatch');
        }
        $currentCounts = smokeCounts();
        if (
            $currentCounts['jobs'] !== $state['baseline_counts']['jobs']
            || $currentCounts['failed_jobs'] !== $state['baseline_counts']['failed_jobs']
        ) {
            failHarness('unexpected_queue_rows');
        }

        DB::transaction(static function () use ($ownership, $state): void {
            $admin = User::query()->where('email', $ownership['adminEmail'])->sole();
            $customer = Customer::query()->where('email', $ownership['customerEmail'])->sole();
            $order = Order::query()->where('number', $ownership['orderNumber'])->sole();
            $dispatches = NotificationDispatch::query()
                ->where('source_type', $ownership['dispatchSourceType'])
                ->where('source_id', $ownership['dispatchSourceId'])
                ->get();

            if (
                (int) $admin->id !== (int) $state['admin_id']
                || (int) $customer->id !== (int) $state['customer_id']
                || (int) $order->id !== (int) $state['order_id']
                || (int) $order->customer_id !== (int) $customer->id
                || $order->email !== $ownership['customerEmail']
                || $dispatches->count() !== 2
                || $dispatches->contains(static fn (NotificationDispatch $dispatch): bool => (int) $dispatch->order_id !== (int) $order->id)
            ) {
                throw new RuntimeException('fixture_ownership_mismatch');
            }

            NotificationDispatch::query()
                ->where('source_type', $ownership['dispatchSourceType'])
                ->where('source_id', $ownership['dispatchSourceId'])
                ->delete();
            DB::table('sessions')->where('user_id', $admin->id)->delete();
            $order->delete();
            $customer->delete();
            $admin->delete();

            foreach ($state['created_templates'] as $createdTemplate) {
                $template = NotificationTemplate::query()
                    ->whereKey($createdTemplate['id'])
                    ->where('event_key', $createdTemplate['eventKey'])
                    ->sole();
                $snapshot = array_values(array_filter(
                    templateSnapshot(),
                    static fn (array $candidate): bool => $candidate['id'] === (int) $template->id,
                ));
                if ($snapshot !== [$createdTemplate]) {
                    throw new RuntimeException('created_template_ownership_mismatch');
                }
                $template->delete();
            }
        });

        $finalCounts = smokeCounts();
        $countsRestored = $finalCounts === $state['baseline_counts']
            && templateSnapshot() === $state['baseline_templates'];
        if ($countsRestored) {
            @unlink($statePath);
        }
        passHarness([
            'mode' => 'cleanup',
            'database' => config('database.connections.mysql.database'),
            'countsRestored' => $countsRestored,
            'businessStateUnchanged' => $businessStateUnchanged,
            'preCleanupBusinessFingerprint' => $preCleanupBusiness['fingerprint'],
            'finalCounts' => $finalCounts,
        ]);
        exit($countsRestored ? 0 : 1);
    }

    failHarness('unsupported_mode');
} catch (Throwable) {
    if ($mode === 'setup') {
        @unlink($statePath);
    }
    failHarness('harness_exception');
}
