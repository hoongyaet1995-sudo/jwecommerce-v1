<?php
namespace App\Services\Support;

class SupportGenerationContract
{
    public function budget(int $proseSetting): int
    {
        // Existing setting controls prose; metadata and completeness have their own envelope.
        return max(1200, min(2400, $proseSetting * 3));
    }

    public function decode(string $output): array
    {
        try { $value = json_decode($output, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \RuntimeException('invalid_answer_json'); }
        if (!is_array($value) || !is_string($value['answer'] ?? null) || !is_array($value['faq_ids'] ?? null)
            || count($value['faq_ids']) > 40 || array_filter($value['faq_ids'], fn($id)=>!is_string($id))) {
            throw new \RuntimeException('invalid_answer_schema');
        }
        return $value;
    }

    public function diagnosis(\Throwable $error): string
    {
        if ($error instanceof \App\Services\AI\ProviderFailure) return 'provider_'.$error->reason;
        if ($error instanceof \Illuminate\Validation\ValidationException) return 'contract_validation';
        return match ($error->getMessage()) {
            'invalid_answer_json', 'invalid_answer_schema' => $error->getMessage(),
            'Knowledge base exceeds bounded context', 'Follow-up exceeds bounded context', 'Retrieved facts exceed context budget' => 'context_budget',
            default => 'internal_generation_failure',
        };
    }
}
