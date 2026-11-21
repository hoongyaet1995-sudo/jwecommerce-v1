<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use App\Domain\Cms\ShellDesign;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
final class FooterConvergenceTest extends TestCase {
 use RefreshDatabase;
 public function test_footer_uses_shared_layout_and_keeps_saved_cta_out_of_footer():void {
  $design=array_replace(ShellDesign::defaults('service'),['brandName'=>'Merchant','tagline'=>'Saved description','contactEmail'=>'hello@example.test','ctaLabel'=>'Preserved header action','ctaUrl'=>'/contact']);
  $group=new MenuItem(['label'=>'Company','is_active'=>true]);$group->setRelation('children',collect([new MenuItem(['label'=>'Policy','url'=>'/policy','is_active'=>true,'badge'=>'NEW','badge_color'=>'#4338ca']),new MenuItem(['label'=>'Hidden','url'=>'/hidden','is_active'=>false])]));
  $html=view('components.storefront.designed-footer',['design'=>$design,'footerSections'=>collect([$group]),'shopBrandName'=>'Fallback'])->render();
  $this->assertStringContainsString('cms-footer-navigation',$html);$this->assertStringContainsString('Saved description',$html);$this->assertStringContainsString('NEW',$html);$this->assertStringNotContainsString('Preserved header action',$html);$this->assertStringNotContainsString('Hidden',$html);$this->assertSame('Preserved header action',$design['ctaLabel']);
 }
 public function test_surface_foreground_remains_readable_and_legacy_settings_round_trip():void {
  $this->assertSame('#ffffff',ShellDesign::footerForeground('#18232b'));
  $this->assertSame('#151515',ShellDesign::footerForeground('#ffffff'));
  $settings=app(\App\Services\StorefrontShellSettingsService::class)->live();
  $settings['shellDesign']=array_replace(ShellDesign::defaults('service'),['brandName'=>'Merchant','tagline'=>'Saved description','contactEmail'=>'hello@example.test','ctaLabel'=>'Preserved header action','ctaUrl'=>'/contact','style'=>'soft','appearance'=>'dark','footerLayout'=>'compact']);
  $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]))->putJson('/admin/api/storefront-shell-settings',$settings)->assertOk();
  $this->getJson('/admin/api/storefront-shell-settings')->assertOk()->assertJsonPath('shellDesign.ctaLabel','Preserved header action')->assertJsonPath('shellDesign.style','soft')->assertJsonPath('shellDesign.appearance','dark')->assertJsonPath('shellDesign.contactEmail','hello@example.test')->assertJsonPath('shellDesign.footerLayout','compact');
 }

 public function test_compact_moves_only_the_legal_group_to_the_bottom():void {
  $group=new MenuItem(['label'=>'Legal','is_active'=>true]);
  $group->setRelation('children',collect([new MenuItem(['label'=>'Privacy Policy','url'=>'/pages/privacy-policy','is_active'=>true])]));
  $design=array_replace(ShellDesign::defaults(),['footerLayout'=>'compact','contactEmail'=>'hello@example.test']);
  $html=view('components.storefront.designed-footer',['design'=>$design,'footerSections'=>collect([$group]),'shopBrandName'=>'Merchant'])->render();
  $this->assertStringContainsString('data-footer-layout="compact"',$html);
  $this->assertStringNotContainsString('class="cms-footer-navigation"',$html);
  $this->assertMatchesRegularExpression('/cms-footer-legal.*Privacy Policy/s',$html);
  $this->assertSame('classic',ShellDesign::resolve([],['business'=>'service'])['footerLayout']);
 }
}
