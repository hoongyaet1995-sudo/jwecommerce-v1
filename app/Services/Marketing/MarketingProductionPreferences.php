<?php

namespace App\Services\Marketing;

use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Facades\Validator;

/** A planning preference, never a generation or spending authorization. */
class MarketingProductionPreferences
{
    public const TIERS = ['ordinary' => '普通', 'advanced' => '高级', 'professional' => '专业级'];

    public function save(string $project, int $owner, string $request, int $version, string $tier): array
    {
        Validator::make(['tier' => $tier], ['tier' => 'required|in:'.implode(',', array_keys(self::TIERS))])->validate();
        $service = app(MarketingStoryboardWorkspace::class);

        return $service->transact($project, $owner, $request, ['production_tier', $version, $tier], function ($p) use ($project, $version, $tier, $service) {
            $w = Workspace::where('project_id', $project)->lockForUpdate()->firstOrFail();
            abort_unless($w->version === $version, 409, '故事已更新，请刷新后修改档位。');
            if (($w->profile['production_tier'] ?? $p->preferences['production_tier'] ?? 'ordinary') === $tier) {
                return;
            }
            $wasFresh=$service->sourceFresh($w,$p);
            $profile = $w->profile;
            $profile['production_tier'] = $tier;
            $w->update(['profile' => $profile, 'version' => $w->version + 1, 'last_confirmation' => null]);
            $p->update(['preferences' => array_replace($p->preferences ?? [], ['production_tier' => $tier]), 'version' => $p->version + 1]);
            if($wasFresh){$profile['source_fingerprint']=$service->sourceFingerprint($p);$w->update(['profile'=>$profile]);}
        });
    }
}
