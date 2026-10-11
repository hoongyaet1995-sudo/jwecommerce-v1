<?php

namespace App\Domain\Cms;

final class ShellDesign
{
    public static function safeAction(string $url): bool
    {
        return $url === '' || preg_match('/^#[a-zA-Z0-9_-]+$/', $url) === 1
            || (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! preg_match('/[\\\\\x00-\x20]/', $url))
            || (str_starts_with($url, 'https://') && MediaUrlPolicy::isSafe($url));
    }
    public static function defaults(string $business = 'commerce'): array
    {
        return ['business' => $business, 'style' => 'editorial', 'appearance' => 'light',
            'showAnnouncement' => false, 'showAccount' => false, 'showCurrency' => false,
            'showThemeSwitch' => false, 'ctaLabel' => '', 'ctaUrl' => '', 'tagline' => '', 'contactEmail' => ''];
    }

    public static function resolve(array $global, ?array $page = null): ?array
    {
        $value = $page ?? ($global['shellDesign'] ?? null);
        if (! is_array($value)) return null;
        $resolved=array_replace(self::defaults($value['business'] ?? 'commerce'),$value);
        foreach (['ctaLabel','ctaUrl','tagline','contactEmail'] as $field) $resolved[$field]=(string)($resolved[$field]??'');
        return $resolved;
    }

    public static function rules(string $prefix = 'shellDesign'): array
    {
        return [$prefix => ['sometimes', 'array:business,style,appearance,showAnnouncement,showAccount,showCurrency,showThemeSwitch,ctaLabel,ctaUrl,tagline,contactEmail'],
            "$prefix.business" => ['required_with:'.$prefix, 'in:commerce,service'],
            "$prefix.style" => ['required_with:'.$prefix, 'in:editorial,soft,modern'],
            "$prefix.appearance" => ['required_with:'.$prefix, 'in:light,dark'],
            "$prefix.showAnnouncement" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.showAccount" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.showCurrency" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.showThemeSwitch" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.ctaLabel" => ['nullable', 'string', 'max:80'],
            "$prefix.ctaUrl" => ['nullable', 'string', 'max:2048'],
            "$prefix.tagline" => ['nullable', 'string', 'max:255'],
            "$prefix.contactEmail" => ['nullable', 'email', 'max:255']];
    }
}
