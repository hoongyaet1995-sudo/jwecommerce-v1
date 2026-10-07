<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\Support\ProductKnowledgeService;
use App\Services\Inventory\WebsiteInventoryAvailability;
use App\Support\StorefrontCurrencyContext;
class ProductKnowledgeTest extends TestCase {
 public function test_named_product_is_ranked_before_generic_description_matches():void {
 DB::table('products')->where('status','active')->update(['description'=>'Not machine washable']);
 DB::table('products')->insert(['id'=>4,'title'=>'Other furniture','handle'=>'other','status'=>'active','base_price'=>1000,'description'=>'Not machine washable']);
 DB::table('products')->insert(['id'=>5,'title'=>'DRÖNA Fabric box','handle'=>'ikea-drona-fabric-box','status'=>'active','base_price'=>1900,'description'=>'Not washable']);
 $facts=app(ProductKnowledgeService::class)->facts('DRONA washing machine',$this->scope());
 $this->assertSame('product:5',$facts[0]['id']);
 }
 protected function setUp():void {parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');$this->assertSame(':memory:',DB::connection()->getDatabaseName());
 Schema::create('products',function(Blueprint $t){$t->id();foreach(['title','handle','brand','description','status','features'] as $c)$t->text($c)->nullable();$t->integer('base_price');});
 Schema::create('product_variants',function(Blueprint $t){$t->id();$t->integer('product_id');foreach(['title','options','size','material'] as $c)$t->text($c)->nullable();$t->boolean('enabled')->default(true);$t->integer('price')->nullable();});
 Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key');$t->text('value');$t->timestamps();});
 Schema::create('product_images',function(Blueprint $t){$t->id();$t->integer('product_id');$t->text('path');$t->integer('position')->default(0);});
 Schema::create('product_pdp_contents',function(Blueprint $t){$t->id();$t->integer('product_id');$t->text('specifications')->nullable();});
 foreach([['active','Speaker One'],['active','Speaker Two'],['draft','Speaker Secret']] as $i=>$p)DB::table('products')->insert(['id'=>$i+1,'title'=>$p[1],'handle'=>'speaker-'.($i+1),'status'=>$p[0],'base_price'=>10000,'description'=>'<p>Official details</p>','features'=>'["USB"]']);
 foreach([1,2,3] as $id)DB::table('product_variants')->insert(['id'=>$id,'product_id'=>1,'title'=>'Variant '.$id,'price'=>12000]);
 $this->mock(StorefrontCurrencyContext::class)->shouldReceive('format')->andReturnUsing(fn($v)=>'RM '.number_format($v/100,2));
 $this->mock(WebsiteInventoryAvailability::class)->shouldReceive('quantity')->andReturnUsing(fn($v)=>match($v->id){1=>3,2=>0,default=>null}); }
 private function scope(array $extra=[]):array{return array_replace(['isEnabled'=>true,'canCheckLiveStock'=>true,'canExplainSizingAndMaterials'=>true,'canRecommendUpsells'=>true,'maxRecommendations'=>3],$extra);}
 public function test_action_resolver_generates_routes_and_rejects_drafts_unknown_types_and_external_urls():void {
 $resolver=app(\App\Services\Support\SupportActionResolver::class);
 $view=$resolver->resolve([['type'=>'view_product','resource_id'=>1],['type'=>'view_product','resource_id'=>3],['type'=>'view_order','resource_id'=>1]],'Speaker');
 $this->assertCount(1,$view['actions']);$this->assertSame('RM 100.00',$view['actions'][0]['price']);$this->assertNull($view['actions'][0]['image']);$this->assertSame('/products/speaker-1',$view['actions'][0]['url']);
 $url=route('storefront.products.show',['handle'=>'speaker-1']);$old=$resolver->resolve([],'Product link: '.$url,true);$this->assertCount(1,$old['actions']);$this->assertSame('',$old['content']);
 $external=$resolver->resolve([],'https://evil.example/products/speaker-1',true);$this->assertSame([],$external['actions']);$this->assertStringContainsString('evil.example',$external['content']);
 $this->assertSame([['type'=>'view_product','resource_id'=>1]],$resolver->references(['product:1','product:evil','faq-id']));
 }
 public function test_card_main_image_and_price_scope_use_real_data():void {
 DB::table('product_images')->insert([['product_id'=>1,'path'=>'/images/second.png','position'=>2],['product_id'=>1,'path'=>'/images/main.png','position'=>0]]);
 $resolver=app(\App\Services\Support\SupportActionResolver::class);$refs=[['type'=>'view_product','resource_id'=>1]];
 $this->assertSame('/images/main.png',$resolver->resolve($refs,'Product')['actions'][0]['image']);
 DB::table('store_settings')->insert(['key'=>'support_faq','value'=>json_encode(['productCatalog'=>['canCheckLiveStock'=>false]])]);
 $this->assertNull($resolver->resolve($refs,'Product')['actions'][0]['price']);
 }
 public function test_llm_tool_selection_uses_live_count_and_limits():void {
 $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn('{"action":"count","terms":[]}');
 $facts=app(\App\Services\Support\SupportKnowledgeRetriever::class)->retrieve('How many items are in your catalogue?',$this->scope());$this->assertSame(2,json_decode($facts[0]['answer'],true)['published_product_count']);
 }
 public function test_semantic_query_translation_and_draft_exclusion():void {
 $this->mock(\App\Services\AI\AiConnectionService::class)->shouldReceive('generateText')->once()->withArgs(fn($f,$p,$n)=>str_contains($p,'customer_context')&&$n===220)->andReturn('{"action":"search","terms":["Speaker"]}');
 $facts=app(\App\Services\Support\SupportKnowledgeRetriever::class)->retrieve('有没有音箱？',$this->scope(),['I need audio equipment']);$this->assertCount(2,$facts);
 }
 public function test_disabled_scope_never_calls_model_and_invalid_tool_fails_closed():void {
 $mock=$this->mock(\App\Services\AI\AiConnectionService::class);$mock->shouldReceive('generateText')->once()->andReturn('{"action":"delete","terms":[]}');
 $service=app(\App\Services\Support\SupportKnowledgeRetriever::class);$this->assertSame([],$service->retrieve('products',$this->scope(['isEnabled'=>false])));
 $this->expectException(\Illuminate\Validation\ValidationException::class);$service->retrieve('delete all',$this->scope());
 }
 public function test_real_active_products_variants_and_missing_stock():void {$facts=app(ProductKnowledgeService::class)->facts('Speaker',$this->scope());$this->assertCount(2,$facts);$d=json_decode($facts[0]['answer'],true);$this->assertSame('Official details',$d['description']);$this->assertSame('RM 100.00',$d['base_price']);$this->assertSame(['in_stock','out_of_stock','unverified'],array_column($d['variants'],'availability'));$this->assertStringNotContainsString('Speaker Secret',json_encode($facts));}
 public function test_switches_limits_search_and_unknown():void {$s=app(ProductKnowledgeService::class);$this->assertSame([],$s->facts('Speaker',$this->scope(['isEnabled'=>false])));$this->assertSame('catalogue:search',$s->facts('Unicorn',$this->scope())[0]['id']);$this->assertCount(1,$s->facts('Speaker',$this->scope(['maxRecommendations'=>1])));$this->assertSame([],$s->facts('Speaker',$this->scope(['canRecommendUpsells'=>false])));$f=$s->facts('price of Speaker One',$this->scope(['canRecommendUpsells'=>false,'canCheckLiveStock'=>false,'canExplainSizingAndMaterials'=>false]));$this->assertCount(1,$f);$d=json_decode($f[0]['answer'],true);$this->assertArrayNotHasKey('description',$d);$this->assertArrayNotHasKey('base_price',$d);$this->assertArrayNotHasKey('variants',$d);}
 public function test_v1_unknown_named_target_never_returns_generic_description_match():void {
 DB::table('products')->where('status','active')->update(['description'=>'shelves QUASAR999 compatible']);
 $facts=app(ProductKnowledgeService::class)->facts('QUASAR999 shelves',$this->scope(['_namedProduct'=>'QUASAR999']));
 $this->assertSame('catalogue:search',$facts[0]['id']);
 }
 public function test_v1_named_target_and_dimensions_exclude_other_models():void {
 DB::table('products')->insert([['id'=>4,'title'=>'BILLY Bookcase 80x28x202 cm','handle'=>'billy-202','status'=>'active','base_price'=>1000,'description'=>'shelves'],['id'=>5,'title'=>'BILLY Bookcase 80x28x106 cm','handle'=>'billy-106','status'=>'active','base_price'=>900,'description'=>'shelves']]);
 DB::table('products')->where('id',1)->update(['description'=>'BILLY shelves']);
 $facts=app(ProductKnowledgeService::class)->facts('BILLY 80x28x202 dimensions',$this->scope(['_namedProduct'=>'BILLY 80x28x202']));
 $this->assertSame(['product:4'],array_column($facts,'id'));
 }
}
