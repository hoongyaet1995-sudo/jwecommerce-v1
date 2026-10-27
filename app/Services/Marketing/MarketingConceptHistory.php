<?php

namespace App\Services\Marketing;

use App\Models\MarketingPlanningEvent;
use App\Models\MarketingProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Immutable snapshots in the existing project event ledger; no provider calls. */
class MarketingConceptHistory
{
    public function context(MarketingProject $p): array
    {
        return ['brief' => $p->brief, 'source' => $p->snapshot, 'preferences' => $p->preferences];
    }

    private function canonical($value)
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($v) => $this->canonical($v), $value);
    }

    public function compatible(array $saved, MarketingProject $p): bool
    {
        return $this->canonical($saved) === $this->canonical($this->context($p));
    }

    public function capture(MarketingProject $p, string $reason): ?MarketingPlanningEvent
    {
        if (! $p->concepts) {
            return null;
        }

        return DB::transaction(function () use ($p, $reason) {
            $p = MarketingProject::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $payload = ['concepts' => $p->concepts, 'context' => $this->context($p)];
            $hash = app(MarketingWorkspacePreparation::class)->hash($payload);
            $last = MarketingPlanningEvent::where('project_id', $p->id)->where('kind', 'concept_snapshot')->latest('created_at')->latest('id')->first();
            if ($last && $last->payload_hash === $hash) {
                return $last;
            }

            return MarketingPlanningEvent::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'request_id' => (string) Str::uuid(), 'kind' => 'concept_snapshot', 'source_version' => $p->version, 'payload_hash' => $hash, 'payload' => $payload + ['reason' => $reason]]);
        });
    }

    public function restore(string $id, int $actor, int $version, string $eventId, string $request, ?int $index): void
    {
        DB::transaction(function () use ($id, $actor, $version, $eventId, $request, $index) {
            $owned = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($id);
            $prep = app(MarketingWorkspacePreparation::class);
            $data = ['event' => $eventId, 'index' => $index];
            if ($prep->replay($actor, $request, 'concept_restored', $data, $id)) {
                return;
            }
            $p = app(MarketingWorkflow::class)->locked($id, $actor, $version);
            abort_unless($p->planning_state === null || ($p->planning_state['status'] ?? '') === 'confirmed', 422, '请先确认当前策划摘要。');
            $event = MarketingPlanningEvent::where('project_id', $id)->where('kind', 'concept_snapshot')->findOrFail($eventId);
            if (! $this->compatible($event->payload['context'], $p)) {
                throw ValidationException::withMessages(['history' => '当前商品、摘要或偏好已变化，不能直接恢复旧创意；请先核对资料差异。']);
            }
            $concepts = $event->payload['concepts'];
            abort_if($index !== null && ! isset($concepts[$index]), 422);
            if($index!==null)app(MarketingWorkspaceCreative::class)->assertSelectedForm($p,$concepts[$index]);
            $this->capture($p, 'before_restore');
            $p->update(['concepts' => $concepts, 'selected_concept' => $index, 'current_revision_id' => null, 'confirmed_revision_id' => null, 'stage' => $index === null ? 'concepts' : 'storyboard', 'status' => 'needs_confirmation', 'last_error' => null, 'version' => $p->version + 1]);
            $prep->record($p, $actor, $request, 'concept_restored', $data);
            $this->capture($p, 'restored');
        });
    }

    public function hints(MarketingProject $p): array
    {
        $groups=[];$bytes=0;$seen=[];
        foreach (MarketingPlanningEvent::where('project_id',$p->id)->where('kind','concept_snapshot')->latest('created_at')->latest('id')->cursor() as $event) {
            $group=[];
            foreach ($event->payload['concepts'] as $concept) {
                $key=hash('sha256',$this->normalize($concept['story']));
                if(isset($seen[$key]))continue;
                $seen[$key]=true;
                $group[]=['title'=>mb_substr($concept['title'],0,120),'hook'=>mb_substr($concept['hook'],0,150),'story'=>mb_substr($concept['story'],0,220)];
            }
            if(!$group)continue;
            $size=strlen(json_encode($group,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            if($bytes+$size>6000)break;
            $groups[]=$group;$bytes+=$size;
        }
        return $groups;
    }

    private function normalize(string $s): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($s));
    }

    private function similarity(string $a, string $b): float
    {
        $a = $this->normalize($a);
        $b = $this->normalize($b);
        if ($a === $b) {
            return 1;
        }
        if (! $a || ! $b) {
            return 0;
        }
        $grams = function ($s) {
            $out = [];
            for ($i = 0; $i < mb_strlen($s) - 2; $i++) {
                $out[mb_substr($s, $i, 3)] = true;
            }

            return $out;
        };
        $x = $grams($a);
        $y = $grams($b);

        return count(array_intersect_key($x, $y)) / max(1, count($x + $y));
    }

    public function assertNovel(MarketingProject $p, array $concepts): void
    {
        $check = function ($prior) use ($concepts) {
            foreach ($concepts as $c) {
                foreach ($prior as $old) {
                    if ($this->similarity($c['story'], $old['story']) >= 0.82 || ($this->similarity($c['hook'], $old['hook']) >= 0.9 && $this->similarity($c['story'], $old['story']) >= 0.6)) {
                        throw ValidationException::withMessages(['generation' => '新创意与项目历史过于相似，未替换当前方案，也未自动再次调用AI。请补充希望改变的故事切入点。']);
                    }
                }
            }
        };
        $check($p->concepts ?? []);
        foreach (MarketingPlanningEvent::where('project_id',$p->id)->where('kind','concept_snapshot')->cursor() as $event) {
            $check($event->payload['concepts']);
        }
    }
}
