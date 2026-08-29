<?php

namespace App\Services\Shipping;

use App\Jobs\ReconcileEasyParcelTask;
use App\Models\EasyParcelReconciliationTask;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EasyParcelReconciliationPublisher
{
    private const SWEEP_LIMIT = 100;

    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(EasyParcelReconciliationTask|int $task): bool
    {
        $taskId = $task instanceof EasyParcelReconciliationTask ? $task->id : $task;
        $claimedId = DB::transaction(function () use ($taskId): ?int {
            $locked = EasyParcelReconciliationTask::query()->lockForUpdate()->find($taskId);
            if (! $locked instanceof EasyParcelReconciliationTask) {
                return null;
            }

            $now = now();
            $staleSeconds = max(30, (int) config('services.easyparcel.reconciliation_stale_queue_seconds', 120));
            $eligible = in_array($locked->state, ['pending', 'retrying'], true)
                && ($locked->available_at === null || ! $locked->available_at->isFuture());
            $staleQueued = $locked->state === 'queued'
                && ($locked->published_at === null || $locked->published_at->lte($now->copy()->subSeconds($staleSeconds)));
            if (! $eligible && ! $staleQueued) {
                return null;
            }

            $locked->forceFill([
                'state' => 'queued',
                'published_at' => $now,
                'publication_attempt_count' => min(65535, $locked->publication_attempt_count + 1),
                'last_error' => $locked->last_error === 'queue_publish_failed' ? null : $locked->last_error,
            ])->save();

            return $locked->id;
        });

        if ($claimedId === null) {
            return false;
        }

        try {
            $this->dispatcher->dispatch(new ReconcileEasyParcelTask($claimedId));
        } catch (Throwable $exception) {
            EasyParcelReconciliationTask::query()->whereKey($claimedId)->update([
                'last_error' => 'queue_publish_failed',
                'updated_at' => now(),
            ]);
            Log::warning('EasyParcel reconciliation queue publication failed', [
                'task_id' => $claimedId,
                'exception_class' => $exception::class,
            ]);
        }

        return true;
    }

    public function sweep(): int
    {
        $staleSeconds = max(30, (int) config('services.easyparcel.reconciliation_stale_queue_seconds', 120));
        $ids = EasyParcelReconciliationTask::query()
            ->where(function ($query) use ($staleSeconds): void {
                $query->where(function ($due): void {
                    $due->whereIn('state', ['pending', 'retrying'])
                        ->where(fn ($time) => $time->whereNull('available_at')->orWhere('available_at', '<=', now()));
                })->orWhere(function ($stale) use ($staleSeconds): void {
                    $stale->where('state', 'queued')
                        ->where(fn ($time) => $time->whereNull('published_at')->orWhere('published_at', '<=', now()->subSeconds($staleSeconds)));
                });
            })
            ->orderBy('id')
            ->limit(self::SWEEP_LIMIT)
            ->pluck('id');

        $published = 0;
        foreach ($ids as $id) {
            $published += $this->publish((int) $id) ? 1 : 0;
        }

        return $published;
    }
}
