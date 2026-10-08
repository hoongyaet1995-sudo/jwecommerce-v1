<?php

namespace Tests\Unit\Domain\Cms;

use App\Domain\Cms\PageDocumentContract;
use Closure;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PageDocumentContractTest extends TestCase
{
    public function test_shared_typography_round_trips_and_rejects_unsafe_or_unbounded_overrides(): void
    {
        $document=$this->fixture();
        foreach (array_keys($this->manifest()['section']['sectionTypes']) as $type) {
            $document['sections']=[['id'=>'s','type'=>$type,'title'=>'Text','isVisible'=>true,'typography'=>['title'=>['fontFamily'=>'serif','fontSize'=>48,'fontWeight'=>700,'lineHeight'=>1.2,'letterSpacing'=>-0.5,'color'=>'#6847d4']]]];
            $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        }
        foreach ([['fontSize',999],['fontFamily','url(evil)'],['color','red'],['unknown','value'],['fontWeight',450]] as [$key,$value]) {
            $invalid=$document;$invalid['sections'][0]['typography']=['title'=>[$key=>$value]];
            $exception=$this->validationException(fn()=>app(PageDocumentContract::class)->validate($invalid));
            $this->assertArrayHasKey('sections.0.typography.title.'.$key,$exception->errors());
        }
    }

    public function test_chapter_fields_and_image_slots_are_owned_by_their_layout(): void
    {
        $document=$this->fixture();
        $document['sections']=[
            ['id'=>'cover','type'=>'media_text_split','title'=>'Cover','isVisible'=>true,'layoutPreset'=>'editorial-cover','leadText'=>'Introduction'],
            ['id'=>'nav','type'=>'chapter_navigation','title'=>'Chapters','isVisible'=>true],
            ['id'=>'story','type'=>'chapter_block','title'=>'Story','isVisible'=>true,'chapterPreset'=>'story','imageSlots'=>[['target'=>'mediaUrl','imageRatio'=>'3:2']]],
            ['id'=>'view','type'=>'chapter_block','title'=>'View','isVisible'=>true,'chapterPreset'=>'perspective','items'=>[['id'=>'photo','title'=>'Photo','imageUrl'=>'https://images.unsplash.com/photo-chapter']],'imageSlots'=>[['target'=>'item:photo:imageUrl','imagePosition'=>'top-center']]],
            ['id'=>'band','type'=>'cta_strip','title'=>'Explore','isVisible'=>true,'ctaPreset'=>'editorial-band'],
        ];
        $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        $invalid=$document;$invalid['sections'][2]['chapterPreset']='invented';
        $this->assertArrayHasKey('sections.2.chapterPreset',$this->validationException(fn()=>app(PageDocumentContract::class)->validate($invalid))->errors());
        $invalid=$document;$invalid['sections'][2]['chapterPreset']='principles';
        $this->assertArrayHasKey('sections.2.imageSlots.0.target',$this->validationException(fn()=>app(PageDocumentContract::class)->validate($invalid))->errors());
    }

    public function test_editorial_collection_fields_are_owned_and_bounded(): void
    {
        $document=$this->fixture();
        foreach ([['media_gallery','galleryPreset','editorial-montage'],['media_gallery','galleryPreset','editorial-cards'],['feature_cards','featureCardsPreset','editorial-columns']] as [$type,$field,$value]) {
            $document['sections']=[['id'=>'studio','type'=>$type,'title'=>'Studio','isVisible'=>true,$field=>$value,'showSectionHeading'=>false,'columnsCount'=>2]];
            $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        }
        foreach ([['rich_text','galleryPreset','editorial-montage'],['media_gallery','galleryPreset','invented'],['rich_text','showSectionHeading',true],['feature_cards','showSectionHeading','yes']] as [$type,$field,$value]) {
            $document['sections']=[['id'=>'studio','type'=>$type,'title'=>'Studio','isVisible'=>true,$field=>$value]];
            $exception=$this->validationException(fn()=>app(PageDocumentContract::class)->validate($document));
            $this->assertArrayHasKey('sections.0.'.$field,$exception->errors());
        }
    }
    public function test_page_container_presets_are_optional_and_bounded(): void
    {
        $document=$this->fixture();$this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        foreach (['standard','wide'] as $width) {$document['containerWidth']=$width;$this->assertSame($document,app(PageDocumentContract::class)->validate($document));}
        $document['containerWidth']='invented';$exception=$this->validationException(fn()=>app(PageDocumentContract::class)->validate($document));$this->assertArrayHasKey('containerWidth',$exception->errors());
    }

    public function test_shared_borders_are_saved_on_every_section_type_and_reject_invalid_positions(): void
    {
        $document=$this->fixture();
        foreach (array_keys(json_decode(file_get_contents(resource_path('cms/page-contract.v1.json')),true)['section']['sectionTypes']) as $type) {
            $document['sections']=[['id'=>'s','type'=>$type,'title'=>'Section','isVisible'=>true,'sectionBorder'=>'none','sectionBorderColor'=>'#aabbcc']];
            $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
        }
        $document['sections'][0]['sectionBorder']='invented';
        $exception=$this->validationException(fn()=>app(PageDocumentContract::class)->validate($document));
        $this->assertArrayHasKey('sections.0.sectionBorder',$exception->errors());
    }

    public function test_cta_banner_preset_and_background_slot_are_owned_and_validated(): void
    {
        $document = $this->fixture();
        $document['sections'] = [['id'=>'banner','type'=>'cta_strip','title'=>'Banner','isVisible'=>true,'ctaPreset'=>'image-banner','ctaBannerHeight'=>'compact','mediaUrl'=>'https://images.unsplash.com/photo-banner','imageSlots'=>[['target'=>'mediaUrl','imageOverlay'=>true,'imageOverlayOpacity'=>40]]]];
        $this->assertSame($document, app(PageDocumentContract::class)->validate($document));
        foreach ([['cta_strip','ctaPreset','invented'],['rich_text','ctaPreset','image-banner'],['cta_strip','ctaBannerHeight','fullscreen']] as [$type,$field,$value]) {
            $invalid=$document;$invalid['sections'][0]=['id'=>'banner','type'=>$type,'title'=>'Banner','isVisible'=>true,$field=>$value];
            $exception=$this->validationException(fn()=>app(PageDocumentContract::class)->validate($invalid));
            $this->assertArrayHasKey('sections.0.'.$field,$exception->errors());
        }
    }

    public function test_standalone_values_list_controls_round_trip_and_reject_wrong_owners_and_values(): void
    {
        $input = $this->fixture();
        $input['sections'] = [['id' => 'v', 'type' => 'values_list', 'title' => 'Values', 'isVisible' => true, 'valuesShowHeading' => false, 'valuesShowDividers' => true, 'valuesRowSpacing' => 'compact', 'valuesNumberColor' => '#cccccc', 'valuesDividerColor' => '#eeeeee', 'items' => [['id' => 'one', 'title' => 'First', 'description' => 'Saved value']]]];
        $this->assertSame($input, app(PageDocumentContract::class)->validate($input));
        foreach ([['values_list', 'valuesRowSpacing', 'invented'], ['values_list', 'valuesShowDividers', 'yes'], ['feature_cards', 'valuesRowSpacing', 'normal'], ['values_list', 'featureCardsPreset', 'values-list']] as [$type, $field, $value]) {
            $invalid = $input;
            $invalid['sections'][0] = ['id' => 'v', 'type' => $type, 'title' => 'Values', 'isVisible' => true, $field => $value];
            $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($invalid));
            $this->assertArrayHasKey('sections.0.'.$field, $exception->errors());
        }
    }
    public function test_values_list_fields_are_bounded_and_owned_by_feature_cards(): void
    {
        $input = $this->fixture();
        $input['sections'] = [['id' => 'values', 'type' => 'feature_cards', 'title' => 'Values', 'isVisible' => true, 'featureCardsPreset' => 'values-list', 'valuesShowHeading' => false]];
        $this->assertSame($input, app(PageDocumentContract::class)->validate($input));
        foreach ([['feature_cards', 'featureCardsPreset', 'invented'], ['rich_text', 'featureCardsPreset', 'values-list'], ['feature_cards', 'valuesShowHeading', 'yes'], ['rich_text', 'valuesShowHeading', false]] as [$type, $field, $value]) {
            $invalid = $input;
            $invalid['sections'][0] = ['id' => 'values', 'type' => $type, 'title' => 'Values', 'isVisible' => true, $field => $value];
            $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($invalid));
            $this->assertArrayHasKey('sections.0.'.$field, $exception->errors());
        }
    }

    public function test_manifesto_preset_is_owned_only_by_rich_text_and_rejects_unknown_values(): void
    {
        $input = $this->fixture();
        $input['sections'] = [['id' => 'manifesto', 'type' => 'rich_text', 'title' => 'Statement', 'isVisible' => true, 'richTextPreset' => 'manifesto']];
        $this->assertSame($input, app(PageDocumentContract::class)->validate($input));
        foreach ([['rich_text', 'invented'], ['media_text_split', 'manifesto']] as [$type, $preset]) {
            $input['sections'][0]['type'] = $type;
            $input['sections'][0]['richTextPreset'] = $preset;
            $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));
            $this->assertArrayHasKey('sections.0.richTextPreset', $exception->errors());
        }
    }

    public function test_image_slots_reject_unknown_targets_duplicates_and_locked_geometry(): void
    {
        foreach ([
            [['target' => 'ghost', 'imageRatio' => '1:1']],
            [['target' => 'mediaUrl'], ['target' => 'mediaUrl']],
            [['target' => 'mediaUrl', 'imageShape' => 'circle']],
        ] as $slots) {
            $input = $this->fixture();
            $input['sections'] = [['id' => 'banner', 'type' => 'hero_banner', 'title' => 'Banner', 'isVisible' => true, 'imageSlots' => $slots]];
            $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));
            $this->assertNotEmpty($exception->errors());
        }
    }
    public function test_editorial_hero_rejects_unsupported_presets_and_crop_positions(): void
    {
        foreach (['layoutPreset' => 'invented', 'mediaFocalPoint' => '200% 0', 'buttonStyle' => 'invented'] as $field => $value) {
            $input = $this->fixture();
            $input['sections'] = [['id' => 'hero', 'type' => 'media_text_split', 'title' => 'Story', 'isVisible' => true, $field => $value]];
            $exception = $this->validationException(fn () => app(PageDocumentContract::class)->validate($input));
            $this->assertArrayHasKey('sections.0.'.$field, $exception->errors());
        }
    }
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

    public function test_all_visible_fixture_section_types_can_publish_when_runtime_facts_are_valid(): void
    {
        $input = $this->fixture();

        app(PageDocumentContract::class)->assertPublishable($input, $this->fixtureRuntimeFacts());

        $this->assertTrue(true);
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
