<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class MarketplaceHubActivityReporter
{
    public function run(): array
    {
        if (config('marketplace.hub.activity_reporting_enabled') !== true) {
            return ['reported' => 0, 'failed' => 0, 'disabled' => true];
        }
        $base = rtrim((string) config('marketplace.hub.url'), '/');
        $clientId = (string) config('marketplace.hub.client_id');
        $secret = (string) config('marketplace.hub.client_secret');
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($base !== 'https://marketplace.jwaistudio.online' || $clientId === '' || $secret === '' || ! is_string($domain)) {
            throw new RuntimeException('Marketplace Hub activity reporting is not configured.');
        }
        $counts = ['reported' => 0, 'failed' => 0, 'disabled' => false];
        foreach (['marketplace_publishing_tasks' => 'Publish Product',
            'marketplace_catalog_runs' => 'Fetch Products',
            'marketplace_order_runs' => 'Fetch Orders',
            'marketplace_stock_sync_tasks' => 'Update Stock'] as $table => $type) {
            $rows = DB::table($table)->select('id', 'store_id', 'status', 'updated_at')
                ->orderByDesc('updated_at')->limit(100)->get();
            foreach ($rows->chunk(50) as $chunk) {
                $tasks = $chunk->map(function ($row) use ($type) {
                    $raw = strtolower((string) $row->status);
                    $completed = in_array($raw, ['confirmed', 'succeeded', 'completed'], true);
                    $failed = in_array($raw, ['failed', 'rejected'], true);
                    $superseded = $raw === 'superseded';
                    return ['clientTaskId' => (string) $row->id, 'clientStoreId' => (string) $row->store_id,
                        'type' => $type,
                        'platformExecutionStatus' => $completed ? 'Executed' : ($failed ? 'Failed'
                            : ($superseded ? 'Cancelled' : (in_array($raw, ['queued', 'staged', 'pending'], true) ? 'Pending' : 'Executing'))),
                        'clientDeliveryStatus' => $completed || $failed ? 'Delivered' : 'Awaiting Ack',
                        'status' => $completed ? 'Completed' : ($failed ? 'Requires Attention'
                            : ($superseded ? 'Superseded' : 'Processing')),
                        'issue' => $failed ? 'Provider request failed' : ($raw === 'uncertain' ? 'Confirmation pending' : null),
                        'sourceUpdatedAt' => \Illuminate\Support\Carbon::parse($row->updated_at)->toIso8601String()];
                })->values()->all();
                if ($tasks === []) continue;
                $body = json_encode(['domain' => strtolower($domain), 'tasks' => $tasks], JSON_THROW_ON_ERROR);
                $timestamp = (string) now()->timestamp;
                $nonce = Str::random(32);
                try {
                    $response = Http::timeout(10)->withoutRedirecting()->withHeaders([
                        'X-Hub-Client' => $clientId, 'X-Hub-Timestamp' => $timestamp,
                        'X-Hub-Nonce' => $nonce,
                        'X-Hub-Signature' => hash_hmac('sha256', "$timestamp.$nonce.$body", $secret),
                    ])->withBody($body, 'application/json')->post($base.'/api/activity/tasks');
                    $response->successful() ? $counts['reported'] += count($tasks) : $counts['failed'] += count($tasks);
                } catch (\Throwable) {
                    $counts['failed'] += count($tasks);
                }
            }
        }
        return $counts;
    }
}
