<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageRuntimePayloadFactory};
final class SectionActionNavigationTest extends TestCase {
 use RefreshDatabase;
 public function test_section_actions_use_shared_navigation_ownership_and_never_expose_drafts_in_public():void {
  $crud=app(PageCrudService::class);$source=$crud->create(['title'=>'Plans','slug'=>'plans','template'=>'standard','sections'=>[['id'=>'plans','type'=>'rich_text','title'=>'Plans','isVisible'=>true]]]);
  $consumer=$crud->create(['title'=>'Feature','slug'=>'feature','template'=>'standard','sections'=>[['id'=>'compare','type'=>'cta_strip','title'=>'Compare','isVisible'=>true,'buttonLink'=>'#section-plans','buttonText'=>'Compare plans']]]);$factory=app(PageRuntimePayloadFactory::class);$preview=$factory->forDraft($consumer);
  $this->assertSame('/admin/pages/'.$source->id.'/preview#section-plans',$preview['links']['#section-plans']);$this->assertSame('#section-plans',$consumer->draftDocument->document['sections'][0]['buttonLink']);
  $publish=function($page){$published=$page->draftDocument->replicate();$published->stage='published';$published->save();$page->update(['status'=>'published']);};$publish($consumer);
  $this->assertArrayNotHasKey('#section-plans',(array)$factory->forCanary($consumer->fresh())['links']);$publish($source);$public=$factory->forCanary($consumer->fresh());$this->assertSame('/pages/plans#section-plans',$public['links']['#section-plans']);$this->assertStringNotContainsString('/admin/',json_encode($public['links']));
 }
}
