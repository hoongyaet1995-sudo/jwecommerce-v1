<?php
namespace Tests\Feature\Admin;
use App\Models\{Category,Collection,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CatalogBannerMediaTest extends TestCase {
 use RefreshDatabase;
 public function test_media_settings_round_trip_and_reject_unsafe_mobile_urls(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $settings=['mobile'=>'https://images.unsplash.com/mobile.jpg','focus'=>'right','focusY'=>'top','alt'=>'Our range','hidden'=>false];
  foreach (['categories','collections'] as $kind) {
   $payload=$kind==='categories'?['name'=>'Banner','status'=>'active','sortOrder'=>0,'image'=>'https://images.unsplash.com/desktop.jpg']:['title'=>'Banner','status'=>'active','type'=>'manual','coverImage'=>'https://images.unsplash.com/desktop.jpg'];
   $payload['imageSettings']=$settings;
   $data=$this->postJson('/admin/api/catalog/'.$kind,$payload)->assertCreated()->assertJsonPath('data.imageSettings',$settings)->json('data');
   $model=$kind==='categories'?Category::findOrFail($data['id']):Collection::findOrFail($data['id']);
   $this->assertSame($settings,$model->image_settings);
   unset($payload['imageSettings']);
   $this->patchJson('/admin/api/catalog/'.$kind.'/'.$data['id'],$payload)->assertOk()->assertJsonPath('data.imageSettings',$settings);
   $payload['imageSettings']=['mobile'=>'javascript:alert(1)'];
   $this->patchJson('/admin/api/catalog/'.$kind.'/'.$data['id'],$payload)->assertUnprocessable()->assertJsonValidationErrors('imageSettings.mobile');
  }
 }
 public function test_banner_markup_uses_mobile_source_and_focal_position_with_safe_fallback(): void {
  $media=['desktop'=>'https://images.unsplash.com/wide.jpg','mobile'=>'https://images.unsplash.com/tall.jpg','alt'=>'Range','focus'=>'right','focusY'=>'top'];
  $html=view('storefront.partials.catalog-banner',['media'=>$media])->render();
  $this->assertStringContainsString('srcset="https://images.unsplash.com/tall.jpg"',$html);
  $this->assertStringContainsString('object-position:right top',$html);
  $media['mobile']='';
  $html=view('storefront.partials.catalog-banner',['media'=>$media])->render();
  $this->assertStringNotContainsString('<source',$html);
  $this->assertStringContainsString('src="https://images.unsplash.com/wide.jpg"',$html);
 }
 public function test_every_theme_renders_category_and_collection_responsive_banners(): void {
  $settings=['mobile'=>'https://images.unsplash.com/mobile.jpg','focus'=>'left','focusY'=>'bottom','alt'=>'Catalog range'];
  $category=Category::create(['name'=>'Banner','slug'=>'banner','is_active'=>true,'image_url'=>'https://images.unsplash.com/desktop.jpg','image_settings'=>$settings]);
  $collection=Collection::create(['title'=>'Banner','slug'=>'banner','is_active'=>true,'status'=>'active','type'=>'manual','cover_image'=>'https://images.unsplash.com/desktop.jpg','image_settings'=>$settings]);
  foreach (\App\Services\WebsiteDesignService::THEMES as $theme) {
   \App\Models\StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>['theme'=>$theme,'overrides'=>[]]]]);
   foreach (['/categories/banner','/collections/banner'] as $url) {
    $response=$this->get($url)->assertOk();
    $this->assertStringContainsString('data-catalog-banner',$response->getContent(),$theme.' '.$url);
    $response->assertSee('srcset="https://images.unsplash.com/mobile.jpg"',false)->assertSee('object-position:left bottom',false);
   }
  }
  $category->update(['image_settings'=>['hidden'=>true]]);
  $this->get('/categories/banner')->assertOk()->assertDontSee('data-catalog-banner',false);
 }
}
