<?php

namespace App\Services\Marketing;

/** Current timeline readout. Never changes frozen designs, image signatures or film recipes. */
class MarketingStoryboardDirectorReadout
{
    public function scenes(array $scenes): array
    {
        foreach ($scenes as $i => &$scene) {
            $director = $scene['director'] ?? [];
            $beats = $director['beats'] ?? [];
            foreach ($beats as &$beat) {
                foreach (['action', 'description'] as $key) {
                    if (isset($beat[$key])) {
                        $beat[$key] = preg_replace('/至\s*\d+(?:\.\d+)?\s*秒结束/u', '至本段结束', $beat[$key]);
                    }
                }
            }
            unset($beat);
            $director['beats'] = $beats;
            if ($beats) {
                $director['motion'] = '按当前'.$scene['seconds'].'秒分时动作执行：'.implode('；', array_map(fn ($beat) => $beat['start'].'–'.$beat['end'].'秒：'.preg_replace('/[。；;]+$/u', '', $beat['action'] ?? $beat['description'] ?? ''), $beats));
            }
            foreach (['continuity', 'end_state'] as $key) {
                $pattern=$key==='end_state'?'/[^。；;，,]*(?:下一场|下一段)[^。；;，,]*[。；;，,]?/u':'/[^。；;]*(?:下一场|下一段)[^。；;]*[。；;]?/u';
                $director[$key] = preg_replace($pattern, '', $director[$key] ?? '');
            }
            if (isset($scenes[$i + 1])) {
                $director['continuity'] .= '下一段：'.preg_replace('/[。；;]+$/u', '', $scenes[$i + 1]['message']).'。按下一段已保存的起始状态切换，不预设场景延续。';
            } else {
                $director['continuity'] .= '本段为当前故事收尾，字幕停留至本段结束。';
            }
            $scene['director_readout'] = $director;
            if (!empty($scene['material_plan']) && !empty($scene['frames']['start_frame']['employee_acceptance']['sync_framing'])) {
                $scene['material_reason_readout'] = '沿用用户已确认画面与构图；'.$scene['material_plan']['reason'];
                $scene['material_reason_readout'] = preg_replace('/[^。]*需先按QA意见[^。]*[。]?/u', '', $scene['material_reason_readout']);
            }
        }
        unset($scene);

        return $scenes;
    }

    public function snapshot(array $snapshot): array
    {
        $snapshot['scenes'] = $this->scenes($snapshot['scenes']);
        return $snapshot;
    }
}
