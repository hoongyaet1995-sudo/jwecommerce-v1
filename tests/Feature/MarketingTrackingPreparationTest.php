<?php
namespace Tests\Feature;

use App\Models\{MarketingCampaign,MarketingChannelConnection,MarketingPost,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingTrackingPreparationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $accountChanges=[]): array
    {
        Http::preventStrayRequests();
        config(['services.facebook.client_id'=>'tracking-test','app.url'=>'http://localhost','marketing.execution_enabled'=>false]);
        $user=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]); $this->actingAs($user);
        $merchant=app(\App\Services\Marketing\MarketingPosts::class)->merchant();
        $account=MarketingChannelConnection::create(array_merge(['merchant_key'=>$merchant,'platform'=>'facebook','provider_account_id'=>(string)random_int(10000,99999),'app_id'=>'tracking-test','name'=>'Connected test Page','access_token'=>'FAKE-NO-PROVIDER','permissions'=>['pages_show_list','pages_read_engagement'],'authorized_by'=>$user->id,'state'=>'connected'], $accountChanges));
        $campaign=MarketingCampaign::create(['merchant_key'=>$merchant,'user_id'=>$user->id,'name'=>'Link preparation','objective'=>'Purchases','status'=>'Draft','starts_on'=>now()->toDateString(),'ends_on'=>now()->toDateString(),'timezone'=>'UTC','destination_url'=>'http://localhost/shop?category=test#items','cta'=>'Shop now','version'=>1]);
        $post=MarketingPost::create(['merchant_key'=>$merchant,'user_id'=>$user->id,'campaign_id'=>$campaign->id,'version'=>1,'status'=>'Draft','timezone'=>'UTC','document'=>['title'=>'Draft','copy'=>'Shared original','targets'=>[$account->id],'copies'=>[$account->id=>'Account-specific original'],'type'=>'Post']]);
        return [$user,$account,$campaign,$post];
    }

    private function prepare($post,$account,int $version=1)
    {
        return $this->postJson('/admin/marketing/campaigns/links',['post_id'=>$post->id,'destination_id'=>$account->id,'version'=>$version]);
    }

    public function test_preparation_is_local_account_specific_and_idempotent_even_on_double_click(): void
    {
        [$user,$account,$campaign,$post]=$this->fixture();
        $first=$this->prepare($post,$account)->assertOk();
        $url=$first->json('link.url');
        $again=$this->prepare($post,$account)->assertOk();
        $this->assertSame($url,$again->json('link.url'));
        $this->assertDatabaseCount('marketing_tracking_links',1);
        $doc=$post->fresh()->document;
        $this->assertSame('Shared original',$doc['copy']);
        $this->assertSame("Account-specific original\n".$url,$doc['copies'][$account->id]);
        $this->assertSame(2,$post->fresh()->version);
        $this->assertSame('Draft',$post->fresh()->status);
        $this->assertDatabaseCount('marketing_post_attempts',0);
        Http::assertNothingSent();
    }

    public function test_unavailable_foreign_expired_and_unsupported_accounts_are_rejected(): void
    {
        foreach ([['state'=>'disconnected'],['merchant_key'=>'another-business'],['expires_at'=>now()->subMinute()],['platform'=>'instagram','permissions'=>['instagram_basic','pages_read_engagement']]] as $changes) {
            [, $account,,$post]=$this->fixture($changes);
            $this->prepare($post,$account)->assertUnprocessable();
        }
        $this->assertDatabaseCount('marketing_tracking_links',0);
        Http::assertNothingSent();
    }

    public function test_stale_draft_and_locked_publication_do_not_gain_links(): void
    {
        [,$account,,$post]=$this->fixture();
        $this->prepare($post,$account,0)->assertStatus(409);
        $post->update(['status'=>'Scheduled']);
        $this->prepare($post,$account)->assertStatus(409);
        $this->assertDatabaseCount('marketing_tracking_links',0);
        Http::assertNothingSent();
    }

    public function test_changed_landing_creates_new_snapshot_without_changing_old_link(): void
    {
        [,$account,$campaign,$post]=$this->fixture();
        $old=$this->prepare($post,$account)->assertOk()->json('link');
        $campaign->update(['destination_url'=>'http://localhost/new-page','version'=>2]);
        $this->prepare($post,$account,1)->assertStatus(409);
        $new=$this->prepare($post,$account,2)->assertOk()->json('link');
        $this->assertNotSame($old['url'],$new['url']);
        $this->assertDatabaseHas('marketing_tracking_links',['id'=>$old['id'],'destination_url'=>'http://localhost/shop?category=test#items']);
        $this->assertStringNotContainsString($old['url'],$post->fresh()->document['copies'][$account->id]);
        Http::assertNothingSent();
    }

    public function test_two_accounts_keep_separate_links_and_posts_save_retains_preparation(): void
    {
        [$user,$first,,$post]=$this->fixture();
        config(['services.youtube.client_id'=>'youtube-test']);
        $second=MarketingChannelConnection::create(['merchant_key'=>$first->merchant_key,'platform'=>'youtube','provider_account_id'=>'UC-local-test','app_id'=>'youtube-test','name'=>'Saved YouTube channel','access_token'=>'FAKE-VIDEO-TOKEN','permissions'=>['https://www.googleapis.com/auth/youtube.readonly'],'authorized_by'=>$user->id,'state'=>'connected']);
        $doc=$post->document; $doc['targets'][]=$second->id; $post->update(['document'=>$doc]);
        $snapshot=$first->getRawOriginal('access_token');
        $a=$this->prepare($post,$first)->assertOk()->json('link.url');
        $b=$this->prepare($post,$second,2)->assertOk()->json('link.url');
        $this->assertNotSame($a,$b);
        $doc=$post->fresh()->document;
        $this->assertStringNotContainsString($b,$doc['copies'][$first->id]);
        $this->assertStringNotContainsString($a,$doc['copies'][$second->id]);
        $this->postJson('/admin/marketing/posts/data/draft',array_merge($doc,['id'=>$post->id,'version'=>3,'tz'=>'UTC','campaign_links'=>['forged'=>'https://evil.example']]))->assertOk();
        $this->assertSame($a,$post->fresh()->document['campaign_links'][$first->id]);
        $this->assertArrayHasKey($second->id,$post->fresh()->document['campaign_link_context']);
        $this->assertSame($snapshot,$first->fresh()->getRawOriginal('access_token'));
        $this->getJson('/admin/marketing/campaigns/data')->assertOk()->assertDontSee('FAKE-VIDEO-TOKEN')->assertDontSee('FAKE-NO-PROVIDER');
        $this->assertDatabaseCount('marketing_tracking_links',2);
        Http::assertNothingSent();
    }

    public function test_unselected_account_and_other_owner_and_missing_edit_permission_cannot_prepare(): void
    {
        [$user,$account,,$post]=$this->fixture();
        $doc=$post->document; $doc['targets']=[]; $post->update(['document'=>$doc]);
        $this->prepare($post,$account)->assertUnprocessable();
        $other=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $this->actingAs($other); $this->prepare($post,$account)->assertNotFound();
        $user->update(['is_super_admin'=>false]);
        foreach(['marketing.view','marketing.manage_campaigns'] as $permission)$user->permissions()->create(['permission'=>$permission]);
        $this->actingAs($user->fresh()); $this->prepare($post,$account)->assertForbidden();
        $this->assertDatabaseCount('marketing_tracking_links',0);
        Http::assertNothingSent();
    }

    public function test_full_post_copy_is_not_silently_expanded_beyond_the_draft_limit(): void
    {
        [,$account,,$post]=$this->fixture();
        $doc=$post->document; $doc['copies'][$account->id]=str_repeat('x',10000); $post->update(['document'=>$doc]);
        $this->prepare($post,$account)->assertUnprocessable();
        $this->assertDatabaseCount('marketing_tracking_links',0);
        $this->assertSame(1,$post->fresh()->version);
        Http::assertNothingSent();
    }

    public function test_fake_hub_saved_identity_flows_through_posts_campaign_and_link_preparation(): void
    {
        [$user,$existing,$campaign]=$this->fixture();
        $hub=$this->mock(\App\Services\Social\SocialHubOAuth::class);
        $hub->shouldReceive('fingerprint')->andReturn('local-fake-hub');
        $hub->shouldReceive('installation')->andReturn((object)['registered_domain'=>'127.0.0.1:8500']);
        $hub->shouldReceive('call')->once()->with('select',\Mockery::any(),'facebook')->andReturn(['permissions'=>['pages_show_list','pages_read_engagement'],'page'=>['id'=>'new-saved-page','name'=>'Saved via test authorization','access_token'=>'FAKE-HUB-TOKEN','tasks'=>[]]]);
        $pending=['actor'=>$user->id,'merchant'=>$existing->merchant_key,'app'=>'tracking-test','installation'=>'local-fake-hub','transaction'=>(string)Str::uuid(),'expires'=>now()->addMinutes(5)->timestamp,'pages'=>[['id'=>'new-saved-page','name'=>'Saved via test authorization']]];
        $this->withSession(['facebook_marketing_oauth'=>['actor'=>$user->id,'platform'=>'facebook','expires'=>now()->addMinutes(5)->timestamp],'facebook_pending'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode($pending))])
            ->postJson('/admin/marketing/channels/facebook/select',['account_id'=>'new-saved-page'])->assertOk();
        $account=MarketingChannelConnection::where('provider_account_id','new-saved-page')->firstOrFail();
        Http::fake(['https://graph.facebook.com/*'=>Http::response(['id'=>'new-saved-page','name'=>'Verified saved Page'])]);
        $this->postJson('/admin/marketing/channels/facebook/'.$account->id.'/check')->assertOk();
        $this->assertSame('connected',$account->fresh()->state);
        $payload=['id'=>(string)Str::uuid(),'version'=>0,'title'=>'Local linked draft','copy'=>'Original shared copy','copies'=>[],'targets'=>[$account->id],'type'=>'Post','tz'=>'UTC'];
        $this->postJson('/admin/marketing/posts/data/draft',$payload)->assertOk();
        $post=MarketingPost::findOrFail($payload['id']);
        $this->postJson('/admin/marketing/campaigns/data/'.$campaign->id.'/posts',['version'=>1,'post_ids'=>[$post->id]])->assertOk();
        $link=$this->prepare($post,$account,2)->assertOk()->json('link');
        $this->getJson('/admin/marketing/posts/data')->assertOk()->assertSee($link['id'])->assertDontSee('FAKE-HUB-TOKEN');
        $this->assertSame('Draft',$post->fresh()->status);
        $this->assertDatabaseCount('marketing_channel_connections',2);
        $this->assertDatabaseCount('marketing_post_attempts',0);
        Http::assertSentCount(1); // The identity check is faked; preparing the link makes no provider request.
    }
}
