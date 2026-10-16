<?php
namespace Tests\Feature\Cms;

use App\Domain\Cms\{PageCrudService,PageDraftService,PagePublicationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class IndustryTemplatePublicationTest extends TestCase
{
 use RefreshDatabase;

 public function test_every_industry_factory_can_create_save_publish_and_render_without_changing_its_design():void
 {
  $this->assertSame('sqlite',config('database.default'),'Only isolated SQLite may be used.');
  $process=new Process(['node','scripts/export-industry-publication-fixtures.mjs'],resource_path('admin'));
  $process->setTimeout(60);
  $process->mustRun();
  $fixtures=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
  $this->assertCount(9,$fixtures);
  // Production uploads already have managed-media records. Reproduce that
  // prerequisite in this isolated database without changing template URLs.
  $register=function($value) use (&$register):void {
   if(is_array($value)){foreach($value as $child){$register($child);}return;}
   if(!is_string($value)||!\App\Domain\Cms\MediaUrlPolicy::isManaged($value)){return;}
   \App\Models\CmsMediaAsset::firstOrCreate(['url'=>$value],[
    'id'=>pathinfo($value,PATHINFO_FILENAME),'disk'=>'public',
    'path'=>substr($value,strlen('/storage/')),'mime_type'=>'image/png',
    'size'=>1,'checksum'=>hash('sha256',$value),
   ]);
  };
  foreach($fixtures as $fixture){$register($fixture['document']);}
  $failures=[];
  foreach($fixtures as $fixture){
   try{
    $page=app(PageCrudService::class)->create($fixture['document']);
    $saved=$page->draftDocument->document;
    app(PageDraftService::class)->save($page,$saved,1);
    $fresh=$page->fresh();
    $this->assertSame(json_encode($saved),json_encode($fresh->draftDocument->document),$fixture['id'].' save');
    app(PagePublicationService::class)->publish($fresh,2);
    $published=$page->fresh();
    $this->assertSame('published',$published->status,$fixture['id'].' status');
    $this->assertSame(json_encode($saved),json_encode($published->publishedDocument->document),$fixture['id'].' snapshot');
    $this->get('/pages/'.$published->publishedDocument->slug)->assertOk()->assertDontSee('Server Error');
   }catch(\Illuminate\Validation\ValidationException $e){$failures[$fixture['id']]=$e->errors();}
  }
  $this->assertSame([],$failures,json_encode($failures,JSON_PRETTY_PRINT));
 }
}
