<?php
namespace Tests\Feature\Cms;

use App\Domain\Cms\RuntimeAdapterRegistry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PantryTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pantry_tabs_save_reload_and_resolve_independent_live_product_feeds(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $product=Product::create(['title'=>'Coffee','handle'=>'coffee','status'=>'active','base_price'=>2800]);
        $product->variants()->create(['sku'=>'PANTRY-COFFEE','title'=>'250g','price'=>4200,'stock'=>3,'enabled'=>true]);
        $section=['id'=>'occasions','type'=>'product_listing','title'=>'Shop by Occasion','isVisible'=>true,'listingPreset'=>'pantry-tabs','productTabs'=>[
            ['id'=>'everyday','label'=>'Everyday','sourceType'=>'manual','productIds'=>[(string)$product->id]],
            ['id'=>'gifts','label'=>'Gifts','sourceType'=>'manual','productIds'=>[]],
        ]];
        $page=$this->postJson('/admin/api/pages',['title'=>'Pantry','slug'=>'pantry-tabs-test','template'=>'landing','sections'=>[$section]])->assertCreated()->json('data');
        $this->getJson('/admin/api/pages/'.$page['id'].'/draft')->assertOk()->assertJsonPath('data.document.sections.0.productTabs.0.label','Everyday')->assertJsonPath('data.document.sections.0.listingPreset','pantry-tabs');
        $facts=app(RuntimeAdapterRegistry::class)->factsFor(['sections'=>[$section]]);
        $tabs=$facts['sections'][0]['tabs'];
        $this->assertCount(2,$tabs);
        $this->assertSame('Coffee',$tabs[0]['products'][0]['title']);
        $this->assertSame(42,$tabs[0]['products'][0]['commerce']['price']);
        $this->assertSame([],$tabs[1]['products']);
        $this->get('/admin/pages/'.$page['id'].'/preview')->assertOk()->assertSee('pantry-tabs')->assertSee('Coffee');
    }
}
