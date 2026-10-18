<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use App\Models\SearchRefresh;
use App\Models\SearchTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchRefreshService
{
    public function advance(int $id): array
    {
        $refresh = SearchRefresh::findOrFail($id);
        Cache::lock('search-refresh-advance:'.$id, 120)->get(function () use ($id) {
            SearchTask::where('refresh_id', $id)->where('status', 'running')->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'failed', 'error' => 'worker_interrupted', 'finished_at' => now()]);
            $task = SearchTask::where('refresh_id', $id)->where('status', 'queued')->oldest('id')->first();
            if ($task) {
                app(SearchOptimization::class)->run($task);
            }
        });

        return $this->summary($refresh);
    }

    public function summary(?SearchRefresh $refresh = null): ?array
    {
        $refresh ??= SearchRefresh::latest('id')->first();
        if (! $refresh) {
            return null;
        }
        $counts = SearchTask::where('refresh_id', $refresh->id)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $pending = (int) ($counts['queued'] ?? 0) + (int) ($counts['running'] ?? 0);
        $completed = (int) ($counts['applied'] ?? 0) + $refresh->skipped;
        $failed = $refresh->total - $completed - $pending;

        return ['id' => $refresh->id, 'total' => $refresh->total, 'completed' => $completed, 'skipped' => $refresh->skipped, 'failed' => $failed, 'active' => $pending > 0,
            'status' => $pending > 0 ? 'running' : ($failed > 0 ? 'partial' : 'completed'),
        ];
    }

    public function start(?int $actor = null): array
    {
        return Cache::lock('search-refresh-start', 120)->block(3, function () use ($actor) {
            $current = $this->summary();
            if ($current && $current['active']) {
                return $current;
            }
            abort_if(SearchTask::where('status', 'running')->exists(), 409, 'SEO is already updating. Please try again shortly.');
            $ai = app(SearchOptimization::class);
            $useAi = app(SearchSettings::class)->read()['mode'] !== 'off';
            abort_if($useAi && ! $ai->connection(), 422, 'Connect Search AI or select System automation only before refreshing SEO.');

            return DB::transaction(function () use ($actor, $ai, $useAi) {
                $refresh = SearchRefresh::create(['actor_id' => $actor, 'use_ai' => $useAi]);
                $total = $skipped = 0;
                foreach (['product' => Product::class, 'category' => Category::class, 'collection' => Collection::class, 'page' => Page::class] as $kind => $class) {
                    $query = $class::query();
                    if ($kind === 'page') {
                        $query->whereHas('draftDocument');
                    }
                    // Freeze membership at click time; new records cannot grow this refresh.
                    $maximumId = (int) (clone $query)->max('id');
                    $query->where('id', '<=', $maximumId)->select('id')->chunkById(100, function ($rows) use ($kind, $actor, $ai, $refresh, &$total, &$skipped) {
                        foreach ($rows as $row) {
                            $total++;
                            if (! $ai->request($kind, $row->id, 'live', true, $actor, true, $refresh->id)) {
                                $skipped++;
                            }
                        }
                    });
                }
                $refresh->update(['total' => $total, 'skipped' => $skipped]);
                Cache::forget('search:sitemap');

                return $this->summary($refresh);
            });
        });
    }
}
