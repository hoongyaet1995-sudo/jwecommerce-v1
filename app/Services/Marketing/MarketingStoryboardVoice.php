<?php

namespace App\Services\Marketing;

use App\Models\MarketingAsset;
use App\Models\MarketingProduction;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingScene;
use App\Models\MarketingSceneRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Models\MarketingStoryboardWorkspace as Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MarketingStoryboardVoice
{
    public function options(): array
    {
        return array_values(array_filter(config('marketing.storyboard.voices', []), fn ($v) => isset($v['id'],$v['version'],$v['name'],$v['provider_voice_id'],$v['model']) && preg_match('/^[A-Za-z0-9_-]{1,80}$/', $v['provider_voice_id']) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $v['model'])));
    }

    public function resolve(array $voice): array
    {
        $entry = collect($this->options())->first(fn ($v) => $v['id'] === ($voice['id'] ?? null) && $v['version'] === ($voice['version'] ?? null));
        abort_unless($entry, 422, '选择的声线没有有效供应商配置。');

        return $entry;
    }

    public function signature(array $scene, array $voice): string
    {
        return hash('sha256', json_encode([$scene['voiceover'], $voice, 'format' => 'mp3_44100_128'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function receipt(string $project, MarketingSceneRevision $revision, array $profile): ?array
    {
        if (trim($revision->content['voiceover'] ?? '') === '' || ($profile['voice']['mode'] ?? 'none') !== 'preset') {
            return null;
        }$sig = $this->signature($revision->content, $profile['voice']);
        $call = MarketingStoryboardProviderCall::where('project_id', $project)->where('role', 'voice')->where('status', 'completed')->where('input->voice_signature', $sig)->first();
        if (! $call) {
            return null;
        }$ref = $call->result['asset'] ?? null;
        if (! $ref) {
            return null;
        }try {
            $actual = app(MarketingStoryboardUpload::class)->reference($project, $ref['asset_id']);
            if (! hash_equals($actual['sha256'], $ref['sha256'])) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }$out = $call->result;
        $out['requires_extension'] = $out['duration_seconds'] > ($revision->content['seconds'] ?? 0);

        return $out;
    }

    public function obtain(string $project, int $owner, string $sceneId, string $authorization, ?string $productionId = null): array
    {
        $run = MarketingProduction::where('project_id', $project)->where('owner_id', $owner)->whereIn('status', ['queued', 'preparing'])->find($productionId);
        abort_unless($run && ($run->result['authorization_id'] ?? null) === $authorization, 409, '正式配音需要Step 4已批准的制作任务。');
        $plan = MarketingProductionPlan::findOrFail($run->plan_id);
        abort_unless($plan->status === 'budget_confirmed', 409, '正式配音尚未确认制作费用。');
        app(MarketingStoryboardProduction::class)->assertPlan($plan, $owner);
        $service = app(MarketingStoryboardWorkspace::class);
        $p = $service->project($project, $owner);
        $w = Workspace::where('project_id', $project)->firstOrFail();
        abort_unless(in_array($sceneId, $w->scene_order, true), 409, '声音所用分镜已移除。');
        $row = MarketingScene::where('project_id', $project)->findOrFail($sceneId);
        $r = MarketingSceneRevision::findOrFail($row->current_revision_id);
        $voice = $plan->manifest['configuration']['audio']['voice_profile'] ?? $w->profile['voice'] ?? [];
        abort_unless(($voice['mode'] ?? 'none') === 'preset', 422, '请先选定实际声线。');
        $selected = $this->resolve($voice);
        abort_unless(($voice['provider_voice_id'] ?? $selected['provider_voice_id']) === $selected['provider_voice_id'], 409, '已选声线配置已改变。');
        $cfg = app(MarketingStoryboardProvider::class)->role('voice');
        abort_unless($selected['model'] === $cfg['model'] && config('marketing.production.elevenlabs_key'), 423, '请先确认声音角色模型与连接。');
        abort_unless(is_numeric($cfg['credit_usd'] ?? null) && $cfg['credit_usd'] >= 0, 423, '请先核验声音角色单价。');
        $text = $r->content['voiceover'];
        abort_unless(is_string($text) && trim($text) !== '', 422, '本段没有口播。');
        $cached = $this->receipt($project, $r, ['voice' => $voice]);
        if ($cached) {
            return $cached;
        }$payload = ['revision_id' => $r->id, 'voice_signature' => $this->signature($r->content, $voice), 'voice_id' => $selected['provider_voice_id'], 'model' => $selected['model'], 'text' => $text, 'seconds' => $r->content['seconds'], 'format' => 'mp3_44100_128'];

        return app(MarketingStoryboardProvider::class)->call('voice', $project, $authorization, $payload, function () use ($project, $payload, $cfg) {
            $response = Http::withHeaders(['xi-api-key' => (string) config('marketing.production.elevenlabs_key')])->acceptJson()->connectTimeout(10)->timeout(180)->withoutRedirecting()->post('https://api.elevenlabs.io/v1/text-to-speech/'.rawurlencode($payload['voice_id']).'/with-timestamps?output_format=mp3_44100_128', ['text' => $payload['text'], 'model_id' => $payload['model']]);
            abort_unless($response->status() === 200 && strlen($response->body()) <= 10000000, 409, '语音结果未知，请核对费用，不会自动重试。');
            $data = $response->json();
            $bytes = base64_decode($data['audio_base64'] ?? '', true);
            abort_unless(is_string($bytes) && strlen($bytes) >= 16 && strlen($bytes) <= 5000000, 409, '语音字节无效。');
            $hash = hash('sha256', $bytes);
            $path = 'marketing/'.$project.'/storyboard-audio/'.$hash.'.mp3';
            Storage::disk('local')->put($path, $bytes);
            $duration = app(MarketingMediaTools::class)->audioDuration(Storage::disk('local')->path($path));
            $a = $data['alignment'] ?? [];
            $chars = $a['characters'] ?? [];
            $starts = $a['character_start_times_seconds'] ?? [];
            $ends = $a['character_end_times_seconds'] ?? [];
            abort_unless(is_array($chars) && count($chars) > 0 && count($chars) === count($starts) && count($chars) === count($ends) && implode('', $chars) === $payload['text'], 409, '口播对齐不完整，未采用。');
            $last = 0;
            foreach ($starts as $i => $start) {
                abort_unless(is_numeric($start) && is_numeric($ends[$i]) && $start >= $last && $ends[$i] >= $start && $ends[$i] <= $duration + .2, 409, '语音时间对齐无效。');
                $last = $start;
            }$asset = MarketingAsset::firstOrCreate(['project_id' => $project, 'path' => $path], ['name' => 'AI storyboard voice.mp3', 'mime' => 'audio/mpeg', 'size' => strlen($bytes), 'authorized' => true]);
            $credits = $response->header('character-cost');
            $cost = is_numeric($credits) && isset($cfg['credit_usd']) ? (int) ceil((float) $credits * $cfg['credit_usd'] * 1000000) : null;

            return ['asset' => app(MarketingStoryboardUpload::class)->reference($project, $asset->id), 'voice_id' => $payload['voice_id'], 'model' => $payload['model'], 'text' => $payload['text'], 'alignment' => $a, 'duration_seconds' => $duration, 'requires_extension' => $duration > $payload['seconds'], 'usage' => ['character_cost' => is_numeric($credits) ? (float) $credits : null], 'actual_microusd' => $cost];
        });
    }

    public function mux(string $project, int $owner, string $video, array $receipt, string $output): array
    {
        app(MarketingStoryboardWorkspace::class)->project($project, $owner);
        $ref = app(MarketingStoryboardUpload::class)->reference($project, $receipt['asset']['asset_id']);
        abort_unless(hash_equals($ref['sha256'], $receipt['asset']['sha256']), 409, '声音文件已改变。');
        $a = MarketingAsset::findOrFail($ref['asset_id']);
        $tools = app(MarketingMediaTools::class);
        $probe = $tools->probe($video);
        $duration = (float) ($probe['format']['duration'] ?? 0);
        abort_unless($duration > 0 && $receipt['duration_seconds'] <= $duration, 422, '完整口播需要延长镜头，不能裁剪。');
        $tools->run([(string) config('marketing.production.ffmpeg_binary'), '-v', 'error', '-i', $video, '-i', Storage::disk('local')->path($a->path), '-map', '0:v:0', '-map', '1:a:0', '-c:v', 'copy', '-c:a', 'aac', '-af', 'apad', '-t', (string) $duration, '-y', $output], 120);

        return ['path' => $output, 'sha256' => hash_file('sha256', $output), 'voice_id' => $receipt['voice_id'], 'duration_seconds' => $duration];
    }
}
