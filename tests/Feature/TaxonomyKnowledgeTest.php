<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\Support\TaxonomyKnowledgeService;
class TaxonomyKnowledgeTest extends TestCase {
 protected function setUp():void{parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');$this->assertSame(':memory:',DB::connection()->getDatabaseName());
 Schema::create('categories',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug');$t->text('description')->nullable();$t->boolean('is_active');});Schema::create('collections',function(Blueprint $t){$t->id();$t->string('title');$t->string('slug');$t->text('description')->nullable();$t->boolean('is_active');});Schema::create('products',function(Blueprint $t){$t->id();$t->integer('category_id');$t->string('title');$t->string('status');});Schema::create('collection_product',function(Blueprint $t){$t->integer('collection_id');$t->integer('product_id');});
 DB::table('categories')->insert([['id'=>1,'name'=>'Audio','slug'=>'audio','is_active'=>true],['id'=>2,'name'=>'Secret','slug'=>'secret','is_active'=>false]]);DB::table('collections')->insert(['id'=>1,'title'=>'Studio','slug'=>'studio','is_active'=>true]);DB::table('products')->insert([['id'=>1,'category_id'=>1,'title'=>'Speaker','status'=>'active'],['id'=>2,'category_id'=>1,'title'=>'Draft secret','status'=>'draft']]);DB::table('collection_product')->insert([['collection_id'=>1,'product_id'=>1],['collection_id'=>1,'product_id'=>2]]);
 }
 public function test_general_browsing_returns_visible_taxonomy():void{
 $this->assertCount(2,app(TaxonomyKnowledgeService::class)->facts('What categories and collections can I browse in your store?',['isEnabled'=>true,'canRecommendUpsells'=>true]));
 }
 public function test_only_visible_taxonomy_and_published_membership_are_returned():void{
 $s=app(TaxonomyKnowledgeService::class);$scope=['isEnabled'=>true,'canRecommendUpsells'=>true];$facts=$s->facts('',$scope);$this->assertCount(2,$facts);foreach($facts as $f){$data=json_decode($f['answer'],true);$this->assertSame(1,$data['published_product_count']);$this->assertCount(1,$data['products']);$this->assertStringNotContainsString('Draft secret',$f['answer']);}
 $this->assertSame([],$s->facts('',['isEnabled'=>false,'canRecommendUpsells'=>true]));$this->assertSame([],$s->facts('secret',$scope));$refs=app(\App\Services\Support\SupportActionResolver::class)->references(['category:1','collection:1']);$this->assertCount(2,$refs);$actions=app(\App\Services\Support\SupportActionResolver::class)->resolve($refs,'')['actions'];$this->assertSame('/categories/audio',$actions[0]['url']);$this->assertSame('/collections/studio',$actions[1]['url']);
 }
}
