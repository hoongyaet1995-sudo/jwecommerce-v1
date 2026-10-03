<?php

namespace App\Services\AI;

class ProviderRegistry
{
    public const LABELS = ['openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'anthropic' => 'Anthropic', 'compatible' => 'OpenAI-compatible'];

    public function client(string $provider): ProviderClient
    {
        return app(match ($provider) {
            'openai' => OpenAiClient::class, 'gemini' => GeminiClient::class,
            'anthropic' => AnthropicClient::class, 'compatible' => CompatibleClient::class,
            default => throw new ProviderFailure('endpoint'),
        });
    }
}
