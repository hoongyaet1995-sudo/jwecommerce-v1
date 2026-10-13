<?php

namespace App\Services;

use App\Domain\Cms\MediaUrlPolicy;
use App\Models\StorefrontShellSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StorefrontShellSettingsService
{
    private const GLOBAL_KEY = 'global';

    /**
     * @return array<string, mixed>
     */
    public function live(): array
    {
        $payload = StorefrontShellSetting::query()
            ->where('key', self::GLOBAL_KEY)
            ->value('payload');

        return $this->normalize(is_array($payload) ? $payload : []);
    }

    public function save(array $input): StorefrontShellSetting
    {
        $validator = validator($input, [
            ...\App\Domain\Cms\ShellDesign::rules(),
            'contentWidth' => ['sometimes', Rule::in(['standard','wide','full'])],
            'enabled' => ['required', 'boolean'],
            'overlayLogos' => ['sometimes','array:light,dark'],
            'overlayLogos.light' => ['nullable','string','max:2048'],
            'overlayLogos.dark' => ['nullable','string','max:2048'],
            'headerPresentation' => ['sometimes', 'array:mode,tone'],
            'headerPresentation.mode' => ['required_with:headerPresentation', Rule::in(['normal','transparent','translucent'])],
            'headerPresentation.tone' => ['required_with:headerPresentation', Rule::in(['dark','light'])],
            'layout' => ['required', Rule::in(['atelier'])],
            'theme' => ['required', 'string', 'max:100'],
            'customPalette' => ['nullable', 'array'],
            'customPalette.background' => ['nullable', 'string', 'regex:/\A#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\z/'],
            'customPalette.text' => ['nullable', 'string', 'regex:/\A#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\z/'],
            'customPalette.accent' => ['nullable', 'string', 'regex:/\A#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\z/'],
            'zones' => ['required', 'array'],
            'zones.left' => ['required', 'array'],
            'zones.left.enabled' => ['required', 'boolean'],
            'zones.left.text' => ['nullable', 'string', 'max:255', 'required_if:zones.left.enabled,true'],
            'zones.left.badge' => ['nullable', 'string', 'max:80'],
            'zones.left.linkUrl' => ['nullable', 'string', 'max:2048'],
            'zones.center' => ['required', 'array'],
            'zones.center.enabled' => ['required', 'boolean'],
            'zones.center.text' => ['nullable', 'string', 'max:255', 'required_if:zones.center.enabled,true'],
            'zones.center.couponCode' => ['nullable', 'string', 'max:80'],
            'zones.center.linkUrl' => ['nullable', 'string', 'max:2048'],
            'zones.right' => ['required', 'array'],
            'zones.right.enabled' => ['required', 'boolean'],
            'zones.right.helperText' => ['nullable', 'string', 'max:255'],
            'zones.right.trackingEnabled' => ['required', 'boolean'],
            'zones.right.trackingLabel' => ['nullable', 'string', 'max:80'],
            'zones.right.trackingUrl' => ['nullable', 'string', 'max:2048', 'required_if:zones.right.trackingEnabled,true'],
            'zones.right.whatsappEnabled' => ['required', 'boolean'],
            'zones.right.whatsappLabel' => ['nullable', 'string', 'max:80'],
            'zones.right.whatsappUrl' => ['nullable', 'string', 'max:2048', 'required_if:zones.right.whatsappEnabled,true'],
        ]);

        $validator->after(function ($validator) use ($input): void {
            if (! \App\Domain\Cms\ShellDesign::safeAction((string) Arr::get($input, 'shellDesign.ctaUrl', ''))) $validator->errors()->add('shellDesign.ctaUrl', 'Use a same-site, anchor or public HTTPS destination.');
            foreach (['light','dark'] as $tone) {
                $logo=Arr::get($input,'overlayLogos.'.$tone);
                if (filled($logo) && ! MediaUrlPolicy::isSafe($logo)) $validator->errors()->add('overlayLogos.'.$tone,'Use a safe image URL.');
            }
            $this->validateLink(Arr::get($input, 'zones.left.linkUrl'), 'zones.left.linkUrl', $validator);
            $this->validateLink(Arr::get($input, 'zones.center.linkUrl'), 'zones.center.linkUrl', $validator);
            $this->validateLink(Arr::get($input, 'zones.right.trackingUrl'), 'zones.right.trackingUrl', $validator);
            $this->validateLink(Arr::get($input, 'zones.right.whatsappUrl'), 'zones.right.whatsappUrl', $validator, whatsappOnly: true);
        });

        $validator->validate();

        return DB::transaction(function () use ($input): StorefrontShellSetting {
            StorefrontShellSetting::query()
                ->where('key', '!=', self::GLOBAL_KEY)
                ->delete();

            $setting = StorefrontShellSetting::query()
                ->where('key', self::GLOBAL_KEY)
                ->first() ?? new StorefrontShellSetting();
            $setting->key = self::GLOBAL_KEY;
            $setting->payload = $this->normalize($input);
            $setting->save();

            return $setting->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        return [
            ...(isset($input['shellDesign']) ? ['shellDesign' => \App\Domain\Cms\ShellDesign::resolve($input)] : []),
            ...(isset($input['contentWidth']) ? ['contentWidth' => in_array($input['contentWidth'], ['standard','wide','full'], true) ? $input['contentWidth'] : 'wide'] : []),
            'enabled' => (bool) ($input['enabled'] ?? false),
            ...(isset($input['overlayLogos']) ? ['overlayLogos'=>['light'=>$this->nullableString(Arr::get($input,'overlayLogos.light')),'dark'=>$this->nullableString(Arr::get($input,'overlayLogos.dark'))]] : []),
            ...(isset($input['headerPresentation']) ? ['headerPresentation' => ['mode' => Arr::get($input,'headerPresentation.mode','normal'), 'tone' => Arr::get($input,'headerPresentation.tone','dark')]] : []),
            'layout' => 'atelier',
            'theme' => $this->string($input['theme'] ?? 'midnight-obsidian'),
            'customPalette' => [
                'background' => $this->nullableString(Arr::get($input, 'customPalette.background')),
                'text' => $this->nullableString(Arr::get($input, 'customPalette.text')),
                'accent' => $this->nullableString(Arr::get($input, 'customPalette.accent')),
            ],
            'zones' => [
                'left' => [
                    'enabled' => (bool) Arr::get($input, 'zones.left.enabled', false),
                    'text' => $this->string(Arr::get($input, 'zones.left.text')),
                    'badge' => $this->string(Arr::get($input, 'zones.left.badge')),
                    'linkUrl' => $this->nullableString(Arr::get($input, 'zones.left.linkUrl')),
                ],
                'center' => [
                    'enabled' => (bool) Arr::get($input, 'zones.center.enabled', false),
                    'text' => $this->string(Arr::get($input, 'zones.center.text')),
                    'couponCode' => $this->string(Arr::get($input, 'zones.center.couponCode')),
                    'linkUrl' => $this->nullableString(Arr::get($input, 'zones.center.linkUrl')),
                ],
                'right' => [
                    'enabled' => (bool) Arr::get($input, 'zones.right.enabled', false),
                    'helperText' => $this->string(Arr::get($input, 'zones.right.helperText')),
                    'trackingEnabled' => (bool) Arr::get($input, 'zones.right.trackingEnabled', false),
                    'trackingLabel' => $this->string(Arr::get($input, 'zones.right.trackingLabel')),
                    'trackingUrl' => $this->nullableString(Arr::get($input, 'zones.right.trackingUrl')),
                    'whatsappEnabled' => (bool) Arr::get($input, 'zones.right.whatsappEnabled', false),
                    'whatsappLabel' => $this->string(Arr::get($input, 'zones.right.whatsappLabel')),
                    'whatsappUrl' => $this->nullableString(Arr::get($input, 'zones.right.whatsappUrl')),
                ],
            ],
        ];
    }

    private function validateLink(mixed $value, string $field, $validator, bool $whatsappOnly = false): void
    {
        $link = $this->nullableString($value);

        if ($link === null) {
            return;
        }

        if ($whatsappOnly) {
            if (! $this->isAllowedWhatsappUrl($link)) {
                $validator->errors()->add($field, 'The '.$field.' field must be a safe WhatsApp URL.');
            }

            return;
        }

        if (! $this->isAllowedLink($link)) {
            $validator->errors()->add($field, 'The '.$field.' field must be a safe same-site, https, tel, or WhatsApp URL.');
        }
    }

    private function isAllowedLink(string $link): bool
    {
        return $this->isAllowedPath($link)
            || $this->isAllowedTel($link)
            || MediaUrlPolicy::isSafe($link)
            || $this->isAllowedWhatsappUrl($link);
    }

    private function isAllowedPath(string $link): bool
    {
        if (! str_starts_with($link, '/')
            || str_starts_with($link, '//')
            || preg_match('/[\x00-\x20\x7f\\\\]/', $link) === 1
            || $this->containsEncodedControlOrBackslash($link)) {
            return false;
        }

        return true;
    }

    private function isAllowedTel(string $link): bool
    {
        return preg_match('/\Atel:\+?[0-9(). \-]{5,32}\z/', $link) === 1;
    }

    private function isAllowedWhatsappUrl(string $link): bool
    {
        if (! MediaUrlPolicy::isSafe($link)) {
            return false;
        }

        $parts = parse_url($link);

        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($host, ['wa.me', 'api.whatsapp.com', 'www.whatsapp.com'], true)) {
            return false;
        }

        return true;
    }

    private function containsEncodedControlOrBackslash(string $value): bool
    {
        $candidate = $value;

        for ($depth = 0; $depth < 5; $depth++) {
            if (preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|5c|7f)/i', $candidate) === 1) {
                return true;
            }

            $decoded = rawurldecode($candidate);

            if ($decoded === $candidate) {
                return false;
            }

            if (preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded) === 1) {
                return true;
            }

            $candidate = $decoded;
        }

        return preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|5c|7f)/i', $candidate) === 1;
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
