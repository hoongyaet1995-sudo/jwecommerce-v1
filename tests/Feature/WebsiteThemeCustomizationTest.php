<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\{WebsiteDesignService,WebsiteThemeCustomization};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteThemeCustomizationTest extends TestCase
{
    use RefreshDatabase;
    public function test_media_contracts_expose_only_renderer_backed_surfaces(): void
    {
        $service=app(WebsiteThemeCustomization::class);
        foreach (['default','home_deco','beauty'] as $theme) {
            $this->assertSame(['account'],$service->mediaContracts()[$theme]['account']);
            $this->assertSame(['account','login'],$service->mediaContracts()[$theme]['login']);
            $this->assertArrayNotHasKey('plp',$service->mediaContracts()[$theme]);
            $this->assertArrayNotHasKey('pdp',$service->mediaContracts()[$theme]);
        }
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/website-design')->assertOk()->assertJsonPath('mediaContracts.beauty.register',['account','register']);
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>['beauty'=>['media'=>['plp'=>['desktop'=>'/test.png']]]]])->assertUnprocessable();
        $this->assertSame('fallback',$service->media('unsupported','login','fallback')['desktop']);
    }
    public function test_saved_theme_profiles_and_page_inheritance_survive_theme_switch(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $profiles=['beauty'=>['style'=>['font'=>'modern','size'=>'big','colorMode'=>'custom','main'=>'#ffffff','sub'=>'#ead8da'],'pages'=>['login'=>['size'=>'small']],'media'=>['account'=>['desktop'=>'/images/themes/beauty/serum-front.png','mobile'=>'','alt'=>'Brand image','focus'=>'right','hidden'=>false]]]];
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profiles])->assertOk()->assertJsonPath('settings.customization.beauty.style.font','modern');
        $service=app(WebsiteThemeCustomization::class);
        $this->assertSame('small',$service->style('beauty','login')['size']);
        $this->assertSame('modern',$service->style('beauty','login')['font']);
        $this->assertSame('right',$service->media('beauty','register','fallback')['focus']);
        $this->get('/account/login')->assertOk()->assertSee('Brand image')->assertSee('object-position:right center',false)->assertSee('data-theme-customization',false);
        $this->putJson('/admin/api/website-design',['theme'=>'default','overrides'=>[]])->assertOk()->assertJsonPath('settings.customization.beauty.style.font','modern');
        $this->assertSame('#000000',$service->foreground('#ffffff'));
    }
    public function test_preview_does_not_persist_and_rejects_unsafe_values(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $draft=['home_deco'=>['style'=>['font'=>'editorial'],'media'=>['account'=>['desktop'=>'/images/themes/beauty/serum-front.png','hidden'=>false]]]];
        $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>'home_deco','surface'=>'login','customization'=>json_encode($draft)]))->assertOk()->assertSee('Lora')->assertSee('/images/themes/beauty/serum-front.png',false);
        $this->assertSame([],app(WebsiteDesignService::class)->live()['customization']);
        $draft['home_deco']['media']['account']['desktop']='javascript:alert(1)';
        $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>[],'customization'=>$draft])->assertUnprocessable();
        $this->putJson('/admin/api/website-design',['theme'=>'home_deco','overrides'=>[],'customization'=>['home_deco'=>['style'=>['font'=>'arbitrary-css']]]])->assertUnprocessable();
    }
    public function test_reset_removes_only_configuration_and_keeps_theme_identity(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>['beauty'=>['style'=>[],'media'=>[]]]])->assertOk();
        $this->get('/account/login')->assertOk()->assertSee('/images/cms-samples/beauty-hero.png',false);
        $this->assertSame('beauty',app(WebsiteDesignService::class)->resolve());
    }

    public function test_managed_theme_media_is_referenced_and_released_without_deleting_upload(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        \Illuminate\Support\Facades\Storage::fake('public');
        $upload=$this->post('/admin/api/media-assets',['file'=>\Illuminate\Http\UploadedFile::fake()->image('brand.png')]);
        $upload->assertCreated();$asset=$upload->json('data');
        $profiles=['beauty'=>['media'=>['account'=>['desktop'=>$asset['url']]]]];
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profiles])->assertOk();
        $this->assertDatabaseHas('catalog_media_references',['asset_id'=>$asset['id'],'owner_type'=>'website_theme']);
        $this->assertNull(\App\Models\CmsMediaAsset::findOrFail($asset['id'])->orphaned_at);
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>[]])->assertOk();
        $this->assertDatabaseMissing('catalog_media_references',['asset_id'=>$asset['id'],'owner_type'=>'website_theme']);
        $this->assertDatabaseHas('cms_media_assets',['id'=>$asset['id']]);
    }

    public function test_brand_palette_is_live_reference_and_reset_preview_is_disabled(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $id=(string)\Illuminate\Support\Str::uuid();
        \App\Models\StoreSetting::create(['key'=>'cms_palette_library','value'=>['data'=>[['id'=>$id,'name'=>'Company Brand','primary'=>'#123456','secondary'=>'#eeeeee','colors'=>['secondary'=>'#eeeeee']]]]]);
        $profile=['beauty'=>['style'=>['colorMode'=>'brand','paletteId'=>$id]]];
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profile])->assertOk();
        $this->assertSame('#123456',app(WebsiteThemeCustomization::class)->style('beauty')['main']);
        $this->get('/admin/website-design/preview?theme=beauty&surface=reset')->assertOk()->assertSee('Choose a new password')->assertSee('design-preview-only')->assertSee('disabled',false);
        $profile['beauty']['style']['paletteId']=(string)\Illuminate\Support\Str::uuid();
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profile])->assertUnprocessable();
    }

    public function test_all_nine_focal_positions_round_trip_and_legacy_focus_keeps_middle(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach (['left','center','right'] as $x) foreach (['top','middle','bottom'] as $y) {
            $profile=['beauty'=>['media'=>['account'=>['focus'=>$x,'focusY'=>$y]]]];
            $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profile])->assertOk()->assertJsonPath('settings.customization.beauty.media.account.focusY',$y);
            $this->get('/account/login')->assertOk()->assertSee('object-position:'.$x.' '.($y==='middle'?'center':$y),false);
        }
        $profile=['beauty'=>['media'=>['account'=>['focus'=>'right']]]];
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profile])->assertOk();
        $this->assertSame('middle',app(WebsiteThemeCustomization::class)->media('beauty','login','')['focusY']);
        $profile['beauty']['media']['account']['focusY']='arbitrary';
        $this->putJson('/admin/api/website-design',['theme'=>'beauty','overrides'=>[],'customization'=>$profile])->assertUnprocessable();
    }
}
