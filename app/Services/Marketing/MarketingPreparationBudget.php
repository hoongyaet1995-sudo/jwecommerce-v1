<?php

namespace App\Services\Marketing;

use App\Models\MarketingPreparationAuthorization as Authorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingPreparationBudget
{
    public function authorize(string $project, int $owner, string $request, int $storyVersion, array $scope, int $capMicrousd): array
    {
        abort_unless(Str::isUuid($request) && $capMicrousd >= 0 && $capMicrousd <= 100000000, 422, '授权范围或额度无效。');

        return DB::transaction(function () use ($project, $owner, $request, $storyVersion, $scope, $capMicrousd) {
            $service = app(MarketingStoryboardWorkspace::class);
            $service->project($project, $owner, true);
            $snap = $service->snapshot($project, $owner);
            $old = Authorization::where('project_id', $project)->where('request_id', $request)->first();
            if ($old) {
                abort_unless($old->scope === $scope && $old->story_version === $storyVersion && $old->cap_microusd === $capMicrousd, 409, '授权编号已绑定其他范围。');

                return $old->toArray();
            }abort_unless($snap['story_version'] === $storyVersion && $scope && count(array_diff($scope, array_column($snap['scenes'], 'revision_id'))) === 0, 409, '授权范围已失效。');

            $quoteId=config('marketing.storyboard.live_grant.mode')==='customer_quote'?config('marketing.billing.execution_quote_id'):null;
            if(config('marketing.storyboard.live_grant.mode')==='customer_quote'){
                $quote=\App\Models\MarketingGenerationQuote::where('project_id',$project)->where('owner_id',$owner)->findOrFail($quoteId);
                abort_unless(in_array($quote->status,['executing','accepted'],true)&&$quote->expires_at->isFuture()&&$capMicrousd<=$quote->cap_microusd,409,'本次费用确认范围已变化。');
            }
            abort_if($capMicrousd===0&&(!$quoteId||$quote->action!=='start-production'||$quote->cap_microusd!==0),409,'零费用本地执行需要有效的本次制作确认。');
            return Authorization::create(['project_id' => $project, 'request_id' => $request, 'story_version' => $storyVersion, 'scope' => $scope, 'cap_microusd' => $capMicrousd, 'billing_quote_id'=>$quoteId,'claims' => [], 'status' => 'active', 'expires_at' => $quoteId?$quote->expires_at:now()->addHour()])->toArray();
        });
    }

    public function reserve(string $id, string $claim, int $amount): bool
    {
        return DB::transaction(function () use ($id, $claim, $amount) {
            $a = Authorization::lockForUpdate()->findOrFail($id);
            $claims = $a->claims;
            if (isset($claims[$claim])) {
                abort_unless($claims[$claim]['cap'] === $amount, 409, '请求额度冲突。');

                return false;
            }abort_unless($amount > 0 && $a->status === 'active' && $a->expires_at->isFuture() && $a->spent_microusd + $a->reserved_microusd + $a->unknown_microusd + $amount <= $a->cap_microusd, 409, '授权额度不足或已过期。');
            $claims[$claim] = ['cap' => $amount, 'status' => 'claimed'];
            $a->update(['claims' => $claims, 'reserved_microusd' => $a->reserved_microusd + $amount]);

            return true;
        });
    }

    public function settle(string $id, string $claim, ?int $actual): void
    {
        DB::transaction(function () use ($id, $claim, $actual) {
            $a = Authorization::lockForUpdate()->findOrFail($id);
            $claims = $a->claims;
            $c = $claims[$claim] ?? null;
            abort_unless($c, 409, '没有对应请求记录。');
            if ($c['status'] !== 'claimed') {
                return;
            }abort_if($actual !== null && ($actual < 0 || $actual > $c['cap']), 409, '费用待核对。');
            $claims[$claim]['status'] = $actual === null ? 'unknown' : 'settled';
            $claims[$claim]['actual'] = $actual;
            $a->update(['claims' => $claims, 'reserved_microusd' => $a->reserved_microusd - $c['cap'], 'unknown_microusd' => $a->unknown_microusd + ($actual === null ? $c['cap'] : 0), 'spent_microusd' => $a->spent_microusd + ($actual ?? 0)]);
        });
    }
}
