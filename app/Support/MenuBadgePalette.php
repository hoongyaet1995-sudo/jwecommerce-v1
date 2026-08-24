<?php

namespace App\Support;

final class MenuBadgePalette
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return ['#111827', '#4338ca', '#0f766e', '#be123c', '#f59e0b', '#f97316', '#06b6d4', '#e5e7eb'];
    }

    public static function textColor(string $background): string
    {
        $hex = ltrim($background, '#');
        $channels = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $luminance = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);
        $relativeLuminance = 0.2126 * $luminance[0] + 0.7152 * $luminance[1] + 0.0722 * $luminance[2];

        return (1.05 / ($relativeLuminance + 0.05)) >= (($relativeLuminance + 0.05) / 0.05) ? '#ffffff' : '#111827';
    }

    public static function inlineStyle(?string $background): ?string
    {
        if (! in_array($background, self::values(), true)) {
            return null;
        }

        return '--menu-badge-bg: '.$background.'; --menu-badge-fg: '.self::textColor($background).'; background-color: var(--menu-badge-bg); border-color: var(--menu-badge-bg); color: var(--menu-badge-fg);';
    }
}
