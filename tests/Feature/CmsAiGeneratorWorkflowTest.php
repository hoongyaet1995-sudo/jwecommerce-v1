<?php
namespace Tests\Feature;
use App\Models\{AiConnection,User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Cache,DB,Http,Schema};
use Tests\TestCase;

class CmsAiGeneratorWorkflowTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(true);$t->boolean('is_super_admin')->default(true);$t->timestamp('email_verified_at')->nullable();$t->rememberToken();$t->timestamps();});
        Schema::create('store_settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->text('value')->nullable();$t->timestamps();});
        Schema::create('products',function(Blueprint $t){$t->id();$t->string('title');$t->string('handle');$t->string('subtitle')->nullable();$t->string('status');});
        Schema::create('categories',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug');$t->boolean('is_active');});
        (require database_path('migrations/2026_10_02_180000_create_ai_connections.php'))->up();
        AiConnection::create(['provider'=>'openai','api_key'=>'fixture-secret','enabled'=>true,'builder_enabled'=>true,'support_enabled'=>false,'text_model'=>'gpt-fixture','verified_at'=>now(),'models'=>[['id'=>'gpt-fixture','text'=>true,'image'=>false]],'version'=>1]);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));Http::preventStrayRequests();Cache::flush();
    }
    public function test_every_open_layout_uses_a_valid_canonical_document_and_real_gallery_media():void
    {
        $facts=['brand'=>app(\App\Services\CmsThemePalette::class)->current(),'supportUrl'=>'/#storefront-support-widget','existingImage'=>'/storage/cms/media/11111111-1111-4111-8111-111111111111.png','existingImages'=>['/storage/cms/media/11111111-1111-4111-8111-111111111111.png','/storage/cms/media/22222222-2222-4222-8222-222222222222.png'],'products'=>[['id'=>'1']],'faqs'=>[['question'=>'Confirmed question','answer'=>'Confirmed answer']]];
        $document=app(\App\Services\AI\CmsAiSectionCatalog::class)->seed($this->doc(),$facts);$registry=app(\App\Services\AI\CmsAiCapabilities::class)->all();$count=0;
        foreach($document['sections'] as $section){$this->assertTrue($registry[$section['type']]['newComposition']);foreach($registry[$section['type']]['aiLayouts']['values'] as $layout){$candidate=$document;foreach($candidate['sections'] as &$item)if($item['id']===$section['id'])$item[$registry[$section['type']]['aiLayouts']['field']]=$layout;unset($item);$this->assertSame($candidate['title'],app(\App\Domain\Cms\PageDocumentContract::class)->validate($candidate)['title']);$count++;}}
        $this->assertSame(22,$count);$gallery=collect($document['sections'])->firstWhere('type','media_gallery');$this->assertSame($facts['existingImages'],array_column($gallery['items'],'imageUrl'));
    }
    private function doc():array{return ['title'=>'Demo','slug'=>'demo','template'=>'standard','sections'=>[]];}
    private function fake(bool $questions=false):void
    {
        Http::fake(['api.openai.com/v1/responses'=>function($request)use($questions){
            $prompt=$request['input'];$sections=json_decode(substr($prompt,strrpos($prompt,'Sections: ')+10),true);
            if(str_contains($prompt,'Write editable CMS copy')){$sections=json_decode(substr($prompt,strrpos($prompt,'Source: ')+8),true);$out=['sections'=>array_map(fn($s)=>['id'=>$s['id'],'title'=>'Ready '.$s['title'],'content'=>'Confirmed copy.'],$sections)];}
            else{$out=['summary'=>'A considered business page','questions'=>($questions&&!str_contains($prompt,'Confirmed merchant answers:'))?[['id'=>'audience','type'=>'multiple_select','question'=>'Who is this for?','required'=>true,'options'=>['Business','Consumer']]]:[],'warnings'=>[],'sections'=>array_map(fn($s)=>['id'=>$s['id'],'purpose'=>'Explain this offering.'],array_slice($sections,0,2))];}
            return Http::response(['output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($out)]]]]]);
        }]);
    }
    public function test_brief_questions_answer_revision_and_real_canonical_candidate():void
    {
        $this->fake(true);$base=['action'=>'brief','document'=>$this->doc(),'direction'=>'A professional page.','mode'=>'create'];
        $r=$this->postJson('/admin/api/cms/ai-generator',$base)->assertOk()->json('data');
        $this->assertCount(2,$r['document']['sections']);$this->assertSame('multiple_select',$r['brief']['questions'][0]['type']);
        $bound=['document'=>$r['document'],'direction'=>$r['direction'],'token'=>$r['token']];
        $this->postJson('/admin/api/cms/ai-generator',$bound+['action'=>'generate'])->assertUnprocessable();
        $this->postJson('/admin/api/cms/ai-generator',$bound+['action'=>'answer','answers'=>['audience'=>['Unknown']]])->assertUnprocessable();
        $this->fake();$updated=$this->postJson('/admin/api/cms/ai-generator',$bound+['action'=>'answer','answers'=>['audience'=>['Business']]])->assertOk()->json('data');
        $this->assertCount(0,$updated['brief']['questions']);$this->assertSame(['Business'],$updated['answerHistory'][0]['answer']);
        $generated=$this->postJson('/admin/api/cms/ai-generator',['action'=>'generate','document'=>$updated['document'],'direction'=>$updated['direction'],'token'=>$updated['token']])->assertOk()->json('data.document');
        $this->assertStringStartsWith('Ready ',$generated['sections'][0]['title']);
    }
    public function test_expired_changed_and_other_user_briefs_cannot_generate():void
    {
        $this->fake();$r=$this->postJson('/admin/api/cms/ai-generator',['action'=>'brief','document'=>$this->doc(),'direction'=>'Plan','mode'=>'create'])->assertOk()->json('data');
        $request=['action'=>'generate','document'=>$r['document'],'direction'=>$r['direction'],'token'=>$r['token']];
        $changed=$request;$changed['document']['title']='Different';$this->postJson('/admin/api/cms/ai-generator',$changed)->assertUnprocessable();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->postJson('/admin/api/cms/ai-generator',$request)->assertUnprocessable();
    }
    public function test_generated_image_is_managed_and_idempotent_for_one_request():void
    {
        (require database_path('migrations/2026_08_20_121000_create_cms_media_assets.php'))->up();
        \Illuminate\Support\Facades\Storage::fake('public');
        $connection=AiConnection::first();$connection->image_model='gpt-image-2';$connection->models=[['id'=>'gpt-fixture','text'=>true,'image'=>false],['id'=>'gpt-image-2','text'=>false,'image'=>true]];$connection->save();
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6XbUAAAAASUVORK5CYII=');
        $this->mock(\App\Services\AI\CmsAiImageClient::class)->shouldReceive('generate')->once()->andReturn(['bytes'=>$png,'usage'=>['total_tokens'=>10]]);
        $this->fake();$r=$this->postJson('/admin/api/cms/ai-generator',['action'=>'brief','document'=>$this->doc(),'direction'=>'Plan','mode'=>'create'])->assertOk()->json('data');
        $request=['action'=>'image','document'=>$r['document'],'direction'=>$r['direction'],'token'=>$r['token'],'sectionId'=>$r['document']['sections'][0]['id'],'imageTarget'=>'mediaUrl','imagePrompt'=>'Illustrative scene','imageModel'=>'gpt-image-2','quality'=>'medium','ratio'=>'16:9','requestId'=>(string)\Illuminate\Support\Str::uuid()];
        $first=$this->postJson('/admin/api/cms/ai-generator',$request)->assertOk()->json('data');
        $second=$this->postJson('/admin/api/cms/ai-generator',$request)->assertOk()->json('data');
        $this->assertSame($first['url'],$second['url']);$this->assertSame($first['url'],$first['document']['sections'][0]['mediaUrl']);$this->assertDatabaseCount('cms_media_assets',1);
        $asset=\App\Models\CmsMediaAsset::first();\Illuminate\Support\Facades\Storage::disk('public')->assertExists($asset->path);
    }
 public function test_quality_checks_real_product_destinations_without_provider_calls():void{
  DB::table('products')->insert(['title'=>'Visible','handle'=>'visible','status'=>'active']);
  $doc=$this->doc();$doc['sections']=[['id'=>'one','type'=>'cta_strip','title'=>'Contact','isVisible'=>true,'buttonText'=>'Go','buttonLink'=>'/products/visible']];
  $this->postJson('/admin/api/cms/ai-generator',['action'=>'quality','document'=>$doc,'direction'=>'Check'])->assertOk()->assertJsonCount(0,'data.issues');
  $doc['sections'][0]['buttonLink']='/products/missing';$this->postJson('/admin/api/cms/ai-generator',['action'=>'quality','document'=>$doc,'direction'=>'Check'])->assertOk()->assertJsonPath('data.issues.0.severity','warning');
  $doc['sections'][0]['buttonLink']='/admin/pages';$this->postJson('/admin/api/cms/ai-generator',['action'=>'quality','document'=>$doc,'direction'=>'Check'])->assertOk()->assertJsonPath('data.issues.0.severity','block');
  $doc['sections']=[['id'=>'listing','type'=>'product_listing','title'=>'Range','isVisible'=>true,'sourceType'=>'manual','productIds'=>['999']]];$this->postJson('/admin/api/cms/ai-generator',['action'=>'quality','document'=>$doc,'direction'=>'Check'])->assertOk()->assertJsonPath('data.issues.0.id','catalog-listing');
  Http::assertNothingSent();
 }
}
