<?php

namespace App\Services\Marketing;

use App\Models\MarketingAiRun;
use App\Models\MarketingPlanningEvent;
use App\Models\MarketingProject;
use Illuminate\Validation\ValidationException;

class MarketingPlanningHandoff
{
    public function confirm(string $id, int $actor, int $version, string $request): MarketingProject
    {
        $p = app(MarketingPlanningConfirmation::class)->confirm($id, $actor, $version, $request);
        // Existing directions and installations without configured planning AI stay read-only.
        if (!empty($p->concepts) || !app(MarketingConversation::class)->ready($p)) {
            return $p;
        }
        $event = MarketingPlanningEvent::where('project_id', $id)->where('actor_id', $actor)
            ->where('request_id', $request)->where('kind', 'summary_confirmed')->firstOrFail();
        $run = MarketingAiRun::where('project_id', $id)->where('request_id', $request)->first();
        if ($run && $run->status !== 'completed') {
            throw ValidationException::withMessages(['generation' => '摘要已保留；上次创意准备未完成，没有自动重试。请检查后点击“生成3个创意”重新发起。']);
        }
        // Replay must never apply an old confirmation to a newer planning draft.
        if (!$run && ($p->version !== $event->source_version || ($p->planning_state['status'] ?? '') !== 'confirmed')) {
            throw ValidationException::withMessages(['generation' => '摘要已有更新，请重新确认最新资料后继续。']);
        }
        app(MarketingWorkflow::class)->generate($id, $actor, $event->source_version, 'concepts', '', $request, null, null, true);

        return $p->fresh();
    }
}
