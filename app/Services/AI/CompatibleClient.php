<?php

namespace App\Services\AI;

class CompatibleClient extends OpenAiClient
{
    protected function custom(): bool
    {
        return true;
    }

    protected function base(?string $url): string
    {
        $parts = parse_url($url ?? '');
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($url, config('ai_connect.compatible_base_urls', []), true)) {
            throw new ProviderFailure('endpoint');
        }

        return rtrim($url, '/');
    }

    public function text(string $key, ?string $baseUrl, string $model, string $prompt, int $maxTokens, ?float $temperature = null): string
    {
        $data = $this->http->json('POST', $this->base($baseUrl).'/chat/completions', ['Authorization' => 'Bearer '.$key], [
            'model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'max_tokens' => $maxTokens, ...($temperature !== null ? ['temperature' => $temperature] : []),
        ], custom: true);
        if (($data['choices'][0]['finish_reason'] ?? '') === 'length') throw new ProviderFailure('output_limit');
        if (($data['choices'][0]['finish_reason'] ?? '') === 'content_filter') throw new ProviderFailure('refusal');
        $text = $data['choices'][0]['message']['content'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new ProviderFailure('response');
        }

        return $text;
    }
}
