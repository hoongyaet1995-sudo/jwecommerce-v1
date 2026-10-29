<?php
namespace App\Services\Marketing\Email;
use stdClass; use DOMDocument; use DOMNode; use DOMElement; use InvalidArgumentException;



final class TemplateService
{
    private $maximumHtmlLength = 500000;
    private $maximumEditorJsonLength = 2000000;
    private $smsMessageService;

    public function __construct(SmsMessageService $smsMessageService = null)
    {
        $this->smsMessageService = $smsMessageService === null ? new SmsMessageService() : $smsMessageService;
    }

    public function validate(array $input)
    {
        $rawHtml = isset($input['content_html']) && !is_array($input['content_html'])
            ? trim((string) $input['content_html'])
            : '';
        $rawEditorJson = isset($input['editor_json']) && !is_array($input['editor_json'])
            ? trim((string) $input['editor_json'])
            : '';
        $data = array(
            'name' => $this->cleanValue($input, 'template_name', 150),
            'channel' => isset($input['channel'])
                && in_array($input['channel'], array('email', 'sms', 'whatsapp', 'push'), true)
                ? $input['channel']
                : 'email',
            'subject' => $this->cleanValue($input, 'subject', 255),
            'preheader' => $this->cleanValue($input, 'preheader', 255),
            'status' => isset($input['status']) && in_array($input['status'], array('active', 'draft', 'archived'), true)
                ? $input['status']
                : 'draft',
            'content_html' => '',
            'content_text' => '',
            'editor_json' => '',
            'editor_type' => 'email_builder_js',
            'editor_version' => '0.0.9',
        );
        $errors = array();

        if ($data['name'] === '') {
            $errors[] = 'Enter a template name.';
        }

        if ($data['channel'] === 'email' && $data['subject'] === '') {
            $errors[] = 'Enter an email subject.';
        }

        if (strlen($rawEditorJson) > $this->maximumEditorJsonLength) {
            $errors[] = 'The editable email design is too large.';
        } else {
            $normalizedEditorJson = $this->normalizeEditorJson($rawEditorJson);

            if ($normalizedEditorJson === null) {
                $errors[] = 'The email editor data is invalid. Refresh the page and try again.';
            } else {
                $data['editor_json'] = $normalizedEditorJson;
            }
        }

        if (strlen($rawHtml) > $this->maximumHtmlLength) {
            $errors[] = 'The email design is too large.';
        } else {
            $data['content_html'] = $this->sanitizeHtml($rawHtml);
            $data['content_text'] = $this->createPlainText($data['content_html']);

            if ($data['content_text'] === '' && stripos($data['content_html'], '<img') === false) {
                $errors[] = 'Add some content to the email design.';
            }
        }

        return array('errors' => $errors, 'data' => $data);
    }

    public function validatePush(array $input)
    {
        $platform = isset($input['platform']) && !is_array($input['platform'])
            ? strtolower(trim((string) $input['platform']))
            : 'all';
        $status = isset($input['status']) && !is_array($input['status'])
            ? strtolower(trim((string) $input['status']))
            : 'draft';
        $title = $this->cleanPlainText($input, 'notification_title', 100, false);
        $message = $this->cleanPlainText($input, 'notification_message', 500, true);
        $imageUrl = $this->cleanValue($input, 'image_url', 1000);
        $clickDestination = $this->cleanValue($input, 'click_destination', 1000);
        $errors = array();

        if (!in_array($platform, array('all', 'web', 'ios', 'android'), true)) {
            $platform = 'all';
        }

        if (!in_array($status, array('active', 'draft', 'archived'), true)) {
            $status = 'draft';
        }

        $data = array(
            'name' => $this->cleanValue($input, 'template_name', 150),
            'channel' => 'push',
            'subject' => $title,
            'preheader' => '',
            'content_html' => '',
            'content_text' => $message,
            'editor_json' => '',
            'editor_type' => 'push_notification',
            'editor_version' => '1.0',
            'status' => $status,
            'platform' => $platform,
            'image_url' => $imageUrl,
            'click_destination' => $clickDestination,
        );

        if ($data['name'] === '') {
            $errors[] = 'Enter a template name.';
        }

        if ($title === '') {
            $errors[] = 'Enter a notification title.';
        }

        if ($message === '') {
            $errors[] = 'Enter a notification message.';
        }

        if ($imageUrl !== '' && !$this->isPushImageUrl($imageUrl)) {
            $errors[] = 'Enter a valid HTTPS image URL.';
        }

        if ($clickDestination !== '' && !$this->isPushDestination($clickDestination, $platform)) {
            $errors[] = $platform === 'ios' || $platform === 'android'
                ? 'Enter a valid HTTPS URL or mobile app deep link for the click destination.'
                : 'Enter a valid HTTP or HTTPS click destination.';
        }

        $configuration = json_encode(array(
            'platform' => $platform,
            'image_url' => $imageUrl,
            'click_destination' => $clickDestination,
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($configuration === false) {
            $errors[] = 'The push notification settings could not be prepared.';
        } else {
            $data['editor_json'] = $configuration;
        }

        return array('errors' => $errors, 'data' => $data);
    }

    public function getPushFormData(array $template)
    {
        $configuration = isset($template['editor_json'])
            ? json_decode((string) $template['editor_json'], true)
            : null;

        if (!is_array($configuration)) {
            $configuration = array();
        }

        $platform = isset($configuration['platform']) ? (string) $configuration['platform'] : 'all';

        if (!in_array($platform, array('all', 'web', 'ios', 'android'), true)) {
            $platform = 'all';
        }

        return array(
            'name' => isset($template['name']) ? (string) $template['name'] : '',
            'channel' => 'push',
            'subject' => isset($template['subject']) ? (string) $template['subject'] : '',
            'content_text' => isset($template['content_text']) ? (string) $template['content_text'] : '',
            'status' => isset($template['status'])
                && in_array($template['status'], array('active', 'draft', 'archived'), true)
                    ? (string) $template['status']
                    : 'draft',
            'platform' => $platform,
            'image_url' => isset($configuration['image_url']) ? (string) $configuration['image_url'] : '',
            'click_destination' => isset($configuration['click_destination'])
                ? (string) $configuration['click_destination']
                : '',
        );
    }

    public function validateSms(array $input)
    {
        $status = isset($input['status']) && !is_array($input['status'])
            ? strtolower(trim((string) $input['status']))
            : 'draft';

        if (!in_array($status, array('active', 'draft', 'archived'), true)) {
            $status = 'draft';
        }

        $message = $this->cleanSmsText($input, 'sms_message');
        $errors = array();

        try {
            $analysis = $this->smsMessageService->analyse($message);
        } catch (InvalidArgumentException $exception) {
            $analysis = $this->smsMessageService->analyse('');
            $errors[] = $exception->getMessage();
        }

        $data = array(
            'name' => $this->cleanValue($input, 'template_name', 150),
            'channel' => 'sms',
            'subject' => '',
            'preheader' => '',
            'content_html' => '',
            'content_text' => $message,
            'editor_json' => '',
            'editor_type' => 'sms_text',
            'editor_version' => '1.0',
            'status' => $status,
            'sms_analysis' => $analysis,
        );

        if ($data['name'] === '') {
            $errors[] = 'Enter a template name.';
        }

        if ($message === '') {
            $errors[] = 'Enter an SMS message.';
        } elseif (!$analysis['within_limit']) {
            $errors[] = $analysis['encoding'] === 'unicode'
                ? 'Unicode SMS templates are limited to three segments or 201 encoding units.'
                : 'SMS templates are limited to three credits or 459 characters.';
        }

        $configuration = json_encode(array(
            'encoding' => $analysis['encoding'],
            'estimated_credits' => $analysis['estimated_credits'],
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($configuration === false) {
            $errors[] = 'The SMS template settings could not be prepared.';
        } else {
            $data['editor_json'] = $configuration;
        }

        return array('errors' => array_values(array_unique($errors)), 'data' => $data);
    }

    public function getSmsFormData(array $template)
    {
        $message = isset($template['content_text']) ? trim((string) $template['content_text']) : '';

        try {
            $analysis = $this->smsMessageService->analyse($message);
        } catch (InvalidArgumentException $exception) {
            $message = '';
            $analysis = $this->smsMessageService->analyse('');
        }

        return array(
            'name' => isset($template['name']) ? (string) $template['name'] : '',
            'channel' => 'sms',
            'subject' => '',
            'preheader' => '',
            'content_html' => '',
            'content_text' => $message,
            'editor_json' => isset($template['editor_json']) ? (string) $template['editor_json'] : '',
            'editor_type' => 'sms_text',
            'editor_version' => '1.0',
            'status' => isset($template['status'])
                && in_array($template['status'], array('active', 'draft', 'archived'), true)
                    ? (string) $template['status']
                    : 'draft',
            'sms_analysis' => $analysis,
        );
    }

    public function createInitialEditorJson($savedEditorJson, $savedHtml)
    {
        $normalizedEditorJson = $this->normalizeEditorJson((string) $savedEditorJson);

        if ($normalizedEditorJson !== null) {
            return $normalizedEditorJson;
        }

        $document = array(
            'root' => array(
                'type' => 'EmailLayout',
                'data' => array(
                    'backdropColor' => '#F5F5F5',
                    'backdropPadding' => 16,
                    'canvasColor' => '#FFFFFF',
                    'textColor' => '#262626',
                    'fontFamily' => 'MODERN_SANS',
                    'childrenIds' => array(),
                ),
            ),
        );
        $savedHtml = $this->sanitizeHtml((string) $savedHtml);

        if ($savedHtml !== '') {
            $document['root']['data']['childrenIds'][] = 'imported-content';
            $document['imported-content'] = array(
                'type' => 'Html',
                'data' => array(
                    'props' => array('contents' => $savedHtml),
                    'style' => array(
                        'fontSize' => 16,
                        'textAlign' => null,
                        'padding' => array('top' => 16, 'bottom' => 16, 'left' => 24, 'right' => 24),
                    ),
                ),
            );
        }

        return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function sanitizeHtml($html)
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $html = preg_replace('/^\s*<!doctype[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?(?:html|head|body)\b[^>]*>/i', '', $html);

        if (!class_exists('DOMDocument')) {
            return $this->fallbackSanitize($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="crm-template-root">'
            . $html
            . '</div></body></html>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return $this->fallbackSanitize($html);
        }

        $root = $document->getElementById('crm-template-root');

        if (!$root) {
            return $this->fallbackSanitize($html);
        }

        $this->sanitizeChildren($root);
        $sanitized = '';

        foreach ($root->childNodes as $childNode) {
            $sanitized .= $document->saveHTML($childNode);
        }

        return $this->restoreEncodedTemplateTokens(trim($sanitized));
    }

    private function sanitizeChildren(DOMNode $parent)
    {
        $children = array();

        foreach ($parent->childNodes as $childNode) {
            $children[] = $childNode;
        }

        $allowedTags = $this->allowedTags();
        $removedTags = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'meta', 'link', 'base', 'svg', 'math');

        foreach ($children as $childNode) {
            if ($childNode->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($childNode);
                continue;
            }

            if ($childNode->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tagName = strtolower($childNode->nodeName);

            if (in_array($tagName, $removedTags, true)) {
                $parent->removeChild($childNode);
                continue;
            }

            $this->sanitizeChildren($childNode);

            if (!isset($allowedTags[$tagName])) {
                while ($childNode->firstChild) {
                    $parent->insertBefore($childNode->firstChild, $childNode);
                }

                $parent->removeChild($childNode);
                continue;
            }

            $this->sanitizeAttributes($childNode, $allowedTags[$tagName]);
        }
    }

    private function sanitizeAttributes(DOMElement $element, array $tagAttributes)
    {
        $allowedAttributes = array_merge(array('style', 'title'), $tagAttributes);
        $attributeNames = array();

        foreach ($element->attributes as $attribute) {
            $attributeNames[] = $attribute->nodeName;
        }

        foreach ($attributeNames as $attributeName) {
            $normalizedName = strtolower($attributeName);

            if (!in_array($normalizedName, $allowedAttributes, true)) {
                $element->removeAttribute($attributeName);
                continue;
            }

            $value = trim((string) $element->getAttribute($attributeName));

            if ($normalizedName === 'style') {
                $value = $this->sanitizeStyle($value);
            } elseif ($normalizedName === 'href' || $normalizedName === 'src') {
                $value = $this->sanitizeUrl($value, $normalizedName === 'src');
            } elseif ($normalizedName === 'target' && !in_array($value, array('_blank', '_self'), true)) {
                $value = '';
            }

            if ($value === '') {
                $element->removeAttribute($attributeName);
            } else {
                $element->setAttribute($attributeName, $value);
            }
        }

        if (strtolower($element->nodeName) === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function sanitizeStyle($style)
    {
        if ($style === '' || preg_match('/(?:expression|javascript\s*:|url\s*\(|@import|behavior\s*:|-moz-binding)/i', $style)) {
            return '';
        }

        $allowedProperties = array(
            'background-color', 'border', 'border-bottom', 'border-collapse', 'border-color',
            'border-left', 'border-radius', 'border-right', 'border-style', 'border-top', 'border-width',
            'color', 'display', 'font-family', 'font-size', 'font-style', 'font-weight', 'height',
            'letter-spacing', 'line-height', 'margin', 'margin-bottom', 'margin-left', 'margin-right',
            'margin-top', 'max-width', 'padding', 'padding-bottom', 'padding-left', 'padding-right',
            'padding-top', 'text-align', 'text-decoration', 'vertical-align', 'width',
        );
        $cleanDeclarations = array();

        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (in_array($property, $allowedProperties, true)
                && $value !== ''
                && strpos($value, '<') === false
                && strpos($value, '>') === false) {
                $cleanDeclarations[] = $property . ': ' . $value;
            }
        }

        return implode('; ', $cleanDeclarations);
    }

    private function sanitizeUrl($url, $isImage)
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }

        if (strpos($url, '#') === 0 || strpos($url, '/') === 0 || strpos($url, '{{') === 0) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null) {
            return $url;
        }

        $allowedSchemes = $isImage ? array('http', 'https') : array('http', 'https', 'mailto', 'tel');

        return in_array(strtolower($scheme), $allowedSchemes, true) ? $url : '';
    }

    private function allowedTags()
    {
        return array(
            'a' => array('href', 'target', 'rel'),
            'b' => array(),
            'blockquote' => array(),
            'br' => array(),
            'div' => array('align'),
            'em' => array(),
            'font' => array('color', 'face', 'size'),
            'h1' => array('align'),
            'h2' => array('align'),
            'h3' => array('align'),
            'h4' => array('align'),
            'h5' => array('align'),
            'h6' => array('align'),
            'hr' => array(),
            'i' => array(),
            'img' => array('src', 'alt', 'width', 'height', 'align'),
            'li' => array(),
            'ol' => array(),
            'p' => array('align'),
            'span' => array(),
            'strong' => array(),
            'table' => array('align', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'width'),
            'tbody' => array(),
            'td' => array('align', 'bgcolor', 'colspan', 'rowspan', 'valign', 'width', 'height'),
            'tfoot' => array(),
            'th' => array('align', 'colspan', 'rowspan', 'valign', 'width', 'height'),
            'thead' => array(),
            'tr' => array('align', 'valign'),
            'u' => array(),
            'ul' => array(),
        );
    }

    private function createPlainText($html)
    {
        $textHtml = preg_replace('/<(?:br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($textHtml), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function fallbackSanitize($html)
    {
        $html = preg_replace('/<(script|style|iframe|object|embed|form)[^>]*>.*?<\/\1>/is', '', $html);
        $html = strip_tags($html, '<a><b><blockquote><br><div><em><font><h1><h2><h3><h4><h5><h6><hr><i><img><li><ol><p><span><strong><table><tbody><td><tfoot><th><thead><tr><u><ul>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);

        return $this->restoreEncodedTemplateTokens(trim($html));
    }

    private function restoreEncodedTemplateTokens($html)
    {
        $restoredHtml = preg_replace_callback(
            '/%7B%7B([a-z][a-z0-9_]*)%7D%7D/i',
            function ($matches) {
                return '{{' . strtolower($matches[1]) . '}}';
            },
            (string) $html
        );

        return is_string($restoredHtml) ? $restoredHtml : (string) $html;
    }

    private function normalizeEditorJson($editorJson)
    {
        $editorJson = trim((string) $editorJson);

        if ($editorJson === '') {
            return null;
        }

        $document = json_decode($editorJson, true);

        if (!is_array($document)
            || json_last_error() !== JSON_ERROR_NONE
            || !isset($document['root'])
            || !is_array($document['root'])
            || !isset($document['root']['type'])
            || $document['root']['type'] !== 'EmailLayout') {
            return null;
        }

        foreach ($document as &$block) {
            if (is_array($block)
                && isset($block['type'])
                && $block['type'] === 'Spacer'
                && isset($block['data'])
                && is_array($block['data'])
                && $block['data'] === array()) {
                $block['data'] = new stdClass();
            }
        }
        unset($block);

        $normalized = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $normalized === false ? null : $normalized;
    }

    private function cleanValue(array $input, $fieldName, $maximumLength)
    {
        $value = isset($input[$fieldName]) && !is_array($input[$fieldName])
            ? trim((string) $input[$fieldName])
            : '';

        return substr($value, 0, $maximumLength);
    }

    private function cleanPlainText(array $input, $fieldName, $maximumLength, $allowLineBreaks)
    {
        $value = isset($input[$fieldName]) && !is_array($input[$fieldName])
            ? (string) $input[$fieldName]
            : '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = str_replace(array("\r\n", "\r"), "\n", $value);

        if ($allowLineBreaks) {
            $value = preg_replace('/[\t ]+/', ' ', $value);
            $value = preg_replace('/\n{3,}/', "\n\n", $value);
        } else {
            $value = preg_replace('/\s+/', ' ', $value);
        }

        return substr(trim((string) $value), 0, $maximumLength);
    }

    private function cleanSmsText(array $input, $fieldName)
    {
        $value = isset($input[$fieldName]) && !is_array($input[$fieldName])
            ? (string) $input[$fieldName]
            : '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        $value = preg_replace('/[\t ]+/', ' ', $value);
        $value = preg_replace('/\n{3,}/', "\n\n", $value);

        return trim((string) $value);
    }

    private function isHttpUrl($url, $requireHttps)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $requireHttps ? $scheme === 'https' : in_array($scheme, array('http', 'https'), true);
    }

    private function isPushDestination($destination, $platform)
    {
        if ($this->isHttpUrl($destination, false)) {
            return true;
        }

        if (!in_array($platform, array('ios', 'android'), true)) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:\/\/[^\s]+$/i', $destination) === 1
            && preg_match('/^(?:javascript|data):/i', $destination) !== 1;
    }

    private function isPushImageUrl($url)
    {
        if (!$this->isHttpUrl($url, false)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, array('localhost', '127.0.0.1', '::1'), true));
    }
}
