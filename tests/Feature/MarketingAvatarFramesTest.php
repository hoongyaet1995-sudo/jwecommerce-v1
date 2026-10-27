<?php

namespace Tests\Feature;

use App\Services\Marketing\MarketingStoryboardContract;
use App\Services\Marketing\MarketingStoryboardImages;
use Tests\TestCase;

class MarketingAvatarFramesTest extends TestCase
{
    public function test_distant_or_back_requirement_is_explicit_in_generator_and_qa_design(): void
    {
        $c = app(MarketingStoryboardContract::class)->content(['seconds' => 5, 'message' => 'Host', 'visual' => 'Host near product', 'voiceover' => '', 'subtitle' => '', 'frames' => ['start_frame' => ['required' => true, 'person_presence' => 'distant_or_back', 'instruction' => 'Show host near product']]]);
        $compiled = app(MarketingStoryboardImages::class)->compile($c, ['presenter' => ['id' => 'a']], []);
        $this->assertSame('distant_or_back', $compiled['person_presence']);
        $this->assertStringContainsString('distant or back', $compiled['prompt']);
    }

    public function test_role_presence_and_reference_signatures_isolate_product_images_from_person_changes(): void
    {
        $c = app(MarketingStoryboardContract::class)->content(['seconds' => 5, 'message' => 'Detail', 'visual' => 'Host then product detail', 'voiceover' => '', 'subtitle' => '', 'presenter_required' => true, 'frames' => ['start_frame' => ['required' => true, 'person_presence' => 'visible', 'instruction' => 'Host introduces'], 'end_frame' => ['required' => true, 'person_presence' => 'absent', 'instruction' => 'Only product detail']]]);
        $this->assertSame('absent', $c['frames']['end_frame']['person_presence']);
        $images = app(MarketingStoryboardImages::class);
        $a = ['presenter' => ['id' => 'a', 'version' => 1]];
        $b = ['presenter' => ['id' => 'b', 'version' => 1]];
        $first = $images->compile($c, $a, [], 'start_frame');
        $other = $images->compile($c, $b, [], 'start_frame');
        $this->assertNotSame($first['image_signature'], $other['image_signature']);
        $tail = $images->compile($c, $a, [], 'end_frame');
        $tailB = $images->compile($c, $b, [], 'end_frame');
        $this->assertSame($tail['image_signature'], $tailB['image_signature']);
        $this->assertStringContainsString('No people', $tail['prompt']);
        $anchor = $images->compile($c, $a, ['scene_start' => ['sha256' => 'different']], 'end_frame');
        $this->assertNotSame($tail['image_signature'],$anchor['image_signature']);
    }
}
