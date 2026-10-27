<?php

namespace App\Services\Marketing;

use App\Models\{AiConnection, MarketingTemplateCall};
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/** One transport attempt per durable caller reservation; never retries unknown outcomes. */
class MarketingTemplateProvider
{
    public const MODEL = 'gpt-6-luna';
    public const LLM_RESERVE_MICROUSD = 3700;
    public const VOICES = ['ByhETIclHirOlWnWKhHc', 'hpp4J3VqNfWAUOO0d1Us'];

    public function llm(array $payload, string $clientRequestId): array
    {
        $connection = AiConnection::find('openai');
        if (! $connection?->enabled || ! $connection->api_key) {
            throw new RuntimeException('template_openai_connection_unavailable');
        }
        if (! isset($payload['input']) || ! is_string($payload['input']) && ! is_array($payload['input'])) {
            throw new RuntimeException('template_input_required');
        }
        // Only these bounded data/schema fields may enter the fixed provider request.
        $body = array_intersect_key($payload, array_flip(['input', 'instructions', 'text']));
        $body += ['model' => self::MODEL, 'store' => false, 'reasoning' => ['effort' => 'medium']];
        $headers = ['Authorization' => 'Bearer '.$connection->api_key, 'X-Client-Request-Id' => $clientRequestId];
        $countBody = $body;
        unset($countBody['store']);
        $count = $this->request($headers)->post('https://api.openai.com/v1/responses/input_tokens', $countBody)->throw()->json('input_tokens');
        if (! is_int($count) || $count < 0 || $count > 12000) {
            throw new RuntimeException('template_input_token_limit');
        }
        $body['max_output_tokens'] = 5000;
        $response = $this->request($headers, $clientRequestId)->post('https://api.openai.com/v1/responses', $body)->throw();
        $data = $this->json($response, 4000000);
        $receipt = ['data' => $data, 'server_request_id' => $this->requestId($response)];
        $usage = $data['usage'] ?? [];
        $input = $usage['input_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? null;
        $cached = $usage['input_tokens_details']['cached_tokens'] ?? 0;
        if (is_int($input) && is_int($output) && is_int($cached) && $input >= 0 && $input <= 12000 && $output >= 0 && $output <= 5000 && $cached >= 0 && $cached <= $input) {
            // Rates are microUSD per token. Integer arithmetic avoids float rounding drift.
            $receipt['actual_microusd'] = intdiv(($input - $cached) * 100 + $cached * 10 + $output * 500 + 999, 1000);
        }
        return $receipt;
    }

    public function tts(array $payload, string $clientRequestId): array
    {
        $voice = $payload['voice_id'] ?? '';
        if (! in_array($voice, self::VOICES, true)) {
            throw new RuntimeException('template_voice_not_approved');
        }
        $text = $payload['text'] ?? null;
        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 300) {
            throw new RuntimeException('template_voice_text_limit');
        }
        if (isset($payload['model']) && $payload['model'] !== 'eleven_multilingual_v2' || isset($payload['model_id']) && $payload['model_id'] !== 'eleven_multilingual_v2') {
            throw new RuntimeException('template_voice_model_not_approved');
        }
        $key = config('marketing.production.elevenlabs_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('template_voice_connection_unavailable');
        }
        $response = $this->request(['xi-api-key' => $key, 'X-Client-Request-Id' => $clientRequestId], $clientRequestId)
            ->post('https://api.elevenlabs.io/v1/text-to-speech/'.$voice.'/with-timestamps?output_format=mp3_44100_128', ['text' => $text, 'model_id' => 'eleven_multilingual_v2'])->throw();
        $data = $this->json($response, 10000000);
        $receipt = ['data' => $data, 'server_request_id' => $this->requestId($response)];
        $cost = $response->header('character-cost');
        if (is_string($cost) && preg_match('/^\d+(?:\.\d{1,6})?$/D', $cost) && (float) $cost <= 300) {
            $parts = explode('.', $cost, 2);
            $units = (int) $parts[0] * 1000000 + (int) str_pad($parts[1] ?? '', 6, '0');
            $receipt['actual_microusd'] = intdiv($units * 80 + 999999, 1000000);
        }
        return $receipt;
    }

    private function request(array $headers, ?string $clientRequestId = null): PendingRequest
    {
        $request = Http::withHeaders($headers)->acceptJson()->connectTimeout(10)->timeout(180)->withoutRedirecting();
        if ($clientRequestId !== null) {
            $request->withOptions(['on_headers' => function (ResponseInterface $response) use ($clientRequestId): void {
                $metadata = ['server_request_id' => substr($response->getHeaderLine('x-request-id') ?: $response->getHeaderLine('request-id'), 0, 255), 'http_status' => $response->getStatusCode()];
                $characterCost = $response->getHeaderLine('character-cost');
                if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $characterCost) && (float) $characterCost <= 300) {
                    $metadata['character_cost'] = $characterCost;
                }
                // Headers survive a timeout/body failure. Never retain auth or arbitrary headers.
                $call = MarketingTemplateCall::where('client_request_id', $clientRequestId)->first();
                if ($call) {
                    $call->update(['receipt' => array_replace($call->receipt ?? [], $metadata)]);
                }
            }]);
        }
        return $request;
    }

    private function json(Response $response, int $limit): array
    {
        if (strlen($response->body()) > $limit || ! is_array($response->json())) {
            throw new RuntimeException('template_provider_body_invalid');
        }
        return $response->json();
    }

    private function requestId(Response $response): ?string
    {
        $id = $response->header('x-request-id') ?: $response->header('request-id');
        return is_string($id) && $id !== '' ? substr($id, 0, 255) : null;
    }
}
