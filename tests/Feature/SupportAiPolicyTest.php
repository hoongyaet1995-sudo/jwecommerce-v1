<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\SupportAiPolicy;
class SupportAiPolicyTest extends TestCase
{
 public function test_channels_are_authoritative_without_a_hidden_master_gate(): void {
  $p=new SupportAiPolicy(); $s=['autoReplyEnabled'=>true,'aiChannels'=>['facebook'=>false,'instagram'=>true]];
  $this->assertFalse($p->enabled($s,'facebook')); $this->assertTrue($p->enabled($s,'instagram'));
  $s['autoReplyEnabled']=false; $this->assertTrue($p->enabled($s,'instagram'));
  $s['autoReplyEnabled']=true; $this->assertFalse($p->enabled($s,'facebook'));
 }
 public function test_legacy_storefront_and_social_defaults_and_unknown_channels(): void {
  $p=new SupportAiPolicy();$s=['autoReplyEnabled'=>true];
  foreach(['web_guest','web_customer','facebook','instagram','whatsapp'] as $c) $this->assertTrue($p->enabled($s,$c));
  $this->assertFalse($p->enabled($s,'tiktok'));$this->assertFalse($p->enabled($s,'unknown'));
  $s['aiChannels']=['storefront'=>false];$this->assertFalse($p->enabled($s,'web_guest'));
 }
 public function test_conversion_preserves_previously_paused_effective_states():void {
  config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
  \Illuminate\Support\Facades\DB::purge('sqlite');
  \Illuminate\Support\Facades\Schema::create('store_settings',function($t){$t->id();$t->string('key');$t->json('value');$t->timestamps();});
  \Illuminate\Support\Facades\DB::table('store_settings')->insert(['key'=>'support_faq','value'=>json_encode(['version'=>4,'autoReplyEnabled'=>false,'aiChannels'=>['facebook'=>true],'identity'=>['botName'=>'Keep']])]);
  (require database_path('migrations/2026_10_08_140000_convert_support_ai_to_channel_controls.php'))->up();
  $value=json_decode(\Illuminate\Support\Facades\DB::table('store_settings')->value('value'),true);
  $this->assertSame(5,$value['version']);$this->assertFalse($value['aiChannels']['facebook']);$this->assertSame('Keep',$value['identity']['botName']);
  $value['aiChannels']['facebook']=true;$this->assertTrue((new SupportAiPolicy())->enabled($value,'facebook'));$this->assertFalse((new SupportAiPolicy())->enabled($value,'whatsapp'));
 }
}
