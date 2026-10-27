<?php

namespace App\Services\Marketing;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionPlan;

class MarketingVideoRenderer
{
    public function assertReady(): void
    {
        foreach (['browser_binary', 'ffmpeg_binary', 'ffprobe_binary'] as $key) {
            if (! is_file((string) config('marketing.production.'.$key))) {
                throw new MarketingProductionFailure('media_runtime_not_configured', 'blocked');
            }
        }
        if (! is_file(base_path('resources/marketing-renderer/node_modules/remotion/package.json'))) {
            throw new MarketingProductionFailure('media_runtime_not_configured', 'blocked');
        }
        app(MarketingMediaTools::class)->run([(string) config('marketing.production.node_binary'), base_path('resources/marketing-renderer/render.mjs'), '--check'], 15);
    }

    public function render(MarketingProduction $task, MarketingProductionPlan $plan, string $directory): array
    {
        $tools = app(MarketingMediaTools::class);
        $tools->run([(string) config('marketing.production.node_binary'), base_path('resources/marketing-renderer/render.mjs'), $directory, (string) config('marketing.production.browser_binary'), (string) config('marketing.production.ffmpeg_binary'), (string) config('marketing.production.ffprobe_binary')], 540, true);
        $path = $directory.'/output.mp4';
        if (! is_file($path) || filesize($path) < 1000 || filesize($path) > 100000000 || ! is_file($directory.'/result.json')) {
            throw new MarketingProductionFailure('render_result_invalid');
        }
        $render = json_decode(file_get_contents($directory.'/result.json'), true, 64, JSON_THROW_ON_ERROR);
        $probe = $tools->probe($path);
        $video = collect($probe['streams'])->firstWhere('codec_type', 'video');
        $audio = collect($probe['streams'])->firstWhere('codec_type', 'audio');
        $duration = (float) ($probe['format']['duration'] ?? 0);
        if (($video['codec_name'] ?? '') !== 'h264' || ($video['width'] ?? 0) !== 720 || ($video['height'] ?? 0) !== 1280 || (int) ($video['nb_read_frames'] ?? 0) !== ($render['frames'] ?? -1) || $duration <= 0 || $duration > 120 || abs($duration - $render['frames'] / 24) > 0.15 || ($plan->manifest['audio']['requests'] && ($audio['codec_name'] ?? '') !== 'aac')) {
            throw new MarketingProductionFailure('render_result_invalid');
        }

        return ['duration_seconds' => $duration, 'width' => 720, 'height' => 1280, 'fps' => 24, 'frames' => (int) $video['nb_read_frames'], 'video_codec' => $video['codec_name'], 'audio_codec' => $audio['codec_name'] ?? null, 'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path), 'render_seconds' => $render['elapsed_seconds'], 'scene_timings' => $render['scene_timings']];
    }
}
