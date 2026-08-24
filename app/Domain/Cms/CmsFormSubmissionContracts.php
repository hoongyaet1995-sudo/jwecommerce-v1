<?php

namespace App\Domain\Cms;

use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Support\Facades\URL;
use stdClass;

final class CmsFormSubmissionContracts
{
    private const LIFETIME_MINUTES = 10;

    /** @param array<string, mixed> $document @return array{contact: stdClass, newsletter: stdClass} */
    public function forDocument(Page $page, PageDocument $pageDocument, array $document, string $audience): array
    {
        abort_unless(PageRuntimeAudience::isKnown($audience), 500);
        $forms = ['contact' => [], 'newsletter' => []];

        foreach ($document['sections'] ?? [] as $section) {
            if (! is_array($section) || ($section['isVisible'] ?? false) !== true) {
                continue;
            }

            $kind = match ($section['type'] ?? null) {
                'contact_form' => 'contact',
                'newsletter_signup' => 'newsletter',
                default => null,
            };
            if ($kind === null || ! is_string($section['id'] ?? null)) {
                continue;
            }

            $forms[$kind][$section['id']] = $this->contract($kind, $page, $pageDocument, $section['id'], $section['type'], $audience);
        }

        return [
            'contact' => (object) $forms['contact'],
            'newsletter' => (object) $forms['newsletter'],
        ];
    }

    /** @return array<string, mixed> */
    private function contract(string $kind, Page $page, PageDocument $pageDocument, string $sectionId, string $sectionType, string $audience): array
    {
        $parameters = [
            'page' => $page->getKey(),
            'stage' => $pageDocument->stage,
            'section' => $sectionId,
            'hash' => $pageDocument->content_hash,
            'version' => $pageDocument->lock_version,
            'type' => $sectionType,
            'audience' => $audience,
        ];

        return [
            'endpoint' => URL::temporarySignedRoute('cms.forms.'.$kind.'.submit', now()->addMinutes(self::LIFETIME_MINUTES), $parameters, absolute: false),
            'pageId' => (string) $page->getKey(),
            'stage' => $pageDocument->stage,
            'documentHash' => $pageDocument->content_hash,
            'lockVersion' => $pageDocument->lock_version,
            'sectionId' => $sectionId,
            'sectionType' => $sectionType,
            'audience' => $audience,
        ];
    }
}
