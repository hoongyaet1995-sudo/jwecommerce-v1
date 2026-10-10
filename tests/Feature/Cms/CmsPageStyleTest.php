<?php
namespace Tests\Feature\Cms;
use App\Domain\Cms\PageDocumentContract;
use App\Models\User;
use App\Services\CmsPaletteLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class CmsPageStyleTest extends TestCase {
 use RefreshDatabase;
 private function palette():array{return ['paper'=>'#f7f6f2','ink'=>'#292b24','muted'=>'#6d7267','accent'=>'#454e37','light'=>'#f7f6f2','secondary'=>'#dad7c7','secondaryInk'=>'#292b24','hover'=>'#343c29'];}
 public function test_style_and_surface_contract_roundtrip():void {$doc=['title'=>'Style','slug'=>'style','template'=>'standard','style'=>['palette'=>$this->palette(),'typography'=>['title'=>['fontFamily'=>'lora','fontSize'=>38]],'buttonRadius'=>6],'sections'=>[['id'=>'s','type'=>'rich_text','title'=>'Title','isVisible'=>true,'surface'=>'contrast','brandPalette'=>$this->palette()]]];$this->assertSame($doc,app(PageDocumentContract::class)->validate($doc));$doc['style']['typography']['title']['fontFamily']='unapproved';$this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate($doc);}
 public function test_named_library_retains_other_records_and_updates_snapshots_only():void {$lib=app(CmsPaletteLibrary::class);$a=['id'=>'a','name'=>'Company','primary'=>'#454e37','secondary'=>null,'colors'=>$this->palette()];$lib->save($a);$lib->save([...$a,'id'=>'b','name'=>'Christmas']);$lib->save([...$a,'name'=>'Company updated']);$this->assertCount(2,$lib->all());$this->assertSame('Christmas',$lib->all()[0]['name']);$this->assertSame('Company updated',$lib->all()[1]['name']);$this->expectException(ValidationException::class);$lib->save([...$a,'id'=>'c','name'=>'Christmas']);}
 public function test_api_is_authenticated_and_validates_palette():void {$this->getJson('/admin/api/cms/palettes')->assertUnauthorized();$this->actingAs(User::factory()->create(['is_admin'=>true]));$this->postJson('/admin/api/cms/palettes',['id'=>'bad','name'=>'Name','primary'=>'red','secondary'=>null,'colors'=>[]])->assertUnprocessable();}

 public function test_all_registered_section_styles_roundtrip_and_draft_retains_style():void {
  $doc=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-style.v1.json')),true,512,JSON_THROW_ON_ERROR);
  $this->assertSame($doc,app(PageDocumentContract::class)->validate($doc));
  $page=app(\App\Domain\Cms\PageCrudService::class)->create(['title'=>'Style draft','slug'=>'style-draft','template'=>'standard','style'=>$doc['style'],'sections'=>[['id'=>'s','type'=>'rich_text','title'=>'Title','isVisible'=>true]]]);
  $this->assertSame($doc['style'],$page->draftDocument->document['style']);
 }
}
