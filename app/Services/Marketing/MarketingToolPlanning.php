<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingRevision,MarketingVoiceAsset};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Offline proposals and preflight only. Never obtains voice, renders or calls a provider. */
class MarketingToolPlanning
{
    public const RECIPE='capability-plan-v1';
    public function enabled(string $project):bool
    {
        return (bool)config('marketing.planning.enabled')&&config('marketing.planning.project')===$project&&(app()->environment('local')||app()->runningUnitTests());
    }
    public function executionEnabled(string $project):bool
    {
        return $this->enabled($project)&&(bool)config('marketing.planning.execution_enabled');
    }
    public function authorizeText(array $facts,string $action):void
    {
        $project=$facts['project_id']??'';
        if(!$this->enabled($project)||!config('marketing.planning.live_text_enabled')||$action!=='revise'||($facts['source_revision_id']??null)!==config('marketing.planning.text_revision')||!app(MarketingCreativePlanning::class)->enabled($project)||!\Illuminate\Support\Str::isUuid(config('marketing.creative.authorization'))||(float)config('marketing.creative.budget_usd')>0.04||(int)config('marketing.creative.max_calls')!==1)throw ValidationException::withMessages(['generation'=>'A current source-specific, single-request tool-routing allowance is required.']);
    }
    public function conceptCheck(array $concept,array $brief):array
    {
        $catalog=app(MarketingCapabilities::class)->snapshot();$blockers=[];
        foreach($concept['capabilities'] as $id){
            $cap=$catalog['capabilities'][$id];
            if($cap['validation_status']!=='legacy_execution_verified')$blockers[]='This story needs a capability whose production or quality acceptance is still pending.';
            if($id==='native_presenter'&&($concept['duration']!==15||!in_array($brief['language']??'', $cap['constraints']['language'],true)))$blockers[]='The native presenter pilot does not match this duration or language.';
        }
        if($concept['ratio']!=='9:16'||$concept['duration']>60)$blockers[]='This story needs a production profile beyond the current vertical60-second pilot.';
        return ['capability_version'=>$catalog['version'],'blockers'=>array_values(array_unique($blockers)),'execution_authorized'=>false,'note'=>'Early capability check only; exact materials, voice and cost still require storyboard preflight.'];
    }
    public function schema():array
    {
        $object=fn($properties)=>['type'=>'object','properties'=>$properties,'required'=>array_keys($properties),'additionalProperties'=>false];
        $scene=$object(['scene_id'=>['type'=>'string','pattern'=>'^scene-[1-6]$'],'capability'=>['type'=>'string','enum'=>array_keys(app(MarketingCapabilities::class)->snapshot()['capabilities'])],'reason'=>['type'=>'string','minLength'=>1,'maxLength'=>600],'asset_ids'=>['type'=>'array','items'=>['type'=>'string','format'=>'uuid'],'maxItems'=>12],'catalog_paths'=>['type'=>'array','items'=>['type'=>'string','minLength'=>1,'maxLength'=>1000],'maxItems'=>12]]);
        return $object(['schema_version'=>['type'=>'integer','enum'=>[1]],'route'=>['type'=>'string','enum'=>['local_composition','native_video','hybrid']],'audio_policy'=>['type'=>'string','enum'=>['silent','existing_voice','native_audio']],'scenes'=>['type'=>'array','items'=>$scene,'minItems'=>1,'maxItems'=>6]]);
    }
    public function storyboardSchema():array
    {
        $schema=app(MarketingDraftContract::class)->storyboardSchema();unset($schema['properties']['production']);
        $schema['properties']['scenes']['minItems']=1;$schema['properties']['scenes']['maxItems']=6;
        $schema['properties']['production_strategy']=$this->schema();$schema['required']=array_keys($schema['properties']);return $schema;
    }
    public function validateProposal(array $proposal):array
    {
        return Validator::make(['proposal'=>$proposal],['proposal'=>'required|array:schema_version,route,audio_policy,scenes','proposal.schema_version'=>'required|integer|in:1','proposal.route'=>'required|in:local_composition,native_video,hybrid','proposal.audio_policy'=>'required|in:silent,existing_voice,native_audio','proposal.scenes'=>'required|array|list|min:1|max:6','proposal.scenes.*'=>'required|array:scene_id,capability,reason,asset_ids,catalog_paths','proposal.scenes.*.scene_id'=>'required|string|regex:/^scene-[1-6]$/D','proposal.scenes.*.capability'=>'required|in:'.implode(',',array_keys(app(MarketingCapabilities::class)->snapshot()['capabilities'])),'proposal.scenes.*.reason'=>'required|string|max:600','proposal.scenes.*.asset_ids'=>'present|array|max:12','proposal.scenes.*.asset_ids.*'=>'required|uuid','proposal.scenes.*.catalog_paths'=>'present|array|max:12','proposal.scenes.*.catalog_paths.*'=>'required|string|max:1000'])->validate()['proposal'];
    }
    /** Explicitly a rules-derived offline proposal, never described as a new AI result. */
    public function fromSaved(MarketingProject $p,array $document):array
    {
        $scenes=[];
        foreach($document['scenes'] as $i=>$s){
            $files=app(MarketingProductionCompiler::class)->planningMedia($p,$s)['media'];
            $video=collect($files)->contains(fn($f)=>$f['mime']==='video/mp4');
            if(!$files&&$s['asset_ids'])$video=$p->assets()->whereIn('id',$s['asset_ids'])->where('mime','video/mp4')->exists();
            $referencedCatalog=collect($p->snapshot['images']??[])->contains(fn($image)=>is_string($image['path']??null)&&$image['path']!==''&&str_contains($s['material'].' '.$s['visual'],$image['path']));
            $cap=$video?'existing_video':(($files||$s['asset_ids']||$referencedCatalog)?'image_motion':(preg_match('/\btext-only\b|纯文字/u',$s['material'])?'text_card':'generated_broll'));
            $scenes[]=['scene_id'=>'scene-'.($i+1),'capability'=>$cap,'reason'=>$cap==='generated_broll'?'This scene has no exact reusable media; generation support and budget need review.':'Use the exact confirmed scene material without changing the story.','asset_ids'=>$s['asset_ids'],'catalog_paths'=>array_values(array_map(fn($f)=>$f['path'],array_filter($files,fn($f)=>$f['source']==='catalog_snapshot')))];
        }
        return ['schema_version'=>1,'route'=>in_array('generated_broll',array_column($scenes,'capability'),true)?'hybrid':'local_composition','audio_policy'=>collect($document['scenes'])->contains(fn($s)=>trim($s['voiceover']??'')!=='')?'existing_voice':'silent','scenes'=>$scenes];
    }
    public function compile(MarketingProject $p,MarketingRevision $r):array
    {
        $saved=$r->context['production_strategy']??null;
        $proposal=$saved['proposal']??$this->fromSaved($p,$r->document);
        $review=$this->review($p,$r->document,$proposal);
        if($saved&&($saved['review']['input_digest']??null)!==$review['input_digest'])$review['blockers'][]='The reviewed materials or capability facts changed. Save and review a new strategy before production.';
        $scenes=[];
        foreach($r->document['scenes'] as $i=>$s){
            $plan=$review['scenes'][$i];$cap=$plan['capability'];
            $label=['image_motion'=>'Use the saved image with gentle motion','existing_video'=>'Use the saved footage','text_card'=>'Use original text graphics','generated_broll'=>'Create a supporting illustrative scene','native_presenter'=>'Create a synthetic presenter'][$cap];
            $scenes[]=['number'=>$i+1,'scene_id'=>$plan['scene_id'],'viewer_copy'=>$plan['viewer_copy'],'mode'=>$cap==='text_card'?'text_card':($cap==='existing_video'?'existing_video':'existing_images'),'visual'=>$s['visual'],'material'=>$s['material'].' Suggested approach: '.$label.'. '.$plan['reason'],'images'=>array_values(array_filter($plan['media'],fn($f)=>str_starts_with($f['mime'],'image/'))),'videos'=>array_values(array_filter($plan['media'],fn($f)=>$f['mime']==='video/mp4')),'voiceover'=>$s['voiceover']??'','subtitle'=>$s['subtitle']??'','planned_seconds'=>$s['duration']];
        }
        $spoken=implode("\n\n",array_filter(array_column($r->document['scenes'],'voiceover'),fn($s)=>trim($s??'')!==''));
        $execute=$this->executionEnabled($p->id)&&($r->context['production_recipe']??null)===self::RECIPE&&$review['route']==='local_composition';
        $brand=$r->context['presentation']['brand']??null;
        $style=$r->context['presentation']['style']??null;
        if($style!==null&&!in_array($style,['legacy','immersive-v1'],true))$review['blockers'][]='The saved visual treatment is unsupported; no substitute was applied.';
        if($execute||(app(MarketingHybridProduction::class)->enabled($p->id)&&$review['route']==='hybrid')){
            if(!is_array($brand)||!is_string($brand['name']??null)||trim($brand['name'])===''||mb_strlen($brand['name'])>16||!is_string($brand['descriptor']??null)||mb_strlen($brand['descriptor'])>60)$review['blockers'][]='Review the saved brand before dynamic composition.';
            $source=$r->context['dynamic_source']??null;
            if(!$source||!is_array($source))$source=null;
            $anchor=$source?MarketingRevision::where('project_id',$p->id)->find($source['revision_id']??null):null;
            $sameTiming=$anchor&&array_map(fn($s)=>[$s['duration'],$s['voiceover']??''],$anchor->document['scenes'])===array_map(fn($s)=>[$s['duration'],$s['voiceover']??''],$r->document['scenes']);
            if($source&&($sameTiming||($source['document_digest']??null)===hash('sha256',json_encode($r->document,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))){foreach($scenes as $i=>&$scene){$frames=$source['scene_timings'][$i]['frames']??null;if(is_int($frames))$scene['minimum_frames']=$frames;}unset($scene);}
        }
        $manifest=['schema_version'=>1,'recipe'=>self::RECIPE,'execution_profile'=>$execute?'local-only-v1':'planning-only','strategy_origin'=>$saved?($saved['origin']??'saved_ai_proposal'):'rules_from_saved_storyboard','strategy'=>$review,'research_digest'=>hash('sha256',json_encode($r->context['research']??null,JSON_THROW_ON_ERROR)),'ratio'=>'9:16','planned_seconds'=>$r->document['duration'],'summary'=>$r->document['summary'],'cta'=>$r->document['cta'],'presentation'=>array_filter(['brand'=>$brand,'style'=>$style],fn($v)=>$v!==null),'scenes'=>$scenes,'audio'=>['text'=>$spoken,'characters'=>mb_strlen($spoken),'requests'=>$execute&&$review['audio_policy']==='existing_voice'?1:0,'new_requests'=>0,'reuse_only'=>true,'provider'=>'elevenlabs','model'=>'eleven_flash_v2_5','voice'=>'21m00Tcm4TlvDq8ikWAM'],'budget'=>['currency'=>'USD','cap_microusd'=>$execute?0:null,'supplier_estimate_usd'=>$review['cost']['supplier_estimate_usd'],'ai_video_requests'=>$review['cost']['new_video_requests'],'composition_external_api_usd'=>0,'kind'=>$execute?'saved_assets_only':'planning_only'],'blockers'=>$execute?$review['blockers']:array_merge($review['blockers'],['Planning only: '.$review['route'].'. No text, voice, video generation or rendering is authorized. Dynamic execution awaits T1-B and separate budget confirmation.']),'publication_authorized'=>false];
        return app(MarketingHybridProduction::class)->decorate($manifest,$p,$r);
    }
    public function review(MarketingProject $p,array $document,array $proposal):array
    {
        Validator::make(['document'=>$document],['document'=>'required|array:summary,duration,cta,changes,gaps,notes,requirements,scenes','document.summary'=>'required|string|max:2500','document.cta'=>'required|string|max:150','document.duration'=>'required|integer|min:1|max:60','document.changes'=>'present|array|list','document.gaps'=>'sometimes|array|list','document.notes'=>'sometimes|array|list','document.requirements'=>'sometimes|array|list','document.scenes'=>'required|array|list|min:1|max:6','document.scenes.*'=>'required|array:visual,voiceover,subtitle,duration,material,asset_ids,rhythm','document.scenes.*.visual'=>'required|string|max:2000','document.scenes.*.material'=>'required|string|max:1500','document.scenes.*.voiceover'=>'present|nullable|string|max:1500','document.scenes.*.subtitle'=>'present|nullable|string|max:1000','document.scenes.*.duration'=>'required|integer|min:1|max:60','document.scenes.*.asset_ids'=>'present|array|max:12','document.scenes.*.asset_ids.*'=>'required|uuid'])->validate();
        $proposal=$this->validateProposal($proposal);
        if(count($proposal['scenes'])!==count($document['scenes'])||$document['duration']!==array_sum(array_column($document['scenes'],'duration'))||$document['cta']!==$p->brief['cta'])throw ValidationException::withMessages(['strategy'=>'The scene mapping, timing or CTA changed.']);
        $catalog=app(MarketingCapabilities::class)->snapshot();$blockers=[];$scenes=[];$nodes=[];$generation=[];
        if(($p->concepts[$p->selected_concept]['ratio']??null)!=='9:16')$blockers[]='This planning pilot supports only9:16; the story was not resized.';
        foreach($document['gaps']??[] as $gap)$blockers[]=(string)$gap;
        foreach($document['scenes'] as $i=>$s){
            $choice=$proposal['scenes'][$i];$id='scene-'.($i+1);$cap=$choice['capability'];
            if($choice['scene_id']!==$id||!isset($catalog['capabilities'][$cap])||$choice['asset_ids']!==$s['asset_ids'])throw ValidationException::withMessages(['strategy'=>'The proposal references an unknown capability or changed scene material binding.']);
            $files=app(MarketingProductionCompiler::class)->planningMedia($p,$s);$blockers=array_merge($blockers,$files['blockers']);
            $paths=array_values(array_map(fn($f)=>$f['path'],array_filter($files['media'],fn($f)=>$f['source']==='catalog_snapshot')));
            if($choice['catalog_paths']!==$paths)throw ValidationException::withMessages(['strategy'=>'The proposed catalog references do not match the scene and saved source.']);
            $images=array_values(array_filter($files['media'],fn($f)=>str_starts_with($f['mime'],'image/')));$videos=array_values(array_filter($files['media'],fn($f)=>$f['mime']==='video/mp4'));
            if(($cap==='image_motion'&&(count($images)!==1||$videos))||($cap==='existing_video'&&(count($videos)!==1||$images))||($cap==='text_card'&&$files['media'])||in_array($cap,['native_presenter','generated_broll'],true)&&$files['media'])$blockers[]=$id.': Material does not match the requested capability; no fallback was substituted.';
            if($cap==='existing_video'&&$videos&&$videos[0]['duration_seconds']+0.1<$s['duration'])$blockers[]=$id.': The saved clip is shorter than the requested scene; looping or slowing it was not authorized.';
            if($cap==='existing_video'&&$videos&&($videos[0]['has_audio']??false))$blockers[]=$id.': This clip has source audio. Intentional treatment must be reviewed before muting or mixing it.';
            if($cap==='native_presenter'){
                $blockers[]='New native presenter generation requires a current separate spending authorization; prior pilot quality acceptance is not permission to generate again.';
                if(count($document['scenes'])!==1||$s['duration']!==15||!in_array($p->brief['language'],$catalog['capabilities'][$cap]['constraints']['language'],true)||trim($s['subtitle']??'')!=='')$blockers[]='Native presenter supports only the verified single15-second Mandarin pilot without exact text overlays.';
            }
            if($cap==='generated_broll'){
                $blockers[]='Generated b-roll capability is not verified for execution.';
                if(!in_array($s['duration'],[4,5],true))$blockers[]='This b-roll pilot supports only4–5seconds without generated dialogue.';
            }
            if(in_array($cap,['native_presenter','generated_broll'],true)){$generation[]=$id;$nodes[]=['key'=>'generate-'.$id,'kind'=>'generate_video','capability'=>$cap,'scene_ids'=>[$id],'depends_on'=>[]];}
            else $nodes[]=['key'=>'reuse-'.$id,'kind'=>'reuse_media','scene_ids'=>[$id],'media'=>$files['media'],'depends_on'=>[]];
            $scenes[]=['scene_id'=>$id,'capability'=>$cap,'reason'=>$choice['reason'],'media'=>$files['media'],'viewer_copy'=>['text'=>$s['subtitle']??'','source'=>'document.scenes.'.$i.'.subtitle'],'voiceover'=>$s['voiceover']??'','planned_seconds'=>$s['duration']];
        }
        $native=in_array('native_presenter',array_column($scenes,'capability'),true);$expected=$native?'native_video':($generation?'hybrid':'local_composition');
        if($proposal['route']!==$expected||($native&&count($scenes)!==1))$blockers[]='The route does not match this supported scene combination.';
        if(count($generation)>1)$blockers[]='This pilot permits at most one new video-generation node.';
        if(!$native){
            if(count($scenes)<3)$blockers[]='The dynamic local adapter is scoped to3–6scenes.';
            if(!in_array(strtolower(trim($p->brief['language']??'')),['english','en','en-my','en-us','en-gb'],true))$blockers[]='Local composition has only verified English voice and typography; this language needs separate acceptance.';
            $nodes[]=['key'=>'compose','kind'=>'compose','capability'=>'local_composition','scene_ids'=>array_column($scenes,'scene_id'),'depends_on'=>array_column($nodes,'key')];
        }
        $text=implode("\n\n",array_filter(array_column($document['scenes'],'voiceover'),fn($s)=>trim($s??'')!==''));$voice=null;
        if($proposal['audio_policy']==='native_audio'&&!$native||$native&&$proposal['audio_policy']!=='native_audio'||$text!==''&&$proposal['audio_policy']==='silent')$blockers[]='The audio policy would drop or mix approved speech.';
        if($proposal['audio_policy']==='existing_voice'){
            $spec=['text'=>$text,'model'=>'eleven_flash_v2_5','voice'=>'21m00Tcm4TlvDq8ikWAM','output_format'=>'mp3_44100_128'];
            $library=app(MarketingVoiceLibrary::class);$cached=MarketingVoiceAsset::where('project_id',$p->id)->where('signature',$library->signature($spec))->where('status','ready')->first();
            try{if(!$cached)throw new \RuntimeException;$library->validateCached($cached);$voice=['id'=>$cached->id,'sha256'=>$cached->sha256,'alignment_sha256'=>$cached->usage['alignment_sha256']??null];}catch(\Throwable){$blockers[]='An exact authorized saved voice and alignment are unavailable; a new voice needs a separate budget.';}
        }
        if($generation)$blockers[]='A current supplier estimate and separate new video spending authorization are required; no price was assumed.';
        $digest=hash('sha256',json_encode([$p->id,$p->brief,$p->snapshot,$p->concepts[$p->selected_concept]??null,$document,$proposal,$catalog,$scenes,$voice],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        return ['schema_version'=>1,'route'=>$proposal['route'],'scenes'=>$scenes,'nodes'=>$nodes,'audio_policy'=>$proposal['audio_policy'],'voice'=>$voice,'blockers'=>array_values(array_unique($blockers)),'notes'=>['Proposal only. The local saved-media adapter has passed JTG acceptance; this review grants no content, spending, generation or publishing authorization.'],'capability_version'=>$catalog['version'],'input_digest'=>$digest,'execution_authorized'=>false,'cost'=>['currency'=>'USD','supplier_estimate_usd'=>($generation||($proposal['audio_policy']==='existing_voice'&&!$voice))?null:0,'new_video_requests'=>count($generation),'authorization'=>null]];
    }
}
