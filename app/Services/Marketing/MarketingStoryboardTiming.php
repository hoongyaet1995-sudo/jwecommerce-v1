<?php

namespace App\Services\Marketing;

class MarketingStoryboardTiming
{
    public function calculate(array $scenes, array $profile = [], array $capabilities = []): array
    {
        $time = 0;
        $segments = [];
        $reasons = [];
        foreach ($scenes as $s) {
            $seconds = (int) $s['seconds'];
            $segments[] = ['scene_id' => $s['scene_id'], 'start' => $time, 'end' => $time + $seconds];
            $time += $seconds;
            if ($seconds < 3 || $seconds > 15) {
                $reasons[] = 'segment_duration';
            }if (app(MarketingStoryboardSaveScope::class)->speechSeconds($s['voiceover'] ?? '') > $seconds) {
                $reasons[] = 'voiceover_needs_more_time';
            }
        }
        if (count($scenes) > 12) {
            $reasons[] = 'scene_limit';
        }$out = $time + (int) ($profile['outro_seconds'] ?? 0);
        if ($out > ($capabilities['max_output_seconds'] ?? 180)) {
            $reasons[] = 'output_limit';
        }

        return ['pacing_review'=>app(MarketingSpeechRhythm::class)->review($scenes),'speech_rhythm'=>array_map(fn($s)=>app(MarketingSpeechRhythm::class)->analyze($s),$scenes), 'requested_seconds' => $profile['requested_seconds'] ?? 15, 'content_seconds' => $time, 'output_seconds' => $out, 'segments' => $segments, 'requires_attention' => count($reasons) > 0, 'reasons' => array_values(array_unique($reasons))];
    }

    public function validateProposal(array $before, array $after, array $capabilities = []): void
    {
        abort_if(count($after) < 1 || count($after) > 12, 422, '分镜数量必须为1至12段。');
        foreach ($after as $s) {
            abort_if(($s['seconds'] ?? 0) < 3 || $s['seconds'] > 15, 422, '每段当前支持3至15秒。');
        }
    }
}
