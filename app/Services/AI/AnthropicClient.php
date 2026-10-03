<?php

namespace App\Services\AI;

class AnthropicClient implements ProviderClient
{
    public function __construct(private ProviderTransport $http) {}

    private function headers(string $key): array
    {
        return ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
    }

    public function models(string $key, ?string $baseUrl): array
    {
        $models = [];
        $cursor = null;
        for ($page = 0; $page < 10; $page++) {
            $data = $this->http->json('GET', 'https://api.anthropic.com/v1/models', $this->headers($key), array_filter(['limit' => 1000, 'after_id' => $cursor]));
            if (! isset($data['data']) || ! is_array($data['data'])) {
                throw new ProviderFailure('models');
            }
            foreach ($data['data'] as $item) {
                $id = $item['id'] ?? '';
                if (is_string($id) && preg_match('/^[a-zA-Z0-9_.-]{1,150}$/', $id)) {
                    $models[] = ['id' => $id, 'text' => true, 'image' => false];
                }
            }
            if (! ($data['has_more'] ?? false)) {
                return $models;
            }
            $cursor = $data['last_id'] ?? null;
            if (! $cursor) {
                throw new ProviderFailure('models');
            }
        }
        throw new ProviderFailure('models');
    }

    public function text(string $key, ?string $baseUrl, string $model, string $prompt, int $maxTokens, ?float $temperature = null): string
    {
        if ($temperature !== null && preg_match('/claude-(?:opus-4-[7-9]|sonnet-5|mythos)/', $model)) {
            throw new ProviderFailure('temperature');
        }
        $data = $this->http->json('POST', 'https://api.anthropic.com/v1/messages', $this->headers($key), [
            'model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'max_tokens' => $maxTokens, ...($temperature !== null ? ['temperature' => $temperature] : []),
        ]);
        $text = '';
        foreach ($data['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'text') {
                $text .= $part['text'] ?? '';
            }
        }
        if (trim($text) === '') {
            throw new ProviderFailure('response');
        }

        return $text;
    }
}
