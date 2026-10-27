<?php

namespace App\Services\Marketing;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Trusted adapter facts. Creative AI chooses intent; only verified account configurations can execute. */
class MarketingVideoRoutes
{
    public const VERSION = 'video-routes-v1';

    public function catalog(): array
    {
        $seed = ['provider' => 'evolink', 'quality' => '720p', 'durations' => range(4, 15), 'modes' => ['single_image', 'first_last_frames'], 'audio_control' => true, 'max_images' => 2];

        return [
            'seedance-2.0-image-to-video' => $seed + ['name' => 'Seedance 2.0'],
            'seedance-2.0-reference-to-video' => array_replace($seed, ['name' => 'Seedance 2.0 多参考', 'modes' => ['reference_images'], 'max_images' => 3]),
            MarketingEconomyVideoPolicy::PRO => array_replace($seed,['name'=>'Seedance 1.5 Pro','durations'=>range(4,12),'max_image_bytes'=>10485760,'max_prompt_tokens'=>2000,'tiers'=>['ordinary']]),
            MarketingEconomyVideoPolicy::FAST => ['provider'=>'evolink','name'=>'Seedance 1.0 Pro Fast','quality'=>'720p','durations'=>range(2,12),'modes'=>['single_image'],'audio_control'=>false,'max_images'=>1,'source_has_audio'=>false,'max_image_bytes'=>10485760,'max_prompt_tokens'=>2000,'tiers'=>['ordinary']],
            'wan2.5-image-to-video' => ['provider' => 'evolink', 'name' => 'Wan 2.5', 'quality' => '720p', 'durations' => [5, 10], 'modes' => ['single_image'], 'audio_control' => false, 'max_images' => 1],
        ];
    }

    public function intent(array $scene): array
    {
        if (isset($scene['material_plan'])) {
            $plan=$scene['material_plan'];
            if(!empty($scene['frames']['start_frame']['employee_acceptance']['sync_framing'])){
                $plan['reason']=match($plan['mode']){
                    'single_image'=>'沿用用户已确认的首帧与构图，由导演稿驱动本段动作；本次不要求额外尾帧或补充帧。',
                    'first_last_frames'=>'沿用已确认首帧与当前尾帧设计，保持人物、服装、场景和商品一致，并按导演稿完成首尾动作。',
                    default=>'沿用用户已确认的画面与构图，按当前导演设计使用所需参考图。'
                };
            }
            return $plan;
        }
        if ($scene['frames']['extra_image']['required'] ?? false) {
            $roles = array_values(array_filter(['start_frame', 'end_frame', 'extra_image'], fn ($role) => $role === 'start_frame' || ($scene['frames'][$role]['required'] ?? false)));

            return ['mode' => 'reference_images', 'roles' => $roles, 'reason' => '沿用已确认的补充参考，不会静默丢弃素材。', 'origin' => 'legacy_frame_flags'];
        }
        $pair = $scene['frames']['end_frame']['required'] ?? false;

        return ['mode' => $pair ? 'first_last_frames' : 'single_image', 'roles' => $pair ? ['start_frame', 'end_frame'] : ['start_frame'], 'reason' => '沿用已保存的素材设计；调整时由 AI 重新判断必要素材。', 'origin' => 'legacy_frame_flags'];
    }

    public function pricing(string $model, ?int $seconds=null): array
    {
        abort_unless(isset($this->catalog()[$model]), 423, '视频模型尚未接入受支持的执行适配器。');
        $c = app(MarketingStoryboardProvider::class)->role('video', $model, $seconds);
        abort_unless(! empty($c['access_verified_at']) && is_numeric($c['credit_usd'] ?? null) && is_finite((float) $c['credit_usd']) && $c['credit_usd'] >= 0, 423, '视频账户访问或实际计费换算尚未核验。');
        abort_if(isset($c['pricing_valid_until']) && now()->gte($c['pricing_valid_until']), 423, '视频报价已过期，需要重新核对。');
        if (! $this->catalog()[$model]['audio_control']) {
            abort_unless(isset($c['source_has_audio']) && is_bool($c['source_has_audio']) && ! empty($c['source_audio_verified_at']), 423, '当前模型的源音轨能力待核验。');
        }

        return $c;
    }

    public function select(array $scene, string $tier): array
    {
        abort_unless(isset(MarketingProductionPreferences::TIERS[$tier]), 422, '制作档位无效。');
        if(($scene['execution_policy_version']??null)===MarketingSceneExecutionPolicy::VERSION){
            $decision=app(MarketingSceneExecutionPolicy::class)->decide($scene);
            abort_if($decision['blockers'],423,$decision['reason']);
            if(in_array($decision['route'],['source_locked','conservative_motion'],true))return app(MarketingLocalSceneRenderer::class)->route($scene,$decision,$tier);
        }
        $intent = $this->intent($scene);
        $candidates = [];
        $configured=array_keys(config('marketing.storyboard.video_models',[]));$configured[]=config('marketing.storyboard.roles.video.model','');
        $economyPool=$tier==='ordinary'&&(count(array_intersect($configured,MarketingEconomyVideoPolicy::MODELS))>0||in_array($intent['preferred_model']??'',MarketingEconomyVideoPolicy::MODELS,true));
        foreach ($this->catalog() as $id => $cap) {
            if($economyPool&&!in_array($id,MarketingEconomyVideoPolicy::MODELS,true))continue;
            if(isset($cap['tiers'])&&!in_array($tier,$cap['tiers'],true))continue;
            if($tier==='ordinary'&&in_array($id,MarketingEconomyVideoPolicy::MODELS,true)){
                $rec=app(MarketingEconomyVideoPolicy::class)->recommend($scene,$scene['profile']??[]);
                if(($rec['model']??null)!==$id)continue;
            }
            if (! in_array($intent['mode'], $cap['modes'], true) || ! in_array($scene['seconds'], $cap['durations'], true) || count($intent['roles']) > $cap['max_images']) {
                continue;
            }
            try {
                $price = $this->pricing($id,$scene['seconds']);
            } catch (HttpExceptionInterface) {
                continue;
            }
            if (isset($price['tiers']) && ! in_array($tier, $price['tiers'], true)) {
                continue;
            }
            $rank = (int) ($price['quality_rank'] ?? 2);
            $candidates[] = ['video' => $price, 'capability' => $cap + ['version' => self::VERSION], 'image_roles' => $intent['roles'], 'mode' => $intent['mode'], 'reason' => $intent['reason'], 'tier' => $tier, 'rank_distance' => abs(($tier === 'professional' ? 3 : 2) - $rank)];
        }
        abort_unless($candidates, 423, '没有输入方式、时长、账户能力和费用均已核验的制作路线；故事与素材已保留。');
        usort($candidates, fn ($a, $b) => $tier === 'ordinary' ? ($a['video']['cap_microusd'] <=> $b['video']['cap_microusd']) : (($a['rank_distance'] <=> $b['rank_distance']) ?: ($a['video']['cap_microusd'] <=> $b['video']['cap_microusd'])));
        $out = $candidates[0];
        unset($out['rank_distance']);

        return $out;
    }

    public function preview(array $scene, string $tier): array
    {
        $intent = $this->intent($scene);
        $decision=app(MarketingSceneExecutionPolicy::class)->decide($scene);
        if($decision['route']!=='generative')return ['status'=>'needs_verification','model'=>'local-image-compose','reason'=>$decision['reason'],'mode'=>$intent['mode'],'image_roles'=>$intent['roles'],'execution'=>$decision,'public_estimate_microusd'=>null,'voice_description'=>$decision['audio']==='independent'?'独立配音：声线与费用需核验，不会回退到原生声音。':'无口播','pricing_note'=>'本地外部API费用为零；正式计算费率待核验。'];
        if($tier==='ordinary')return app(MarketingEconomyVideoPolicy::class)->recommend($scene,$scene['profile']??[]);
        try {
            $r = $this->select($scene, $tier);

            return ['status' => 'priced', 'model' => $r['video']['model'], 'cap_microusd' => $r['video']['cap_microusd'], 'quality' => $r['capability']['quality']] + array_intersect_key($r, array_flip(['mode', 'image_roles', 'reason', 'tier']));
        } catch (HttpExceptionInterface $e) {
            return ['status' => 'needs_verification', 'mode' => $intent['mode'], 'image_roles' => $intent['roles'], 'reason' => $intent['reason'], 'tier' => $tier, 'note' => $e->getMessage()];
        }
    }

    public function inputs(array $clip, array $route): array
    {
        app(MarketingVideoBoundaryPolicy::class)->assertExecutable($clip,$route['capability']??null);
        $inputs = [];
        foreach ($route['image_roles'] as $role) {
            $f = $clip['frames'][$role] ?? [];
            abort_unless(! empty($f['asset']) && in_array($f['readiness'] ?? '', ['qa_passed', 'employee_declared_local_validated'], true), 422, '本次路线需要的参考素材尚未就绪。');
            if(isset($route['capability']['max_image_bytes'])){
                $asset=\App\Models\MarketingAsset::findOrFail($f['asset']['asset_id']);$path=\Illuminate\Support\Facades\Storage::disk('local')->path($asset->path);
                abort_unless(is_file($path)&&hash_file('sha256',$path)===$f['asset']['sha256']&&filesize($path)<=$route['capability']['max_image_bytes'],422,'经济模型要求每张图片不超过10MB，素材已改变或超限；不会上传。');
            }
            $inputs[$role] = $f['asset'];
        }

        return $inputs;
    }

    public function payload(array $payload, array $route): array
    {
        abort_unless(($payload['model'] ?? '') === $route['video']['model'] && in_array($payload['duration'], $route['capability']['durations'], true), 409, '执行路线或时长已变化。');
        if(in_array($payload['model'],MarketingEconomyVideoPolicy::MODELS,true)){
            abort_unless(($payload['quality']??'')==='720p'&&strlen($payload['prompt']??'')<=2000,422,'本段制作指令需要适配所选模型的长度或质量限制，请先核价；原导演稿保留，未发送生成请求。');
            unset($payload['content_filter']);
            if($payload['model']===MarketingEconomyVideoPolicy::FAST){abort_if($payload['generate_audio']??false,422,'Fast不支持原生声音；已保留口播要求且未调用。');unset($payload['generate_audio']);}
            return $payload;
        }
        if (! $route['capability']['audio_control']) {
            abort_if($payload['generate_audio'] ?? false, 422, '本模型没有已核验的精确原生口播路线。');
            unset($payload['aspect_ratio'],$payload['generate_audio'],$payload['content_filter']);
            $payload['prompt_extend'] = false;
        }

        return $payload;
    }

    public function sourceHasAudio(array $input): bool
    {
        return ($input['configuration']['capability']['audio_control'] ?? true) ? (bool) ($input['payload']['generate_audio'] ?? false) : $input['configuration']['video']['source_has_audio'];
    }
}
