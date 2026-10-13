<?php
namespace App\Domain\Cms;

final class HeaderPresentation
{
    public static function resolve(array $document, array $global): array
    {
        $page = $document['headerPresentation'] ?? [];
        $preference = $global['headerPresentation'] ?? (($page['mode'] ?? 'inherit') === 'inherit' ? [] : $page);
        $first = collect($document['sections'] ?? [])->first(fn ($section) => $section['isVisible'] ?? false);
        $mode = ($first['type'] ?? null) === 'hero_slider' ? ($preference['mode'] ?? 'normal') : 'normal';
        return ['mode' => in_array($mode, ['normal','transparent','translucent'], true) ? $mode : 'normal', 'tone' => ($preference['tone'] ?? 'dark') === 'light' ? 'light' : 'dark'];
    }
}
