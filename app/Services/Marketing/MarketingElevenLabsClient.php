<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;

class MarketingElevenLabsClient
{
    public function generate(array $spec): array
    {
        request()->attributes->remove('marketing_elevenlabs_usage');
        try {
            $response = Http::withHeaders(['xi-api-key' => (string) config('marketing.production.elevenlabs_key')])
                ->acceptJson()->connectTimeout(10)->timeout(180)->withOptions(['allow_redirects' => false])
                ->post('https://api.elevenlabs.io/v1/text-to-speech/21m00Tcm4TlvDq8ikWAM/with-timestamps?output_format=mp3_44100_128', ['text' => $spec['text'], 'model_id' => $spec['model']]);
        } catch (\Throwable) {
            throw new MarketingProductionFailure('voice_outcome_unknown', 'unknown');
        }
        $usage = ['character_cost' => is_numeric($response->header('character-cost')) ? (float) $response->header('character-cost') : null, 'usd_cost' => null, 'http_status' => $response->status()];
        request()->attributes->set('marketing_elevenlabs_usage', $usage);
        if ($response->status() >= 400 && $response->status() < 500) {
            throw new MarketingProductionFailure('voice_request_rejected');
        }
        if ($response->status() !== 200) {
            throw new MarketingProductionFailure('voice_outcome_unknown', 'unknown');
        }
        try {
            if (strlen($response->body()) > 10000000) {
                throw new \RuntimeException;
            }
            $data = $response->json();
            $bytes = base64_decode($data['audio_base64'] ?? '', true);
            if ($bytes === false || strlen($bytes) < 16 || strlen($bytes) > 5000000 || ! is_array($data['alignment'] ?? null)) {
                throw new \RuntimeException;
            }

            return ['bytes' => $bytes, 'alignment' => $data['alignment'], 'usage' => $usage];
        } catch (\Throwable) {
            throw new MarketingProductionFailure('voice_result_invalid', 'unknown');
        }
    }
}
