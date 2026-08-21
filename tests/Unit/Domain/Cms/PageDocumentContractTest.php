<?php

namespace Tests\Unit\Domain\Cms;

use App\Domain\Cms\PageDocumentContract;
use Closure;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PageDocumentContractTest extends TestCase
{
    /** @var list<string> */
    private const CURRENT_PUBLIC_RENDERER_TYPES = [
        'hero_banner',
        'rich_text',
        'product_grid',
        'collection_tabs',
        'feature_cards',
        'media_gallery',
        'faq_accordion',
        'cta_strip',
        'contact_form',
        'newsletter_signup',
    ];

    public function test_all_known_fields_round_trip_without_loss(): void
    {
        $input = $this->fixture();

        $validated = app(PageDocumentContract::class)->validate($input);

        $this->assertSame($input, $validated);
        $this->assertSame('', data_get($validated, 'sections.0.subtitle'));
        $this->assertSame(0, data_get($validated, 'sections.0.overlayOpacity'));
        $this->assertSame([], data_get($validated, 'sections.0.items'));
        $this->assertFalse(data_get($validated, 'sections.1.sliderAutoplay'));
        $this->assertNull(data_get($validated, 'heroImage'));
    }

    public function test_unknown_fields_are_rejected_instead_of_removed(): void
    {
        $input = $this->fixture();
        $input['sections'][0]['inventedField'] = 'must fail';

        $this->expectException(ValidationException::class);

        app(PageDocumentContract::class)->validate($input);
    }

    public function test_unknown_section_types_are_rejected(): void
    {
        $input = $this->fixture();
        $input['sections'][0]['type'] = 'invented_section';

        $this->expectException(ValidationException::class);

        app(PageDocumentContract::class)->validate($input);
    }

    public function test_nested_unknown_fields_are_rejected_with_their_exact_path(): void
    {
        $input = $this->fixture();
        $input['sections'][1]['items'][0]['inventedField'] = 'must fail';

        $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));

        $this->assertSame([
            'This field is not supported by the page contract.',
        ], $exception->errors()['sections.1.items.0.inventedField']);
    }

    public function test_scalar_fields_reject_a_value_with_the_wrong_json_type(): void
    {
        $input = $this->fixture();
        $input['sections'][0]['overlayOpacity'] = '0';

        $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));

        $this->assertSame([
            'This value has an unsupported JSON type.',
        ], $exception->errors()['sections.0.overlayOpacity']);
    }

    public function test_object_fields_reject_a_non_empty_json_list(): void
    {
        $input = $this->fixture();
        $input['sections'][0]['customColors'] = ['not-an-object'];

        $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));

        $this->assertSame([
            'This value has an unsupported JSON type.',
        ], $exception->errors()['sections.0.customColors']);
    }

    public function test_data_urls_are_rejected_with_the_media_field_path(): void
    {
        $input = $this->fixture();
        $input['sections'][0]['mediaUrl'] = 'data:image/png;base64,AA==';

        $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));

        $this->assertSame([
            'Data URLs are not allowed in page documents.',
        ], $exception->errors()['sections.0.mediaUrl']);
    }

    public function test_media_hostname_policy_matches_shared_raw_authority_parity_vectors(): void
    {
        $contract = app(PageDocumentContract::class);
        $input = $this->fixture();
        $parity = json_decode(
            file_get_contents(base_path('tests/Fixtures/Cms/media-url-host-parity.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($parity['unsafe'] as $url) {
            $input['heroImage'] = $url;
            $exception = $this->validationException(fn () => $contract->validate($input));

            $this->assertSame([
                'Media must use a managed asset URL or a safe public HTTPS URL.',
            ], $exception->errors()['heroImage'], $url);
        }

        foreach ($parity['safe'] as $url) {
            $input['heroImage'] = $url;

            $this->assertSame($url, $contract->validate($input)['heroImage']);
        }
    }

    public function test_visible_empty_publish_required_fields_fail_with_their_exact_path(): void
    {
        $input = $this->fixture();
        $this->hideSectionsWithoutCurrentRenderer($input);
        $input['sections'][0]['title'] = '';

        $exception = $this->validationException(fn () => app(PageDocumentContract::class)->assertPublishable($input, []));

        $this->assertSame([
            'This visible section field is required to publish.',
        ], $exception->errors()['sections.0.title']);
    }

    public function test_hidden_empty_publish_required_fields_do_not_block_publication(): void
    {
        $input = $this->fixture();
        $this->hideSectionsWithoutCurrentRenderer($input);
        $input['sections'][0]['title'] = '';
        $input['sections'][0]['isVisible'] = false;

        app(PageDocumentContract::class)->assertPublishable($input, $this->fixtureRuntimeFacts());

        $this->assertTrue(true);
    }

    public function test_visible_sections_without_a_public_renderer_fail_publication(): void
    {
        $input = $this->fixture();
        $this->hideSectionsWithoutCurrentRenderer($input, [1]);

        $exception = $this->validationException(fn () => app(PageDocumentContract::class)->assertPublishable($input, []));

        $this->assertSame([
            'This visible section type cannot be rendered.',
        ], $exception->errors()['sections.1.type']);
    }

    public function test_hidden_sections_without_a_public_renderer_do_not_block_publication(): void
    {
        $input = $this->fixture();
        $this->hideSectionsWithoutCurrentRenderer($input);
        $input['sections'][1]['isVisible'] = false;

        app(PageDocumentContract::class)->assertPublishable($input, $this->fixtureRuntimeFacts());

        $this->assertTrue(true);
    }

    public function test_runtime_requirement_failure_uses_the_declared_section_field_path(): void
    {
        $input = $this->fixture();
        $this->hideSectionsWithoutCurrentRenderer($input);

        $manifest = $this->manifest();
        $manifest['section']['sectionTypes']['product_grid']['runtimeRequirements'] = [[
            'field' => 'productIds',
            'fact' => 'sections.8.productIds.valid',
        ]];

        $exception = $this->validationException(fn () => (new PageDocumentContract($manifest))->assertPublishable($input, [
            'sections' => [
                8 => ['productIds' => ['valid' => false]],
            ],
        ]));

        $this->assertSame([
            'The runtime reference for this visible section is invalid.',
        ], $exception->errors()['sections.8.productIds']);
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        return json_decode(
            file_get_contents(base_path('resources/cms/page-contract.v1.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, mixed> */
    private function fixtureRuntimeFacts(): array
    {
        return [
            'sections' => [
                9 => [
                    'tabs' => [
                        ['sourceId' => ['valid' => true]],
                        ['sourceId' => ['valid' => true]],
                    ],
                ],
            ],
        ];
    }

    private function validationException(Closure $attempt): ValidationException
    {
        try {
            $attempt();
        } catch (ValidationException $exception) {
            return $exception;
        }

        $this->fail('Expected page contract validation to fail.');
    }

    /** @param array<string, mixed> $document @param list<int> $except */
    private function hideSectionsWithoutCurrentRenderer(array &$document, array $except = []): void
    {
        foreach ($document['sections'] as $index => &$section) {
            if (! in_array($section['type'], self::CURRENT_PUBLIC_RENDERER_TYPES, true)
                && ! in_array($index, $except, true)) {
                $section['isVisible'] = false;
            }
        }
        unset($section);
    }
}
