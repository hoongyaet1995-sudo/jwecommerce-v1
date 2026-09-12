<?php

namespace App\Services\Marketplace;

use App\Domain\Cms\MediaUrlPolicy;

final class MarketplaceProductDescription
{
    public function render(array $observation): ?string
    {
        if (array_key_exists('sourceDescriptionHtml',$observation) && $observation['sourceDescriptionHtml'] !== null) {
            abort_unless(is_string($observation['sourceDescriptionHtml']),422,'Invalid channel HTML description.');
            return app(MarketplaceHtmlDescription::class)->render($observation['sourceDescriptionHtml']) ?: null;
        }
        $type = $observation['descriptionType'] ?? 'normal';
        abort_unless(in_array($type, ['normal', 'extended'], true), 422, 'Review the channel description format before import.');
        $html = '';
        if ($type === 'normal') {
            $text = $observation['description'] ?? '';
            $html = $this->text($text);
        } else {
            $fields = $observation['descriptionFields'] ?? null;
            abort_unless(is_array($fields) && array_is_list($fields), 422, 'The channel extended description is incomplete.');
            foreach ($fields as $field) {
                abort_unless(is_array($field), 422, 'Review the channel description block.');
                if (($field['field_type'] ?? null) === 'text') {
                    $html .= $this->text($field['text'] ?? null);
                } elseif (($field['field_type'] ?? null) === 'image') {
                    $url = data_get($field, 'image_info.image_url');
                    abort_unless(is_string($url) && $url !== '' && strlen($url) <= 2048 && MediaUrlPolicy::isSafe($url),
                        422, 'Review the channel description image before import.');
                    $html .= '<img src="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" alt="">';
                } else {
                    abort(422, 'Unsupported channel description block; review before import.');
                }
                $this->checkLength($html);
            }
        }
        $this->checkLength($html);
        return $html === '' ? null : $html;
    }

    private function text(mixed $text): string
    {
        abort_unless(is_string($text), 422, 'Review the channel description text before import.');
        $this->checkLength($text);
        return $text === '' ? '' : '<p>'.nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
    }

    private function checkLength(string $value): void
    {
        // Byte bound is safe for the existing MySQL TEXT column, including escaped markup.
        abort_if(strlen($value) > 50000, 422, 'Channel description exceeds the supported import size; review before import.');
    }
}
