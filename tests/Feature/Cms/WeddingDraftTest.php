<?php
namespace Tests\Feature\Cms;
use App\Domain\Cms\PageCrudService;
use App\Domain\Cms\PageDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class WeddingDraftTest extends TestCase {
 use RefreshDatabase;
 public function test_wedding_draft_create_edit_and_refresh_retains_gallery_behavior_alt_and_stable_order():void {
  $this->assertSame('sqlite',config('database.default'),'This acceptance must use isolated SQLite.');
  $s=json_decode(file_get_contents(resource_path('admin/src/cms/weddingTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $doc=['containerWidth'=>'site','title'=>'Wedding acceptance','slug'=>'wedding-acceptance','template'=>'standard','style'=>$s['style'],'sections'=>$s['sections']];
  $page=app(PageCrudService::class)->create($doc);
  $doc=$page->draftDocument->document;
  $gallery=&$doc['sections'][2];$gallery['marqueeSpeed']=65;$gallery['marqueeDirection']='right';$gallery['marqueePauseOnHover']=false;
  $gallery['items']=array_reverse($gallery['items']);$id=$gallery['items'][0]['id'];
  $gallery['imageSlots']=[['target'=>'item:'.$id.':imageUrl','mediaAlt'=>'Approved garden photograph']];
  app(PageDraftService::class)->save($page,$doc,1);
  $fresh=$page->fresh()->draftDocument;
  $this->assertSame('site',$fresh->document['containerWidth']);
  $this->assertSame($doc,$fresh->document);$this->assertSame(2,$fresh->lock_version);$this->assertNull($page->fresh()->publishedDocument);
 }
}
