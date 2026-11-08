<?php

namespace App\Services\Marketing;

use App\Models\MarketingAsset;
use App\Models\MarketingAvatar;
use App\Models\MarketingLibraryAsset;
use App\Models\MarketingProduction;
use App\Models\MarketingProductionApproval;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingProject;
use App\Models\MarketingStoryboardPresenter;
use App\Models\MarketingTemplateRun;
use App\Models\MarketingVideoClip;
use App\Models\MarketingVideoFilm;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketingMediaAccess
{
    public function query(User $actor): Builder
    {
        $libs = app(MarketingAvatarAccess::class)->libraries($actor);

        return MarketingLibraryAsset::query()->where(function ($q) use ($actor, $libs) {
            $q->where('owner_id', $actor->id)->orWhereIn('library_id', $libs);
        })
            ->where(function ($q) use ($actor) {
                $q->whereNull('project_id')->orWhereIn('project_id', MarketingProject::where('owner_id', $actor->id)->select('id'));
            })
            ->where(function ($q) use ($libs) {
                $q->whereNull('avatar_id')->orWhereIn('avatar_id', MarketingAvatar::whereIn('library_id', $libs)->where('status', '!=', 'revoked')->select('id'));
            })
            ->where(function ($q) {
                $q->where('source_type', '!=', 'marketing_assets')->orWhereIn('source_id', MarketingAsset::where('authorized', true)->select('id'));
            });
    }

    public function asset(User $actor, string $id): MarketingLibraryAsset
    {
        $a = $this->query($actor)->findOrFail($id);
        $exists = match ($a->source_type) {
            'marketing_email_upload' => $a->disk === 'public' && str_starts_with($a->path, 'marketing/email/'.$a->owner_id.'/'),
            'marketing_post_media' => DB::table('marketing_post_media')->where('id', $a->source_id)->where('user_id', $a->owner_id)->exists(),
            'marketing_assets' => MarketingAsset::whereKey($a->source_id)->where('project_id', $a->project_id)->where('authorized', true)->exists(),
            default => in_array($a->source_type, array_map(fn ($class) => (new $class)->getTable(), MarketingMediaSources::MODELS), true) && DB::table($a->source_type)->where('id', $a->source_id)->exists()
        };
        abort_unless($exists, 404, 'This asset is no longer available.');

        return $a;
    }

    private function references(array $data, MarketingProject $project, int $depth = 0): void
    {
        if ($depth > 14) {
            return;
        }
        if (is_string($data['asset_id'] ?? null) && is_string($data['sha256'] ?? null)) {
            app(MarketingClipSource::class)->path($project, $data['asset_id'], $data['sha256']);
        }
        if (is_string($data['avatar_id'] ?? null) && isset($data['avatar_version'],$data['version_id'],$data['project_references'])) {
            app(MarketingAvatarBinding::class)->assertUsable($project->id, $project->owner_id, $data);
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $this->references($value, $project, $depth + 1);
            }
        }
    }

    private function upstream(MarketingLibraryAsset $a): void
    {
        if (! $a->project_id) {
            return;
        }$project = MarketingProject::where('owner_id', $a->owner_id)->findOrFail($a->project_id);
        $class = match ($a->source_type) {
            'marketing_video_clips' => MarketingVideoClip::class,'marketing_video_films' => MarketingVideoFilm::class,'marketing_productions' => MarketingProduction::class,'marketing_template_runs' => MarketingTemplateRun::class,'marketing_storyboard_presenters' => MarketingStoryboardPresenter::class,default => null
        };
        if (! $class) {
            return;
        }$source = $class::findOrFail($a->source_id);
        $this->references($source->input ?? ($source->avatar_binding ?? []), $project);
        if ($source instanceof MarketingVideoFilm) {
            foreach ($source->input['scenes'] ?? [] as $scene) {
                if (empty($scene['clip_id'])) {
                    continue;
                }$clip = MarketingVideoClip::where('project_id', $project->id)->findOrFail($scene['clip_id']);
                $this->references($clip->input, $project);
            }
        }
        if ($source instanceof MarketingProduction) {
            $plan = MarketingProductionPlan::where('project_id', $project->id)->findOrFail($source->plan_id);
            $this->references($plan->manifest ?? [], $project);
        }
    }

    public function downloadable(User $actor, MarketingLibraryAsset $a): bool
    {
        if ($a->source_type === 'marketing_productions') {
            return MarketingProduction::whereKey($a->source_id)->where('status', 'completed')->exists() && MarketingProductionApproval::where('production_id', $a->source_id)->where('output_sha256', $a->sha256)->exists();
        }
        if ($a->source_type === 'marketing_video_films') {
            $film = MarketingVideoFilm::find($a->source_id);

            return $film && $film->status === 'completed' && (! empty($film->input['template_run']) || ($film->approved_at && hash_equals($film->approved_sha256 ?? '', $a->sha256)));
        }
        if ($a->source_type === 'marketing_template_runs') {
            return MarketingTemplateRun::whereKey($a->source_id)->where('status', 'completed')->exists();
        }

        return true;
    }

    public function file(User $actor, string $id): array
    {
        $a = $this->asset($actor, $id);
        abort_unless(app(MarketingMediaRegister::class)->ownsPath($a->getAttributes(), $a->path, $a->disk), 404);
        $this->upstream($a);
        $file = app(MarketingMediaRegister::class)->localPath($a->path, $a->disk);
        abort_unless($file,404,'The original file is unavailable.');
        abort_unless(hash_equals($a->sha256,hash_file('sha256',$file)),409,'The file changed. Its original registered version cannot be served.');

        return [$a, $file];
    }
}
