<?php

namespace App\Services;

final class ProductHighlightIcons
{
    public const NAMES = ['check', 'audio-lines', 'battery', 'usb', 'bluetooth', 'wifi', 'cpu', 'monitor', 'headphones', 'zap', 'shield-check', 'package', 'truck', 'sparkles', 'leaf', 'gem', 'gift', 'droplets', 'award', 'CheckCircle2'];

    public static function resolve(?string $icon): string
    {
        return in_array($icon, self::NAMES, true) && $icon !== 'CheckCircle2' ? $icon : 'check';
    }
}
