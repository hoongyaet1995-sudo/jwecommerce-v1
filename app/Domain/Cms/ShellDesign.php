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
    /** Same contextual surface foreground as the existing JS onColor resolver. */
    public static function footerForeground(string $background): string
    {
        if (!preg_match('/^#[a-f0-9]{6}$/i', $background)) return '#ffffff';
        $channels = array_map(fn ($channel) => hexdec($channel) / 255, str_split(substr($background, 1), 2));
        $linear = array_map(fn ($value) => $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4, $channels);
        $luminance = $linear[0] * .2126 + $linear[1] * .7152 + $linear[2] * .0722;
        $dark = ((21 / 255 + .055) / 1.055) ** 2.4;
        $whiteRatio = 1.05 / ($luminance + .05);
        $darkRatio = (max($luminance, $dark) + .05) / (min($luminance, $dark) + .05);
        return $whiteRatio >= $darkRatio ? '#ffffff' : '#151515';
    }

    public static function footerColor(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A#[a-f0-9]{6}\z/i', $value) === 1
            ? strtolower($value)
            : null;
    }

    public static function defaults(string $business = 'commerce'): array
    {
        return ['business' => $business, 'style' => 'editorial', 'appearance' => 'light',
            'showAnnouncement' => false, 'showAccount' => false, 'showCurrency' => false,
            'showThemeSwitch' => false, 'brandName' => '', 'brandLogo' => '', 'ctaLabel' => '', 'ctaUrl' => '', 'tagline' => '', 'contactEmail' => '', 'footerLayout' => 'classic'];
    }

    public static function resolve(array $global, ?array $page = null): ?array
    {
        $value = $global['shellDesign'] ?? $page;
        if (! is_array($value)) return null;
        $resolved=array_replace(self::defaults($value['business'] ?? 'commerce'),$value);
        foreach (['brandName','brandLogo','ctaLabel','ctaUrl','tagline','contactEmail'] as $field) $resolved[$field]=(string)($resolved[$field]??'');
        foreach (['footerBackground', 'footerText'] as $field) {
            if (array_key_exists($field, $resolved)) {
                $resolved[$field] = self::footerColor($resolved[$field]);
            }
        }
        return $resolved;
    }

    public static function rules(string $prefix = 'shellDesign'): array
    {
        return [$prefix => ['sometimes', 'array:brandName,brandLogo,business,style,appearance,showAnnouncement,showAccount,showCurrency,showThemeSwitch,ctaLabel,ctaUrl,tagline,contactEmail,footerLayout,footerBackground,footerText'],
            "$prefix.brandName" => ['nullable','string','max:100'],
            "$prefix.brandLogo" => ['nullable','string','max:2048'],
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
            "$prefix.footerBackground" => ['nullable', 'string', 'regex:/\A#[a-f0-9]{6}\z/i'],
            "$prefix.footerText" => ['nullable', 'string', 'regex:/\A#[a-f0-9]{6}\z/i'],
            "$prefix.footerLayout" => ['sometimes', 'in:classic,compact'],
            "$prefix.contactEmail" => ['nullable', 'email', 'max:255']];
    }
}
