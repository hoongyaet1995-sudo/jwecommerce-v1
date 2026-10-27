<?php

namespace App\Services\Marketing;

/** Complete Step 3 data, independent of suppliers and finished audio. */
class MarketingStoryboardPackage
{
    public const ROLES = ['start_frame', 'end_frame', 'extra_image'];

    public function content(array $c, array $before = []): array
    {
        if (empty($c['director'])) {
            $same = $before && ($before['visual'] ?? null) === $c['visual'] && ($before['seconds'] ?? null) === $c['seconds'];
            $c['director'] = $same ? ($before['director'] ?? null) : null;
            $c['director'] ??= ['subject' => $c['visual'], 'framing' => '保持已确认的画面构图（系统默认）', 'motion' => '按已保存演出执行，不添加额外事件（系统默认）', 'lighting' => '沿用参考素材的光线（系统默认）', 'continuity' => '保持人物、服装和可见商品事实一致', 'start_state' => $c['visual'], 'end_state' => '完成本段演出，不增加新的商品主张', 'beats' => [['start' => 0, 'end' => $c['seconds'], 'action' => $c['visual']]], 'origin' => 'compiled_scene_defaults'];
        }
        abort_unless(is_array($c['director']) && strlen(json_encode($c['director'], JSON_THROW_ON_ERROR)) <= 20000, 422, '导演稿格式或长度无效。');
        $fallback = ['subject' => $c['visual'], 'framing' => '保持已确认构图（系统默认）', 'motion' => $c['visual'], 'lighting' => '沿用参考光线（系统默认）', 'continuity' => '保持主体及可见商品事实一致', 'start_state' => $c['visual'], 'end_state' => '完成已保存的镜头动作', 'beats' => [['start' => 0, 'end' => $c['seconds'], 'action' => $c['visual']]]];
        $c['director'] = array_replace($fallback, $c['director']);
        foreach ($fallback as $key => $value) {
            if ($key === 'beats') {
                continue;
            }abort_unless(is_string($c['director'][$key]) && mb_strlen($c['director'][$key]) <= 4000, 422, '导演稿字段无效。');
        }
        abort_unless(is_array($c['director']['beats']) && count($c['director']['beats']) >= 1 && count($c['director']['beats']) <= 50, 422, '导演节奏无效。');
        foreach ($c['director']['beats'] as $beat) {
            abort_unless(is_array($beat) && is_numeric($beat['start'] ?? null) && is_numeric($beat['end'] ?? null) && $beat['end'] > $beat['start'], 422, '导演节奏时间无效。');
        }
        foreach (['voiceover', 'subtitle'] as $key) {
            if (array_key_exists($key, $c['director'])) {
                $c['director'][$key] = $c[$key];
            }
        }
        if(isset($c['material_plan'])){
            $plan=$c['material_plan'];
            $expected=match($plan['mode']??''){'single_image'=>['start_frame'],'first_last_frames'=>['start_frame','end_frame'],'reference_images'=>$plan['roles']??[],default=>[]};
            abort_unless($expected && ($plan['roles']??[])===$expected && in_array('start_frame',$expected,true) && count(array_unique($expected))===count($expected) && !array_diff($expected,self::ROLES),422,'AI素材方案与输入角色不一致。');
        }
        $frames = $c['frames'] ?? ($before['frames'] ?? []);
        $out = [];
        foreach (self::ROLES as $role) {
            $f = $frames[$role] ?? [];
            $required = isset($c['material_plan'])?in_array($role,$c['material_plan']['roles'],true):($role === 'start_frame' || (bool) ($f['required'] ?? false));
            $instruction = $f['instruction'] ?? match ($role) {
                'start_frame' => '首帧状态：'.($c['director']['start_state'] ?? $c['visual']),'end_frame' => '尾帧落点：'.($c['director']['end_state'] ?? $c['visual']),default => '本镜头的人物或商品补充参考'
            };
            abort_unless(is_string($instruction) && mb_strlen($instruction) <= 4000, 422, '图片用途说明无效。');
            $presence=$f['person_presence']??(($c['presenter_required']??false)?'visible':'absent');
            abort_unless(in_array($presence,['visible','distant_or_back','absent'],true),422,'图片人物状态无效。');
            $out[$role] = [...array_intersect_key($f,array_flip(['state','candidate_asset','candidate_revision_id','employee_acceptance'])), 'person_presence'=>$presence, 'required' => $required, 'instruction' => $instruction, 'asset' => $f['asset'] ?? null, 'readiness' => $f['readiness'] ?? ($required ? 'pending' : 'not_required'), 'qa' => $f['qa'] ?? null];
        }
        $c['frames'] = $out;
        $c['presenter_required']=collect($out)->contains(fn($f)=>$f['required']&&$f['person_presence']!=='absent');

        return $c;
    }

    public function ready(array $c): bool
    {
        if (!empty($c['director_needs_detail']) || empty($c['director']) || empty($c['frames'])) {
            return false;
        }
        foreach ($c['frames'] as $f) {
            if ($f['required'] && ((($f['asset']['kind'] ?? 'image') !== 'image') || empty($f['asset']) || ! in_array($f['readiness'], ['qa_passed', 'employee_declared_local_validated'], true))) {
                return false;
            }
        }

        return true;
    }

    public function digest(array $scenes): string
    {
        return hash('sha256',json_encode($scenes,JSON_THROW_ON_ERROR));
    }
}
