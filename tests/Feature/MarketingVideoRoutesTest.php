<?php

namespace Tests\Feature;

use App\Services\Marketing\MarketingVideoRoutes;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketingVideoRoutesTest extends TestCase
{
    private function setupModels(): void
    {
        Http::preventStrayRequests();
        $price = ['source_has_audio' => false, 'source_audio_verified_at' => 'fake', 'version' => 'fixture', 'cap_microusd' => 100, 'credit_usd' => .000001, 'access_verified_at' => now()->toIso8601String(), 'pricing_verified_at' => now()->toIso8601String()];
        config(['marketing.storyboard.roles.video' => $price + ['model' => 'seedance-2.0-image-to-video', 'quality_rank' => 2], 'marketing.storyboard.video_models' => ['wan2.5-image-to-video' => $price + ['model' => 'wan2.5-image-to-video', 'cap_microusd' => 20, 'quality_rank' => 1]]]);
        $models = config('marketing.storyboard.video_models');
        $models['wan2.5-image-to-video']['cap_microusd'] = 20;
        config(['marketing.storyboard.video_models' => $models]);
    }

    private function scene(string $mode = 'single_image'): array
    {
        return ['seconds' => 5, 'material_plan' => ['mode' => $mode, 'roles' => $mode === 'single_image' ? ['start_frame'] : ['start_frame', 'end_frame'], 'reason' => '测试导演意图'], 'frames' => []];
    }

    public function test_economy_prefers_verified_lower_cap_single_image_model_without_calling_provider(): void
    {
        $this->setupModels();
        $r = app(MarketingVideoRoutes::class)->select($this->scene(), 'ordinary');
        $this->assertSame('wan2.5-image-to-video', $r['video']['model']);
        $this->assertSame(['start_frame'], $r['image_roles']);
        Http::assertNothingSent();
    }

    public function test_visual_control_is_not_dropped_to_fit_a_cheaper_model(): void
    {
        $this->setupModels();
        $r = app(MarketingVideoRoutes::class)->select($this->scene('first_last_frames'), 'ordinary');
        $this->assertSame('seedance-2.0-image-to-video', $r['video']['model']);
        $this->assertSame(['start_frame', 'end_frame'], $r['image_roles']);
    }

    public function test_unknown_price_is_excluded_and_no_compatible_route_stops_locally(): void
    {
        $this->setupModels();
        $models = config('marketing.storyboard.video_models');
        $models['wan2.5-image-to-video']['credit_usd'] = null;
        config(['marketing.storyboard.roles.video' => [], 'marketing.storyboard.video_models' => $models]);
        $this->expectException(HttpException::class);
        app(MarketingVideoRoutes::class)->select($this->scene(), 'ordinary');
    }

    public function test_advanced_is_a_preference_not_a_fixed_frame_count(): void
    {
        $this->setupModels();
        $r = app(MarketingVideoRoutes::class)->select($this->scene(), 'advanced');
        $this->assertSame('seedance-2.0-image-to-video', $r['video']['model']);
        $this->assertSame(['start_frame'], $r['image_roles']);
    }

    public function test_legacy_extra_reference_is_preserved_in_intent(): void
    {
        $this->setupModels();
        $intent = app(MarketingVideoRoutes::class)->intent(['frames' => ['end_frame' => ['required' => true], 'extra_image' => ['required' => true]]]);
        $this->assertSame('reference_images', $intent['mode']);
        $this->assertSame(['start_frame', 'end_frame', 'extra_image'], $intent['roles']);
    }
}
