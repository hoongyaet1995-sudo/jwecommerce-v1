<?php
namespace Tests\Feature;
use App\Models\StoreSetting;
use App\Services\CmsThemePalette;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;
class CmsThemePaletteTest extends TestCase
{
 protected function setUp():void{parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key');$t->text('value')->nullable();$t->timestamps();});}
 public function test_global_custom_palette_excludes_page_specific_overrides():void{
  StoreSetting::create(['key'=>'website_design','value'=>['data'=>['theme'=>'logistics','customization'=>['profiles'=>['logistics'=>['style'=>['colorMode'=>'custom','main'=>'#112233','sub'=>'#ffaa00'],'pages'=>['plp'=>['main'=>'#ff0000']]]]]]]]);
  $result=app(CmsThemePalette::class)->current();$this->assertSame('#112233',$result['palette']['accent']);$this->assertSame('#ffaa00',$result['palette']['secondary']);
 }
 public function test_theme_mode_ignores_stale_custom_values_and_brand_resolves_library():void{
  $s=StoreSetting::create(['key'=>'website_design','value'=>['data'=>['theme'=>'logistics','customization'=>['profiles'=>['logistics'=>['style'=>['colorMode'=>'theme','main'=>'#ff0000']]]]]]]);
  $this->assertSame('#173247',app(CmsThemePalette::class)->current()['main']);
  StoreSetting::create(['key'=>'cms_palette_library','value'=>['data'=>[['id'=>'brand','primary'=>'#114422','secondary'=>'#aa5500']]]]);
  $s->update(['value'=>['data'=>['theme'=>'logistics','customization'=>['profiles'=>['logistics'=>['style'=>['colorMode'=>'brand','paletteId'=>'brand']]]]]]]);
  $this->assertSame('#114422',app(CmsThemePalette::class)->current()['palette']['accent']);
 }
 public function test_palette_matches_shared_frontend_generation_and_schema_roundtrip():void{
  $palette=app(CmsThemePalette::class)->generate('#173247','#ef7b32');
  $this->assertSame('#f6f7f8',$palette['paper']);
  $document=['title'=>'Brand','slug'=>'brand','template'=>'standard','style'=>['colorSource'=>'theme','palette'=>$palette],'sections'=>[]];
  $this->assertSame('theme',app(\App\Domain\Cms\PageDocumentContract::class)->validate($document)['style']['colorSource']);
  $document['style']['colorSource']='invalid';
  $this->expectException(\Illuminate\Validation\ValidationException::class);app(\App\Domain\Cms\PageDocumentContract::class)->validate($document);
 }
 public function test_ai_inventory_matches_canonical_types_and_surface_gates():void{
  $manifest=json_decode(file_get_contents(resource_path('cms/page-contract.v1.json')),true);
  $capabilities=app(\App\Services\AI\CmsAiCapabilities::class)->all();
  $this->assertSame(array_keys($manifest['section']['sectionTypes']),array_keys($capabilities));
  $this->assertCount(8,array_filter($capabilities,fn($s)=>$s['newComposition']));
  $this->assertSame(['default'],$capabilities['hero_banner']['surfaces']);
  $this->assertContains('contrast',$capabilities['cta_strip']['surfaces']);
 }
}
