<?php
namespace Tests\Feature;

use App\Models\{User, StoreSetting};
use App\Services\Marketing\{MarketingEmailBounce, MarketingEmailDsn, MarketingEmailImap, MarketingEmailSettings, MarketingEmailUnsubscribe};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB, Mail, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingEmailBounceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): object
    {
        Mail::fake(); Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        config(['marketing.email_bounce_enabled'=>false,'marketing.email_smtp_hosts'=>['smtp.hostinger.com']]);
        app(MarketingEmailSettings::class)->save(['version'=>0,'host'=>'smtp.hostinger.com','port'=>465,'encryption'=>'ssl','username'=>'sender@example.test','password'=>'fake-only','from_name'=>'Test store','from_address'=>'sender@example.test','reply_to'=>'','minute_limit'=>10,'hour_limit'=>100,'day_limit'=>1000,'unit_minor'=>0,'currency'=>'USD']);
        $id=(string)Str::uuid();
        DB::table('marketing_email_deliveries')->insert(['id'=>$id,'campaign_id'=>(string)Str::uuid(),'user_id'=>auth()->id(),'merchant_key'=>app(\App\Services\Marketing\Audience\AudienceDirectory::class)->merchant(),'campaign_version'=>1,'recipient'=>Crypt::encryptString('person@example.test'),'recipient_hash'=>hash_hmac('sha256','person@example.test',config('app.key')),'state'=>'accepted','link_id'=>(string)Str::uuid(),'snapshot'=>'{}','created_at'=>now(),'updated_at'=>now()]);
        return DB::table('marketing_email_deliveries')->find($id);
    }

    private function dsn(string $messageId, string $action='failed', string $status='5.1.1', string $recipient='person@example.test'): string
    {
        return "From: MAILER-DAEMON@example.test\r\nContent-Type: multipart/report; report-type=delivery-status; boundary=\"bnd\"\r\n\r\n--bnd\r\nContent-Type: text/plain\r\n\r\nNot delivered\r\n--bnd\r\nContent-Type: message/delivery-status\r\n\r\nReporting-MTA: dns; example.test\r\n\r\nFinal-Recipient: rfc822; $recipient\r\nAction: $action\r\nStatus: $status\r\n\r\n--bnd\r\nContent-Type: text/rfc822-headers\r\n\r\nMessage-ID: <$messageId>\r\nTo: $recipient\r\n\r\n--bnd--\r\n";
    }

    public function test_signed_permanent_bounce_is_deduplicated_and_suppresses(): void
    {
        $r=$this->fixture(); $s=app(MarketingEmailBounce::class);
        $mid=$s->messageId($r,'sender@example.test');
        DB::table('marketing_email_deliveries')->where('id',$r->id)->update(['snapshot'=>json_encode(['message_id'=>$mid])]);
        $raw=$this->dsn($mid);
        $this->assertSame(1,$s->ingest($raw)); $this->assertSame(0,$s->ingest($raw));
        $this->assertDatabaseCount('marketing_email_feedback',1);
        $this->assertDatabaseHas('marketing_email_deliveries',['id'=>$r->id,'state'=>'bounced','error_code'=>'hard_bounce']);
        $this->assertTrue(app(MarketingEmailUnsubscribe::class)->suppressed($r->merchant_key,'person@example.test'));
        Mail::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_invalid_token_wrong_recipient_foreign_store_and_ordinary_mail_are_ignored(): void
    {
        $r=$this->fixture(); $s=app(MarketingEmailBounce::class); $mid=$s->messageId($r,'sender@example.test');
        DB::table('marketing_email_deliveries')->where('id',$r->id)->update(['snapshot'=>json_encode(['message_id'=>$mid])]);
        $this->assertSame(0,$s->ingest($this->dsn(str_replace('jw.','xx.',$mid))));
        $this->assertSame(0,$s->ingest($this->dsn($mid,recipient:'other@example.test')));
        $this->assertSame(0,$s->ingest("Content-Type: text/plain\r\n\r\n".$this->dsn($mid)));
        DB::table('marketing_email_deliveries')->where('id',$r->id)->update(['merchant_key'=>'another-store']);
        $this->assertSame(0,$s->ingest($this->dsn($mid)));
        $this->assertDatabaseCount('marketing_email_feedback',0);
    }

    public function test_delayed_does_not_suppress_or_trigger_retry_and_malformed_reports_are_ignored(): void
    {
        $r=$this->fixture(); $s=app(MarketingEmailBounce::class); $mid=$s->messageId($r,'sender@example.test');
        DB::table('marketing_email_deliveries')->where('id',$r->id)->update(['snapshot'=>json_encode(['message_id'=>$mid])]);
        $this->assertSame(0,$s->ingest($this->dsn($mid,'delivered','2.0.0')));
        $this->assertSame(0,$s->ingest($this->dsn($mid,'failed','4.1.1')));
        $this->assertSame(1,$s->ingest($this->dsn($mid,'delayed','4.2.2')));
        $this->assertFalse(app(MarketingEmailUnsubscribe::class)->suppressed($r->merchant_key,'person@example.test'));
        $this->assertDatabaseHas('marketing_email_deliveries',['id'=>$r->id,'state'=>'delayed','error_code'=>'soft_bounce']);
        $this->assertSame([],app(MarketingEmailDsn::class)->parse(str_repeat('x',1048577)));
        Mail::assertNothingSent();
    }

    public function test_owner_only_disabled_gate_and_readonly_get(): void
    {
        $this->fixture(); $this->mock(MarketingEmailImap::class)->shouldNotReceive('scan');
        $this->getJson('/admin/marketing/settings/email/data')->assertOk()->assertJsonPath('settings.complaint_connected',false);
        $this->postJson('/admin/marketing/settings/email/bounces',['version'=>1])->assertStatus(409);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]));
        $this->postJson('/admin/marketing/settings/email/bounces',['version'=>1])->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_scan_checkpoint_survives_repeat_and_safe_error_preserves_cursor(): void
    {
        $r=$this->fixture(); $s=app(MarketingEmailBounce::class); $mid=$s->messageId($r,'sender@example.test');
        DB::table('marketing_email_deliveries')->where('id',$r->id)->update(['snapshot'=>json_encode(['message_id'=>$mid])]);
        config(['marketing.email_bounce_enabled'=>true]);
        $this->mock(MarketingEmailImap::class,function($m)use($mid){
            $m->shouldReceive('scan')->once()->withArgs(fn($c,$v,$cursor)=>$c['host']==='imap.hostinger.com'&&$c['port']===993&&$cursor===0)->andReturn(['uidvalidity'=>7,'cursor'=>50,'has_more'=>true,'scanned'=>2,'skipped_large'=>0,'messages'=>[$this->dsn($mid)]]);
        });
        $this->postJson('/admin/marketing/settings/email/bounces',['version'=>1])->assertOk()->assertJsonPath('bounces.matched',1)->assertJsonPath('bounces.has_more',true);
        $this->mock(MarketingEmailImap::class,function($m){$m->shouldReceive('scan')->once()->withArgs(fn($c,$v,$cursor)=>$v===7&&$cursor===50)->andThrow(new \RuntimeException('private password should never leak'));});
        $this->postJson('/admin/marketing/settings/email/bounces',['version'=>1])->assertOk()->assertJsonPath('bounces.error','connection_failed')->assertDontSee('private password');
        $this->assertSame(50,$s->status()['cursor']);
        Mail::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_adapter_uses_examine_peek_and_bounded_uid_window(): void
    {
        $r=$this->fixture();$mid=app(MarketingEmailBounce::class)->messageId($r,'sender@example.test');$raw=$this->dsn($mid);
        $reply=fn($data)=>new class($data){public function __construct(private array $data){}public function validatedData(){return $this->data;}};
        $p=\Mockery::mock();
        $p->shouldReceive('examineFolder')->once()->with('INBOX')->andReturn($reply(['uidvalidity'=>9,'uidnext'=>101]));
        $p->shouldReceive('fetch')->once()->with(['UID','RFC822.SIZE'],1,50)->andReturn($reply([3=>['UID'=>3,'RFC822.SIZE'=>strlen($raw)],4=>['UID'=>4,'RFC822.SIZE'=>99],5=>['UID'=>5,'RFC822.SIZE'=>2000000]]));
        $p->shouldReceive('fetch')->once()->with(['UID','BODY.PEEK[HEADER]<0.65536>'],3,3)->andReturn($reply([3=>['UID'=>3,'BODY[HEADER]<0>'=>"Content-Type: multipart/report; report-type=delivery-status\r\n"]]));
        $p->shouldReceive('fetch')->once()->with(['UID','BODY.PEEK[]<0.1048577>'],3,3)->andReturn($reply([3=>['UID'=>3,'BODY[]<0>'=>$raw]]));
        $p->shouldReceive('fetch')->once()->with(['UID','BODY.PEEK[HEADER]<0.65536>'],4,4)->andReturn($reply([4=>['UID'=>4,'BODY[HEADER]<0>'=>"Content-Type: text/plain\r\n"]]));
        $out=app(MarketingEmailImap::class)->readWindow($p,8,500);
        $this->assertSame(50,$out['cursor']);$this->assertTrue($out['has_more']);$this->assertSame(1,$out['skipped_large']);$this->assertSame([$raw],$out['messages']);
    }

    public function test_mailable_preserves_signed_id_and_return_mailbox_without_sending(): void
    {
        $r=$this->fixture();$id=app(MarketingEmailBounce::class)->messageId($r,'brand@example.test');
        $mail=new \App\Mail\MarketingCampaignTestMail(['message_id'=>$id,'bounce_address'=>'sender@example.test','from_address'=>'brand@example.test','from_name'=>'Store','subject'=>'Hello','html'=>'<p>Original</p>']);
        $this->assertSame($id,$mail->headers()->messageId);
        $mail->build();$email=new \Symfony\Component\Mime\Email();foreach($mail->callbacks as $callback)$callback($email);
        $this->assertSame('sender@example.test',$email->getReturnPath()->getAddress());Mail::assertNothingSent();
    }

    public function test_hard_bounce_is_not_downgraded_by_a_late_delayed_report(): void
    {
        $r=$this->fixture();$s=app(MarketingEmailBounce::class);$id=$s->messageId($r,'sender@example.test');
        DB::table('marketing_email_deliveries')->where('id',$r->id)->update(['snapshot'=>json_encode(['message_id'=>$id])]);
        $s->ingest($this->dsn($id));$s->ingest($this->dsn($id,'delayed','4.2.2'));
        $this->assertDatabaseHas('marketing_email_deliveries',['id'=>$r->id,'error_code'=>'hard_bounce']);
    }

    public function test_mime_encodings_folded_headers_and_ambiguous_original_ids(): void
    {
        $this->fixture();$parser=app(MarketingEmailDsn::class);$raw=$this->dsn('example-id@sender.test');
        $folded=str_replace('multipart/report; report-type','multipart/report;' . "\r\n " . 'report-type',$raw);
        $this->assertCount(1,$parser->parse($folded));
        $status="Reporting-MTA: dns; example.test\r\n\r\nFinal-Recipient: rfc822; person@example.test\r\nAction: failed\r\nStatus: 5.1.1\r\n";
        $encoded=str_replace("Content-Type: message/delivery-status\r\n\r\n".$status,"Content-Type: message/delivery-status\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode($status)."\r\n",$raw);
        $this->assertCount(1,$parser->parse($encoded));
        $this->assertSame([],$parser->parse(str_replace('Message-ID: <example-id@sender.test>',"Message-ID: <example-id@sender.test>\r\nMessage-ID: <other@sender.test>",$raw)));
    }

    public function test_wrong_revision_and_concurrent_check_do_not_access_mailbox(): void
    {
        $this->fixture();config(['marketing.email_bounce_enabled'=>true]);
        $this->mock(MarketingEmailImap::class)->shouldNotReceive('scan');
        $this->postJson('/admin/marketing/settings/email/bounces',['version'=>2])->assertStatus(409);
        $settings=app(MarketingEmailSettings::class);$key='marketing_bounce:'.hash('sha256',app(\App\Services\Marketing\MarketingPosts::class)->merchant().'|'.$settings->quotaKey($settings->raw()));
        StoreSetting::create(['key'=>$key,'value'=>['lease'=>'test','lease_until'=>time()+60,'cursor'=>5]]);
        $this->postJson('/admin/marketing/settings/email/bounces',['version'=>1])->assertStatus(409);
        $this->assertSame(5,app(MarketingEmailBounce::class)->status()['cursor']);
    }
}
