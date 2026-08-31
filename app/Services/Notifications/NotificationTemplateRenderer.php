<?php

namespace App\Services\Notifications;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Domain\Notifications\RenderedNotification;
use App\Models\NotificationTemplate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationTemplateRenderer
{
    /** @param array<string, mixed> $variables */
    public function render(NotificationTemplate $template, array $variables): RenderedNotification
    {
        $allowedTokens = NotificationEventCatalog::variablesFor($template->event_key);
        $fields = [
            'email_subject' => (string) $template->email_subject,
            'email_preheader' => (string) $template->email_preheader,
            'email_heading' => (string) $template->email_heading,
            'email_body' => (string) $template->email_body,
            'email_cta_text' => (string) $template->email_cta_text,
            'email_cta_url' => (string) $template->email_cta_url,
        ];

        $this->validateTokens($fields, $allowedTokens, $variables);

        $escapedVariables = [];
        foreach ($variables as $name => $value) {
            $escapedVariables[$name] = e((string) $value);
        }

        $renderedFields = [
            'email_subject' => $this->renderEscapedText($fields['email_subject'], $variables),
            'email_preheader' => $this->renderEscapedText($fields['email_preheader'], $variables),
            'email_heading' => $this->renderEscapedText($fields['email_heading'], $variables),
            'email_body' => $this->renderEscapedText($fields['email_body'], $variables),
            'email_cta_text' => $this->renderEscapedText($fields['email_cta_text'], $variables),
            'email_cta_url' => e($this->validatedCtaUrl($this->replaceRawTokens($fields['email_cta_url'], $variables))),
        ];
        $this->validateNoPrivateProviderUrls($renderedFields);

        return new RenderedNotification(
            subject: $renderedFields['email_subject'],
            preheader: $renderedFields['email_preheader'],
            heading: $renderedFields['email_heading'],
            body: $renderedFields['email_body'],
            ctaText: $renderedFields['email_cta_text'],
            ctaUrl: $renderedFields['email_cta_url'],
            variables: $escapedVariables,
        );
    }

    /**
     * @param  array<string, string>  $fields
     * @param  list<string>  $allowedTokens
     * @param  array<string, mixed>  $variables
     */
    private function validateTokens(array $fields, array $allowedTokens, array $variables): void
    {
        $errors = [];

        foreach ($fields as $field => $value) {
            preg_match_all('/\{[a-z0-9_]+\}/', $value, $matches);

            foreach (array_unique($matches[0]) as $token) {
                $name = trim($token, '{}');

                if (! in_array($token, $allowedTokens, true)) {
                    $errors[$field][] = "The {$token} variable is not available for this notification event.";
                } elseif (! array_key_exists($name, $variables) || $variables[$name] === null || trim((string) $variables[$name]) === '') {
                    $errors[$field][] = "The {$token} variable must be resolved before rendering.";
                } elseif (in_array($name, ['tracking_url', 'order_url'], true) && $variables[$name] !== url('/order-tracking')) {
                    $errors[$field][] = "The {$token} variable must resolve to the JW order-tracking URL.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $variables */
    private function renderEscapedText(string $value, array $variables): string
    {
        preg_match_all('/\{([a-z0-9_]+)\}/', $value, $matches, PREG_OFFSET_CAPTURE);

        $rendered = '';
        $offset = 0;
        foreach ($matches[0] as $index => [$token, $position]) {
            $rendered .= e(substr($value, $offset, $position - $offset));
            $rendered .= e((string) $variables[$matches[1][$index][0]]);
            $offset = $position + strlen($token);
        }

        return $rendered.e(substr($value, $offset));
    }

    /** @param array<string, mixed> $variables */
    private function replaceRawTokens(string $value, array $variables): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/', static fn (array $matches): string => (string) $variables[$matches[1]], $value) ?? $value;
    }

    private function validatedCtaUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if ($url === url('/order-tracking') || $this->isApplicationRelativePath($url) || $this->isSafeHttpsUrl($url) || $this->isAllowedMailtoUrl($url)) {
            return $url;
        }

        throw ValidationException::withMessages([
            'email_cta_url' => 'The email CTA URL must be an application path, a safe HTTPS URL, or an allowlisted mailto address.',
        ]);
    }

    /** @param array<string, string> $fields */
    private function validateNoPrivateProviderUrls(array $fields): void
    {
        $errors = [];

        foreach ($fields as $field => $value) {
            $normalized = $this->normalizeForProviderDetection($value);

            if (preg_match('~(?<![a-z0-9-])(?:[a-z0-9-]+\.)*easyparcel\.com(?=[/:?#\s<>"\']|$)~i', $normalized) === 1) {
                $errors[$field][] = 'Customer notifications cannot contain EasyParcel provider URLs.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeForProviderDetection(string $value): string
    {
        $normalized = $value;

        for ($pass = 0; $pass < 5; $pass++) {
            $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = rawurldecode($decoded);

            if ($decoded === $normalized) {
                break;
            }

            $normalized = $decoded;
        }

        $normalized = strtolower(str_replace('\\', '/', $normalized));

        return preg_replace('~/+~', '/', $normalized) ?? $normalized;
    }

    private function isApplicationRelativePath(string $url): bool
    {
        return Str::startsWith($url, '/')
            && ! Str::startsWith($url, '//')
            && ! preg_match('/[\x00-\x20\x7F<>"\'\\\\]/', $url);
    }

    private function isSafeHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isAllowedMailtoUrl(string $url): bool
    {
        if (! Str::startsWith($url, 'mailto:')) {
            return false;
        }

        $address = substr($url, strlen('mailto:'));
        $allowedAddresses = array_filter([
            config('mail.from.address'),
            ...config('notifications.allowed_mailto_addresses', []),
        ], 'is_string');

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            && in_array(Str::lower($address), array_map(Str::lower(...), $allowedAddresses), true);
    }
}
