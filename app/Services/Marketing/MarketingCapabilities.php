<?php
namespace App\Services\Marketing;

/** Trusted local capability facts, never supplied or upgraded by the model. */
class MarketingCapabilities
{
    public const VERSION='marketing-capabilities-t1c-v1';
    public function snapshot():array
    {
        $local=['validation_status'=>'legacy_execution_verified','execution_enabled'=>false,'constraints'=>['ratio'=>'9:16','quality'=>'720p','language'=>['English'],'dynamic_adapter'=>'local_only_verified_JTG_6_scenes','execution'=>'Requires project-scoped local execution, confirmed content, exact saved voice and separate zero-supplier budget.']];
        return ['version'=>self::VERSION,'capabilities'=>[
            'image_motion'=>$local+['tool'=>'remotion','media'=>'image'],
            'existing_video'=>$local+['tool'=>'remotion','media'=>'video'],
            'text_card'=>$local+['tool'=>'remotion','media'=>'none'],
            'native_presenter'=>['tool'=>'evolink','validation_status'=>'JTG_pilot_quality_accepted_by_merchant','execution_enabled'=>false,'constraints'=>['scenes'=>1,'seconds'=>15,'ratio'=>'9:16','quality'=>'720p','language'=>['Mandarin Chinese','Mandarin','zh-MY'],'identity'=>'synthetic','voice'=>'generated','reference_media'=>false,'execution'=>'New generation still requires a current separate supplier budget and authorization. Real-person avatar and cloned voice are not yet verified.']],
            'generated_broll'=>['tool'=>'evolink','validation_status'=>'not_verified','execution_enabled'=>false,'constraints'=>['seconds'=>[4,5],'dialogue'=>false,'identity_continuity'=>false]],
        ]];
    }
}
