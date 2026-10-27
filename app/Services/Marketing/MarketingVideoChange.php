<?php

namespace App\Services\Marketing;

use App\Models\{MarketingProduction, MarketingProductionPlan, MarketingProject, MarketingRevision, MarketingVoiceAsset};

/** Compares saved facts; never invents a quote or calls a provider. */
class MarketingVideoChange
{
    public function brandMatches(MarketingRevision $r): bool
    {
        if(empty($r->context['production_change']))return true;
        $source=MarketingRevision::where('project_id',$r->project_id)->findOrFail($r->context['production_change']['revision_id']);
        return ($r->context['presentation']['brand']??null)===($source->context['presentation']['brand']??null);
    }

    public function restoreBrand(string $project,int $actor,int $version,string $revision): void
    {
        \Illuminate\Support\Facades\DB::transaction(function()use($project,$actor,$version,$revision){
            $p=app(MarketingWorkflow::class)->locked($project,$actor,$version);
            abort_unless($p->current_revision_id===$revision,409,'Review the current draft.');
            $r=MarketingRevision::where('project_id',$project)->findOrFail($revision);
            abort_unless(!empty($r->context['production_change'])&&!$this->brandMatches($r),409,'No brand restoration is needed.');
            $source=MarketingRevision::where('project_id',$project)->findOrFail($r->context['production_change']['revision_id']);
            $context=$r->context;$context['presentation']['brand']=$source->context['presentation']['brand'];
            $context['production_change']['impact']['brand_changed']=false;
            $document=$r->document;$document['changes'][]='Restored the original approved brand display. All other draft content and timing remain unchanged. No AI request was made.';
            $new=MarketingRevision::create(['project_id'=>$project,'actor_id'=>$actor,'number'=>(int)$p->revisions()->max('number')+1,'document'=>$document,'context'=>$context,'feedback'=>$r->feedback,'parent_id'=>$r->id]);
            $p->update(['current_revision_id'=>$new->id,'stage'=>'storyboard','status'=>empty($document['gaps'])?'needs_confirmation':'needs_information','version'=>$p->version+1]);
        });
    }

    public function source(MarketingProject $p, string $production): array
    {
        $task = MarketingProduction::where('project_id', $p->id)->where('owner_id', $p->owner_id)->findOrFail($production);
        $plan = MarketingProductionPlan::where('project_id', $p->id)->findOrFail($task->plan_id);
        abort_unless($p->current_revision_id === $plan->revision_id && $p->confirmed_revision_id === $plan->revision_id && $p->version === $plan->project_version && $p->status === 'prepared', 409, 'Review or finish the current change draft before changing another completed video.');
        app(MarketingProductionDelivery::class)->completedFile($task);
        return ['production_id'=>$task->id,'output_sha256'=>$task->output_sha256,'revision_id'=>$plan->revision_id,'scene_timings'=>$task->result['scene_timings']??[],'planned_scenes'=>$plan->manifest['scenes']];
    }

    public function impact(array $old, array $new): array
    {
        $changed=[];$voice=false;$materials=false;
        foreach (range(0, max(count($old['scenes']), count($new['scenes']))-1) as $i) {
            $a=$old['scenes'][$i]??[];$b=$new['scenes'][$i]??[];
            if ($a!==$b) $changed[]=$i+1;
            $voice=$voice||($a['voiceover']??'')!==($b['voiceover']??'');
            $materials=$materials||($a['asset_ids']??[])!==($b['asset_ids']??[])||($a['material']??'')!==($b['material']??'');
        }
        return ['changed_scenes'=>$changed,'voice_changed'=>$voice,'materials_changed'=>$materials,'planned_seconds_before'=>$old['duration'],'planned_seconds_after'=>$new['duration'],'publication_authorized'=>false];
    }

    public function productionCheck(MarketingProject $p, array $manifest): array
    {
        $library=app(MarketingVoiceLibrary::class);
        $spec=$library->spec(new MarketingProductionPlan(['manifest'=>$manifest]));
        $voice=MarketingVoiceAsset::where('project_id',$p->id)->where('signature',$library->signature($spec))->where('status','ready')->first();
        if (!$manifest['audio']['requests']) return ['voice_reusable'=>true,'new_audio_requests'=>0,'new_video_requests'=>0];
        if ($voice) {
            try { $library->validateCached($voice); return ['voice_reusable'=>true,'new_audio_requests'=>0,'new_video_requests'=>0]; }
            catch (\Throwable) { /* A broken cache must never permit a paid fallback. */ }
        }
        return ['voice_reusable'=>false,'new_audio_requests'=>null,'new_video_requests'=>0,'reason'=>'Matching verified voiceover is unavailable. New voice costs require separate authorization; production is blocked.'];
    }
}
