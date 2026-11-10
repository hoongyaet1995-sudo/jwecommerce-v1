<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{CmsNavigationLinks,PageCrudService};
use App\Models\{PageDocument,StoreSetting};
final class CmsNavigationLinksTest extends TestCase {
 use RefreshDatabase;
 private function source(string $slug='home') {return app(PageCrudService::class)->create(['title'=>'Home','slug'=>$slug,'template'=>'standard','sections'=>[['id'=>'plans','type'=>'rich_text','title'=>'Plans','isVisible'=>true]]]);}
 public function test_preview_resolves_cross_page_owner_but_keeps_current_and_non_cms_anchors():void {
  $home=$this->source();$payload=['context'=>['mode'=>'preview'],'page'=>['document'=>['sections'=>[['id'=>'builder','isVisible'=>true]]]]];
  $map=app(CmsNavigationLinks::class)->forPayload($payload,['#section-plans','#section-builder','#storefront-support-widget','https://example.com/#section-plans']);
  $this->assertSame(['#section-plans'=>'/admin/pages/'.$home->id.'/preview#section-plans'],$map);
 }
 public function test_public_uses_only_published_owners_and_home_selection():void {
  $home=$this->source();$resolver=app(CmsNavigationLinks::class);$urls=['#section-plans'];$this->assertSame([],$resolver->forPayload(null,$urls));
  $published=$home->draftDocument->replicate();$published->stage=PageDocument::STAGE_PUBLISHED;$published->save();$home->update(['status'=>'published']);
  $this->assertSame('/pages/home#section-plans',$resolver->forPayload(null,$urls)['#section-plans']);
  StoreSetting::updateOrCreate(['key'=>'storefront_homepage'],['value'=>['pageId'=>(string)$home->id]]);$this->assertSame('/#section-plans',$resolver->forPayload(null,$urls)['#section-plans']);
  $home->update(['status'=>'archived']);$this->assertSame([],$resolver->forPayload(null,$urls));
 }
 public function test_ambiguous_non_home_anchors_are_not_silently_guessed():void {
  $this->source('a');$this->source('b');$this->assertSame([],app(CmsNavigationLinks::class)->forPayload(['context'=>['mode'=>'preview']],['#section-plans']));
 }
 public function test_existing_page_paths_resolve_to_preview_only_in_preview_context():void {
  $p=$this->source('feature');$resolver=app(CmsNavigationLinks::class);$this->assertSame(['/pages/feature'=>'/admin/pages/'.$p->id.'/preview'],$resolver->forPayload(['context'=>['mode'=>'preview']],['/pages/feature','https://example.org/pages/feature']));$this->assertSame([],$resolver->forPayload(null,['/pages/feature']));$p->update(['status'=>'archived']);$this->assertSame([],$resolver->forPayload(['context'=>['mode'=>'preview']],['/pages/feature']));
 }
 public function test_footer_uses_same_cross_page_resolution_without_writing_menus():void {
  $p=$this->source();$menu=\App\Models\Menu::create(['key'=>'footer','name'=>'Footer']);$group=\App\Models\MenuItem::create(['menu_id'=>$menu->id,'label'=>'Links','url'=>'#section-plans','is_active'=>true]);$child=\App\Models\MenuItem::create(['menu_id'=>$menu->id,'parent_id'=>$group->id,'label'=>'Pricing','url'=>'#section-plans','is_active'=>true]);$payload=['context'=>['mode'=>'preview'],'page'=>['document'=>['sections'=>[]]]];$html=\Illuminate\Support\Facades\Blade::render('<x-storefront.footer :navigation-context="$payload" :design="$design" />',['payload'=>$payload,'design'=>\App\Domain\Cms\ShellDesign::defaults('service')]);$this->assertStringContainsString('/admin/pages/'.$p->id.'/preview#section-plans',$html);$this->assertSame('#section-plans',$child->fresh()->url);
 }
}
