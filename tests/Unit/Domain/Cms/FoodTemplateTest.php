<?php
namespace Tests\Unit\Domain\Cms;

use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;

final class FoodTemplateTest extends TestCase
{
    public function test_food_snapshot_and_all_sparse_presets_round_trip(): void
    {
        $snapshot = json_decode(file_get_contents(resource_path('admin/src/cms/foodTemplate.json')), true, 512, JSON_THROW_ON_ERROR);
        $document = ['title'=>'Food', 'slug'=>'food', 'template'=>'standard', 'style'=>$snapshot['style'], 'sections'=>$snapshot['sections']];
        $contract = app(PageDocumentContract::class);
        $this->assertCount(10, $document['sections']);
        $this->assertSame($document, $contract->validate($document));
        foreach ([['shop_navigation','shopNavigationPreset','catalog-captions'],['shop_navigation','shopNavigationPreset','recipe-cards'],['product_listing','listingPreset','pantry-grid'],['product_listing','listingPreset','pantry-tabs'],['media_text_split','layoutPreset','producer-story']] as [$type,$field,$preset]) {
            $document['sections'] = [['id'=>'sparse','type'=>$type,'title'=>'Editable','isVisible'=>true,$field=>$preset,'items'=>[]]];
            $this->assertSame($document, $contract->validate($document), $preset);
        }
    }
}
