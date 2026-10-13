<?php
namespace Tests\Feature;
use App\Models\{User,Category};
use App\Services\WebsiteDesignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AllProductsThemeMediaTest extends TestCase
{
 use RefreshDatabase;
 public function test_shop_media_is_industry_scoped_and_does_not_replace_category_banners():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $category=Category::create(['name'=>'Category','slug'=>'own-banner','is_active'=>true,'image_url'=>'/category-banner.png']);
  \App\Models\Collection::create(['title'=>'Collection','slug'=>'own-collection','is_active'=>true,'cover_image'=>'/collection-banner.png']);
  foreach(['home_deco','beauty','food'] as $theme){
   $profile=[$theme=>['media'=>['allProducts'=>['desktop'=>'/'.$theme.'-shop.png','mobile'=>'/'.$theme.'-mobile.png','focus'=>'right','focusY'=>'bottom']]]];
   $this->putJson('/admin/api/website-design',['theme'=>$theme,'overrides'=>[],'customization'=>$profile])->assertOk()->assertJsonPath('mediaContracts.'.$theme.'.allProducts',['allProducts']);
   $this->get('/shop')->assertOk()->assertSee('/'.$theme.'-shop.png',false)->assertSee('/'.$theme.'-mobile.png',false)->assertSee('data-all-products-media',false);
   $this->get('/categories/own-banner')->assertOk()->assertSee('/category-banner.png',false)->assertDontSee('data-all-products-media',false);
   $this->get('/collections/own-collection')->assertOk()->assertSee('/collection-banner.png',false)->assertDontSee('data-all-products-media',false);
   $this->get('/admin/website-design/preview?theme='.$theme.'&surface=allProducts')->assertOk()->assertSee('All products')->assertSee('/'.$theme.'-shop.png',false);
   $profile[$theme]['media']['allProducts']['hidden']=true;
   $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>$theme,'surface'=>'allProducts','customization'=>json_encode($profile)]))->assertOk()->assertDontSee('data-all-products-media',false)->assertSee('all-products--compact',false);
  }
 }
}
