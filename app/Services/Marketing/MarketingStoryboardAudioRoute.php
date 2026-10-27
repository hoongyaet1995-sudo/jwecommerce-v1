<?php

namespace App\Services\Marketing;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Local orchestration only. Capability declarations must be verified before enabling live execution. */
class MarketingStoryboardAudioRoute
{
    public function select(array $handoff, array $video): array
    {
        $voice = $handoff['profile']['voice'] ?? ['mode' => 'auto'];
        // Historical unconfigured defaults were not a deliberate mute instruction.
        $mode = ($voice['status'] ?? '') === 'needs_configuration' ? 'auto' : ($voice['mode'] ?? 'auto');
        $spoken = array_values(array_filter($handoff['scene_map'] ?? [], fn ($s) => trim($s['voiceover'] ?? '') !== ''));
        $language = $handoff['source_context']['language'] ?? $handoff['source_context']['brief']['language'] ?? 'English';
        $base = ['language' => $language, 'voice_intent' => $voice];
        if (! $spoken || $mode === 'none') {
            return $base + ['route' => 'silent'];
        }
        abort_unless(in_array($mode, ['auto', 'preset'], true), 422, '声音安排方式无效。');
        $candidates = [];
        $native = config('marketing.storyboard.audio.native', []);
        if(empty($handoff['require_independent_audio'])&&$mode==='auto'&&($video['customer_billing']??false)&&($native['script_request_supported']??false)&&($native['model']??null)===$video['model']&&in_array($language,$native['languages']??[],true)){
            $candidates[]=$base+['route'=>'native','capabilities'=>$native,'cost_cap'=>count($handoff['scene_map'])*$video['cap_microusd']];
        }
        $trial=!empty($video['native_trial_grant'])&&app(MarketingUploadCheckpoint::class)->activateVideo($handoff)&&($native['scoped_trial']??null)===$video['native_trial_grant'];
        if (empty($handoff['require_independent_audio']) && $mode === 'auto' && ($native['model'] ?? null) === ($video['model'] ?? '') && ($native['version'] ?? null) === ($video['version'] ?? '') && filled($native['verified_at'] ?? null) && in_array($language, $native['languages'] ?? [], true) && (($native['exact_script'] ?? false) === true || $trial) && (count($spoken) === 1 || ($trial && ($native['multi_scene_trial']??false) && ($video['batch_approved']??false))) && (! collect($spoken)->contains(fn ($s) => $s['presenter_required'] ?? false) || (($native['lip_sync'] ?? false) === true || $trial))) {
            $candidates[] = $base + ['route' => 'native', 'capabilities' => $native, 'cost_cap' => count($handoff['scene_map']) * $video['cap_microusd']];
        }
        try {
            $selection = $mode === 'preset' ? $voice : config('marketing.storyboard.audio.auto_voice', []);
            $selected = app(MarketingStoryboardVoice::class)->resolve($selection);
            // An explicit selection remains authoritative; automatic selection needs a verified language mapping.
            if ($mode === 'auto') {
                abort_unless(filled($selection['verified_at'] ?? null) && in_array($language, $selection['languages'] ?? [], true), 423, '自动配音声线及语言尚未核验。');
            }
            $role = app(MarketingStoryboardProvider::class)->role('voice');
            abort_unless($role['model'] === $selected['model'] && is_numeric($role['credit_usd'] ?? null) && $role['credit_usd'] >= 0 && filled(config('marketing.production.elevenlabs_key')), 423, '正式配音连接、声线或费率待核验。');
            $candidates[] = $base + ['route' => 'independent', 'voice' => $role, 'voice_selection' => $selected, 'voice_profile' => array_merge(['mode' => 'preset'], $selected), 'voice_connection' => hash_hmac('sha256', (string) config('marketing.production.elevenlabs_key'), (string) config('app.key')), 'cost_cap' => count($handoff['scene_map']) * $video['cap_microusd'] + count($spoken) * $role['cap_microusd']];
        } catch (HttpExceptionInterface $e) {
            if ($mode === 'preset') {
                throw $e;
            }
        }
        abort_unless($candidates, 423, '没有能力、语言和费用均已核验的声音路线；故事和画面已保留。');
        usort($candidates, fn ($a, $b) => $a['cost_cap'] <=> $b['cost_cap']);

        return $candidates[0];
    }
}
