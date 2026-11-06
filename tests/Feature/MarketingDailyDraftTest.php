<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage,DB};
use App\Models\{User,Product,AiConnection};
use App\Services\Marketing\MarketingDailyDrafts;
use Illuminate\Support\Str;
class MarketingDailyDraftTest extends TestCase {
 use RefreshDatabase;
 private function setupSource():array {
  Http::preventStrayRequests(); Storage::fake('public');Storage::fake('local');
  $u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);\App\Models\StoreSetting::updateOrCreate(['key'=>'shop_profile'],['value'=>['data'=>['shopName'=>'Test Company']]]);
  $p=Product::create(['title'=>'Desk Lamp','handle'=>'lamp','status'=>'active','description'=>'White adjustable desk lamp.','base_price'=>100]);
  $img=imagecreatetruecolor(120,120);imagefill($img,0,0,imagecolorallocate($img,180,190,200));ob_start();imagejpeg($img);$bytes=ob_get_clean();imagedestroy($img);Storage::disk('public')->put('products/lamp.jpg',$bytes);$p->images()->create(['path'=>'products/lamp.jpg','position'=>0]);
  AiConnection::create(['provider'=>'openai','enabled'=>true,'verified_at'=>now(),'text_model'=>'gpt-5.6-luna','image_model'=>'gpt-image-2','api_key'=>'TEST-ONLY','base_url'=>'https://api.openai.com/v1','models'=>[['id'=>'gpt-image-2','image'=>true]],'version'=>1]);
  return [$u,$p];
 }
 private function fakeProvider(?array $override=null,bool $imageTimeout=false):void {
  $plan=['angle'=>'A calmer workspace','headline'=>'Make room for focus','cta'=>'Explore this product','background_prompt'=>'Soft cream and olive studio with clear empty space, no objects or text.','used_fact_ids'=>['title','description'],'variants'=>['facebook'=>'Meet the Desk Lamp. Explore this product.','instagram'=>'A little inspiration for your desk. Meet the Desk Lamp.','tiktok'=>'A fresh idea for your desk: Desk Lamp.']];
  $plan=$override??$plan;
  $img=imagecreatetruecolor(1024,1024);imagefill($img,0,0,imagecolorallocate($img,235,230,215));ob_start();imagepng($img);$b64=base64_encode(ob_get_clean());imagedestroy($img);
  Http::fake(['api.openai.com/v1/responses'=>Http::response(['id'=>'resp_test','status'=>'completed','usage'=>['input_tokens'=>300,'output_tokens'=>200,'input_tokens_details'=>['cached_tokens'=>0]],'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($plan)]]]]],200,['x-request-id'=>'text_test']), 'api.openai.com/v1/images/generations'=>function()use($imageTimeout,$b64){if($imageTimeout)throw new \Illuminate\Http\Client\ConnectionException('timeout');return Http::response(['data'=>[['b64_json'=>$b64]],'usage'=>['input_tokens'=>40,'input_tokens_details'=>['text_tokens'=>40,'image_tokens'=>0],'output_tokens'=>1000]],200,['x-request-id'=>'image_test']);}]);
 }
 public function test_random_eligible_source_becomes_three_persistent_real_drafts_without_publishing(){
  [$u,$p]=$this->setupSource();$this->fakeProvider();$id=(string)Str::uuid();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,$id);$this->assertSame($p->id,$r->snapshot['product_id']);$r=$s->generate($u,$id);
  $this->assertSame('completed',$r->status);$this->assertNull($r->snapshot['company_logo']);$this->assertCount(3,$r->post_ids);$this->assertDatabaseCount('marketing_posts',3);$this->assertDatabaseCount('marketing_post_media',3);$this->assertDatabaseCount('marketing_post_attempts',0);
  foreach($r->post_ids as $post){$this->assertDatabaseHas('marketing_posts',['id'=>$post,'status'=>'Draft']);}
  $again=$s->generate($u,$id);$this->assertSame($r->post_ids,$again->post_ids);Http::assertSentCount(2);
  $this->get('/admin/marketing/daily-drafts/'.$id)->assertOk()->assertSee('Make room for focus')->assertSee('Desk Lamp')->assertDontSee('TEST-ONLY');
  $this->get('/admin/marketing/posts')->assertOk();$this->getJson('/admin/marketing/posts/data')->assertOk()->assertJsonCount(3,'posts');
 }
 public function test_draft_and_missing_image_products_are_not_eligible(){
  [$u,$p]=$this->setupSource();$p->update(['status'=>'draft']);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingDailyDrafts::class)->prepare($u,(string)Str::uuid());
 }
 public function test_unknown_image_call_is_durable_and_is_not_resent(){
  [$u]=$this->setupSource();$this->fakeProvider(null,true);$s=app(MarketingDailyDrafts::class);$id=(string)Str::uuid();$s->prepare($u,$id);$r=$s->generate($u,$id);$this->assertSame('image_unknown',$r->status);$this->assertNotEmpty($r->creative['headline']);$r=$s->generate($u,$id);$this->assertSame('image_unknown',$r->status);$this->assertDatabaseCount('marketing_posts',0);Http::assertSentCount(1);
 }
 public function test_private_run_is_not_visible_to_other_admin(){
  [$u]=$this->setupSource();$r=app(MarketingDailyDrafts::class)->prepare($u,(string)Str::uuid());$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->get('/admin/marketing/daily-drafts/'.$r->id)->assertNotFound();Http::assertNothingSent();
 }
 public function test_completed_product_is_not_randomly_reused_in_thirty_day_window(){
  [$u]=$this->setupSource();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$id=(string)Str::uuid();$s->prepare($u,$id);$s->generate($u,$id);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);$s->prepare($u,(string)Str::uuid());
 }
 public function test_missing_catalog_file_never_triggers_provider(){
  [$u]=$this->setupSource();Storage::disk('public')->delete('products/lamp.jpg');try{app(MarketingDailyDrafts::class)->prepare($u,(string)Str::uuid());$this->fail('Missing image must block selection.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}Http::assertNothingSent();
 }
 public function test_invalid_fact_claim_stops_before_any_image_request(){
  [$u]=$this->setupSource();$this->fakeProvider(['angle'=>'Offer','headline'=>'Guaranteed best-selling lamp','cta'=>'Buy now','background_prompt'=>'Neutral room','used_fact_ids'=>['title'],'variants'=>['facebook'=>'Desk Lamp','instagram'=>'Desk Lamp','tiktok'=>'Desk Lamp']]);$s=app(MarketingDailyDrafts::class);$id=(string)Str::uuid();$s->prepare($u,$id);$r=$s->generate($u,$id);$this->assertSame('text_unknown',$r->status);$this->assertDatabaseCount('marketing_posts',0);Http::assertSentCount(1);
 }
 public function test_fallback_company_identity_blocks_before_paid_calls(){
  [$u]=$this->setupSource();\App\Models\StoreSetting::whereIn('key',['shop_profile','shop_name'])->delete();$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingDailyDrafts::class)->prepare($u,(string)Str::uuid());
 }
 public function test_local_render_recovery_does_not_repeat_paid_calls(){
  [$u]=$this->setupSource();$this->fakeProvider();$this->mock(\App\Services\Marketing\MarketingPostMedia::class)->shouldReceive('store')->once()->andThrow(new \RuntimeException('temporary local save failure'));$s=app(MarketingDailyDrafts::class);$id=(string)Str::uuid();$s->prepare($u,$id);$r=$s->generate($u,$id);$this->assertSame('render_needs_attention',$r->status);$this->app->forgetInstance(\App\Services\Marketing\MarketingPostMedia::class);$r=$s->generate($u,$id);$this->assertSame('completed',$r->status);$this->assertDatabaseCount('marketing_posts',3);Http::assertSentCount(2);
 }
 private function setLogo(string $path='branding/logo.png'):void {
  $im=imagecreatetruecolor(200,60);imagefill($im,0,0,imagecolorallocate($im,240,20,180));ob_start();imagepng($im);Storage::disk('public')->put($path,ob_get_clean());imagedestroy($im);
  \App\Models\StoreSetting::where('key','shop_profile')->update(['value'=>['data'=>['shopName'=>'Test Company','shopLogo'=>'/storage/'.$path]]]);
 }
 public function test_original_company_logo_is_composed_on_all_three_platform_images(){
  [$u]=$this->setupSource();$this->setLogo();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,(string)Str::uuid());
  $this->assertSame('shop_profile.data.shopLogo',$r->snapshot['company_logo']['source']??null);
  $r=$s->generate($u,$r->id);$this->assertSame('completed',$r->status);
  foreach($r->post_ids as $id){$post=\App\Models\MarketingPost::findOrFail($id);$media=DB::table('marketing_post_media')->where('id',substr($post->document['asset'],7))->first();$im=imagecreatefromjpeg(Storage::disk('local')->path($media->path));$rgb=imagecolorsforindex($im,imagecolorat($im,80,70));imagedestroy($im);$this->assertGreaterThan(210,$rgb['red']);$this->assertLessThan(50,$rgb['green']);}
  Http::assertSentCount(2);
 }
 public function test_configured_but_unreadable_logo_blocks_before_provider(){
  [$u]=$this->setupSource();$this->setLogo();Storage::disk('public')->put('branding/logo.png','not an image');
  try{app(MarketingDailyDrafts::class)->prepare($u,(string)Str::uuid());$this->fail('Invalid logo must not be silently omitted.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}Http::assertNothingSent();
 }
 public function test_recompose_uses_existing_background_and_keeps_post_ids_copy_and_receipts(){
  [$u]=$this->setupSource();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,(string)Str::uuid());$r=$s->generate($u,$r->id);$ids=$r->post_ids;$receipts=$r->receipts;$before=\App\Models\MarketingPost::findOrFail($ids['instagram'])->document;
  $this->setLogo();$r=$s->recompose($u,$r->id);$after=\App\Models\MarketingPost::findOrFail($ids['instagram']);
  $this->assertSame($ids,$r->post_ids);$this->assertSame($receipts,$r->receipts);$this->assertSame($before['copy'],$after->document['copy']);$this->assertNotSame($before['asset'],$after->document['asset']);$this->assertSame('Draft',$after->status);$this->assertDatabaseCount('marketing_posts',3);Http::assertSentCount(2);
 }
 public function test_recompose_rejects_different_company_scope_without_provider(){
  [$u]=$this->setupSource();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,(string)Str::uuid());$r=$s->generate($u,$r->id);$r->update(['merchant_key'=>'another-company']);
  $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);$s->recompose($u,$r->id);
 }
 public function test_recompose_missing_background_preserves_existing_drafts(){
  [$u]=$this->setupSource();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,(string)Str::uuid());$r=$s->generate($u,$r->id);$before=\App\Models\MarketingPost::findOrFail($r->post_ids['instagram'])->toArray();Storage::disk('local')->delete($r->snapshot['background_path']);
  try{$s->recompose($u,$r->id);$this->fail('Missing background must block local repair.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
  $this->assertSame($before,\App\Models\MarketingPost::findOrFail($r->post_ids['instagram'])->toArray());Http::assertSentCount(2);
 }
 public function test_another_company_logo_is_not_used_for_existing_run(){
  [$u]=$this->setupSource();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,(string)Str::uuid());$r=$s->generate($u,$r->id);$this->setLogo();\App\Models\StoreSetting::where('key','shop_profile')->update(['value'=>['data'=>['shopName'=>'Different Company','shopLogo'=>'/storage/branding/logo.png']]]);
  try{$s->recompose($u,$r->id);$this->fail('Different company identity must block.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}Http::assertSentCount(2);
 }
 public function test_recompose_does_not_overwrite_user_edited_draft_media(){
  [$u]=$this->setupSource();$this->fakeProvider();$s=app(MarketingDailyDrafts::class);$r=$s->prepare($u,(string)Str::uuid());$r=$s->generate($u,$r->id);$p=\App\Models\MarketingPost::findOrFail($r->post_ids['instagram']);$doc=$p->document;$doc['copy']='User revised caption';$p->update(['document'=>$doc,'version'=>2]);$before=$p->fresh()->toArray();
  try{$s->recompose($u,$r->id);$this->fail('Edited drafts require explicit review before replacing media.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(409,$e->getStatusCode());}
  $this->assertSame($before,$p->fresh()->toArray());Http::assertSentCount(2);
 }
}
