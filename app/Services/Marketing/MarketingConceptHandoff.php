<?php

namespace App\Services\Marketing;

use App\Models\MarketingPlanningEvent;
use App\Models\MarketingProject;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/** POST-only first-story preparation. Media generation is deliberately excluded. */
class MarketingConceptHandoff
{
    public function select(string $id, int $actor, int $version, int $index, string $request): void
    {
        $start = DB::transaction(function () use ($id, $actor, $version, $index, $request) {
            $p = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($id);
            $prep = app(MarketingWorkspacePreparation::class);
            $intent = ['version' => $version, 'index' => $index];
            if ($event = MarketingPlanningEvent::where('actor_id', $actor)->where('request_id', $request)->first()) {
                abort_unless($event->project_id === $id && $event->kind === 'concept_handoff' && hash_equals($event->payload_hash, $prep->hash($intent)), 409, '请求内容已改变，请刷新。');
                if (!empty($event->payload['completed'])) return null;
                throw ValidationException::withMessages(['generation' => '本次编排尚未完成，没有自动重试。已保存内容保留，请检查后重新准备导演稿与分镜。']);
            }
            if (Workspace::where('project_id', $id)->exists()) {
                abort_unless($p->selected_concept === $index, 409, '当前已有分镜，不能直接覆盖。请返回现有分镜调整故事。');
                return null;
            }
            app(MarketingWorkflow::class)->selectConcept($id, $actor, $version, $index);
            $p->refresh();
            $prep->record($p, $actor, $request, 'concept_handoff', $intent);
            return $p;
        });
        if (!$start) return;

        $p = $start;
        if (!$p->current_revision_id) {
            app(MarketingWorkflow::class)->generate($id, $actor, $p->version, 'storyboard', '', $this->request($request, 'storyboard'), null, null, true);
            $p->refresh();
        }
        $revision = $p->currentRevision;
        abort_unless($revision && ($revision->context['brief'] ?? null) === $p->brief && ($revision->context['concept'] ?? null) === ($p->concepts[$index] ?? null), 409, '资料已变化，请检查当前故事后继续。');
        if (empty($revision->document['director_plan'])) {
            app(MarketingDirectorWorkflow::class)->draft($id, $actor, $p->version, $revision->id, $this->request($request, 'director'));
            $p->refresh();
        }
        // Storage-only initialization: never invokes Director::prepare or image/video providers.
        app(MarketingStoryboardWorkspace::class)->initialize($id, $actor, $this->request($request, 'workspace'), $p->version);
        $event = MarketingPlanningEvent::where('project_id', $id)->where('request_id', $request)->firstOrFail();
        $event->update(['payload' => $event->payload + ['completed' => true]]);
    }

    private function request(string $request, string $stage): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'marketing-concept-handoff/'.$request.'/'.$stage)->toString();
    }
}
