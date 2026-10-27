<?php

namespace Tests\Feature;

use App\Models\MarketingPreparationAuthorization;
use App\Models\MarketingRevision;
use App\Models\MarketingStoryboardProviderCall;
use App\Services\Marketing\MarketingImagePricing;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingStoryboardProvider;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingSynchronousImageBudgetTest extends TestCase
{
    use MarketingResearchFixtures;

    public function test_standard_cost_does_not_apply_batch_or_cached_input_discount(): void
    {
        $pricing = app(MarketingImagePricing::class);
        $this->assertSame(43000, $pricing->cost(['input_tokens_details' => ['text_tokens' => 1000, 'image_tokens' => 1000], 'output_tokens' => 1000]));
        $this->assertNull($pricing->cost(['input_tokens_details' => ['text_tokens' => 1], 'output_tokens' => 1]));
        $this->assertNull($pricing->cost(['input_tokens_details' => ['text_tokens' => -1, 'image_tokens' => 0], 'output_tokens' => 1]));
        $this->assertNull($pricing->cost(['input_tokens_details' => ['text_tokens' => 1.2, 'image_tokens' => 0], 'output_tokens' => 1]));
    }

    public function test_unknown_image_input_bound_blocks_before_transport(): void
    {
        $pricing = app(MarketingImagePricing::class);
        $this->assertSame(1078, $pricing->outputTokens(864, 1536, 'medium'));
        $this->assertNull($pricing->upperBound('Host', '864x1536', ['unverified-reference.png']));
        $this->assertNull($pricing->upperBound('Host', '864x1536', []));
    }

    private function setupGrant(int $maxCalls = 14, int $total = 4000000): array
    {
        $this->artisan('migrate:fresh')->run();
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['title' => 'Pilot', 'visual' => 'Fictional host', 'duration' => 5, 'voiceover' => '', 'subtitle' => '']]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $s = app(MarketingStoryboardWorkspace::class)->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $a = app(MarketingPreparationBudget::class)->authorize($p->id, $p->owner_id, (string) Str::uuid(), $s['story_version'], [$s['scenes'][0]['revision_id']], $total);
        config(['marketing.storyboard.live_enabled' => true, 'marketing.storyboard.live_grant' => ['reference' => 'FAKE ONLY synchronous budget test', 'project_id' => $p->id, 'owner_id' => $p->owner_id, 'roles' => ['image'], 'expires_at' => now()->addHour()->toIso8601String(), 'max_calls' => $maxCalls, 'max_total_microusd' => $total], 'marketing.storyboard.roles.image' => ['model' => 'gpt-image-2', 'version' => 'sync-standard-2026-10-22', 'cap_microusd' => 500000, 'pricing_verified_at' => now()->toIso8601String(), 'billing_mode' => 'standard', 'text_usd_per_million' => 5, 'image_usd_per_million' => 8, 'output_usd_per_million' => 30]]);

        return [$p, $a, ['revision_id' => $s['scenes'][0]['revision_id']]];
    }

    public function test_request_cap_is_reserved_before_http_and_cached_result_does_not_repeat_post(): void
    {
        [$p,$a,$payload] = $this->setupGrant();
        $sent = 0;
        $send = function () use (&$sent, $a) {
            $sent++;
            $this->assertSame(500000, MarketingPreparationAuthorization::findOrFail($a['id'])->reserved_microusd);

            return ['actual_microusd' => 43000];
        };
        $svc = app(MarketingStoryboardProvider::class);
        $svc->call('image', $p->id, $a['id'], $payload, $send);
        $svc->call('image', $p->id, $a['id'], $payload, $send);
        $this->assertSame(1, $sent);
        $this->assertSame(43000, MarketingPreparationAuthorization::findOrFail($a['id'])->spent_microusd);
    }

    public function test_unknown_cost_blocks_next_request_and_does_not_release_the_reservation(): void
    {
        [$p,$a,$payload] = $this->setupGrant();
        $sent = 0;
        $svc = app(MarketingStoryboardProvider::class);
        try {
            $svc->call('image', $p->id, $a['id'], $payload, function () use (&$sent) {
                $sent++;

                return ['actual_microusd' => null];
            });
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        try {
            $svc->call('image', $p->id, $a['id'], $payload + ['attempt' => 1], function () use (&$sent) {
                $sent++;

                return ['actual_microusd' => 1];
            });
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(1, $sent);
        $this->assertSame(500000, MarketingPreparationAuthorization::findOrFail($a['id'])->unknown_microusd);
    }

    public function test_count_and_total_limit_block_before_the_next_transport(): void
    {
        [$p,$a,$payload] = $this->setupGrant(1, 500000);
        $sent = 0;
        $svc = app(MarketingStoryboardProvider::class);
        $send = function () use (&$sent) {
            $sent++;

            return ['actual_microusd' => 43000];
        };
        $svc->call('image', $p->id, $a['id'], $payload, $send);
        try {
            $svc->call('image', $p->id, $a['id'], $payload + ['attempt' => 1], $send);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(1, $sent);
        $this->assertSame(1, MarketingStoryboardProviderCall::count());
    }

    public function test_stale_batch_rate_is_rejected_before_transport(): void
    {
        [$p,$a,$payload] = $this->setupGrant();
        config(['marketing.storyboard.roles.image.output_usd_per_million' => 15]);
        $this->expectException(HttpException::class);
        app(MarketingStoryboardProvider::class)->call('image', $p->id, $a['id'], $payload, fn () => throw new \LogicException('must not send'));
    }

    public function test_unknown_request_bound_is_blocked_without_claim_or_post(): void
    {
        [$p,$a,$payload] = $this->setupGrant();
        config(['marketing.storyboard.roles.image.enforce_request_bound' => true]);
        try {
            app(MarketingStoryboardProvider::class)->call('image', $p->id, $a['id'], $payload + ['request_upper_bound_microusd' => null], fn () => throw new \LogicException('must not send'));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
        }
        $this->assertSame(0, MarketingStoryboardProviderCall::count());
        $this->assertSame(0, MarketingPreparationAuthorization::findOrFail($a['id'])->reserved_microusd);
    }

    public function test_fourteen_post_limit_and_total_reservation_are_independent(): void
    {
        [$p,$a,$payload] = $this->setupGrant();
        $svc = app(MarketingStoryboardProvider::class);
        $sent = 0;
        $send = function () use (&$sent) {
            $sent++;

            return ['actual_microusd' => 43000];
        };
        for ($i = 0; $i < 14; $i++) {
            $svc->call('image', $p->id, $a['id'], $payload + ['operation' => $i], $send);
        }
        try {
            $svc->call('image', $p->id, $a['id'], $payload + ['operation' => 14], $send);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(14, $sent);
        config(['marketing.storyboard.live_grant.max_calls' => 15, 'marketing.storyboard.live_grant.max_total_microusd' => 602001]);
        try {
            $svc->call('image',$p->id,$a['id'],$payload + ['operation' => 14],$send);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409,$e->getStatusCode());
        }
        $this->assertSame(14,$sent);
    }
}
