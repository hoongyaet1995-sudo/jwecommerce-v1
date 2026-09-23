<?php

namespace App\Services\Marketplace;

final class MarketplaceOrderImage
{
    public static function fromPayload(array $payload, string $platform): ?string
    {
        $items = $platform === 'lazada' ? ($payload['order_items'] ?? []) : ($payload['item_list'] ?? []);
        if (!is_array($items)) return null;
        foreach ($items as $item) {
            $url = is_array($item) ? self::fromItem($item, $platform) : null;
            if ($url !== null) return $url;
        }
        return null;
    }

    public static function fromItem(array $item, string $platform): ?string
    {
        $url = $platform === 'lazada' ? ($item['product_main_image'] ?? null) : ($item['image_info']['image_url'] ?? null);
        if (!is_string($url) || strlen($url) > 2048) return null;
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || !isset($parts['host'], $parts['path'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) return null;
        $host = strtolower($parts['host']);
        $allowed = $platform === 'lazada'
            ? ($host === 'slatic.net' || str_ends_with($host, '.slatic.net'))
            : in_array($host, ['shopee.com.my', 'shopee.sg'], true)
                || str_ends_with($host, '.shopee.com.my') || str_ends_with($host, '.shopee.sg');
        if ($allowed) return $url;
        return null;
    }
}
