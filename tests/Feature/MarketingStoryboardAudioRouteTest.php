<?php
namespace Tests\Feature;

use App\Services\Marketing\MarketingStoryboardAudioRoute;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class MarketingStoryboardAudioRouteTest extends TestCase
{
    private function handoff(array $voice = ['mode'=>'auto'], int $count = 1): array
    {
        return ['profile'=>['voice'=>$voice], 'source_context'=>['brief'=>['language'=>'zh-MY']], 'scene_map'=>array_fill(0,$count,['voiceover'=>'已确认台词','presenter_required'=>true])];
    }
    private function capabilities(): array
    {
        return ['model'=>'fixture-video','version'=>'fixture-1','verified_at'=>'2026-10-22', 'languages'=>['zh-MY'], 'exact_script'=>true, 'lip_sync'=>true, 'consistent_voice'=>true];
    }
    public function test_auto_native_route_requires_verified_capability_and_keeps_exact_script(): void
    {
        config(['marketing.storyboard.audio.native'=>$this->capabilities()]);
        $r=app(MarketingStoryboardAudioRoute::class)->select($this->handoff(),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
        $this->assertSame('native',$r['route']);
        $this->assertSame('zh-MY',$r['language']);
    }
    public function test_unknown_native_capability_blocks_instead_of_guessing(): void
    {
        config(['marketing.storyboard.audio'=>[]]);
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingStoryboardAudioRoute::class)->select($this->handoff(),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
    }
    public function test_multiscene_native_route_requires_consistent_voice(): void
    {
        config(['marketing.storyboard.audio.native'=>array_replace($this->capabilities(),['consistent_voice'=>false])]);
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingStoryboardAudioRoute::class)->select($this->handoff(count:2),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
    }
    public function test_native_multiscene_without_a_voice_identity_transport_is_blocked_even_with_a_capability_flag(): void
    {
        config(['marketing.storyboard.audio.native'=>$this->capabilities()]);
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingStoryboardAudioRoute::class)->select($this->handoff(count:2),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
    }
    public function test_user_selected_voice_is_never_replaced_by_native_audio(): void
    {
        config(['marketing.storyboard.audio.native'=>$this->capabilities()]);
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingStoryboardAudioRoute::class)->select($this->handoff(['mode'=>'preset','id'=>'missing','version'=>1]),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
    }
    public function test_explicit_silent_preserves_script_without_tts(): void
    {
        $r=app(MarketingStoryboardAudioRoute::class)->select($this->handoff(['mode'=>'none']),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
        $this->assertSame('silent',$r['route']);
    }
    public function test_auto_uses_verified_independent_voice_when_native_is_unavailable(): void
    {
        config(['marketing.storyboard.audio.native'=>[], 'marketing.storyboard.audio.auto_voice'=>['id'=>'a','version'=>1,'verified_at'=>'fixture','languages'=>['zh-MY']], 'marketing.storyboard.voices'=>[['id'=>'a','version'=>1,'name'=>'Fixture','provider_voice_id'=>'voiceA','model'=>'fixture-tts']], 'marketing.production.elevenlabs_key'=>'fake', 'marketing.storyboard.roles.voice'=>['model'=>'fixture-tts','version'=>'1','cap_microusd'=>50,'pricing_verified_at'=>'fixture','credit_usd'=>.000001]]);
        $r=app(MarketingStoryboardAudioRoute::class)->select($this->handoff(),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
        $this->assertSame('independent',$r['route']);
        $this->assertSame('voiceA',$r['voice_selection']['provider_voice_id']);
        config(['marketing.storyboard.roles.voice.credit_usd'=>null]);
        $this->expectException(HttpExceptionInterface::class);
        app(MarketingStoryboardAudioRoute::class)->select($this->handoff(),['model'=>'fixture-video','version'=>'fixture-1','cap_microusd'=>100]);
    }
}
