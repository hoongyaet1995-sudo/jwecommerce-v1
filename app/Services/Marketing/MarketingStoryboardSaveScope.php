<?php
namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;

/** Employee save choices, validated before any supplier operation. */
class MarketingStoryboardSaveScope
{
    public function speechSeconds(string $text):int
    {
        // Estimate CJK by characters and Latin speech by words, rather than counting every English letter.
        preg_match_all('/[a-zA-Z]+(?:[\x{2019}\x{0027}][a-zA-Z]+)*/u', $text, $words);
        $rest=preg_replace('/[a-zA-Z]+(?:[\x{2019}\x{0027}][a-zA-Z]+)*|\s+/u','',$text);
        return (int) ceil(mb_strlen($rest)/4 + count($words[0])/2.5);
    }

    public function validate(array $command,array $before):?array
    {
        if(!array_key_exists('update_scope',$command))return null; // Existing callers retain their contract.
        Validator::make($command,['update_scope'=>'required|array:images,director,frames','update_scope.images'=>'required|boolean','update_scope.director'=>'required|boolean','update_scope.frames'=>'sometimes|array|max:3','update_scope.frames.*'=>'required|distinct|in:start_frame,end_frame,extra_image','requested_seconds'=>'sometimes|integer|min:3|max:15','confirmed_voiceover'=>'sometimes|string|max:4000','timing_resolution'=>'sometimes|in:keep,shorten'])->validate();
        $s=$command['update_scope'];
        abort_unless(($command['type']??'')==='edit',422,'更新范围仅用于已有分镜调整。');
        abort_unless($s['images']||$s['director'],422,'至少选择一项要更新的内容。');
        if(!$s['director']){
            abort_unless(!empty($before['scene_id']),422,'当前分镜不存在。');
            abort_if(!empty($command['rewrite'])||isset($command['asset_id']),422,'重写或更换素材需要同时更新导演稿。');
            abort_if(isset($command['requested_seconds'])&&$command['requested_seconds']!==$before['seconds'],422,'修改时长需要同步更新导演稿与分镜资料。');
            abort_if(isset($command['content'])&&collect(['message','visual','voiceover','subtitle','seconds'])->contains(fn($k)=>($command['content'][$k]??null)!==($before[$k]??null)),422,'只更新图片时不能修改分镜内容。');
            abort_unless(is_string($command['intent']??null)&&mb_strlen(trim($command['intent']))>0&&mb_strlen($command['intent'])<=4000,422,'请说明图片需要怎么调整。');
        }
        if($s['images']&&isset($s['frames'])){
            abort_unless(count($s['frames'])>0,422,'请至少选择一张图片，或使用 AI 判断。');
            abort_if($s['director']&&!in_array('start_frame',$s['frames'],true),422,'同时更新导演稿时，请勾选首帧以建立新版场景连续性。');
            foreach($s['frames'] as $role)abort_unless($before['frames'][$role]['required']??false,422,'选择的图片不属于本段所需图片。');
            if(!in_array('start_frame',$s['frames'],true))abort_unless(!empty($before['frames']['start_frame']['asset'])&&in_array($before['frames']['start_frame']['readiness']??'', ['qa_passed','employee_declared_local_validated'],true),422,'请先准备通过检查的首帧，或把首帧一起更新。');
        }
        if(isset($command['requested_seconds'])){
            abort_if(!empty($command['rewrite']),422,'指定单段时长时请关闭允许拆分；拆分请使用 AI 安排时长。');
            // AI rewrites dialogue inside the existing analysis request; explicit employee copy remains locked.
            if (($command['mode']??'') === 'ai' && empty($command['refine_manual']) && empty($command['timing_resolution'])) return $s;
            $text=($command['timing_resolution']??'')==='shorten'?($command['confirmed_voiceover']??''):($command['content']['voiceover']??$before['voiceover']??'');
            abort_if(($command['timing_resolution']??'')==='shorten'&&trim($text)==='',422,'请明确确认精简后的口播。');
            abort_if($this->speechSeconds($text)>$command['requested_seconds'],422,'目标时长不足以自然讲完口播，请确认更短的口播或保留信息并延长时长。');
        }
        return $s;
    }
}
