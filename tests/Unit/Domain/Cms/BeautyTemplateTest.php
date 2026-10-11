<?php
namespace Tests\Unit\Domain\Cms;

use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;

final class BeautyTemplateTest extends TestCase
{
    public function test_beauty_snapshot_and_sparse_presets_round_trip(): void
    {
        $snapshot = json_decode(file_get_contents(resource_path('admin/src/cms/beautyTemplate.json')), true, 512, JSON_THROW_ON_ERROR);
        $document = ['title'=>'Beauty', 'slug'=>'beauty', 'template'=>'standard', 'style'=>$snapshot['style'], 'sections'=>$snapshot['sections'], 'headerPresentation'=>['mode'=>'translucent','tone'=>'dark']];
        $contract = app(PageDocumentContract::class);
        $this->assertCount(9, $document['sections']);
        $this->assertSame($document, $contract->validate($document));
        foreach ([['shop_navigation','shopNavigationPreset','image-overlay'],['shop_navigation','shopNavigationPreset','image-caption'],['product_listing','listingPreset','beauty-grid'],['media_gallery','galleryPreset','routine-steps'],['media_text_split','layoutPreset','flush-split']] as [$type,$field,$preset]) {
            $document['sections'] = [['id'=>'sparse','type'=>$type,'title'=>'Editable','isVisible'=>true,$field=>$preset,'items'=>[]]];
            $this->assertSame($document, $contract->validate($document), $preset);
        }
    }
}
