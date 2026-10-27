<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MarketingResearchFixtures;
use App\Services\Marketing\MarketingPlanningAllowance;
class MarketingPlanningAllowanceExemptionTest extends TestCase {
 use RefreshDatabase,MarketingResearchFixtures;
 public function test_only_explicit_project_can_skip_daily_limit_while_rates_remain_required():void {
  $p=$this->researchProject();config(['marketing.billing.enabled'=>true,'marketing.billing.included_daily_microusd'=>0,'marketing.billing.included_daily_unlimited_projects'=>[$p->id]]);
  app(MarketingPlanningAllowance::class)->assertAvailable($p->id,250000);$this->assertTrue(true);
  try{app(MarketingPlanningAllowance::class)->assertAvailable('other-project',50000);$this->fail('other project bypassed');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(429,$e->getStatusCode());}
  config(['marketing.billing.pricing_valid_until'=>'2000-01-01']);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingPlanningAllowance::class)->assertAvailable($p->id,50000);
 }
}
