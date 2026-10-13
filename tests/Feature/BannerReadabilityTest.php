<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BannerReadabilityTest extends TestCase
{
 use RefreshDatabase;
 public function test_overlay_text_styles_are_local_validated_and_hide_with_image():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  foreach(['beauty','food'] as $theme){
   $profile=[$theme=>['media'=>['allProducts'=>['desktop'=>'/images/cms-samples/wedding-hero.png','textMode'=>'light','textColor'=>'#aabbcc','overlay'=>'dark','overlayStrength'=>45]]]];
   $this->putJson('/admin/api/website-design',['theme'=>$theme,'overrides'=>[],'customization'=>$profile])->assertOk();
   $this->get('/shop')->assertOk()->assertSee('data-banner-readability',false)->assertSee('#ffffff',false)->assertSee('rgba(0,0,0,0.45)',false);
   $profile[$theme]['media']['allProducts']['textMode']='custom';
   $profile[$theme]['media']['allProducts']['overlay']='none';
   $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>$theme,'surface'=>'allProducts','customization'=>json_encode($profile)]))->assertOk()->assertSee('#aabbcc',false)->assertDontSee('<div class="banner-readability-overlay"',false);
   $profile[$theme]['media']['allProducts']['hidden']=true;
   $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>$theme,'surface'=>'allProducts','customization'=>json_encode($profile)]))->assertOk()->assertDontSee('data-banner-readability',false);
   $profile[$theme]['media']['allProducts']['overlayStrength']=101;
   $this->putJson('/admin/api/website-design',['theme'=>$theme,'overrides'=>[],'customization'=>$profile])->assertUnprocessable();
  }
 }
}
