<?php

namespace App\Services\Marketing;

use App\Models\MarketingStoryboardSemanticRun;
use Illuminate\Validation\ValidationException;

class MarketingStoryboardSemanticClient
{
    public function propose(array $facts, array $sceneContext, array $intent, array $media = []): array
    {
        if(!empty($intent['project_id'])){ $p=\App\Models\MarketingProject::findOrFail($intent['project_id']);$sceneContext['creative_tone']=app(MarketingVideoTone::class)->direction($p->brief['tone']??null);$sceneContext['video_form_policy']=app(MarketingVideoFormPolicy::class)->instruction($p->concepts[$p->selected_concept]['video_type']??$p->preferences['video_type']??'auto'); }
        $fixture=app()->environment('testing')&&app()->bound('marketing.storyboard.semantic_transport');
        if(!$fixture){$project=(string)($intent['project_id']??'');$authorization=(string)($intent['authorization_id']??'');$safe=$intent;unset($safe['authorization_id']);$payload=['facts'=>$facts,'context'=>$sceneContext,'intent'=>$safe,'media'=>$media,'revision_ids'=>isset($safe['scene_id'])&&$safe['scene_id']?array_column(array_filter($sceneContext['scenes'],fn($s)=>$s['scene_id']===$safe['scene_id']),'revision_id'):array_column($sceneContext['scenes'],'revision_id'),'contract'=>'story-creative-v1'];$result=app(MarketingStoryboardOpenAi::class)->semantic($project,$authorization,$payload);foreach($result['answer']['scenes'] as &$scene){app(MarketingStoryboardSceneDesign::class)->validate($scene,count($sceneContext['product_reference_catalog']??[]));$scene['scene_design']['product_reference_hashes']=[];foreach($scene['scene_design']['product_reference_indexes'] as $i){$hash=$sceneContext['product_reference_catalog'][$i]['sha256']??null;abort_unless(is_string($hash)&&preg_match('/^[a-f0-9]{64}$/D',$hash),422,'商品参考文件不可用。');$scene['scene_design']['product_reference_hashes'][$i]=$hash;}}unset($scene);return $this->validateDraft($result['answer'],$facts,true);}
        $payload = ['facts' => $facts, 'context' => $sceneContext, 'intent' => $intent, 'media' => $media, 'contract' => 'story-creative-v1'];
        $key = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $old = MarketingStoryboardSemanticRun::find($key);
        if ($old) {
            abort_unless($old->status === 'completed', 409, '此前理解结果未知，不会自动重发。');

            return $old->result;
        }$run = MarketingStoryboardSemanticRun::create(['id' => $key, 'status' => 'requesting']);
        try {
            $draft = app('marketing.storyboard.semantic_transport')($payload);
            $result = $this->validateDraft($draft, $facts, true);
            $run->update(['status' => 'completed', 'result' => $result]);

            return $result;
        } catch (\Throwable $e) {
            $run->update(['status' => 'unknown']);
            throw $e;
        }
    }

    public function validateDraft(array $draft, array $facts, bool $creative = false): array
    {
        if (! isset($draft['scenes']) || ! is_array($draft['scenes']) || count($draft['scenes']) < 1 || count($draft['scenes']) > 12) {
            throw ValidationException::withMessages(['draft' => 'AI分镜结构无效。']);
        }
        $scenes = [];
        foreach ($draft['scenes'] as $s) {
            $s = app(MarketingStoryboardContract::class)->content($s);
            $refs = $s['fact_refs'] ?? [];
            // Accept a JSON-root qualifier only when it resolves to an exact approved key.
            $refs=array_map(fn($ref)=>is_string($ref)&&!isset($facts[$ref])&&str_starts_with($ref,'facts.')&&isset($facts[substr($ref,6)])?substr($ref,6):$ref,$refs);
            $s['fact_refs']=$refs;
            if($creative){
                $s['fact_refs']=array_values(array_filter($refs,fn($ref)=>is_string($ref)&&isset($facts[$ref])));
                $scenes[]=$s;
                continue;
            }
            $approved = [];
            foreach ($refs as $ref) {
                if (! is_string($ref) || ! isset($facts[$ref])) {
                    throw ValidationException::withMessages(['facts' => '分镜没有有效事实来源。']);
                }$approved[] = $facts[$ref];
            }
            // Whole confirmed fields are indivisible: no dropped qualifiers, OCR claims or invented offers.
            foreach (['voiceover', 'subtitle'] as $field) {
                if ($s[$field] !== '' && ! in_array($s[$field], $approved, true)) {
                    throw ValidationException::withMessages(['facts' => '正式口播和字幕必须保留完整已确认事实。']);
                }
            }
            $scenes[] = $s;
        }

return ['scenes' => $scenes, 'missing_facts' => $draft['missing_facts'] ?? []];
    }
}
