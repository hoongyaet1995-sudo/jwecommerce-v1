<?php
namespace App\Services\Support;

/** Literal public catalogue constraints, shared across industries and themes. */
class ProductRequirements
{
    public function normalize(string $text): string
    {
        $text = mb_strtolower(\Illuminate\Support\Str::ascii($text));
        $text = preg_replace('/(?<=\d)[\s-]+(?=(?:gb|tb|mb|ml|kg|cm|mm|inch|hz|w)\b)/i', '', $text);
        return trim(preg_replace('/[^\p{L}\p{N}.]+/u', ' ', $text));
    }

    public function fromQuery(string $query): array
    {
        // Ambiguous alternatives/negation belong to the validated understanding step,
        // never turn them into a conjunction of positive measurement filters.
        if (preg_match('/\b(?:or|not|except|without)\b|或者|或是|不要|不是|除了/iu', $query)) return ['required'=>[], 'excluded'=>[]];
        preg_match_all('/(?<![\p{L}\p{N}])\d+(?:\.\d+)?[\s-]*(?:gb|tb|mb|ml|kg|cm|mm|inch|hz)(?![\p{L}\p{N}])/iu', $query, $matches);
        return ['required'=>array_values(array_unique($matches[0])), 'excluded'=>[]];
    }

    public function matches(string $identity, array $requirements): bool
    {
        $identity = $this->normalize($identity);
        foreach (['required', 'excluded'] as $mode) {
            foreach ($requirements[$mode] ?? [] as $term) {
                $term = $this->normalize($term);
                if ($term === '') continue;
                $found = preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $identity) === 1;
                if (($mode === 'required' && !$found) || ($mode === 'excluded' && $found)) return false;
            }
        }
        return true;
    }
}
