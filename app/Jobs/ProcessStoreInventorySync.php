<?php

namespace App\Jobs;

use App\Contracts\StoreInventoryPublisher;
use App\Exceptions\StoreInventoryAuthorizationExpired;
use App\Exceptions\StoreInventoryResultUncertain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProcessStoreInventorySync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $taskId) {}

    public function handle(StoreInventoryPublisher $publisher): void
    {
        $context = DB::transaction(function (): ?array {
            $task = DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)->lockForUpdate()->first();
            if (! $task || ! in_array($task->status, ['queued', 'failed', 'waiting_source'], true)) {
                return null;
            }
            $inFlight = DB::table('inventory_store_sync_tasks')->where('marketplace_store_id', $task->marketplace_store_id)
                ->where('product_variant_id', $task->product_variant_id)->where('status', 'processing')->where('id', '!=', $task->id)
                ->lockForUpdate()->exists();
            if ($inFlight) {
                return null;
            }
            if ($task->stage === 'destination') {
                $source = DB::table('inventory_store_sync_tasks')->where('operation_id', $task->operation_id)
                    ->where('stage', 'source')->lockForUpdate()->first();
                if (! $source || ! in_array($source->status, ['confirmed', 'not_applicable'], true)) {
                    DB::table('inventory_store_sync_tasks')->where('id', $task->id)->update(['status' => 'waiting_source', 'updated_at' => now()]);

                    return null;
                }
            }
            $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
            $pool = $mode === 'shared_pool' ? DB::table('inventory_pools')->where('kind', 'shared')->lockForUpdate()->first()
                : DB::table('inventory_pools')->where('marketplace_store_id', $task->marketplace_store_id)->lockForUpdate()->first();
            $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)
                ->where('product_variant_id', $task->product_variant_id)->lockForUpdate()->first() : null;
            if (! $balance || $balance->counted_at === null) {
                throw new RuntimeException('Managed store balance is not counted.');
            }
            $target = max(0, (int) $balance->physical_stock - (int) $balance->reserved_stock - (int) $balance->safety_stock);
            DB::table('inventory_store_sync_tasks')->where('id', $task->id)->update([
                'status' => 'processing', 'target_quantity' => $target, 'balance_version' => $balance->version,
                'attempts' => DB::raw('attempts + 1'), 'updated_at' => now(),
            ]);
            $listing = DB::table('marketplace_listing_variants as l')
                ->join('marketplace_variant_mappings as m', 'm.listing_variant_id', '=', 'l.id')
                ->where('l.store_id', $task->marketplace_store_id)->where('m.product_variant_id', $task->product_variant_id)
                ->select('l.*')->orderBy('l.id')->first();
            if (! $listing) {
                throw new RuntimeException('Map this SKU to the selected store before synchronizing stock.');
            }

            return ['task' => $task, 'target' => $target, 'listing' => $listing,
                'store' => DB::table('marketplace_stores')->find($task->marketplace_store_id)];
        }, 3);
        if (! $context) {
            return;
        }

        try {
            $receipt = $publisher->publish($context['store'], $context['listing'], $context['target'], $context['task']->event_key);
            DB::transaction(function () use ($receipt): void {
                $task = DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)->where('status', 'processing')->lockForUpdate()->first();
                if (! $task) {
                    return;
                }
                $mode = DB::table('inventory_configuration')->where('id', 1)->value('operating_mode');
                $pool = $mode === 'shared_pool' ? DB::table('inventory_pools')->where('kind', 'shared')->first()
                    : DB::table('inventory_pools')->where('marketplace_store_id', $task->marketplace_store_id)->first();
                $balance = $pool ? DB::table('inventory_balances')->where('inventory_pool_id', $pool->id)->where('product_variant_id', $task->product_variant_id)->first() : null;
                $stale = $balance && (int) $balance->version > (int) $task->balance_version;
                DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)->update([
                    'status' => $stale ? 'superseded' : 'confirmed', 'provider_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
                if (! $stale && $task->operation_id) {
                    DB::table('inventory_store_sync_tasks')->where('operation_id', $task->operation_id)
                        ->where('status', 'waiting_source')->pluck('id')->each(fn ($id) => self::dispatch((string) $id)->afterCommit());
                }
            }, 3);
        } catch (StoreInventoryAuthorizationExpired $error) {
            $this->finish('auth_expired', ['code' => 'authorization_expired', 'message' => $error->getMessage()]);
        } catch (StoreInventoryResultUncertain $error) {
            $this->finish('uncertain', ['code' => 'provider_result_unknown', 'message' => $error->getMessage()]);
        } catch (\Throwable $error) {
            $this->finish('failed', ['code' => 'provider_rejected', 'message' => $error->getMessage()]);
        }
    }

    private function finish(string $status, array $receipt): void
    {
        DB::table('inventory_store_sync_tasks')->where('id', $this->taskId)->where('status', 'processing')->update([
            'status' => $status, 'provider_receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    }
}
