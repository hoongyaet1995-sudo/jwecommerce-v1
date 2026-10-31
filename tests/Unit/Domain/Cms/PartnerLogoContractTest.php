<?php

namespace Tests\Unit\Domain\Cms;

use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PartnerLogoContractTest extends TestCase
{
    public function test_partner_settings_and_owned_logo_slots_round_trip_and_reject_out_of_range_sizes(): void
    {
        $document = json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')), true, flags: JSON_THROW_ON_ERROR);
        $base = collect($document['sections'])->firstWhere('type', 'logo_cloud');
        $document['sections'] = [array_merge($base, [
            'id' => 'partners-test', 'type' => 'logo_cloud', 'title' => 'Our brands',
            'logoPreset' => 'partners', 'logoHeight' => 42, 'logoGap' => 48,
            'marqueeSpeed' => 32, 'marqueeDirection' => 'right', 'marqueePauseOnHover' => true,
            'items' => [['id' => 'logo-one', 'title' => 'Brand', 'imageUrl' => 'https://images.unsplash.com/photo-brand', 'linkUrl' => 'https://example.com']],
            'imageSlots' => [['target' => 'item:logo-one:imageUrl', 'imageFit' => 'contain']],
        ])];
        $contract = app(PageDocumentContract::class);
        $this->assertSame($document, $contract->validate($document));
        $document['sections'][0]['marqueeSpeed'] = 0;
        $this->assertSame($document, $contract->validate($document));
        $document['sections'][0]['logoHeight'] = 10000;
        $this->expectException(ValidationException::class);
        $contract->validate($document);
    }
}

