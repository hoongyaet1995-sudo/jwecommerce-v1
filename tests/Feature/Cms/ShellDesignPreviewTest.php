<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{ShellDesign,PageDocumentHasher,HomepageSelection};
use App\Models\{Page,User,StoreSetting};
final class ShellDesignPreviewTest extends TestCase {
 use RefreshDatabase;
 public function test_preview_uses_published_home_and_never_writes_settings_or_documents():void {
  $page=Page::create(['status'=>'published','published_at'=>now()]);
  $doc=['title'=>'Published Home','slug'=>'home-preview','template'=>'standard','sections'=>[['id'=>'hero','type'=>'hero_slider','title'=>'Real Home hero','isVisible'=>true]],'shellDesign'=>ShellDesign::defaults('commerce')];
  $snapshot=$page->documents()->create(['stage'=>'published','slug'=>$doc['slug'],'schema_version'=>1,'lock_version'=>1,'content_hash'=>PageDocumentHasher::hash($doc),'document'=>$doc]);
  StoreSetting::create(['key'=>HomepageSelection::KEY,'value'=>['pageId'=>(string)$page->id]]);
  $before=app(\App\Services\StorefrontShellSettingsService::class)->live();
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson('/admin/api/storefront-shell-preview',['shellDesign'=>ShellDesign::defaults('service'),'headerPresentation'=>['mode'=>'transparent','tone'=>'light']])->assertOk()->assertSee('Real Home hero')->assertSee('data-header-mode="transparent"',false)->assertSee('data-shell-business="service"',false)->assertDontSee('data-shell-search-toggle',false)->assertSee('data-atelier-theme-root',false);
  $this->assertSame('',\App\Domain\Cms\ShellDesign::resolve(['shellDesign'=>['ctaLabel'=>null]])['ctaLabel']);
  $this->assertSame($doc,$snapshot->fresh()->document);$this->assertSame($before,app(\App\Services\StorefrontShellSettingsService::class)->live());
 }
 public function test_preview_requires_admin_and_safe_inputs():void {
  $input=['shellDesign'=>ShellDesign::defaults(),'headerPresentation'=>['mode'=>'normal','tone'=>'dark']];
  $this->postJson('/admin/api/storefront-shell-preview',$input)->assertUnauthorized();
  $input['shellDesign']['ctaUrl']='javascript:alert(1)';
  $this->actingAs(User::factory()->create(['is_admin'=>true]))->postJson('/admin/api/storefront-shell-preview',$input)->assertUnprocessable();
 }
}
