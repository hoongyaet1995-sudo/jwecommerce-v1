<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage};
use Illuminate\Support\Str;
use App\Models\{User,Product,Category,Coupon,Page,AiConnection,StoreSetting,MarketingPost};

class MarketingAutomationManualTest extends TestCase
{
    use RefreshDatabase;

    private function setupSource(): Product
    {
        Http::preventStrayRequests(); Queue::fake(); Storage::fake('public'); Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        StoreSetting::create(['key'=>'shop_profile','value'=>['data'=>['shopName'=>'Test Company']]]);
        AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','api_key'=>'TEST-ONLY','version'=>1,'models'=>[['id'=>'gpt-5.6-luna','text'=>true],['id'=>'gpt-image-2.5-flare','image'=>true]]]);
        $p=Product::create(['title'=>'Desk Lamp','handle'=>'desk-lamp','status'=>'active','description'=>'White lamp. No stand included.','base_price'=>100]);
        $im=imagecreatetruecolor(100,100); ob_start(); imagepng($im); $png=ob_get_clean(); imagedestroy($im);
        Storage::disk('public')->put('products/lamp.png',$png); $p->images()->create(['path'=>'products/lamp.png','position'=>0]);
        return $p;
    }

    private function settings(Product $p): array
    {
        return ['id'=>(string)Str::uuid(),'version'=>0,'name'=>'Monday product ideas','preset'=>'spotlight','sources'=>['products'=>[$p->id],'categories'=>[],'collections'=>[],'promotions'=>[],'pages'=>[]], 'prompt'=>'Show a useful workspace idea.','type'=>'poster','image_mode'=>'single','cta_url'=>'https://shop.example/workspace','platforms'=>['Facebook'],'language'=>'English','tone'=>'Professional','theme'=>'#123e5a','days'=>[1,3],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur'];
    }

    public function test_editor_reuses_ai_post_cards_brand_and_platform_layout_without_floating_checks():void
    {
        $this->setupSource();$this->get('/admin/marketing/automation/ai-posts/new?preset=spotlight')->assertOk()
            ->assertSee('marketing-ai-brief-preview/brief.css')->assertSee('marketing-ai-brief-preview2/free.css')
            ->assertSee('class="brief-grid"',false)->assertSee('id="automation-types" class="content-types"',false)
            ->assertSee('id="automation-images" class="content-types"',false)->assertSee('class="platform-choices automation-days"',false)
            ->assertSee('Your brand stays recognisable')->assertSee('Test Company')->assertSee('Generation preview')
            ->assertDontSee('form-check-inline',false)->assertDontSee('form-check-input',false);
        Http::assertNothingSent();$this->assertDatabaseCount('marketing_posts',0);
    }

    public function test_unavailable_selected_sources_remain_visible_until_explicitly_removed():void
    {
        $p=$this->setupSource();$v=$this->settings($p);$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();$p->update(['status'=>'draft']);
        $this->get('/admin/marketing/automation/ai-posts/'.$v['id'].'/edit')->assertOk()->assertSee('Unavailable products #'.$p->id);
    }

    public function test_product_only_education_handoff_includes_confirmed_product_evidence():void
    {
        $p=$this->setupSource();$v=$this->settings($p);$v['type']='education';$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['product_ids'=>[$p->id],'page_ids'=>[],'promotion_ids'=>[],'reason'=>'Product knowledge'])]]]]])]);
        $id=(string)Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$v['id'].'/generate',['id'=>$id,'version'=>1])->assertOk();$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk()->assertJsonPath('status','free_prepared');
        $this->assertStringContainsString('White lamp',\App\Models\MarketingDailyDraftRun::findOrFail($id)->snapshot['facts']['confirmed_source']);Http::assertSentCount(1);
    }

    public function test_hidden_cms_sections_and_settings_are_excluded_from_source_text():void
    {
        $this->setupSource();$s=app(\App\Services\Marketing\MarketingAutomationSources::class);$m=new \ReflectionMethod($s,'pageText');$text=$m->invoke($s,['title'=>'Public title','settings'=>['label'=>'PRIVATE SETTING'],'sections'=>[['isVisible'=>false,'content'=>'HIDDEN COPY'],['isVisible'=>true,'content'=>'PUBLIC COPY','settings'=>['label'=>'PRIVATE LABEL']]]]);$this->assertStringContainsString('PUBLIC COPY',$text);$this->assertStringNotContainsString('HIDDEN COPY',$text);$this->assertStringNotContainsString('PRIVATE',$text);
    }

    public function test_selected_product_must_stay_in_its_allowed_category():void
    {
        $p=$this->setupSource();$c=Category::create(['name'=>'Office','slug'=>'office','is_active'=>true]);$p->update(['category_id'=>$c->id]);$context=['products'=>[['id'=>$p->id]],'pages'=>[],'promotions'=>[],'allowed_sources'=>['products'=>[],'categories'=>[$c->id],'collections'=>[],'promotions'=>[],'pages'=>[]]];$p->update(['category_id'=>null]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(\App\Services\Marketing\MarketingAutomationSources::class)->verify($context);
    }

    public function test_dynamic_collection_uses_current_rules_without_mutating_membership():void
    {
        $p=$this->setupSource();$p->update(['brand'=>'ProLab']);$c=\App\Models\Collection::create(['title'=>'ProLab picks','slug'=>'prolab','is_active'=>true,'type'=>'dynamic','match_type'=>'all','dynamic_rules'=>[['field'=>'vendor','operator'=>'equals','value'=>'ProLab']]]);$v=$this->settings($p);$v['sources']['products']=[];$v['sources']['collections']=[$c->id];$pool=app(\App\Services\Marketing\MarketingAutomationSources::class)->pool($v);$this->assertSame([$p->id],array_column($pool['products'],'id'));$this->assertSame(0,$c->products()->count());
    }

    public function test_verified_voucher_terms_are_retained_without_allowing_invented_prices():void
    {
        $p=$this->setupSource();$voucher=Coupon::create(['code'=>'SAVE10','name'=>'Voucher offer','discount_type'=>'fixed_amount','value'=>1000,'min_spend'=>5000,'is_active'=>true,'per_customer_limit'=>1]);
        $v=$this->settings($p);$v['sources']['promotions']=[$voucher->id];$v['type']='promotion';$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();
        $plan=['angle'=>'Workspace offer','headline'=>'A useful desk','cta'=>'Explore','variants'=>['facebook'=>'Desk Lamp. Explore the voucher offer.'],'slides'=>[['headline'=>'Workspace offer','prompt'=>'Desk Lamp with the verified voucher offer.']]];
        Http::fake(['api.openai.com/*'=>function($r)use($p,$voucher,$plan){return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(str_contains(json_encode($r->data()),'AUTOMATION_SOURCE_SELECTION')?['product_ids'=>[$p->id],'page_ids'=>[],'promotion_ids'=>[$voucher->id],'reason'=>'Current offer']:$plan)]]]]]);}]);
        $id=(string)Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$v['id'].'/generate',['id'=>$id,'version'=>1])->assertOk();for($i=0;$i<3;$i++)$r=$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();$r->assertJsonPath('status','free_text_completed');$copy=\App\Models\MarketingDailyDraftRun::findOrFail($id)->creative['variants']['facebook'];$this->assertStringContainsString('RM 10.00 off',$copy);$this->assertStringContainsString('SAVE10',$copy);$this->assertStringContainsString('Minimum spend RM 50.00',$copy);
    }

    public function test_settings_are_independent_owned_versioned_and_do_not_generate(): void
    {
        $p=$this->setupSource(); $v=$this->settings($p);
        $this->get('/admin/marketing/automation/ai-posts/create')->assertOk()->assertSee('Create Blank')->assertSee('Promotion &amp; Offers',false);
        $this->getJson('/admin/marketing/automation/sources?kind=products')->assertOk()->assertJsonPath('items',[]);
        $this->getJson('/admin/marketing/automation/sources?kind=products&q=Desk')->assertOk()->assertJsonPath('items.0.id',$p->id);
        $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk()->assertJsonPath('version',1);
        $this->get('/admin/marketing/automation/ai-posts/'.$v['id'].'/edit')->assertOk()->assertSee('Monday product ideas');
        $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertConflict();
        $v['id']=(string)Str::uuid(); $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();
        $this->assertDatabaseCount('marketing_post_automations',2);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $this->get('/admin/marketing/automation/ai-posts/'.$v['id'].'/edit')->assertNotFound();
        Http::assertNothingSent(); Queue::assertNothingPushed(); $this->assertDatabaseCount('marketing_posts',0);
    }

    public function test_selection_and_generation_save_real_drafts_once_without_publication(): void
    {
        $p=$this->setupSource(); $v=$this->settings($p); $this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();
        $plan=['angle'=>'Workspace ideas','headline'=>'A useful desk','cta'=>'Explore','variants'=>['facebook'=>'Desk Lamp.\n\nNo stand included.'],'slides'=>[['headline'=>'A useful desk','prompt'=>'A complete lamp marketing artwork.']]];
        $im=imagecreatetruecolor(1024,1280); ob_start(); imagepng($im); $png=base64_encode(ob_get_clean()); imagedestroy($im);
        Http::fake(['api.openai.com/v1/responses'=>function($request)use($p,$plan){$isSelect=str_contains(json_encode($request->data()),'AUTOMATION_SOURCE_SELECTION');return Http::response(['status'=>'completed','id'=>'test-response','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($isSelect?['product_ids'=>[$p->id],'page_ids'=>[],'promotion_ids'=>[],'reason'=>'Relevant workspace product']:$plan)]]]]]);},'api.openai.com/v1/images/edits'=>Http::response(['data'=>[['b64_json'=>$png]]])]);
        $id=(string)Str::uuid(); $url='/admin/marketing/automation/ai-posts/'.$v['id'].'/generate';
        $this->postJson($url,['id'=>$id,'version'=>1])->assertOk()->assertJsonPath('status','selection_prepared');
        for($i=0;$i<5;$i++)$result=$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();
        $result->assertJsonPath('status','completed');
        $this->postJson($url,['id'=>$id,'version'=>1])->assertOk();
        $this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk()->assertJsonPath('status','completed');
        Http::assertSentCount(3); $this->assertDatabaseCount('marketing_posts',1); $this->assertDatabaseCount('marketing_post_attempts',0);
        $post=MarketingPost::first(); $this->assertSame('Draft',$post->status); $this->assertEmpty($post->document['targets']);
        $this->assertSame($v['id'],$post->document['ai_context']['automation_id']);
        $this->get($result->json('review_url'))->assertOk()->assertSee('Workspace ideas'); Queue::assertNothingPushed();
    }

    public function test_manual_automation_works_without_enabling_global_execution():void
    {
        $p=$this->setupSource();config(['marketing.execution_enabled'=>false]);
        $this->postJson('/admin/marketing/automation/ai-posts',$this->settings($p))->assertOk();Http::assertNothingSent();
    }

    public function test_out_of_pool_ai_selection_is_durable_and_is_never_retried():void
    {
        $p=$this->setupSource();$v=$this->settings($p);$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['product_ids'=>[999999],'page_ids'=>[],'promotion_ids'=>[],'reason'=>'Invalid source'])]]]]])]);
        $id=(string)Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$v['id'].'/generate',['id'=>$id,'version'=>1])->assertOk();
        $this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk()->assertJsonPath('status','selection_unknown');
        $this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk()->assertJsonPath('status','selection_unknown');
        Http::assertSentCount(1);$this->assertDatabaseCount('marketing_daily_draft_runs',0);
    }

    public function test_changed_or_expired_voucher_blocks_generation_before_spending():void
    {
        $p=$this->setupSource();$c=Coupon::create(['code'=>'CHRISTMAS','name'=>'Christmas sale','discount_type'=>'percentage','value'=>30,'min_spend'=>5000,'is_active'=>true,'ends_at'=>now()->addDay()]);$v=$this->settings($p);$v['sources']['promotions']=[$c->id];$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();$c->update(['ends_at'=>now()->subMinute()]);$this->postJson('/admin/marketing/automation/ai-posts/'.$v['id'].'/generate',['id'=>(string)Str::uuid(),'version'=>1])->assertUnprocessable();Http::assertNothingSent();
    }

    public function test_blank_source_search_does_not_read_catalog_and_unsafe_cta_cannot_save():void
    {
        $p=$this->setupSource();\Illuminate\Support\Facades\DB::enableQueryLog();$this->getJson('/admin/marketing/automation/sources?kind=products')->assertOk()->assertJsonPath('items',[]);$this->assertFalse(collect(\Illuminate\Support\Facades\DB::getQueryLog())->contains(fn($q)=>str_contains($q['query'],'"products"')));
        $v=$this->settings($p);$v['cta_url']='https://127.0.0.1/secret';$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertUnprocessable();$this->assertDatabaseCount('marketing_post_automations',0);Http::assertNothingSent();
    }

    public function test_published_page_only_can_generate_without_inventing_a_product():void
    {
        $p=$this->setupSource();$page=Page::create(['slug'=>'our-story','title'=>'Our Studio Story','is_published'=>true,'status'=>'published']);
        $document=['title'=>'Our Studio Story','slug'=>'our-story','description'=>'We design useful everyday spaces.','template'=>'landing','heroImage'=>null,'seoTitle'=>'Our Studio Story','seoDescription'=>'Our story','sections'=>[['id'=>'story-copy','type'=>'rich_text','title'=>'Our approach','content'=>'Our team makes practical workspaces.','isVisible'=>true]]];
        $page->documents()->create(['stage'=>'published','slug'=>$page->slug,'schema_version'=>1,'lock_version'=>1,'content_hash'=>\App\Domain\Cms\PageDocumentHasher::hash($document),'document'=>$document]);
        $v=$this->settings($p);$v['sources']['products']=[];$v['sources']['pages']=[$page->id];$v['type']='storytelling';$this->postJson('/admin/marketing/automation/ai-posts',$v)->assertOk();
        $plan=['angle'=>'Our approach','headline'=>'Practical spaces','cta'=>'Explore','variants'=>['facebook'=>'Our Studio Story. Our team makes practical workspaces.'],'slides'=>[['headline'=>'Practical spaces','prompt'=>'An illustrative studio scene, no invented product or people claims.']]];
        $im=imagecreatetruecolor(1024,1280);ob_start();imagepng($im);$png=base64_encode(ob_get_clean());imagedestroy($im);
        Http::fake(['api.openai.com/v1/responses'=>function($request)use($page,$plan){return Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(str_contains(json_encode($request->data()),'AUTOMATION_SOURCE_SELECTION')?['product_ids'=>[],'page_ids'=>[$page->id],'promotion_ids'=>[],'reason'=>'Documented company story']:$plan)]]]]]);},'api.openai.com/v1/images/generations'=>Http::response(['data'=>[['b64_json'=>$png]]])]);
        $id=(string)Str::uuid();$this->postJson('/admin/marketing/automation/ai-posts/'.$v['id'].'/generate',['id'=>$id,'version'=>1])->assertOk();for($i=0;$i<5;$i++)$r=$this->postJson('/admin/marketing/automation/runs/'.$id.'/step')->assertOk();$r->assertJsonPath('status','completed');$this->assertSame([] ,MarketingPost::first()->document['ai_context']['product_ids']);Http::assertSentCount(3);
    }
}
