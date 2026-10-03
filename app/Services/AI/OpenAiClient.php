<?php

namespace App\Services\AI;

class OpenAiClient implements ProviderClient
{
    public function __construct(protected ProviderTransport $http) {}

    protected function base(?string $url): string
    {
        return 'https://api.openai.com/v1';
    }

    protected function custom(): bool
    {
        return false;
    }

    public function models(string $key, ?string $baseUrl): array
    {
        $data = $this->http->json('GET', $this->base($baseUrl).'/models', ['Authorization' => 'Bearer '.$key], custom: $this->custom());
        if (! isset($data['data']) || ! is_array($data['data'])) {
            throw new ProviderFailure('models');
        }
        $models = [];
        foreach ($data['data'] as $item) {
            $id = $item['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_.:\/-]{1,150}$/', $id)) {
                continue;
            }
            $image = ! $this->custom() && str_starts_with($id, 'gpt-image');
            $text = $this->custom() || (! $image && preg_match('/^(gpt-|chatgpt-|o[1-9])/', $id) && ! preg_match('/(audio|realtime|transcribe|tts|search|instruct)/', $id));
            if ($text || $image) {
                $models[] = ['id' => $id, 'text' => (bool) $text, 'image' => $image];
            }
        }

        return $models;
    }

    public function text(string $key, ?string $baseUrl, string $model, string $prompt, int $maxTokens): string
    {
        $payload = [
            'model' => $model, 'input' => $prompt, 'max_output_tokens' => $maxTokens, 'store' => false,
        ];
        $data = $this->http->json('POST', $this->base($baseUrl).'/responses', ['Authorization' => 'Bearer '.$key], $payload);
        $text = '';
        foreach ($data['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= $part['text'] ?? '';
                }
            }
        }
        if (trim($text) === '') {
            throw new ProviderFailure('response');
        }

        return $text;
    }
}
