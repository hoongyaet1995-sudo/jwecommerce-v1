<?php

namespace App\Services\Marketing;

/** Direct Images API, Standard processing. No Batch or cache discount. */
class MarketingImagePricing
{
    public const VERSION = 'gpt-image-2-standard-2026-10-22';

    public const RATES = ['text_input' => 5, 'image_input' => 8, 'image_output' => 30];

    public function cost(array $usage): ?int
    {
        $counts = [$usage['input_tokens_details']['text_tokens'] ?? null, $usage['input_tokens_details']['image_tokens'] ?? null, $usage['output_tokens'] ?? null];
        foreach ($counts as $count) {
            if (! is_int($count) || $count < 0 || $count > 10000000) {
                return null;
            }
        }
        if (isset($usage['input_tokens']) && (! is_int($usage['input_tokens']) || $usage['input_tokens'] !== $counts[0] + $counts[1])) {
            return null;
        }

        return $counts[0] * 5 + $counts[1] * 8 + $counts[2] * 30;
    }

    public function assertStandard(array $pricing): void
    {
        abort_unless(($pricing['billing_mode'] ?? null) === 'standard' &&
            ($pricing['text_usd_per_million'] ?? null) === 5 &&
            ($pricing['image_usd_per_million'] ?? null) === 8 &&
            ($pricing['output_usd_per_million'] ?? null) === 30, 423, '同步图片请求必须使用已核对的 Standard 费率，不可套用 Batch 折扣。');
    }

    public function outputTokens(int $width, int $height, string $quality): int
    {
        // Official image-generation output calculator, fetched 2026-10-22.
        $long = ['low' => 16, 'medium' => 48, 'high' => 96][$quality] ?? null;
        abort_unless($long && min($width, $height) > 0 && $width % 16 === 0 && $height % 16 === 0 && max($width, $height) <= 3840 && max($width, $height) / min($width, $height) <= 3 && $width * $height >= 655360 && $width * $height <= 8294400, 422, '无法核对图片输出费用。');
        $short = (int) round($long * min($width, $height) / max($width, $height), 0, PHP_ROUND_HALF_EVEN);

        return (int) ceil($long * $short * (2000000 + $width * $height) / 4000000);
    }

    public function upperBound(string $prompt, string $size, array $references): ?int
    {
        // GPT Image 1 tile rules do not establish GPT Image 2 reference costs.
        if ($references) {
            return null;
        }
        if (! preg_match('/^(\d+)x(\d+)$/D', $size, $m)) {
            return null;
        }
        $output = $this->outputTokens((int) $m[1], (int) $m[2], 'medium');

        // The calculator estimates output, not a provider-enforced total bound.
        // No verified bound for text token overhead is supplied by the direct API.
        // Do not label a multiplier or arbitrary buffer as a guaranteed upper bound.
        return null;
    }
}
