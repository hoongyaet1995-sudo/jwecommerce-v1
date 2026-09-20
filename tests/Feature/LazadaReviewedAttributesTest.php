<?php

namespace Tests\Feature;

use App\Services\Marketplace\LazadaReviewedAttributes;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LazadaReviewedAttributesTest extends TestCase
{
    private array $schema = [
        ['name' => 'title', 'attribute_type' => 'normal', 'is_mandatory' => 1, 'input_type' => 'text'],
        ['name' => 'description', 'attribute_type' => 'normal', 'is_mandatory' => 0, 'input_type' => 'richText'],
        ['name' => 'weight', 'attribute_type' => 'normal', 'is_mandatory' => 1, 'input_type' => 'numeric'],
        ['name' => 'release_date', 'attribute_type' => 'normal', 'is_mandatory' => 0, 'input_type' => 'date'],
        ['name' => 'material', 'attribute_type' => 'normal', 'is_mandatory' => 0, 'input_type' => 'singleSelect', 'options' => [['en_name' => 'Gold'], ['en_name' => 'Silver']]],
        ['name' => 'features', 'attribute_type' => 'normal', 'is_mandatory' => 0, 'input_type' => 'multiSelect', 'max_value_count' => 2, 'options' => [['en_name' => 'Light'], ['en_name' => 'Strong']]],
        ['name' => 'model', 'attribute_type' => 'normal', 'is_mandatory' => 0, 'input_type' => 'enumInput'],
        ['name' => 'tags', 'attribute_type' => 'normal', 'is_mandatory' => 0, 'input_type' => 'multiEnumInput', 'max_value_count' => 3],
        ['name' => 'color_thumbnail', 'attribute_type' => 'sku', 'is_mandatory' => 1, 'input_type' => 'img'],
        ['name' => 'color_family', 'attribute_type' => 'sku', 'is_mandatory' => 1, 'input_type' => 'singleSelect', 'options' => [['en_name' => 'Blue'], ['en_name' => 'Red']]],
        ['name' => 'chart_asset', 'label' => 'Size Chart', 'attribute_type' => 'normal', 'is_mandatory' => 1, 'input_type' => 'numeric'],
    ];

    public function test_it_allowlists_all_supported_types_and_preserves_owned_image_ids(): void
    {
        $bound = LazadaReviewedAttributes::bind($this->schema, $this->source(), [
            'normal' => [
                'title' => 'Bracelet', 'description' => '<p>Fine</p>', 'weight' => '1.25',
                'release_date' => '2026-09-20', 'material' => 'Gold', 'features' => 'Light,Strong',
                'model' => 'JW-1', 'tags' => 'Gift,Premium',
            ],
            'skus' => ['BLUE-1' => ['color_family' => 'Blue'], 'RED-1' => ['color_family' => 'Red']],
            'imageBindings' => [
                'lazada:variant:BLUE-1:color_thumbnail' => 7,
                'lazada:variant:RED-1:color_thumbnail' => 8,
                'lazada:size-chart:chart_asset' => 7,
            ],
        ]);

        $this->assertSame('1.25', $bound['normal']['weight']);
        $this->assertSame('Blue', $bound['skus']['BLUE-1']['color_family']);
        $this->assertSame([
            'lazada:variant:BLUE-1:color_thumbnail' => 7,
            'lazada:variant:RED-1:color_thumbnail' => 8,
            'lazada:size-chart:chart_asset' => 7,
        ], $bound['jw_attribute_image_ids']);
    }

    public static function invalidReviewedValues(): array
    {
        return [
            'infinite numeric' => ['normal', 'weight', 'INF'],
            'invalid date' => ['normal', 'release_date', '2026-02-30'],
            'unknown option' => ['normal', 'material', 'Platinum'],
            'duplicate multi' => ['normal', 'features', 'Light,Light'],
            'too many multi' => ['normal', 'features', 'Light,Strong,Extra'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidReviewedValues')]
    public function test_it_rejects_invalid_typed_values(string $scope, string $name, string $value): void
    {
        $reviewed = ['normal' => ['title' => 'Bracelet', 'weight' => '1'], 'skus' => [], 'imageBindings' => []];
        $reviewed[$scope][$name] = $value;
        $this->expectException(ValidationException::class);
        LazadaReviewedAttributes::bind($this->schema, $this->source(), $reviewed);
    }

    public function test_it_rejects_malformed_unknown_and_foreign_image_bindings(): void
    {
        foreach ([
            ['normal' => ['__proto__' => 'x'], 'skus' => [], 'imageBindings' => []],
            ['normal' => [], 'skus' => ['OTHER' => ['color_family' => 'Blue']], 'imageBindings' => []],
            ['normal' => [], 'skus' => [], 'imageBindings' => ['lazada:variant:OTHER:color_thumbnail' => 7]],
            ['normal' => [], 'skus' => [], 'imageBindings' => ['lazada:variant:BLUE-1:color_thumbnail' => 999]],
        ] as $reviewed) {
            try {
                LazadaReviewedAttributes::bind($this->schema, $this->source(), $reviewed);
                $this->fail('Unsafe reviewed attribute data was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_brand_from_the_verified_external_brand_list_is_accepted_when_schema_has_no_inline_options(): void
    {
        $schema = [[
            'name' => 'brand', 'attribute_type' => 'normal', 'is_mandatory' => 1,
            'input_type' => 'singleSelect',
        ]];

        $bound = LazadaReviewedAttributes::bind($schema, $this->source(), [
            'normal' => ['brand' => 'No Brand'], 'skus' => [], 'imageBindings' => [],
        ], ['No Brand']);

        $this->assertSame('No Brand', $bound['normal']['brand']);

        $this->expectException(ValidationException::class);
        LazadaReviewedAttributes::bind($schema, $this->source(), [
            'normal' => ['brand' => 'Unverified Brand'], 'skus' => [], 'imageBindings' => [],
        ], ['No Brand']);
    }

    private function source(): array
    {
        return [
            'product_id' => 1,
            'variants' => [['id' => 10, 'sku' => 'BLUE-1'], ['id' => 11, 'sku' => 'RED-1']],
            'images' => [['id' => 7, 'path' => '/storage/blue.jpg'], ['id' => 8, 'path' => '/storage/red.jpg']],
        ];
    }
}
