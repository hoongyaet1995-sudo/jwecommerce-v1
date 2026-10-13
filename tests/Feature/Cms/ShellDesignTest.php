<?php
namespace Tests\Feature\Cms;
use App\Domain\Cms\ShellDesign;
use App\Domain\Cms\PageDocumentContract;
use App\Models\User;
use App\Services\StorefrontShellSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class ShellDesignTest extends TestCase
{
 use RefreshDatabase;
 public function test_menu_depth_uses_grouped_panels_and_mobile_leaf_links():void {
  $settings=app(StorefrontShellSettingsService::class)->live();
  $settings['shellDesign']=ShellDesign::defaults('service');
  app(StorefrontShellSettingsService::class)->save($settings);
  $menu=\App\Models\Menu::firstOrCreate(['key'=>'header'],['name'=>'Header']);
  $root=$menu->items()->create(['label'=>'Portfolio','url'=>'/portfolio','is_active'=>true,'position'=>99]);
  $leaf=$menu->items()->create(['parent_id'=>$root->id,'label'=>'City stories','url'=>'/city','is_active'=>true]);
  $this->get('/')->assertOk()->assertSee('data-menu-depth="1"',false)->assertDontSee('<summary>City stories</summary>',false);
  $menu->items()->create(['parent_id'=>$leaf->id,'label'=>'Garden ceremony','url'=>'/garden','is_active'=>true]);
  $this->get('/')->assertOk()->assertSee('data-menu-depth="2"',false)->assertSee('jw-shell-submenu-grouped',false)->assertSee('<summary>City stories</summary>',false)->assertSee('Garden ceremony');
 }
 public function test_service_design_round_trips_and_public_header_hides_commerce_utilities():void {
  $settings=app(StorefrontShellSettingsService::class)->live();
  $settings['shellDesign']=array_replace(ShellDesign::defaults('service'),['ctaLabel'=>'Enquire','ctaUrl'=>'/contact','tagline'=>'Our studio story.','contactEmail'=>'hello@jwstudio.com']);
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->putJson('/admin/api/storefront-shell-settings',$settings)->assertOk()->assertJsonPath('shellDesign.business','service');
  $this->getJson('/admin/api/storefront-shell-settings')->assertOk()->assertJsonPath('shellDesign.ctaUrl','/contact');
  $this->get('/')->assertOk()->assertSee('data-shell-business="service"',false)->assertSee('Our studio story.')->assertSee('Enquire')->assertDontSee('data-shell-search-toggle',false)->assertDontSee('data-cart-drawer-trigger',false)->assertDontSee('data-atelier-theme-toggle',false);
 }
 public function test_commerce_utilities_and_optional_switches_are_consumed():void {
  $settings=app(StorefrontShellSettingsService::class)->live();
  $settings['shellDesign']=array_replace(ShellDesign::defaults(),['appearance'=>'dark','showAccount'=>true,'showCurrency'=>true,'showThemeSwitch'=>true]);
  app(StorefrontShellSettingsService::class)->save($settings);
  $this->get('/')->assertOk()->assertSee('data-shell-business="commerce"',false)->assertSee('data-shell-search-toggle',false)->assertSee('data-cart-drawer-open',false)->assertSee('data-cart-count-badge',false)->assertSee('data-atelier-theme-toggle',false)->assertSee('data-shell-appearance="dark"',false);
 }
 public function test_page_override_survives_contract_and_does_not_change_global_design():void {
  $settings=['shellDesign'=>ShellDesign::defaults()];$page=ShellDesign::defaults('service');
  $this->assertSame('commerce',ShellDesign::resolve($settings,$page)['business']);
  $this->assertSame('commerce',ShellDesign::resolve($settings)['business']);
  $d=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')),true);
  $d['shellDesign']=$page;
  $this->assertSame($d,app(PageDocumentContract::class)->validate($d));
  $d['shellDesign']['ctaUrl']='javascript:alert(1)';
  try{app(PageDocumentContract::class)->validate($d);$this->fail('Unsafe destination accepted');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('shellDesign.ctaUrl',$e->errors());}
 }
 public function test_company_logo_and_dark_variant_render_without_replacing_company_settings():void {
  \App\Models\StoreSetting::create(['key'=>'shop_profile','value'=>['data'=>['shopLogo'=>'/storage/company-logo.png']]]);
  $settings=app(StorefrontShellSettingsService::class)->live();
  $settings['shellDesign']=ShellDesign::defaults('service');
  app(StorefrontShellSettingsService::class)->save($settings);
  $this->get('/')->assertOk()->assertSee('src="/storage/company-logo.png"',false)->assertSee('jw-shell-brand-logo',false);
  $settings['shellDesign']['appearance']='dark';$settings['overlayLogos']=['light'=>'/storage/cms/media/11111111-1111-4111-8111-111111111111.png','dark'=>null];
  app(StorefrontShellSettingsService::class)->save($settings);
  $this->get('/')->assertOk()->assertSee('src="/storage/cms/media/11111111-1111-4111-8111-111111111111.png"',false);
  $this->assertSame('/storage/company-logo.png',app(\App\Support\ShopBrand::class)->configuredLogo());
  \App\Models\StoreSetting::where('key','shop_profile')->update(['value'=>['data'=>['shopLogo'=>'']]]);
  $this->assertNull(app(\App\Support\ShopBrand::class)->configuredLogo());
 }
 public function test_unsafe_global_action_is_rejected_without_changing_saved_settings():void {
  $settings=app(StorefrontShellSettingsService::class)->live();$settings['shellDesign']=array_replace(ShellDesign::defaults(),['ctaUrl'=>'javascript:alert(1)']);
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->putJson('/admin/api/storefront-shell-settings',$settings)->assertUnprocessable()->assertJsonValidationErrors('shellDesign.ctaUrl');
  $this->assertNull(ShellDesign::resolve(app(StorefrontShellSettingsService::class)->live()));
 }
}

