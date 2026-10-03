<?php

namespace App\Services\AI;

class GeminiClient implements ProviderClient
{
    public function __construct(private ProviderTransport $http) {}

    public function models(string $key, ?string $baseUrl): array
    {
        $models = [];
        $token = null;
        for ($page = 0; $page < 10; $page++) {
            $data = $this->http->json('GET', 'https://generativelanguage.googleapis.com/v1beta/models', ['x-goog-api-key' => $key], array_filter(['pageSize' => 1000, 'pageToken' => $token]));
            if (! isset($data['models']) || ! is_array($data['models'])) {
                throw new ProviderFailure('models');
            }
            foreach ($data['models'] as $item) {
                $id = preg_replace('/^models\//', '', $item['name'] ?? '');
                if (! preg_match('/^[a-zA-Z0-9_.-]{1,150}$/', $id) || ! in_array('generateContent', $item['supportedGenerationMethods'] ?? [], true)) {
                    continue;
                }
                $image = str_contains($id, 'image');
                $models[] = ['id' => $id, 'text' => ! $image, 'image' => $image];
            }
            $token = $data['nextPageToken'] ?? null;
            if (! $token) {
                return $models;
            }
        }
        throw new ProviderFailure('models');
    }

    public function text(string $key, ?string $baseUrl, string $model, string $prompt, int $maxTokens): string
    {
        $data = $this->http->json('POST', 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent', ['x-goog-api-key' => $key], [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]], 'generationConfig' => ['maxOutputTokens' => $maxTokens],
        ]);
        $text = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (! ($part['thought'] ?? false)) {
                $text .= $part['text'] ?? '';
            }
        }
        if (trim($text) === '') {
            throw new ProviderFailure('response');
        }

        return $text;
    }
}
