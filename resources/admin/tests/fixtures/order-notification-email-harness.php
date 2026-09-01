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
    $counts = [
        'users' => DB::table('users')->count(),
        'customers' => DB::table('customers')->count(),
        'orders' => DB::table('orders')->count(),
        'order_items' => DB::table('order_items')->count(),
        'order_addresses' => DB::table('order_addresses')->count(),
        'order_payments' => DB::table('order_payments')->count(),
        'order_status_histories' => DB::table('order_status_histories')->count(),
        'order_refunds' => DB::table('order_refunds')->count(),
        'order_tax_lines' => DB::table('order_tax_lines')->count(),
        'coupon_usages' => DB::table('coupon_usages')->count(),
        'order_tracking_verifications' => DB::table('order_tracking_verifications')->count(),
        'order_payment_proof_events' => DB::table('order_payment_proof_events')->count(),
        'payment_webhook_events' => DB::table('payment_webhook_events')->count(),
        'shipments' => DB::table('shipments')->count(),
        'shipment_events' => DB::table('shipment_events')->count(),
        'shipment_quotes' => DB::table('shipment_quotes')->count(),
        'shipping_webhook_receipts' => DB::table('shipping_webhook_receipts')->count(),
        'easyparcel_reconciliation_tasks' => DB::table('easyparcel_reconciliation_tasks')->count(),
        'easyparcel_shipment_recoveries' => DB::table('easyparcel_shipment_recoveries')->count(),
        'product_reviews' => DB::table('product_reviews')->count(),
        'product_review_media' => DB::table('product_review_media')->count(),
        'product_review_events' => DB::table('product_review_events')->count(),
        'notification_templates' => DB::table('notification_templates')->count(),
        'notification_dispatches' => DB::table('notification_dispatches')->count(),
        'jobs' => DB::table('jobs')->count(),
        'failed_jobs' => DB::table('failed_jobs')->count(),
        'sessions' => DB::table('sessions')->count(),
    ];

    foreach (fixtureClosureTables() as $table) {
        $counts[$table] ??= DB::table($table)->count();
    }

    ksort($counts);

    return $counts;
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

/** @return array{adminEmail: string, customerEmail: string, orderNumber: string, itemSku: string, addressMarker: string, paymentReference: string, dispatchSourceType: string, dispatchSourceId: string} */
function expectedOwnership(string $token): array
{
    $shortToken = strtoupper(substr($token, 0, 8));

    return [
        'adminEmail' => "task9-admin-{$token}@example.test",
        'customerEmail' => "task9-customer-{$token}@example.test",
        'orderNumber' => 'JW-TASK9-'.strtoupper(substr($token, 0, 12)),
        'itemSku' => 'TASK9-'.$shortToken,
        'addressMarker' => 'TASK9-ADDRESS-'.$shortToken,
        'paymentReference' => 'TASK9-PAY-'.$shortToken,
        'dispatchSourceType' => 'task9_smoke',
        'dispatchSourceId' => $token,
    ];
}

/** @return array{ids: list<int>, fingerprint: string} */
function querySnapshot($query, bool $lockForUpdate = false): array
{
    if ($lockForUpdate) {
        $query->lockForUpdate();
    }

    $rows = $query->orderBy('id')->get()->map(static function (object $row): array {
        $attributes = (array) $row;
        ksort($attributes);

        return $attributes;
    })->values()->all();

    return [
        'ids' => array_map(static fn (array $row): int => (int) $row['id'], $rows),
        'fingerprint' => smokeHash(['rows' => $rows]),
    ];
}

/** @param list<int> $ids */
function constrainIds($query, string $column, array $ids): void
{
    if ($ids === []) {
        $query->whereRaw('1 = 0');

        return;
    }

    $query->whereIn($column, $ids);
}

/** @return list<array{childTable: string, childColumn: string, parentTable: string, parentColumn: string}> */
function fixtureForeignKeys(): array
{
    return array_map(
        static fn (object $row): array => [
            'childTable' => (string) $row->child_table,
            'childColumn' => (string) $row->child_column,
            'parentTable' => (string) $row->parent_table,
            'parentColumn' => (string) $row->parent_column,
        ],
        DB::select(<<<'SQL'
            SELECT
                TABLE_NAME AS child_table,
                COLUMN_NAME AS child_column,
                REFERENCED_TABLE_NAME AS parent_table,
                REFERENCED_COLUMN_NAME AS parent_column
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY REFERENCED_TABLE_NAME, TABLE_NAME, COLUMN_NAME
            SQL),
    );
}

/** @return list<string> */
function fixtureClosureTables(): array
{
    $reachable = array_fill_keys([
        'users',
        'customers',
        'orders',
        'notification_templates',
        'notification_dispatches',
    ], true);
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach (fixtureForeignKeys() as $foreignKey) {
            if (isset($reachable[$foreignKey['parentTable']]) && ! isset($reachable[$foreignKey['childTable']])) {
                $reachable[$foreignKey['childTable']] = true;
                $changed = true;
            }
        }
    }

    $tables = array_keys($reachable);
    sort($tables);

    return $tables;
}

/**
 * Build the exact row closure reachable through real MySQL foreign keys from
 * the dedicated Admin, Customer, Order, created templates, and dispatches.
 * Empty reachable tables remain in the snapshot so a later association cannot
 * be hidden by parent cascade/nullOnDelete behavior.
 *
 * @param  array<string, list<int>>  $rootIds
 * @return array<string, array{ids: list<int>, fingerprint: string}>
 */
function fixtureOwnerClosure(array $rootIds, bool $lockForUpdate = false): array
{
    $idsByTable = [];
    foreach (fixtureClosureTables() as $table) {
        $idsByTable[$table] = [];
    }
    foreach ($rootIds as $table => $ids) {
        if (! array_key_exists($table, $idsByTable)) {
            throw new RuntimeException('fixture_root_table_not_reachable');
        }
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        sort($normalized);
        $idsByTable[$table] = $normalized;
    }

    $foreignKeys = fixtureForeignKeys();
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($foreignKeys as $foreignKey) {
            if (! array_key_exists($foreignKey['parentTable'], $idsByTable)
                || ! array_key_exists($foreignKey['childTable'], $idsByTable)
                || $idsByTable[$foreignKey['parentTable']] === []) {
                continue;
            }
            if ($foreignKey['parentColumn'] !== 'id') {
                throw new RuntimeException('fixture_non_id_parent_foreign_key');
            }

            $query = DB::table($foreignKey['childTable'])
                ->whereIn($foreignKey['childColumn'], $idsByTable[$foreignKey['parentTable']]);
            if ($lockForUpdate) {
                $query->lockForUpdate();
            }
            $discovered = array_map('intval', $query->pluck('id')->all());
            $merged = array_values(array_unique([...$idsByTable[$foreignKey['childTable']], ...$discovered]));
            sort($merged);
            if ($merged !== $idsByTable[$foreignKey['childTable']]) {
                $idsByTable[$foreignKey['childTable']] = $merged;
                $changed = true;
            }
        }
    }

    $snapshots = [];
    foreach ($idsByTable as $table => $ids) {
        $query = DB::table($table);
        constrainIds($query, 'id', $ids);
        if ($table === 'users') {
            if ($lockForUpdate) {
                $query->lockForUpdate();
            }
            $rows = $query->orderBy('id')->get()->map(static function (object $row): array {
                $attributes = (array) $row;
                unset($attributes['created_at'], $attributes['updated_at'], $attributes['last_login_at']);
                ksort($attributes);

                return $attributes;
            })->values()->all();
            $snapshots[$table] = [
                'ids' => array_map(static fn (array $row): int => (int) $row['id'], $rows),
                'fingerprint' => smokeHash(['rows' => $rows]),
            ];

            continue;
        }
        $snapshots[$table] = querySnapshot($query, $lockForUpdate);
    }

    ksort($snapshots);

    return $snapshots;
}

/** @return array{ids: list<string>, fingerprint: string} */
function ownerSessionSnapshot(int $adminId, bool $lockForUpdate = false): array
{
    $query = DB::table('sessions')->where('user_id', $adminId);
    if ($lockForUpdate) {
        $query->lockForUpdate();
    }
    $rows = $query->orderBy('id')->get()->map(static function (object $row): array {
        $attributes = (array) $row;
        ksort($attributes);

        return $attributes;
    })->values()->all();

    return [
        'ids' => array_map(static fn (array $row): string => (string) $row['id'], $rows),
        'fingerprint' => smokeHash(['rows' => $rows]),
    ];
}

/** @return array<string, array{ids: list<int>, fingerprint: string}> */
function orderAssociationSnapshot(int $orderId, bool $lockForUpdate = false): array
{
    $snapshots = [];
    foreach ([
        'order_items',
        'order_addresses',
        'order_payments',
        'order_status_histories',
        'order_refunds',
        'order_tax_lines',
        'coupon_usages',
        'order_tracking_verifications',
        'order_payment_proof_events',
        'shipments',
        'shipment_quotes',
        'easyparcel_shipment_recoveries',
        'notification_dispatches',
    ] as $table) {
        $snapshots[$table] = querySnapshot(DB::table($table)->where('order_id', $orderId), $lockForUpdate);
    }

    $dependentTables = [
        'shipment_events' => ['shipment_id', $snapshots['shipments']['ids']],
        'payment_webhook_events' => ['order_payment_id', $snapshots['order_payments']['ids']],
        'shipping_webhook_receipts' => ['shipment_id', $snapshots['shipments']['ids']],
        'product_reviews' => ['order_item_id', $snapshots['order_items']['ids']],
    ];
    foreach ($dependentTables as $table => [$column, $ids]) {
        $query = DB::table($table);
        constrainIds($query, $column, $ids);
        $snapshots[$table] = querySnapshot($query, $lockForUpdate);
    }

    foreach ([
        'product_review_media' => 'product_review_id',
        'product_review_events' => 'product_review_id',
    ] as $table => $column) {
        $query = DB::table($table);
        constrainIds($query, $column, $snapshots['product_reviews']['ids']);
        $snapshots[$table] = querySnapshot($query, $lockForUpdate);
    }

    $reconciliation = DB::table('easyparcel_reconciliation_tasks');
    $quoteIds = $snapshots['shipment_quotes']['ids'];
    $shipmentIds = $snapshots['shipments']['ids'];
    $receiptIds = $snapshots['shipping_webhook_receipts']['ids'];
    if ($quoteIds === [] && $shipmentIds === [] && $receiptIds === []) {
        $reconciliation->whereRaw('1 = 0');
    } else {
        $reconciliation->where(static function ($query) use ($quoteIds, $shipmentIds, $receiptIds): void {
            if ($quoteIds !== []) {
                $query->orWhereIn('shipment_quote_id', $quoteIds);
            }
            if ($shipmentIds !== []) {
                $query->orWhereIn('shipment_id', $shipmentIds);
            }
            if ($receiptIds !== []) {
                $query->orWhereIn('shipping_webhook_receipt_id', $receiptIds);
            }
        });
    }
    $snapshots['easyparcel_reconciliation_tasks'] = querySnapshot($reconciliation, $lockForUpdate);

    ksort($snapshots);

    return $snapshots;
}

/** @return array<string, int> */
function businessCounts(): array
{
    $counts = smokeCounts();
    unset($counts['sessions']);

    return $counts;
}

/** @return array<string, mixed> */
function ownedBusinessSnapshot(string $token, array $ownerRoots): array
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

    return [
        'counts' => businessCounts(),
        'templates' => templateSnapshot(),
        'admin' => $modelFingerprint($admin),
        'customer' => $modelFingerprint($customer),
        'order' => $modelFingerprint($order),
        'associations' => fixtureOwnerClosure($ownerRoots),
    ];
}

/** @return array{counts: array<string, int>, fingerprint: string} */
function businessEvidence(string $token, array $ownerRoots): array
{
    $snapshot = ownedBusinessSnapshot($token, $ownerRoots);

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
                'sku' => $ownership['itemSku'],
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
                'line_2' => $ownership['addressMarker'],
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
                'reference' => $ownership['paymentReference'],
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
            $ownedAssociations = orderAssociationSnapshot((int) $order->id);
            $ownerRoots = [
                'users' => [(int) $admin->id],
                'customers' => [(int) $customer->id],
                'orders' => [(int) $order->id],
                'notification_templates' => array_map(
                    static fn (array $template): int => (int) $template['id'],
                    $createdTemplates,
                ),
                'notification_dispatches' => $ownedAssociations['notification_dispatches']['ids'],
            ];
            $ownerClosure = fixtureOwnerClosure($ownerRoots);
            $business = businessEvidence($token, $ownerRoots);
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
                'owned_associations' => $ownedAssociations,
                'owner_roots' => $ownerRoots,
                'owner_closure' => $ownerClosure,
                'owned_sessions' => ownerSessionSnapshot((int) $admin->id),
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
            'orderId' => $state['order_id'],
            'baselineCounts' => $baselineCounts,
            'baselineTemplateCount' => count($baselineTemplates),
            'createdTemplateCount' => count($state['created_templates']),
            'fixtureCounts' => ['users' => 1, 'customers' => 1, 'orders' => 1, 'dispatches' => 2, 'jobs' => 0, 'failedJobs' => 0],
            'businessCounts' => $state['setup_business_counts'],
            'businessFingerprint' => $state['setup_business_fingerprint'],
            'ownedAssociations' => $state['owned_associations'],
            'ownerClosure' => $state['owner_closure'],
        ]);
        exit(0);
    }

    $state = readSmokeState($statePath, $token);
    if ($mode === 'inject-cleanup-guard') {
        $ownership = expectedOwnership($token);
        if ($state['ownership'] !== $ownership) {
            failHarness('state_ownership_mismatch');
        }
        if (orderAssociationSnapshot((int) $state['order_id']) !== $state['owned_associations']) {
            failHarness('fixture_association_mismatch');
        }

        $guardId = DB::transaction(static function () use ($ownership, $state, $token): int {
            $order = Order::query()
                ->whereKey($state['order_id'])
                ->where('number', $ownership['orderNumber'])
                ->lockForUpdate()
                ->sole();
            if (orderAssociationSnapshot((int) $order->id, true) !== $state['owned_associations']) {
                throw new RuntimeException('fixture_association_mismatch');
            }

            return (int) DB::table('order_status_histories')->insertGetId([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status,
                'user_id' => $state['admin_id'],
                'note' => "TASK9-CLEANUP-GUARD-{$token}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        passHarness([
            'mode' => 'inject-cleanup-guard',
            'database' => $safeRuntime['database'],
            'associationGuardId' => $guardId,
        ]);
        exit(0);
    }

    if ($mode === 'remove-cleanup-guard') {
        $ownership = expectedOwnership($token);
        if ($state['ownership'] !== $ownership) {
            failHarness('state_ownership_mismatch');
        }

        DB::transaction(static function () use ($ownership, $state, $token): void {
            $admin = User::query()
                ->whereKey($state['admin_id'])
                ->where('email', $ownership['adminEmail'])
                ->lockForUpdate()
                ->sole();
            $order = Order::query()
                ->whereKey($state['order_id'])
                ->where('number', $ownership['orderNumber'])
                ->lockForUpdate()
                ->sole();
            $guardRows = DB::table('order_status_histories')
                ->where('order_id', $order->id)
                ->where('user_id', $admin->id)
                ->where('note', "TASK9-CLEANUP-GUARD-{$token}")
                ->lockForUpdate()
                ->get();
            if ($guardRows->count() !== 1) {
                throw new RuntimeException('cleanup_guard_ownership_mismatch');
            }
            $deleted = DB::table('order_status_histories')
                ->where('id', $guardRows->first()->id)
                ->where('order_id', $order->id)
                ->where('user_id', $admin->id)
                ->where('note', "TASK9-CLEANUP-GUARD-{$token}")
                ->delete();
            if ($deleted !== 1 || orderAssociationSnapshot((int) $order->id, true) !== $state['owned_associations']) {
                throw new RuntimeException('cleanup_guard_restore_mismatch');
            }
        });

        $business = businessEvidence($token, $state['owner_roots']);
        $restored = $business['counts'] === $state['setup_business_counts']
            && hash_equals((string) $state['setup_business_fingerprint'], $business['fingerprint']);
        if (! $restored) {
            failHarness('cleanup_guard_restore_mismatch');
        }
        passHarness([
            'mode' => 'remove-cleanup-guard',
            'database' => $safeRuntime['database'],
            'associationStateRestored' => true,
        ]);
        exit(0);
    }

    if ($mode === 'inject-owner-cleanup-guard') {
        $ownership = expectedOwnership($token);
        if ($state['ownership'] !== $ownership) {
            failHarness('state_ownership_mismatch');
        }
        if (fixtureOwnerClosure($state['owner_roots']) !== $state['owner_closure']) {
            failHarness('fixture_owner_closure_mismatch');
        }

        $guardIds = DB::transaction(static function () use ($ownership, $state, $token): array {
            $admin = User::query()
                ->whereKey($state['admin_id'])
                ->where('email', $ownership['adminEmail'])
                ->lockForUpdate()
                ->sole();
            $customer = Customer::query()
                ->whereKey($state['customer_id'])
                ->where('email', $ownership['customerEmail'])
                ->lockForUpdate()
                ->sole();

            $addressId = DB::table('customer_addresses')->insertGetId([
                'customer_id' => $customer->id,
                'label' => "TASK9-OWNER-GUARD-{$token}",
                'is_default' => false,
                'first_name' => 'Task Nine',
                'last_name' => 'Owner Guard',
                'phone' => null,
                'line_1' => '9 Fail Closed Lane',
                'line_2' => $token,
                'city' => 'Kuala Lumpur',
                'state' => 'Kuala Lumpur',
                'postal_code' => '50000',
                'country' => 'MY',
                'type' => 'shipping',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permissionId = DB::table('user_permissions')->insertGetId([
                'user_id' => $admin->id,
                'permission' => "task9.owner-guard.{$token}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'customerAddressId' => (int) $addressId,
                'userPermissionId' => (int) $permissionId,
            ];
        });

        passHarness([
            'mode' => 'inject-owner-cleanup-guard',
            'database' => $safeRuntime['database'],
            ...$guardIds,
        ]);
        exit(0);
    }

    if ($mode === 'remove-owner-cleanup-guard') {
        $ownership = expectedOwnership($token);
        if ($state['ownership'] !== $ownership) {
            failHarness('state_ownership_mismatch');
        }

        DB::transaction(static function () use ($ownership, $state, $token): void {
            $admin = User::query()
                ->whereKey($state['admin_id'])
                ->where('email', $ownership['adminEmail'])
                ->lockForUpdate()
                ->sole();
            $customer = Customer::query()
                ->whereKey($state['customer_id'])
                ->where('email', $ownership['customerEmail'])
                ->lockForUpdate()
                ->sole();
            if (fixtureOwnerClosure($state['owner_roots'], true) === $state['owner_closure']) {
                throw new RuntimeException('owner_cleanup_guard_missing');
            }
            $addresses = DB::table('customer_addresses')
                ->where('customer_id', $customer->id)
                ->where('label', "TASK9-OWNER-GUARD-{$token}")
                ->where('line_2', $token)
                ->lockForUpdate()
                ->get();
            $permissions = DB::table('user_permissions')
                ->where('user_id', $admin->id)
                ->where('permission', "task9.owner-guard.{$token}")
                ->lockForUpdate()
                ->get();
            if ($addresses->count() !== 1 || $permissions->count() !== 1) {
                throw new RuntimeException('owner_cleanup_guard_ownership_mismatch');
            }
            $deletedAddresses = DB::table('customer_addresses')
                ->where('id', $addresses->first()->id)
                ->where('customer_id', $customer->id)
                ->where('label', "TASK9-OWNER-GUARD-{$token}")
                ->where('line_2', $token)
                ->delete();
            $deletedPermissions = DB::table('user_permissions')
                ->where('id', $permissions->first()->id)
                ->where('user_id', $admin->id)
                ->where('permission', "task9.owner-guard.{$token}")
                ->delete();
            if ($deletedAddresses !== 1 || $deletedPermissions !== 1) {
                throw new RuntimeException('owner_cleanup_guard_delete_mismatch');
            }
        });

        $business = businessEvidence($token, $state['owner_roots']);
        $restored = $business['counts'] === $state['setup_business_counts']
            && hash_equals((string) $state['setup_business_fingerprint'], $business['fingerprint'])
            && fixtureOwnerClosure($state['owner_roots']) === $state['owner_closure'];
        if (! $restored) {
            failHarness('owner_cleanup_guard_restore_mismatch');
        }

        passHarness([
            'mode' => 'remove-owner-cleanup-guard',
            'database' => $safeRuntime['database'],
            'ownerAssociationStateRestored' => true,
        ]);
        exit(0);
    }

    if ($mode === 'seal-browser-sessions') {
        $ownership = expectedOwnership($token);
        if ($state['ownership'] !== $ownership || ($state['owned_sessions']['ids'] ?? null) !== []) {
            failHarness('session_seal_state_mismatch');
        }

        $sessionSnapshot = DB::transaction(static function () use ($ownership, $state): array {
            $admin = User::query()
                ->whereKey($state['admin_id'])
                ->where('email', $ownership['adminEmail'])
                ->lockForUpdate()
                ->sole();
            if (fixtureOwnerClosure($state['owner_roots'], true) !== $state['owner_closure']) {
                throw new RuntimeException('fixture_owner_closure_mismatch');
            }
            $sessions = DB::table('sessions')
                ->where('user_id', $admin->id)
                ->lockForUpdate()
                ->get();
            if ($sessions->count() !== 1 || $sessions->contains(static function (object $session): bool {
                return ! in_array((string) $session->ip_address, ['127.0.0.1', '::1'], true)
                    || ! str_contains((string) $session->user_agent, 'Chrome/')
                    || (string) $session->payload === '';
            })) {
                throw new RuntimeException('unexpected_fixture_session');
            }

            return ownerSessionSnapshot((int) $admin->id, true);
        });

        unset($state['signature']);
        $state['owned_sessions'] = $sessionSnapshot;
        $state = signedSmokeState($state, $token);
        if (file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
            failHarness('session_state_write_failed');
        }

        passHarness([
            'mode' => 'seal-browser-sessions',
            'database' => $safeRuntime['database'],
            'ownedSessionIds' => $sessionSnapshot['ids'],
            'ownerClosureTableCount' => count($state['owner_closure']),
        ]);
        exit(0);
    }

    if ($mode === 'snapshot') {
        $business = businessEvidence($token, $state['owner_roots']);
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

        $preCleanupBusiness = businessEvidence($token, $state['owner_roots']);
        $businessStateUnchanged = hash_equals(
            (string) $state['setup_business_fingerprint'],
            $preCleanupBusiness['fingerprint'],
        ) && $state['setup_business_counts'] === $preCleanupBusiness['counts'];
        if (! $businessStateUnchanged) {
            failHarness('fixture_business_state_changed');
        }
        $currentAssociations = orderAssociationSnapshot((int) $state['order_id']);
        if (($state['owned_associations'] ?? null) !== $currentAssociations) {
            failHarness('fixture_association_mismatch');
        }
        $currentOwnerClosure = fixtureOwnerClosure($state['owner_roots']);
        if (($state['owner_closure'] ?? null) !== $currentOwnerClosure) {
            failHarness('fixture_owner_closure_mismatch');
        }
        $currentSessions = ownerSessionSnapshot((int) $state['admin_id']);
        if (($state['owned_sessions'] ?? null) !== $currentSessions) {
            failHarness('fixture_session_mismatch');
        }
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
            $admin = User::query()->where('email', $ownership['adminEmail'])->lockForUpdate()->sole();
            $customer = Customer::query()->where('email', $ownership['customerEmail'])->lockForUpdate()->sole();
            $order = Order::query()->where('number', $ownership['orderNumber'])->lockForUpdate()->sole();
            $dispatches = NotificationDispatch::query()
                ->where('source_type', $ownership['dispatchSourceType'])
                ->where('source_id', $ownership['dispatchSourceId'])
                ->lockForUpdate()
                ->get();
            $lockedAssociations = orderAssociationSnapshot((int) $order->id, true);
            $lockedOwnerClosure = fixtureOwnerClosure($state['owner_roots'], true);
            $lockedSessions = ownerSessionSnapshot((int) $admin->id, true);

            if (
                (int) $admin->id !== (int) $state['admin_id']
                || (int) $customer->id !== (int) $state['customer_id']
                || (int) $order->id !== (int) $state['order_id']
                || (int) $order->customer_id !== (int) $customer->id
                || $order->email !== $ownership['customerEmail']
                || $dispatches->count() !== 2
                || $dispatches->contains(static fn (NotificationDispatch $dispatch): bool => (int) $dispatch->order_id !== (int) $order->id)
                || $lockedAssociations !== $state['owned_associations']
                || $lockedOwnerClosure !== $state['owner_closure']
                || $lockedSessions !== $state['owned_sessions']
            ) {
                throw new RuntimeException('fixture_ownership_mismatch');
            }

            $expectedOwnerIds = [
                'users' => [(int) $admin->id],
                'customers' => [(int) $customer->id],
                'orders' => [(int) $order->id],
                'notification_templates' => array_map(
                    static fn (array $template): int => (int) $template['id'],
                    $state['created_templates'],
                ),
                'notification_dispatches' => $lockedAssociations['notification_dispatches']['ids'],
                'order_items' => $lockedAssociations['order_items']['ids'],
                'order_addresses' => $lockedAssociations['order_addresses']['ids'],
                'order_payments' => $lockedAssociations['order_payments']['ids'],
            ];
            foreach ($lockedOwnerClosure as $table => $snapshot) {
                $expectedIds = $expectedOwnerIds[$table] ?? [];
                sort($expectedIds);
                if ($snapshot['ids'] !== $expectedIds) {
                    throw new RuntimeException('unexpected_fixture_owner_association');
                }
            }

            $expectedAssociationCounts = array_fill_keys(array_keys($lockedAssociations), 0);
            $expectedAssociationCounts['order_items'] = 1;
            $expectedAssociationCounts['order_addresses'] = 1;
            $expectedAssociationCounts['order_payments'] = 1;
            $expectedAssociationCounts['notification_dispatches'] = 2;
            foreach ($lockedAssociations as $table => $snapshot) {
                if (count($snapshot['ids']) !== $expectedAssociationCounts[$table]) {
                    throw new RuntimeException('unexpected_fixture_association');
                }
            }

            $item = DB::table('order_items')->where('id', $lockedAssociations['order_items']['ids'][0])->first();
            $address = DB::table('order_addresses')->where('id', $lockedAssociations['order_addresses']['ids'][0])->first();
            $payment = DB::table('order_payments')->where('id', $lockedAssociations['order_payments']['ids'][0])->first();
            if (
                $item === null
                || (int) $item->order_id !== (int) $order->id
                || $item->sku !== $ownership['itemSku']
                || $address === null
                || (int) $address->order_id !== (int) $order->id
                || $address->line_2 !== $ownership['addressMarker']
                || $payment === null
                || (int) $payment->order_id !== (int) $order->id
                || $payment->reference !== $ownership['paymentReference']
            ) {
                throw new RuntimeException('fixture_child_ownership_mismatch');
            }

            $deletedDispatches = NotificationDispatch::query()
                ->where('source_type', $ownership['dispatchSourceType'])
                ->where('source_id', $ownership['dispatchSourceId'])
                ->where('order_id', $order->id)
                ->whereIn('id', $lockedAssociations['notification_dispatches']['ids'])
                ->delete();
            $deletedPayments = DB::table('order_payments')
                ->where('order_id', $order->id)
                ->whereIn('id', $lockedAssociations['order_payments']['ids'])
                ->delete();
            $deletedAddresses = DB::table('order_addresses')
                ->where('order_id', $order->id)
                ->whereIn('id', $lockedAssociations['order_addresses']['ids'])
                ->delete();
            $deletedItems = DB::table('order_items')
                ->where('order_id', $order->id)
                ->whereIn('id', $lockedAssociations['order_items']['ids'])
                ->delete();
            if ($deletedDispatches !== 2 || $deletedPayments !== 1 || $deletedAddresses !== 1 || $deletedItems !== 1) {
                throw new RuntimeException('fixture_child_delete_mismatch');
            }

            foreach (orderAssociationSnapshot((int) $order->id, true) as $snapshot) {
                if ($snapshot['ids'] !== []) {
                    throw new RuntimeException('fixture_association_remains');
                }
            }

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

            $deletedSessions = DB::table('sessions')
                ->where('user_id', $admin->id)
                ->whereIn('id', $lockedSessions['ids'])
                ->delete();
            if ($deletedSessions !== count($lockedSessions['ids'])) {
                throw new RuntimeException('fixture_session_delete_mismatch');
            }

            $deletedOrder = Order::query()
                ->whereKey($order->id)
                ->where('number', $ownership['orderNumber'])
                ->where('customer_id', $customer->id)
                ->delete();
            if ($deletedOrder !== 1) {
                throw new RuntimeException('fixture_order_delete_mismatch');
            }

            $remainingCustomerRoots = $state['owner_roots'];
            $remainingCustomerRoots['orders'] = [];
            $remainingCustomerRoots['notification_templates'] = [];
            $remainingCustomerRoots['notification_dispatches'] = [];
            $remainingCustomerClosure = fixtureOwnerClosure($remainingCustomerRoots, true);
            foreach ($remainingCustomerClosure as $table => $snapshot) {
                $expectedIds = match ($table) {
                    'users' => [(int) $admin->id],
                    'customers' => [(int) $customer->id],
                    default => [],
                };
                if ($snapshot['ids'] !== $expectedIds) {
                    throw new RuntimeException('fixture_customer_association_remains');
                }
            }

            $deletedCustomer = Customer::query()
                ->whereKey($customer->id)
                ->where('email', $ownership['customerEmail'])
                ->whereNull('user_id')
                ->delete();
            if ($deletedCustomer !== 1) {
                throw new RuntimeException('fixture_customer_delete_mismatch');
            }

            $remainingAdminRoots = $remainingCustomerRoots;
            $remainingAdminRoots['customers'] = [];
            $remainingAdminClosure = fixtureOwnerClosure($remainingAdminRoots, true);
            foreach ($remainingAdminClosure as $table => $snapshot) {
                $expectedIds = $table === 'users' ? [(int) $admin->id] : [];
                if ($snapshot['ids'] !== $expectedIds) {
                    throw new RuntimeException('fixture_admin_association_remains');
                }
            }
            if (ownerSessionSnapshot((int) $admin->id, true)['ids'] !== []) {
                throw new RuntimeException('fixture_session_remains');
            }

            $deletedAdmin = User::query()
                ->whereKey($admin->id)
                ->where('email', $ownership['adminEmail'])
                ->where('is_admin', true)
                ->where('is_super_admin', true)
                ->delete();
            if ($deletedAdmin !== 1) {
                throw new RuntimeException('fixture_admin_delete_mismatch');
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
