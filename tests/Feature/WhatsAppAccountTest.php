<?php

namespace Tests\Feature;

use App\Models\ManagedSocialAccount;
use App\Models\User;
use App\Services\Social\WhatsAppAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
            'services.whatsapp.app_id'=>'123','services.whatsapp.app_secret'=>'app-secret',
            'services.whatsapp.graph_version'=>'v25.0','services.facebook.merchant_key'=>'shop-a']);
        DB::purge();
        $this->artisan('migrate',['--path'=>'database/migrations/2026_10_04_120000_create_managed_social_accounts.php','--force'=>true]);
        \Illuminate\Support\Facades\Schema::table('managed_social_accounts',function(\Illuminate\Database\Schema\Blueprint $t){$t->boolean('inbound_enabled')->default(false);$t->timestamp('inbound_verified_at')->nullable();$t->string('messaging_error')->nullable();});
        if (file_exists(database_path('migrations/2026_10_06_110000_add_whatsapp_account_metadata.php'))) (require database_path('migrations/2026_10_06_110000_add_whatsapp_account_metadata.php'))->up();
        Http::preventStrayRequests();
        Http::fake(function($request){
            if(str_contains($request->url(),'/debug_token'))return Http::response(['data'=>['is_valid'=>true,'app_id'=>'123','scopes'=>['whatsapp_business_management','whatsapp_business_messaging']]]);
            return Http::response(['data'=>[['id'=>'456','display_phone_number'=>'+60 123','verified_name'=>'JW']]]);
        });
    }

    public function test_marketing_reconnect_preserves_existing_support_subscription(): void
    {
        $service=app(WhatsAppAccountService::class);$a=$service->importLegacy($this->values(),$this->actor());$a->update(['inbound_enabled'=>true,'inbound_verified_at'=>now()]);
        $saved=$service->importLegacy($this->values(),$this->actor(),true);
        $this->assertSame($a->id,$saved->id);$this->assertTrue($saved->inbound_enabled);$this->assertNotNull($saved->inbound_verified_at);$this->assertSame(1,ManagedSocialAccount::count());
    }

    private function values(): array {return ['app_id'=>'123','waba_id'=>'789','phone_number_id'=>'456','access_token'=>'private-token'];}
    private function actor(): User {return (new User)->forceFill(['id'=>1,'is_super_admin'=>true]);}

    public function test_import_is_encrypted_idempotent_and_safe_to_serialize(): void
    {
        $service=app(WhatsAppAccountService::class);
        $account=$service->importLegacy($this->values(),$this->actor());
        $this->assertSame(['shop-a','123','789'],[$account->fresh()->merchant_key,$account->fresh()->app_id,$account->fresh()->waba_id]);
        $again=$service->importLegacy($this->values(),$this->actor());
        $this->assertSame($account->id,$again->id);
        $this->assertSame(1,ManagedSocialAccount::count());
        $this->assertNotSame('private-token',$account->getRawOriginal('access_token'));
        $this->assertSame('private-token',$account->access_token);
        $this->assertSame('789',$account->waba_id);
        $this->assertStringNotContainsString('private-token',json_encode($service->list()));
        $this->assertSame('migrated_sender',$service->list()[0]['connection_method']);
    }

    public function test_other_merchant_cannot_claim_existing_phone(): void
    {
        $service=app(WhatsAppAccountService::class);
        $service->importLegacy($this->values(),$this->actor());
        config(['services.facebook.merchant_key'=>'shop-b']);
        $this->expectExceptionMessage('account_owner_conflict');
        $service->importLegacy($this->values(),$this->actor());
    }

    public function test_wrong_token_app_is_rejected_before_persistence(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['data'=>['is_valid'=>true,'app_id'=>'999','scopes'=>['whatsapp_business_management','whatsapp_business_messaging']]])]);
        try {app(WhatsAppAccountService::class)->importLegacy($this->values(),$this->actor());$this->fail('must reject');}
        catch(\RuntimeException $e){$this->assertSame('authorization_expired',$e->getMessage());}
        $this->assertSame(0,ManagedSocialAccount::count());
    }

    public function test_phone_must_belong_to_waba(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(function($request){return Http::response(str_contains($request->url(),'/debug_token')?['data'=>['is_valid'=>true,'app_id'=>'123','scopes'=>['whatsapp_business_management','whatsapp_business_messaging']]]:['data'=>[['id'=>'999']]]);});
        $this->expectExceptionMessage('account_identity_mismatch');
        app(WhatsAppAccountService::class)->importLegacy($this->values(),$this->actor());
    }
}

