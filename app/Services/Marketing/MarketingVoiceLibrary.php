<?php

namespace App\Services\Marketing;

use App\Models\MarketingProduction;
use App\Models\MarketingProductionBudget;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingProject;
use App\Models\MarketingVoiceAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MarketingVoiceLibrary
{
    public function __construct(private MarketingMediaTools $tools, private MarketingElevenLabsClient $client) {}

    public function spec(MarketingProductionPlan $plan): array
    {
        $a = $plan->manifest['audio'];

        return ['text' => $a['text'], 'model' => $a['model'], 'voice' => $a['voice'], 'output_format' => 'mp3_44100_128'];
    }

    public function signature(array $spec): string
    {
        return hash('sha256', json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function obtain(MarketingProduction $task, MarketingProductionPlan $plan): MarketingVoiceAsset
    {
        $spec = $this->spec($plan);
        $claimed = false;
        $voice = DB::transaction(function () use ($task, $plan, $spec, &$claimed) {
            MarketingProject::whereKey($task->project_id)->lockForUpdate()->firstOrFail();
            $currentTask = MarketingProduction::lockForUpdate()->findOrFail($task->id);
            if (! in_array($currentTask->status, ['preparing', 'audio_requesting'], true)) {
                throw new MarketingProductionFailure('worker_interrupted_outcome_unknown', 'unknown');
            }
            $voice = MarketingVoiceAsset::where('project_id', $task->project_id)->where('signature', $this->signature($spec))->lockForUpdate()->first();
            if ($voice) {
                if ($voice->status !== 'ready') {
                    throw new MarketingProductionFailure('voice_outcome_unknown', 'unknown');
                }
                $task->update(['voice_asset_id' => $voice->id]);

                return $voice;
            }
            if ($currentTask->voice_asset_id) {
                throw new MarketingProductionFailure('voice_cache_invalid', 'blocked');
            }
            if ($plan->manifest['audio']['reuse_only'] ?? false) {
                throw new MarketingProductionFailure('approved_voice_unavailable', 'blocked');
            }
            if (! config('marketing.production.live_audio_enabled') || ! config('marketing.production.elevenlabs_key') || config('marketing.production.live_audio_project') !== $task->project_id) {
                throw new MarketingProductionFailure('live_audio_disabled', 'blocked');
            }
            if ($plan->manifest['audio']['credit_authorization']??null) {
                $authorization=app(MarketingAudioCreditAuthorization::class)->claim($task,$plan,$spec);
                $voice=MarketingVoiceAsset::create(['project_id'=>$task->project_id,'signature'=>$this->signature($spec),'spec'=>$spec,'status'=>'requesting','source'=>'live_elevenlabs','reserved_microusd'=>0,'usage'=>['credit_authorization'=>$authorization,'reserved_credits'=>$authorization['max_credits'],'request_claimed'=>true]]);
                $task->update(['voice_asset_id'=>$voice->id,'status'=>'audio_requesting','new_reserved_microusd'=>0,'result'=>['voice_request_claimed'=>true,'new_reserved_credits'=>$authorization['max_credits'],'credit_authorization_id'=>$authorization['id']]]);
                $claimed=true;
                return $voice;
            }
            $cap = $plan->manifest['budget']['cap_microusd'];
            $budget = MarketingProductionBudget::whereKey((string) config('marketing.production.test_budget'))->lockForUpdate()->first();
            if (! $budget || ! is_int($cap) || $cap < 1 || $budget->reserved_microusd + $cap > $budget->ceiling_microusd) {
                throw new MarketingProductionFailure('test_budget_exhausted', 'blocked');
            }
            $budget->update(['reserved_microusd' => $budget->reserved_microusd + $cap]);
            $voice = MarketingVoiceAsset::create(['project_id' => $task->project_id, 'signature' => $this->signature($spec), 'spec' => $spec, 'status' => 'requesting', 'source' => 'live_elevenlabs', 'reserved_microusd' => $cap]);
            $task->update(['voice_asset_id' => $voice->id, 'status' => 'audio_requesting', 'new_reserved_microusd' => $cap]);
            $claimed = true;

            return $voice;
        });
        if (! $claimed) {
            $this->validateCached($voice);

            return $voice;
        }
        try {
            $result = $this->client->generate($spec);
            $this->save($voice, $result['bytes'], $result['alignment'], $result['usage']);
        } catch (\Throwable $error) {
            $reason = $error instanceof MarketingProductionFailure ? $error : new MarketingProductionFailure('voice_result_invalid', 'unknown');
            $voice->update(['status' => $reason->state === 'unknown' ? 'unknown' : 'rejected', 'usage' => array_replace($voice->usage??[], request()->attributes->get('marketing_elevenlabs_usage', []), ['usd_cost' => null, 'error' => $reason->reason, 'reservation_held' => true])]);
            throw $reason;
        }

        return $voice->fresh();
    }

    public function import(MarketingProductionPlan $plan, string $bytes, array $alignment, array $usage): MarketingVoiceAsset
    {
        return DB::transaction(function () use ($plan, $bytes, $alignment, $usage) {
            MarketingProject::whereKey($plan->project_id)->lockForUpdate()->firstOrFail();
            $spec = $this->spec($plan);
            $existing = MarketingVoiceAsset::where('project_id', $plan->project_id)->where('signature', $this->signature($spec))->first();
            if ($existing) {
                if ($existing->status !== 'ready' || ! hash_equals((string) $existing->sha256, hash('sha256', $bytes))) {
                    throw new MarketingProductionFailure('voice_import_conflict', 'blocked');
                }
                $this->validateCached($existing);

                return $existing;
            }
            $voice = MarketingVoiceAsset::create(['project_id' => $plan->project_id, 'signature' => $this->signature($spec), 'spec' => $spec, 'source' => 'verified_local_probe', 'status' => 'importing']);
            $this->save($voice, $bytes, $alignment, $usage + ['usd_cost' => null]);

            return $voice->fresh();
        });
    }

    private function save(MarketingVoiceAsset $voice, string $bytes, array $alignment, array $usage): void
    {
        // Record measured usage even if subsequent media/cap validation rejects the result.
        $voice->update(['usage'=>array_replace($voice->usage??[],$usage)]);
        if($voice->usage['credit_authorization']??null){
            $cost=$usage['character_cost']??null;
            if(!is_numeric($cost)||!is_finite((float)$cost)||$cost<0||$cost>$voice->usage['credit_authorization']['max_credits'])throw new MarketingProductionFailure('voice_credit_usage_unverified','unknown');
        }
        if (strlen($bytes) < 16 || strlen($bytes) > 5000000) {
            throw new MarketingProductionFailure('voice_result_invalid', 'unknown');
        }
        $base = 'marketing-voices/'.$voice->project_id.'/'.$voice->id;
        $disk = Storage::disk('local');
        if (! $disk->put($base.'.mp3', $bytes)) {
            throw new MarketingProductionFailure('voice_storage_failed', 'unknown');
        }
        $duration = $this->tools->audioDuration($disk->path($base.'.mp3'));
        $this->validateAlignment($voice->spec['text'], $alignment, $duration);
        $json = json_encode($alignment, JSON_THROW_ON_ERROR);
        if (! $disk->put($base.'.json', $json)) {
            throw new MarketingProductionFailure('voice_storage_failed', 'unknown');
        }
        $voice->update(['status' => 'ready', 'audio_path' => $base.'.mp3', 'alignment_path' => $base.'.json', 'duration_seconds' => $duration, 'sha256' => hash('sha256', $bytes), 'usage' => array_replace($voice->usage??[],$usage,['alignment_sha256' => hash('sha256', $json)])]);
    }

    public function validateCached(MarketingVoiceAsset $voice): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($voice->audio_path) || ! $disk->exists($voice->alignment_path) || ! hash_equals((string) $voice->sha256, hash_file('sha256', $disk->path($voice->audio_path))) || ! hash_equals($voice->usage['alignment_sha256'] ?? '', hash_file('sha256', $disk->path($voice->alignment_path)))) {
            throw new MarketingProductionFailure('voice_cache_invalid', 'blocked');
        }
        $alignment = json_decode($disk->get($voice->alignment_path), true, 64, JSON_THROW_ON_ERROR);
        $this->validateAlignment($voice->spec['text'], $alignment, $this->tools->audioDuration($disk->path($voice->audio_path)));
    }

    private function validateAlignment(string $text, array $a, float $duration): void
    {
        $chars = $a['characters'] ?? [];
        $starts = $a['character_start_times_seconds'] ?? [];
        $ends = $a['character_end_times_seconds'] ?? [];
        if (! is_array($chars) || ! is_array($starts) || ! is_array($ends) || count($chars) !== mb_strlen($text) || count($starts) !== count($chars) || count($ends) !== count($chars) || count(array_filter($chars, 'is_string')) !== count($chars) || implode('', $chars) !== $text) {
            throw new MarketingProductionFailure('voice_result_invalid', 'unknown');
        }
        $previous = 0;
        foreach ($starts as $i => $start) {
            $end = $ends[$i] ?? null;
            if ((! is_int($start) && ! is_float($start)) || (! is_int($end) && ! is_float($end)) || ! is_finite((float) $start) || ! is_finite((float) $end) || $start < $previous || $end < $start || $end > $duration + 0.3) {
                throw new MarketingProductionFailure('voice_result_invalid', 'unknown');
            }
            $previous = $start;
        }
    }
}
