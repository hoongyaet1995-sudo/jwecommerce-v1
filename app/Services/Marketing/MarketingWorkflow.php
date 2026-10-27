<?php

namespace App\Services\Marketing;

use App\Models\MarketingAiRun;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingWorkflow
{
    /** Normal free transition to a new unconfirmed execution draft; never edits its source. */
    public function dynamicDraft(string $id,int $actor,int $version,string $revision,string $requestId):MarketingRevision
    {
        return DB::transaction(function()use($id,$actor,$version,$revision,$requestId){
            $owned=MarketingProject::where('owner_id',$actor)->lockForUpdate()->findOrFail($id);
            abort_unless(app(MarketingToolPlanning::class)->executionEnabled($id),403);
            $existing=MarketingRevision::where('project_id',$id)->where('context->dynamic_source_request',$requestId)->first();
            if($existing){abort_unless($existing->parent_id===$revision,409,'This preparation request changed.');return $existing;}
            $p=$this->locked($id,$actor,$version);abort_unless($p->current_revision_id===$revision&&$p->confirmed_revision_id===$revision&&$p->status==='prepared',409,'Confirm the source storyboard first.');
            $source=MarketingRevision::where('project_id',$id)->findOrFail($revision);
            abort_unless(in_array($source->context['production_recipe']??'existing-images-voice-v1',['existing-images-voice-v1','use-scenario-hybrid-v1'],true),422,'This saved video mode needs a separate production review; it will not be converted into a different story.');
            abort_unless($source->confirmed_at&&($source->context['brief']??null)===$p->brief&&($source->context['snapshot']??null)===$p->snapshot&&($source->context['concept']??null)===($p->concepts[$p->selected_concept]??null),409,'The source facts changed.');
            $context=$source->context;unset($context['production_change'],$context['run_id'],$context['current']);
            $context['production_recipe']=MarketingToolPlanning::RECIPE;$context['dynamic_source_request']=$requestId;
            $proposal=app(MarketingToolPlanning::class)->fromSaved($p,$source->document);$review=app(MarketingToolPlanning::class)->review($p,$source->document,$proposal);
            $context['production_strategy']=['origin'=>'rules_from_saved_storyboard','proposal'=>$proposal,'review'=>$review];
            $context['dynamic_source']=['revision_id'=>$source->id,'document_digest'=>hash('sha256',json_encode($source->document,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
            $movie=\App\Models\MarketingProduction::where('project_id',$id)->where('owner_id',$actor)->where('status','completed')->whereIn('plan_id',\App\Models\MarketingProductionPlan::where('revision_id',$source->id)->select('id'))->latest('created_at')->first();
            if($movie){app(MarketingProductionDelivery::class)->completedFile($movie);$context['dynamic_source']+=['production_id'=>$movie->id,'output_sha256'=>$movie->output_sha256,'scene_timings'=>$movie->result['scene_timings']??[]];}
            $new=MarketingRevision::create(['project_id'=>$id,'actor_id'=>$actor,'number'=>(int)$p->revisions()->max('number')+1,'document'=>$source->document,'context'=>$context,'parent_id'=>$source->id,'feedback'=>'Reuse this confirmed story, brand and saved materials for dynamic local production. No AI request was made.']);
            $p->update(['current_revision_id'=>$new->id,'stage'=>'storyboard','status'=>empty($source->document['gaps'])?'needs_confirmation':'needs_information','version'=>$p->version+1]);return $new;
        });
    }
    public function create(User $actor, array $input): MarketingProject
    {
        [$product,$snapshot] = $this->source($input);
        $goal = $product?->purchase_mode === 'quote' ? 'enquiry' : 'introduction';

        return MarketingProject::create(['owner_id' => $actor->id, 'product_id' => $product?->id, 'source_type' => $input['source_type'], 'name' => mb_substr($snapshot['title'], 0, 150), 'snapshot' => $snapshot,
            'brief' => ['source_confirmed' => false, 'facts' => '', 'goal' => $goal, 'goal_reason' => $goal === 'enquiry' ? 'This item is offered by enquiry.' : 'Introduce the offer using confirmed information.', 'cta' => $goal === 'enquiry' ? 'Request a quote' : 'Learn more', 'destination' => $product ? route('storefront.products.show', $product->handle) : '',
                'audience' => 'People considering this product or service', 'market' => '', 'language' => '', 'platforms' => [], 'facts_confirmed' => false, 'goal_confirmed' => false, 'audience_confirmed' => false]])->fresh();
    }

    private function source(array $input): array
    {
        $product = null;
        if ($input['source_type'] === 'product') {
            $product = Product::with('images')->findOrFail($input['product_id']);
        }
        $snapshot = $product ? ['title' => $product->title, 'description' => mb_substr(strip_tags($product->description ?? ''), 0, 8000), 'subtitle' => strip_tags($product->subtitle ?? ''), 'purchase_mode' => $product->purchase_mode, 'handle' => $product->handle,
            'images' => $product->images->map(fn ($i) => ['id' => $i->id, 'path' => $i->path])->all()]
            : ['title' => trim($input['theme']), 'description' => trim($input['theme']), 'images' => []];

        return [$product, $snapshot];
    }

    public function switchSource(string $id, int $actor, int $version, array $input): void
    {
        DB::transaction(function () use ($id, $actor, $version, $input) {
            $p = $this->locked($id, $actor, $version);
            abort_if($p->source_confirmed_at || $p->brief['source_confirmed'], 409, 'The source snapshot has been confirmed. Start a separate project to promote a different source.');
            [$product,$snapshot] = $this->source($input);
            if ($p->preferences !== null) {
                $p->preferences = array_replace($p->preferences, ['source_kind' => $input['source_type'], 'complete' => false]);
                $p->planning_state = null;
            }
            $p->update(['source_type' => $input['source_type'], 'product_id' => $product?->id, 'snapshot' => $snapshot, 'concepts' => null, 'selected_concept' => null, 'current_revision_id' => null, 'stage' => 'source', 'status' => 'needs_confirmation', 'version' => $p->version + 1,
                'brief' => array_replace($p->brief, ['source_confirmed' => false, 'facts_confirmed' => false, 'goal_confirmed' => false, 'audience_confirmed' => false, 'source_summary' => '', 'questions' => [], 'destination' => $product ? route('storefront.products.show', $product->handle) : ''])]);
        });
    }

    public function interrupted(string $id, int $actor, int $version): void
    {
        DB::transaction(function () use ($id, $actor, $version) {
            $p = $this->locked($id, $actor, $version, true);
            $run = MarketingAiRun::where('project_id', $id)->where('id', $p->busy_run_id)->first();
            abort_unless($run && $run->created_at->lte(now()->subMinutes(10)), 409, 'The request may still be running. Wait before marking its outcome unknown.');
            $run->update(['status' => 'unknown', 'error' => 'interrupted_outcome_unknown']);
            $p->update(['busy_run_id' => null, 'status' => 'failed', 'last_error' => 'Previous provider outcome is unknown. Saved work remains. A new explicit request may incur additional text usage.', 'version' => $p->version + 1]);
        });
    }

    public function locked(string $id, int $actor, int $version, bool $allowBusy = false): MarketingProject
    {
        $p = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($id);
        abort_if($p->version !== $version, 409, 'This project changed. Reload before continuing.');
        abort_if($p->active_research_run_id, 409, 'Research is still being checked. Wait before changing this project.');
        abort_if(! $allowBusy && $p->busy_run_id, 409, 'A text request is still being checked. Do not submit it again.');

        return $p;
    }

    public function saveBrief(string $id, int $actor, int $version, array $data): void
    {
        DB::transaction(function () use ($id, $actor, $version, $data) {
            $p = $this->locked($id, $actor, $version);
            $brief = $p->brief;
            $changes = array_diff_assoc(array_map(fn ($v) => json_encode($v), $data), array_map(fn ($v) => json_encode($v), $brief));
            $brief = array_replace($brief, $data);
            $ready = $brief['source_confirmed'] && $brief['facts_confirmed'] && $brief['goal_confirmed'] && $brief['audience_confirmed'] && trim($brief['market']) !== '' && trim($brief['language']) !== '' && count($brief['platforms']) > 0;
            $updates = ['brief' => $brief, 'version' => $p->version + 1, 'last_error' => null];
            if ($brief['source_confirmed'] && ! $p->source_confirmed_at) {
                $updates['source_confirmed_at'] = now();
            }
            if ($changes) {
                // New workspace confirmations cannot survive edits made through the legacy consumer.
                if ($p->planning_state !== null) $updates['planning_state'] = null;
                $updates['current_research_run_id'] = null;
                $updates += ['concepts' => null, 'selected_concept' => null, 'current_revision_id' => null, 'stage' => $ready ? 'concepts' : (! $brief['source_confirmed'] ? 'source' : (! $brief['goal_confirmed'] ? 'goal' : 'audience')), 'status' => 'needs_confirmation'];
            }
            $p->update($updates);
        });
    }

    public function selectConcept(string $id, int $actor, int $version, int $index): void
    {
        DB::transaction(function () use ($id, $actor, $version, $index) {
            $p = $this->locked($id, $actor, $version);
            abort_unless(isset($p->concepts[$index]), 422, 'Select an available concept.');
            app(MarketingWorkspaceCreative::class)->assertSelectedForm($p,$p->concepts[$index]);
            app(MarketingConceptHistory::class)->capture($p,'before_selection');
            if ($p->selected_concept === $index) {
                return;
            }
            $p->update(['selected_concept' => $index, 'current_revision_id' => null, 'confirmed_revision_id'=>null, 'stage' => 'storyboard', 'status' => 'needs_confirmation', 'version' => $p->version + 1]);
        });
    }

    public function generate(string $id, int $actor, int $version, string $action, string $feedback, string $requestId, ?string $parentId = null, ?string $productionId = null, bool $workspace = false): void
    {
        $run = DB::transaction(function () use ($id, $actor, $version, $action, $feedback, $requestId, $productionId, $workspace) {
            $owned = MarketingProject::where('owner_id', $actor)->findOrFail($id);
            $existing = MarketingAiRun::where('project_id', $id)->where('request_id', $requestId)->first();
            if ($existing) {
                abort_unless($existing->action === $action && $existing->feedback === $feedback, 409, 'This request has changed. Start a new request.');
                abort_unless(($existing->usage['production_change_source']['production_id']??null)===$productionId,409,'This request refers to a different video.');
                abort_unless($existing->status === 'completed', 409, 'This request is already recorded and has not completed successfully. Review its status before explicitly starting another request.');

                return null;
            }
            $p = $this->locked($id, $actor, $version);
            if ($action === 'concepts') app(MarketingConceptHistory::class)->capture($p,'before_refresh');
            $change = $productionId ? app(MarketingVideoChange::class)->source($p,$productionId) : null;
            abort_if($productionId && $action!=='revise',422);
            if ($workspace) {
                abort_unless(app(MarketingConversation::class)->ready($p) && (config('marketing.billing.enabled')||config('marketing.conversation.open_testing')) && ($p->planning_state['status'] ?? '') === 'confirmed', 422, '请先确认策划摘要并开启本地文字AI测试。');
            }
            $b = $p->brief;
            abort_unless($action === 'recommend' || ($b['source_confirmed'] && $b['facts_confirmed'] && $b['goal_confirmed'] && $b['audience_confirmed'] && trim($b['market']) && trim($b['language']) && count($b['platforms'])), 422, 'Confirm the source, facts, goal, market, language and platforms first.');
            abort_unless(in_array($action, ['recommend', 'concepts', 'storyboard', 'revise'], true), 422);
            abort_if(in_array($action, ['storyboard', 'revise']) && $p->selected_concept === null, 422, 'Select a concept first.');
            abort_if($action === 'revise' && ! $p->current_revision_id, 422, 'Prepare a storyboard first.');
            if(config('marketing.billing.enabled'))app(MarketingPlanningAllowance::class)->assertAvailable($id);
            $r = MarketingAiRun::create(['project_id' => $id, 'request_id' => $requestId, 'action' => $action, 'feedback' => $feedback, 'status' => 'running','reserved_usd'=>config('marketing.billing.enabled')?0.02:0,'usage'=>$change?['production_change_source'=>$change]:null]);
            $p->update(['busy_run_id' => $r->id, 'status' => 'processing', 'version' => $p->version + 1, 'last_error' => null]);

            return $r;
        });
        if (! $run) {
            return;
        }
        try {
            $p = MarketingProject::with(['assets', 'currentRevision'])->findOrFail($id);
            $creative=!$workspace && app(MarketingCreativePlanning::class)->enabled($p->id);
            $tools=!$workspace && !$productionId&&app(MarketingToolPlanning::class)->enabled($p->id);
            if($creative && $action==='concepts'){app(MarketingCreativePlanning::class)->reuseClip($p);$p->load('assets');}
            $parent = $parentId ? MarketingRevision::where('project_id', $id)->findOrFail($parentId) : $p->currentRevision;
            if($productionId){
                $source=$run->usage['production_change_source'];
                $parent=MarketingRevision::where('project_id',$id)->findOrFail($source['revision_id']);
            }
            if ($action === 'revise' && (($parent?->context['brief'] ?? null) !== $p->brief || ($parent?->context['concept'] ?? null) !== ($p->concepts[$p->selected_concept] ?? null))) {
                throw ValidationException::withMessages(['generation' => 'This historical version uses a different brief or creative direction. Prepare a new storyboard from the current confirmed facts instead.']);
            }
            $context = ['project_id'=>$p->id,'snapshot' => $p->snapshot, 'brief' => $p->brief, 'assets' => $p->assets->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'authorized' => $a->authorized,'mime'=>$a->mime])->all(), 'concept' => $p->selected_concept !== null ? ($p->concepts[$p->selected_concept] ?? null) : null, 'current' => $parent?->document, 'run_id' => $run->id];
            if ($workspace) {
                $context['workspace_planning'] = true;
                $context['creative_history']=['recent_groups'=>app(MarketingConceptHistory::class)->hints($p),'prompt_history_limit_bytes'=>6000,'older_groups_checked_server'=>true];
                $context['preferences'] = $p->preferences;
                $context+=app(MarketingConversation::class)->planningDiscussion($p);
                $context['research'] = app(MarketingCreativePlanning::class)->research($p);
                // Optional inspiration never blocks a product-led story or invalidates the selected narrative.
                if ($action === 'storyboard') app(MarketingWorkspaceCreative::class)->assertSelectedForm($p,$context['concept']??[]);
                if ($action === 'storyboard') {
                    $context['draft_contract'] = MarketingDraftContract::VERSION;
                    $context['production_recipe'] = 'pending-tool-routing';
                }
            }
            if($tools){$context['tool_planning']=true;$context['capabilities']=app(MarketingCapabilities::class)->snapshot();$context['draft_contract']=MarketingDraftContract::VERSION;if($parent?->context['research']??null)$context['research']=$parent->context['research'];}
            if($tools){
                $context['source_revision_id']=$parent?->id;
                $context['tool_materials']=array_map(fn($s)=>array_map(fn($m)=>\Illuminate\Support\Arr::only($m,['source','path','mime','duration_seconds','has_audio','sha256']),app(MarketingProductionCompiler::class)->planningMedia($p,$s)['media']),$parent?->document['scenes']??[]);
                $context['presentation']=\Illuminate\Support\Arr::only($parent?->context['presentation']??[],['brand','style']);
            }
            if($productionId)$context['production_change']=$run->usage['production_change_source'];
            if($creative){
                $context['creative_planning']=true;$context['company']=app(MarketingCreativePlanning::class)->company();
                if(in_array($action,['storyboard','revise'],true))$context['draft_contract']=MarketingDraftContract::VERSION;
                if($action!=='recommend'){
                    $context['research']=app(MarketingCreativePlanning::class)->research($p);
                    if(!$context['research']||count($context['research']['sources'])<2)throw ValidationException::withMessages(['generation'=>'Not enough suitable saved transcript evidence. No new search or paid fallback was requested.']);
                    if($action!=='concepts'&&($context['concept']['_research']['digest']??null)!==$context['research']['digest'])throw ValidationException::withMessages(['generation'=>'Saved research changed. Review fresh concepts before making a storyboard.']);
                }
            }
            $result = app(MarketingWriter::class)->propose($context, $action, $feedback);
            // Server-computed review evidence belongs to context, not the executable document.
            if(isset($result['pacing_review'])){$context['pacing_review']=$result['pacing_review'];unset($result['pacing_review']);}
            if ($workspace && $action==='concepts') app(MarketingConceptHistory::class)->assertNovel($p,$result['concepts']);
            if(($creative || $workspace) && $action==='concepts'){
                foreach($result['concepts'] as &$concept){$concept['_research']=\Illuminate\Support\Arr::only($context['research']??[],['run_id','origin_project_id','digest','coverage']);}unset($concept);
            }elseif($tools&&in_array($action,['storyboard','revise'],true)){
                foreach(['summary','duration','cta','scenes'] as $field)if(($result[$field]??null)!==($parent?->document[$field]??null))throw ValidationException::withMessages(['generation'=>'This tool-only allowance does not authorize changes to the saved story, copy, timing or materials.']);
                $proposal=$result['production_strategy'];unset($result['production_strategy']);
                $review=app(MarketingToolPlanning::class)->review($p,$result,$proposal);
                $context['production_recipe']=MarketingToolPlanning::RECIPE;
                $result['gaps']=array_values(array_unique(array_merge($result['gaps'],$review['blockers'])));
                $context['production_strategy']=['origin'=>'ai','proposal'=>$proposal,'review'=>app(MarketingToolPlanning::class)->review($p,$result,$proposal)];
                if($parent?->context['dynamic_source']??null)$context['dynamic_source']=$parent->context['dynamic_source'];
            }elseif($creative && in_array($action,['storyboard','revise'],true)){
                $context['production_recipe']='use-scenario-hybrid-v1';$context['presentation']=$result['production'];unset($result['production']);
                if($productionId)$context['presentation']['brand']=$parent->context['presentation']['brand'];
                $candidate=new MarketingRevision(['document'=>$result,'context'=>$context]);
                $manifest=app(MarketingProductionCompiler::class)->compile($p,$candidate);
                $result['gaps']=array_values(array_unique(array_merge($result['gaps'],$manifest['blockers'])));
            }
            if($productionId){
                // Preserve a compatible existing renderer snapshot even on the ordinary text path.
                $context['production_recipe']=$parent->context['production_recipe']??'existing-images-voice-v1';
                $context['presentation']=$context['presentation']??($parent->context['presentation']??null);
                $context['production_change']['impact']=app(MarketingVideoChange::class)->impact($parent->document,$result);
                $context['production_change']['impact']['brand_changed']=false;
            }
            DB::transaction(function () use ($id, $actor, $run, $action, $feedback, $parent, $result, $context, $workspace) {
                $p = MarketingProject::lockForUpdate()->findOrFail($id);
                abort_unless($p->busy_run_id === $run->id, 409);
                $updates = ['busy_run_id' => null, 'status' => 'needs_confirmation', 'version' => $p->version + 1, 'last_error' => null];
                if ($workspace) $updates['confirmed_revision_id'] = null;
                if ($action === 'recommend') {
                    $updates['current_research_run_id'] = null;
                    $updates += ['brief' => array_replace($p->brief, $result, ['source_confirmed' => false, 'facts_confirmed' => false, 'goal_confirmed' => false, 'audience_confirmed' => false]), 'concepts' => null, 'selected_concept' => null, 'current_revision_id' => null, 'stage' => 'source'];
                } elseif ($action === 'concepts') {
                    $updates += ['concepts' => $result['concepts'], 'selected_concept' => null, 'current_revision_id' => null, 'stage' => 'concepts'];
                } else {
                    $revision = MarketingRevision::create(['project_id' => $id, 'actor_id' => $actor, 'number' => (int) $p->revisions()->max('number') + 1, 'document' => $result, 'context' => $context, 'feedback' => $feedback, 'parent_id' => $parent?->id]);
                    $updates += ['current_revision_id' => $revision->id, 'stage' => 'storyboard'];
                    $updates['status'] = count($result['gaps']) ? 'needs_information' : 'needs_confirmation';
                }
                $p->update($updates);
                if ($action === 'concepts') app(MarketingConceptHistory::class)->capture($p,'generated');
                $run->update(['status' => 'completed']);
            });
        } catch (\Throwable $error) {
            $reason = $error instanceof \App\Services\AI\ProviderFailure && $error->reason === 'balance' ? 'AI余额不足，请top up；没有自动充值或重试。' : ($error instanceof ValidationException ? mb_substr(implode(' ', array_map(fn ($messages) => $messages[0] ?? '', $error->errors())), 0, 150) : 'Text preparation did not complete.');
            DB::transaction(function () use ($id, $run, $reason) {
                $p = MarketingProject::lockForUpdate()->find($id);
                if ($p?->busy_run_id === $run->id) {
                    $p->update(['busy_run_id' => null, 'status' => 'failed', 'last_error' => mb_substr($reason.' Saved work is preserved.', 0, 160), 'version' => $p->version + 1]);
                }$run->update(['status' => 'failed', 'error' => $reason]);
            });
            logger()->warning('Marketing text preparation failed', ['type'=>class_basename($error)]);
            throw ValidationException::withMessages(['generation' => $reason.' Review the saved project before starting a new request.']);
        }
    }

    public function confirm(string $id, int $actor, int $version, string $revisionId): void
    {
        DB::transaction(function () use ($id, $actor, $version, $revisionId) {
            $p = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($id);
            $r = MarketingRevision::where('project_id', $id)->findOrFail($revisionId);
            if ($p->confirmed_revision_id === $revisionId && $p->current_revision_id === $revisionId && $r->confirmed_at) {
                return;
            }
            $p = $this->locked($id, $actor, $version);
            abort_unless($p->current_revision_id === $revisionId, 409, 'Review the current version before confirming.');
            app(MarketingDirectorWorkflow::class)->assertReady($p,$r);
            if(isset($r->document['transition_plan']))abort_unless(app(MarketingTransitionPlanning::class)->state($p,$r)['ready'],422,'请先确认跨段衔接与相邻首尾图复用。');
            abort_if(count(app(MarketingRequirementReview::class)->pending($p,$r)['gaps']) > 0, 422, 'Resolve the required facts and material gaps before confirming.');
            $used = collect($r->document['scenes'])->pluck('asset_ids')->flatten()->unique();
            $authorized = $p->assets()->where('authorized', true)->pluck('id');
            abort_if($used->diff($authorized)->isNotEmpty(), 422, 'Confirm permission for every referenced project asset.');
            $r->update(['confirmed_by' => $actor, 'confirmed_at' => now()]);
            $p->update(['confirmed_revision_id' => $r->id, 'status' => 'prepared', 'stage' => 'confirmed', 'version' => $p->version + 1]);
        });
    }
}
