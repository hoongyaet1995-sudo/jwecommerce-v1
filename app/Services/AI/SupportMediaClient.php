<?php
namespace App\Services\AI;
interface SupportMediaClient
{
    public function capabilities(string $model): array;
    public function analyze(string $key, ?string $baseUrl, string $model, string $kind, string $mime, string $bytes): array;
}
