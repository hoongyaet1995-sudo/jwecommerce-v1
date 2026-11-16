<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
final class MarketingPrivacySessionTest extends TestCase
{
 use RefreshDatabase;
 private function boot(string $path='/'):array {
  $r=$this->get($path)->assertOk();preg_match('/<script id="jw-marketing-consent"[^>]*>(.*?)<\/script>/s',$r->getContent(),$m);$this->assertNotEmpty($m);return json_decode($m[1],true,flags:JSON_THROW_ON_ERROR);
 }
 public function test_first_prompt_is_claimed_for_the_session_without_granting_consent():void {
  Http::preventStrayRequests();config(['marketing.attribution_enabled'=>true]);$this->assertTrue($this->boot()['showPrompt']);$this->assertNull(session('marketing.consent'));
  $this->assertFalse($this->boot('/shop')['showPrompt']);$this->assertFalse($this->boot()['showPrompt']);
  $this->postJson('/marketing/tracking/page',['navigation_id'=>Str::uuid()])->assertOk()->assertJsonPath('tracked',false);$this->assertDatabaseCount('marketing_analytics_events',0);Http::assertNothingSent();
 }
 public function test_existing_choices_are_retained_and_not_prompted_again():void {
  config(['marketing.attribution_enabled'=>true]);foreach([true,false] as $choice){$expiry=now()->addDays(5)->timestamp;$this->withSession(['marketing'=>['consent'=>$choice,'consent_expires_at'=>$expiry]]);$boot=$this->boot();$this->assertSame($choice,$boot['accepted']);$this->assertFalse($boot['showPrompt']);$this->assertSame($expiry,session('marketing.consent_expires_at'));}
 }
 public function test_disabled_collection_does_not_consume_the_prompt_and_expiry_allows_a_new_choice():void {
  config(['marketing.attribution_enabled'=>false]);$this->assertFalse($this->boot()['showPrompt']);$this->assertNull(session('marketing.privacy_prompt_shown'));
  config(['marketing.attribution_enabled'=>true]);$this->withSession(['marketing'=>['consent'=>true,'consent_expires_at'=>now()->subSecond()->timestamp,'privacy_prompt_shown'=>true]]);$boot=$this->boot();$this->assertNull($boot['accepted']);$this->assertTrue($boot['showPrompt']);
 }
 public function test_choice_saves_continue_to_apply_across_pages():void {
  config(['marketing.attribution_enabled'=>true]);$this->boot();
  foreach([true,false] as $choice){$this->postJson('/marketing/tracking/consent',['accepted'=>$choice])->assertOk();$boot=$this->boot('/shop');$this->assertSame($choice,$boot['accepted']);$this->assertFalse($boot['showPrompt']);}
 }
}
