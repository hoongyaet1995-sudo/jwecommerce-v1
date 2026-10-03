<?php

namespace App\Services\AI;

interface ProviderClient
{
    /** @return list<array{id:string,text:bool,image:bool}> */
    public function models(string $key, ?string $baseUrl): array;

    public function text(string $key, ?string $baseUrl, string $model, string $prompt, int $maxTokens): string;
}
