<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Support\SupportAiPolicy;
class SupportAiPolicyTest extends TestCase
{
 public function test_master_and_channel_switches_preserve_independent_choices(): void {
  $p=new SupportAiPolicy(); $s=['autoReplyEnabled'=>true,'aiChannels'=>['facebook'=>false,'instagram'=>true]];
  $this->assertFalse($p->enabled($s,'facebook')); $this->assertTrue($p->enabled($s,'instagram'));
  $s['autoReplyEnabled']=false; $this->assertFalse($p->enabled($s,'instagram'));
  $s['autoReplyEnabled']=true; $this->assertFalse($p->enabled($s,'facebook'));
 }
 public function test_legacy_storefront_and_social_defaults_and_unknown_channels(): void {
  $p=new SupportAiPolicy();$s=['autoReplyEnabled'=>true];
  foreach(['web_guest','web_customer','facebook','instagram','whatsapp'] as $c) $this->assertTrue($p->enabled($s,$c));
  $this->assertFalse($p->enabled($s,'tiktok'));$this->assertFalse($p->enabled($s,'unknown'));
  $s['aiChannels']=['storefront'=>false];$this->assertFalse($p->enabled($s,'web_guest'));
 }
}
