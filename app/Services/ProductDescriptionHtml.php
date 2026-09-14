<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class ProductDescriptionHtml
{
    public function excerpt(?string $description, ?string $summary = null): string
    {
        $html = $this->clean(filled($summary) ? $summary : $description) ?? '';
        $html = preg_replace('/<(?:br\b[^>]*|\/p|\/li|\/h[1-6]|\/div)>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), 180);
    }

    public function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,strike,h1,h2,h3,h4,h5,h6,blockquote,ul,ol[start],li,hr,a[href|title|rel],img[src|alt|title|width|height],table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        return (new HTMLPurifier($config))->purify($html);
    }
}
