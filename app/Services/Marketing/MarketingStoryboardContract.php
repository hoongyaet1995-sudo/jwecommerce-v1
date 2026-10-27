<?php

namespace App\Services\Marketing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class MarketingStoryboardContract
{
    public function content(array $input): array
    {
        foreach (['voiceover', 'subtitle'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] === null) {
                $input[$field] = '';
            }
        }
        $v = Validator::make($input, ['seconds' => 'required|integer|min:3|max:15', 'message' => 'required|string|max:300', 'visual' => 'required|string|max:4000', 'voiceover' => 'present|string|max:4000', 'subtitle' => 'present|string|max:1000', 'presenter_required' => 'sometimes|boolean', 'fact_refs' => 'sometimes|array', 'material_plan'=>'sometimes|array:mode,reason,roles,shot_kind,preferred_model,production_route,fidelity,motion_kind','material_plan.production_route'=>'sometimes|in:auto,source_locked,conservative_motion,generative','material_plan.fidelity'=>'sometimes|in:standard,exact_brand','material_plan.motion_kind'=>'sometimes|in:auto,camera_only,action','material_plan.shot_kind'=>'sometimes|in:presenter,product_broll,other','material_plan.preferred_model'=>'sometimes|in:auto,seedance-1.5-pro,doubao-seedance-1.0-pro-fast','material_plan.mode'=>'required_with:material_plan|in:single_image,first_last_frames,reference_images','material_plan.reason'=>'required_with:material_plan|string|max:1000','material_plan.roles'=>'required_with:material_plan|array|min:1|max:3','material_plan.roles.*'=>'required|distinct|in:start_frame,end_frame,extra_image','scene_design'=>'sometimes|array', 'director' => 'sometimes|array', 'frames' => 'sometimes|array:start_frame,end_frame,extra_image', 'frames.*' => 'array:required,instruction,state,person_presence,asset,readiness,qa', 'frames.*.person_presence'=>'sometimes|in:visible,distant_or_back,absent', 'frames.*.required' => 'required|boolean', 'frames.*.instruction' => 'sometimes|string|max:4000']);
        $v->validate();

        app(MarketingSpeechRhythm::class)->validate($input);
        $input = app(MarketingStoryboardPackage::class)->content($input);
        foreach ($input['frames'] as &$frame) {
            $frame['asset'] = null;
            $frame['readiness'] = $frame['required'] ? 'pending' : 'not_required';
            $frame['qa'] = null;
        }unset($frame);

        return array_intersect_key($input, array_flip(['seconds', 'message', 'visual', 'voiceover', 'subtitle', 'presenter_required', 'fact_refs', 'director', 'scene_design', 'frames', 'material_plan', 'rhythm']));
    }

    public function signature(array $content, array $profile = []): string
    {
        return hash('sha256', json_encode(['visual' => $content['visual'], 'presenter' => $content['presenter_required'] ?? false, 'profile' => ($content['presenter_required']??false)?($profile['presenter']??null):null, 'image_adjustment'=>$content['image_adjustment']??null, 'scene_design'=>$content['scene_design']??null, 'director' => Arr::except($content['director'] ?? [], ['voiceover', 'subtitle']), 'frames' => array_map(fn ($frame) => array_intersect_key($frame, array_flip(['required', 'instruction','state','person_presence'])), $content['frames'] ?? []), 'ratio' => '9:16'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
