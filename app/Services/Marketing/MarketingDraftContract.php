<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Response shape and review evidence; never edits a saved project or approval. */
class MarketingDraftContract
{
    public const VERSION = 'scene-requirements-v2';

    public function storyboardSchema(): array
    {
        $text = fn (int $max, int $min=1) => ['type'=>'string','minLength'=>$min,'maxLength'=>$max];
        $list = fn (array $items, int $min, int $max) => ['type'=>'array','items'=>$items,'minItems'=>$min,'maxItems'=>$max];
        $object = fn (array $properties) => ['type'=>'object','properties'=>$properties,'required'=>array_keys($properties),'additionalProperties'=>false];
        $scene = $object(['visual'=>$text(2000),'voiceover'=>$text(1500,0),'subtitle'=>$text(1000,0), 'duration'=>['type'=>'integer','minimum'=>1,'maximum'=>60], 'material'=>$text(1500), 'asset_ids'=>$list(['type'=>'string','format'=>'uuid'],0,12)]);
        $scene['properties']['rhythm']=app(MarketingSpeechRhythm::class)->schema();$scene['required'][]='rhythm';
        $display = $object(['layout'=>['type'=>'string','enum'=>['need_open','product_intro','use_map','business_uses','requirements','cta_close']], 'heading'=>$text(100), 'lines'=>$list($text(24),3,3), 'body'=>$text(160)]);
        $display['description']='Internal layout guidance only. These text fields are not rendered as audience copy. Put all intended on-screen marketing text in the matching scenes.subtitle field; visual and material are director instructions.';
        $scene['properties']['subtitle']['description']='Complete audience-visible on-screen copy for this scene. Never include camera, editing or production instructions. The renderer formats this exact field without inventing extra text.';
        $requirement = $object(['kind'=>['type'=>'string','enum'=>['required_fact','material_permission','brief_confirmation','quotation_note']], 'message'=>$text(1000), 'scene_number'=>['type'=>'integer','minimum'=>0,'maximum'=>6], 'evidence'=>$text(2000,0)]);
        $requirement['description']='Only dependencies actually used in scenes block. Attach exact evidence and a 1-based scene number. A quotation boundary for unspecified final details has scene_number 0 and empty evidence; it is a note, not a request for specifications or stock. A concrete claim in a scene must remain linked and blocking.';
        return $object(['summary'=>$text(2500), 'duration'=>['type'=>'integer','minimum'=>1,'maximum'=>60], 'cta'=>$text(150), 'changes'=>$list($text(1000),0,12), 'scenes'=>$list($scene,6,6), 'requirements'=>$list($requirement,0,12), 'production'=>$object(['brand'=>$object(['name'=>$text(16),'descriptor'=>$text(60)]), 'scenes'=>$list($display,6,6)])]);
    }

    public function review(array $data, array $facts): array
    {
        // Do not quietly accept the old untyped gap list in a v2 response.
        if (array_key_exists('gaps',$data) || array_key_exists('notes',$data)) {
            throw ValidationException::withMessages(['generation'=>'The draft did not use evidence-linked review requirements.']);
        }
        $validated = Validator::make($data,[
            'requirements'=>'present|array|max:12',
            'requirements.*'=>'required|array:kind,message,scene_number,evidence',
            'requirements.*.kind'=>'required|in:required_fact,material_permission,brief_confirmation,quotation_note',
            'requirements.*.message'=>'required|string|max:1000',
            'requirements.*.scene_number'=>'required|integer|min:0|max:12',
            'requirements.*.evidence'=>'present|string|max:2000',
        ])->validate();
        $gaps=[];$notes=[];
        foreach ($validated['requirements'] as $item) {
            $number=$item['scene_number'];$evidence=$item['evidence'];
            if ($number===0) {
                if ($evidence!=='' || !in_array($item['kind'],['quotation_note','brief_confirmation'],true)) {
                    throw ValidationException::withMessages(['generation'=>'A required scene dependency needs an exact scene reference.']);
                }
                if ($item['kind']==='quotation_note' && ($facts['snapshot']['purchase_mode']??null)==='quote') {
                    $notes[]=$item['message'];
                } else { $gaps[]=$item['message']; }
                continue;
            }
            $scene=$data['scenes'][$number-1]??null;
            $fields=is_array($scene) ? array_intersect_key($scene,array_flip(['visual','voiceover','subtitle','material'])) : [];
            $matches=false;
            foreach($fields as $field) if(is_string($field) && trim($evidence)!=='' && str_contains($field,$evidence)) { $matches=true;break; }
            if (!$matches) { throw ValidationException::withMessages(['generation'=>'A required fact or permission does not match the referenced scene.']); }
            // Even a mislabeled quotation note stays blocking when a scene makes the claim.
            $gaps[]='Scene '.$number.': '.$item['message'];
        }
        return ['gaps'=>array_values(array_unique($gaps)), 'notes'=>array_values(array_unique($notes)), 'requirements'=>$validated['requirements']];
    }
}
