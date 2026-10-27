<?php

namespace App\Services\Marketing;

use App\Models\MarketingPlanningEvent;
use App\Models\MarketingProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarketingWorkspacePreparation
{
    public function hash(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function replay(int $actor, string $request, string $kind, array $data, ?string $project = null): ?MarketingProject
    {
        $event = MarketingPlanningEvent::where('actor_id', $actor)->where('request_id', $request)->first();
        if (! $event) {
            return null;
        }
        abort_unless($event->kind === $kind && hash_equals($event->payload_hash, $this->hash($data)) && (! $project || $event->project_id === $project), 409, 'This request changed. Reload before submitting.');

        return MarketingProject::where('owner_id', $actor)->findOrFail($event->project_id);
    }

    public function record(MarketingProject $p, int $actor, string $request, string $kind, array $data): void
    {
        MarketingPlanningEvent::create(['project_id' => $p->id, 'actor_id' => $actor, 'request_id' => $request, 'kind' => $kind, 'source_version' => $p->version, 'payload_hash' => $this->hash($data), 'payload' => $data]);
    }

    public function source(User $actor, array $data, string $request, ?string $id = null, ?int $version = null): MarketingProject
    {
        return DB::transaction(function () use ($actor, $data, $request, $id, $version) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($old = $this->replay($actor->id, $request, 'source_saved', $data, $id)) {
                return $old;
            }
            $input = $data['source_kind'] === 'theme' ? ['source_type' => 'theme', 'theme' => $data['theme']] : ['source_type' => 'product', 'product_id' => $data['product_id']];
            if (! $id) {
                $p = app(MarketingWorkflow::class)->create($actor, $input);
                $p->update(['preferences' => ['source_kind' => $data['source_kind'], 'video_type' => $data['video_type']]]);
            } else {
                $p = app(MarketingWorkflow::class)->locked($id, $actor->id, $version);
                $sourceChanged = $p->source_type !== $input['source_type'] || ($input['source_type'] === 'product' ? (int) $p->product_id !== (int) $input['product_id'] : $p->snapshot['title'] !== trim($input['theme']));
                if ($sourceChanged) {
                    app(MarketingWorkflow::class)->switchSource($id, $actor->id, $p->version, $input);
                    $p->refresh();
                }
                $prefs = array_replace($p->preferences ?? [], ['source_kind' => $data['source_kind'], 'video_type' => $data['video_type']]);
                if ($sourceChanged || $prefs !== $p->preferences) {
                    $this->invalidate($p);
                    $p->preferences = $prefs;
                    $p->planning_state = null;
                    $p->version++;
                    $p->save();
                }
            }
            $this->record($p, $actor->id, $request, 'source_saved', $data);

            return $p->fresh();
        });
    }

    public function invalidate(MarketingProject $p): void
    {
        $p->brief = array_replace($p->brief, ['facts_confirmed' => false, 'goal_confirmed' => false, 'audience_confirmed' => false]);
        $p->current_revision_id = null;
        $p->current_research_run_id = null;
        $p->concepts = null;
        $p->selected_concept = null;
        $p->status = 'needs_confirmation';
        $p->stage = 'goal';
    }

    public function preferences(string $id, int $actor, int $version, string $request, array $data): MarketingProject
    {
        return DB::transaction(function () use ($id, $actor, $version, $request, $data) {
            if ($old = $this->replay($actor, $request, 'preferences_saved', $data, $id)) {
                return $old;
            }
            $p = app(MarketingWorkflow::class)->locked($id, $actor, $version);
            $prefs = array_replace($p->preferences ?? [], $data, ['complete' => true]);
            if ($p->planning_state!==null && \Illuminate\Support\Arr::except($prefs,['production_tier'])===\Illuminate\Support\Arr::except($p->preferences??[],['production_tier']) && ($prefs['production_tier']??'ordinary')!==($p->preferences['production_tier']??'ordinary')) {
                $w=\App\Models\MarketingStoryboardWorkspace::where('project_id',$id)->first();
                if($w){app(MarketingProductionPreferences::class)->save($id,$actor,$request,$w->version,$prefs['production_tier']);$p->refresh();}
                else $p->update(['preferences'=>$prefs,'version'=>$p->version+1]);
                $this->record($p,$actor,$request,'preferences_saved',$data);
                return $p->fresh();
            }
            if ($prefs !== $p->preferences || $p->planning_state === null) {
                $state = app(MarketingPlanningConfirmation::class)->seed($p, $prefs);
                $this->invalidate($p);
                $p->preferences = $prefs;
                $p->planning_state = $state;
                $p->version++;
                $p->save();
            }
            $this->record($p,$actor,$request,'preferences_saved',$data);

            return $p->fresh();
        });
    }
}
