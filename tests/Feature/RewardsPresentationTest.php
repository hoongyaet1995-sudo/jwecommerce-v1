<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Services\Rewards\RewardsPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_program_never_promises_coins(): void
    {
        $this->assertNull(app(RewardsPresentation::class)->estimate(44900));
    }

    public function test_estimate_uses_net_merchandise_best_active_offer_and_cap(): void
    {
        StoreSetting::updateOrCreate(['key'=>'rewards_rules'], ['value'=>['enabled'=>true,'coins_per_unit'=>100]]);
        foreach ([['id'=>'a','rate'=>5,'maximum_reward'=>2000],['id'=>'b','rate'=>2,'maximum_reward'=>10000]] as $offer) {
            StoreSetting::create(['key'=>'rewards_offer:'.$offer['id'],'value'=>$offer+['enabled'=>true,'name'=>'Reward','minimum_spend'=>0]]);
        }
        $result=app(RewardsPresentation::class)->estimate(44900,1250);
        $this->assertSame(2000,$result['coins']);
        $this->assertSame('a',$result['offer_id']);
        $this->assertSame(43650,$result['eligible_amount']);
        $this->assertNull(app(RewardsPresentation::class)->estimate(100,100));
    }

    public function test_expired_and_future_offers_are_excluded(): void
    {
        StoreSetting::create(['key'=>'rewards_rules','value'=>['enabled'=>true,'coins_per_unit'=>100]]);
        StoreSetting::create(['key'=>'rewards_offer:a','value'=>['enabled'=>true,'rate'=>5,'maximum_reward'=>10000,'ends_at'=>now()->subDay()->toIso8601String()]]);
        $this->assertNull(app(RewardsPresentation::class)->estimate(44900));
    }
}
