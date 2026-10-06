<?php
namespace Tests\Feature;
use App\Models\{Customer,SupportConversation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BackfillSupportCustomersTest extends TestCase {
 use RefreshDatabase;
 public function test_dry_run_does_not_write_and_apply_is_idempotent():void {
  SupportConversation::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'owner_hash'=>hash('sha256','guest'),'status'=>'open','created_at'=>'2026-09-01 10:00:00','updated_at'=>'2026-09-02 10:00:00','last_message_at'=>'2026-09-02 10:00:00']);
  $this->artisan('social-crm:backfill')->assertSuccessful();$this->assertSame(0,Customer::count());
  $this->artisan('social-crm:backfill',['--apply'=>true])->assertSuccessful();$this->assertSame(1,Customer::count());
  $presence=\App\Models\CustomerPlatformPresence::first();$this->assertSame('2026-09-01',$presence->first_seen_at->toDateString());$this->assertSame('2026-09-02',$presence->last_seen_at->toDateString());
  $this->artisan('social-crm:backfill',['--apply'=>true])->assertSuccessful();$this->assertSame(1,Customer::count());
 }
}
