<?php

namespace App\Services\Marketing;

use App\Services\AI\ProviderTransport;
use Illuminate\Validation\ValidationException;

class MarketingTextClient
{
    public function __construct(private ProviderTransport $http) {}

    public function text(string $key, string $model, string $prompt, ?array $schema = null, int $timeoutSeconds = 30, int $maxOutputTokens=4096): string
    {
        $data = $this->http->json('POST', 'https://api.openai.com/v1/responses', ['Authorization' => 'Bearer '.$key], [
            'model' => $model, 'input' => $prompt, 'max_output_tokens' => $maxOutputTokens, 'store' => false,
            'text' => ['format' => $schema === null ? ['type' => 'json_object'] : ['type'=>'json_schema','name'=>'marketing_storyboard_v2','strict'=>true,'schema'=>$schema]],
        ], false, $timeoutSeconds);
        if (isset($data['status']) && $data['status'] !== 'completed') {
            throw ValidationException::withMessages(['generation' => 'The text response was incomplete. Your saved draft remains available.']);
        }
        $text = '';
        foreach ($data['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'refusal') {
                    throw ValidationException::withMessages(['generation' => 'The text service declined to prepare this draft. Your saved work remains available.']);
                }
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= $part['text'] ?? '';
                }
            }
        }
        if (trim($text) === '') {
            throw ValidationException::withMessages(['generation' => 'The text service did not return a usable draft.']);
        }

        return $text;
    }
}
