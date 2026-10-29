<?php
namespace App\Services\Marketing\Email;
use DOMDocument; use DOMNode; use DOMElement; use InvalidArgumentException;

final class TemplateStarterPreviewService
{
    private $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function render(array $document)
    {
        if (!isset($document['root'])
            || !is_array($document['root'])
            || !isset($document['root']['type'])
            || $document['root']['type'] !== 'EmailLayout') {
            return '';
        }

        $rootData = isset($document['root']['data']) && is_array($document['root']['data'])
            ? $document['root']['data']
            : array();
        $backdropColor = $this->colorValue(isset($rootData['backdropColor']) ? $rootData['backdropColor'] : '', '#F4F6F5');
        $canvasColor = $this->colorValue(isset($rootData['canvasColor']) ? $rootData['canvasColor'] : '', '#FFFFFF');
        $textColor = $this->colorValue(isset($rootData['textColor']) ? $rootData['textColor'] : '', '#262626');
        $fontFamily = $this->fontFamily(isset($rootData['fontFamily']) ? $rootData['fontFamily'] : 'MODERN_SANS');
        $borderRadius = isset($rootData['borderRadius']) ? max(0, min(40, (int) $rootData['borderRadius'])) : 0;
        $childrenIds = isset($rootData['childrenIds']) && is_array($rootData['childrenIds'])
            ? $rootData['childrenIds']
            : array();
        $content = $this->renderChildren($childrenIds, $document, array());

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<style>html,body{margin:0;min-height:100%;}body{box-sizing:border-box;padding:32px 14px;}'
            . 'table{border-collapse:collapse;}img{display:block;max-width:100%;height:auto;}a{text-decoration:none;}'
            . '.starter-image-placeholder{display:flex;align-items:center;justify-content:center;min-height:210px;border:1px dashed #c8d3cd;border-radius:8px;background:#f3f6f4;color:#6c7e75;font:600 14px Arial,sans-serif;}'
            . '</style></head><body style="background:' . $backdropColor . ';color:' . $textColor . ';font-family:' . $fontFamily . ';">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">'
            . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:'
            . $canvasColor . ';border-radius:' . $borderRadius . 'px;overflow:hidden;"><tr><td>'
            . $content
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private function renderChildren(array $childrenIds, array $document, array $ancestors)
    {
        $content = '';

        foreach ($childrenIds as $childId) {
            $childId = (string) $childId;

            if ($childId === '' || in_array($childId, $ancestors, true)) {
                continue;
            }

            $content .= $this->renderBlock($childId, $document, array_merge($ancestors, array($childId)));
        }

        return $content;
    }

    private function renderBlock($blockId, array $document, array $ancestors)
    {
        if (!isset($document[$blockId]) || !is_array($document[$blockId])) {
            return '';
        }

        $block = $document[$blockId];
        $type = isset($block['type']) ? (string) $block['type'] : '';
        $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : array();
        $props = isset($data['props']) && is_array($data['props']) ? $data['props'] : array();
        $style = isset($data['style']) && is_array($data['style']) ? $data['style'] : array();
        $styleAttribute = $this->styleAttribute($style);

        if ($type === 'Heading') {
            $level = isset($props['level']) && in_array($props['level'], array('h1', 'h2', 'h3'), true)
                ? $props['level']
                : 'h2';
            $defaultSize = $level === 'h1' ? 34 : ($level === 'h2' ? 27 : 21);

            return '<' . $level . ' style="margin:0;font-size:' . $defaultSize . 'px;line-height:1.25;' . $styleAttribute . '">'
                . $this->textValue(isset($props['text']) ? $props['text'] : '')
                . '</' . $level . '>';
        }

        if ($type === 'Text') {
            return '<div style="font-size:16px;line-height:1.6;' . $styleAttribute . '">'
                . nl2br($this->textValue(isset($props['text']) ? $props['text'] : ''))
                . '</div>';
        }

        if ($type === 'Button') {
            $buttonColor = $this->colorValue(isset($props['buttonBackgroundColor']) ? $props['buttonBackgroundColor'] : '', '#315F4B');
            $buttonTextColor = $this->colorValue(isset($props['buttonTextColor']) ? $props['buttonTextColor'] : '', '#FFFFFF');
            $buttonRadius = isset($props['buttonStyle']) && $props['buttonStyle'] === 'rounded' ? 8 : 0;
            $buttonUrl = $this->urlValue(isset($props['url']) ? $props['url'] : '');

            return '<div style="' . $styleAttribute . '"><a href="' . $this->escape($buttonUrl) . '" style="display:inline-block;border-radius:'
                . $buttonRadius . 'px;background:' . $buttonColor . ';padding:12px 22px;color:' . $buttonTextColor
                . ';font-weight:700;line-height:1.35;">'
                . $this->textValue(isset($props['text']) ? $props['text'] : 'Learn more')
                . '</a></div>';
        }

        if ($type === 'Image') {
            $imageUrl = $this->urlValue(isset($props['url']) ? $props['url'] : '', true);
            $altText = $this->textValue(isset($props['alt']) ? $props['alt'] : 'Email image');
            $image = $imageUrl === ''
                ? '<div class="starter-image-placeholder">Add your image in the editor</div>'
                : '<img src="' . $this->escape($imageUrl) . '" alt="' . $altText . '" width="552">';

            return '<div style="' . $styleAttribute . '">' . $image . '</div>';
        }

        if ($type === 'Divider') {
            $lineColor = $this->colorValue(isset($props['lineColor']) ? $props['lineColor'] : '', '#E3E9E6');
            $lineHeight = isset($props['lineHeight']) ? max(1, min(8, (int) $props['lineHeight'])) : 1;

            return '<div style="' . $styleAttribute . '"><div style="height:' . $lineHeight . 'px;background:' . $lineColor . ';"></div></div>';
        }

        if ($type === 'Spacer') {
            $height = isset($props['height']) ? max(4, min(160, (int) $props['height'])) : 24;

            return '<div style="height:' . $height . 'px;"></div>';
        }

        if ($type === 'Html') {
            $contents = isset($props['contents']) ? (string) $props['contents'] : '';

            return '<div style="' . $styleAttribute . '">' . $this->templateService->sanitizeHtml($contents) . '</div>';
        }

        if ($type === 'Container') {
            $childIds = isset($props['childrenIds']) && is_array($props['childrenIds']) ? $props['childrenIds'] : array();

            return '<div style="' . $styleAttribute . '">' . $this->renderChildren($childIds, $document, $ancestors) . '</div>';
        }

        if ($type === 'ColumnsContainer') {
            $columns = isset($props['columns']) && is_array($props['columns']) ? $props['columns'] : array();
            $columnsCount = isset($props['columnsCount']) ? max(1, min(3, (int) $props['columnsCount'])) : count($columns);
            $columnsCount = max(1, min(3, $columnsCount));
            $columnHtml = '';

            for ($columnIndex = 0; $columnIndex < $columnsCount; $columnIndex++) {
                $childIds = isset($columns[$columnIndex]['childrenIds']) && is_array($columns[$columnIndex]['childrenIds'])
                    ? $columns[$columnIndex]['childrenIds']
                    : array();
                $columnHtml .= '<td width="' . floor(100 / $columnsCount) . '%" valign="top" style="padding:0 6px;">'
                    . $this->renderChildren($childIds, $document, $ancestors)
                    . '</td>';
            }

            return '<div style="' . $styleAttribute . '"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>'
                . $columnHtml . '</tr></table></div>';
        }

        return '';
    }

    private function styleAttribute(array $style)
    {
        $parts = array();

        if (isset($style['color'])) {
            $parts[] = 'color:' . $this->colorValue($style['color'], 'inherit');
        }

        if (isset($style['backgroundColor'])) {
            $parts[] = 'background-color:' . $this->colorValue($style['backgroundColor'], 'transparent');
        }

        if (isset($style['fontSize'])) {
            $parts[] = 'font-size:' . max(8, min(72, (int) $style['fontSize'])) . 'px';
        }

        if (isset($style['fontWeight']) && in_array($style['fontWeight'], array('normal', 'bold', '400', '500', '600', '700', '800'), true)) {
            $parts[] = 'font-weight:' . $style['fontWeight'];
        }

        if (isset($style['textAlign']) && in_array($style['textAlign'], array('left', 'center', 'right'), true)) {
            $parts[] = 'text-align:' . $style['textAlign'];
        }

        if (isset($style['padding']) && is_array($style['padding'])) {
            $padding = $style['padding'];
            $parts[] = 'padding:'
                . $this->spacingValue(isset($padding['top']) ? $padding['top'] : 0) . 'px '
                . $this->spacingValue(isset($padding['right']) ? $padding['right'] : 0) . 'px '
                . $this->spacingValue(isset($padding['bottom']) ? $padding['bottom'] : 0) . 'px '
                . $this->spacingValue(isset($padding['left']) ? $padding['left'] : 0) . 'px';
        }

        return implode(';', $parts) . ($parts === array() ? '' : ';');
    }

    private function textValue($value)
    {
        $value = str_replace(
            array('{{first_name}}', '{{last_name}}', '{{email}}', '{{store_name}}', '{{unsubscribe_url}}'),
            array('Alex', 'Tan', 'alex@example.com', 'Your Store', '#'),
            (string) $value
        );

        return $this->escape($value);
    }

    private function colorValue($value, $fallback)
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) ? strtoupper((string) $value) : $fallback;
    }

    private function fontFamily($value)
    {
        $families = array(
            'MODERN_SANS' => 'Arial, Helvetica, sans-serif',
            'BOOK_SANS' => 'Verdana, Arial, sans-serif',
            'ORGANIC_SANS' => 'Trebuchet MS, Arial, sans-serif',
            'GEOMETRIC_SANS' => 'Century Gothic, Arial, sans-serif',
            'HEAVY_SANS' => 'Arial Black, Arial, sans-serif',
            'ROUNDED_SANS' => 'Arial Rounded MT Bold, Arial, sans-serif',
            'MODERN_SERIF' => 'Georgia, Times New Roman, serif',
            'BOOK_SERIF' => 'Palatino Linotype, Georgia, serif',
            'MONOSPACE' => 'Courier New, monospace',
        );

        return isset($families[$value]) ? $families[$value] : $families['MODERN_SANS'];
    }

    private function urlValue($value, $imageOnly = false)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $allowedSchemes = $imageOnly ? array('http', 'https') : array('http', 'https', 'mailto', 'tel');

        return in_array($scheme, $allowedSchemes, true) ? $value : '#';
    }

    private function spacingValue($value)
    {
        return max(0, min(100, (int) $value));
    }

    private function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
