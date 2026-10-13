<?php
namespace Tests\Feature;
use App\Models\{Category,Collection};
use App\Services\WebsiteDesignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BeautyCatalogImageHeroTest extends TestCase
{
 use RefreshDatabase;
 public function test_category_and_collection_use_image_only_hero_with_missing_image_collapse():void {
  app(WebsiteDesignService::class)->save(['theme'=>'beauty','overrides'=>[]]);
  Category::create(['name'=>'Category name','slug'=>'image-category','is_active'=>true,'description'=>'Category description should not overlay','image_url'=>'/category-banner.png']);
  Collection::create(['title'=>'Collection name','slug'=>'image-collection','is_active'=>true,'subtitle'=>'Collection description should not overlay','cover_image'=>'/collection-banner.png']);
  foreach(['categories/image-category'=>'/category-banner.png','collections/image-collection'=>'/collection-banner.png'] as $path=>$image){
   $this->get('/'.$path)->assertOk()->assertSee($image,false)->assertDontSee('<div class="heroCopy">',false)->assertDontSee('description should not overlay');
  }
  Category::where('slug','image-category')->update(['image_url'=>null]);
  $this->get('/categories/image-category')->assertOk()->assertSee('Category name')->assertDontSee('class="heroPhoto"',false)->assertDontSee('<div class="heroCopy">',false);
  $this->get('/shop')->assertOk()->assertSee('<div class="heroCopy">',false)->assertSee('All products');
 }
}
