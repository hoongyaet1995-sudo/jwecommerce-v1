<?php
namespace Tests\Feature;
use Tests\TestCase;
class MarketingDirectorReadoutTest extends TestCase
{
    public function test_chinese_suffixes_are_not_cut_as_bytes_when_trimming_punctuation():void
    {
        $scenes=[['seconds'=>5,'message'=>'开场','director'=>['beats'=>[['start'=>0,'end'=>5,'action'=>'展示茶叶细节。；;']]]],['seconds'=>5,'message'=>'展示茶叶细节。；;','director'=>['beats'=>[['start'=>0,'end'=>5,'description'=>'展示包装细节']]]]];
        $snapshot=app(\App\Services\Marketing\MarketingStoryboardDirectorReadout::class)->snapshot(['scenes'=>$scenes]);
        $this->assertTrue(mb_check_encoding($snapshot,'UTF-8'));
        $this->assertStringContainsString('展示茶叶细节',$snapshot['scenes'][0]['director_readout']['motion']);
        $this->assertStringContainsString('下一段：展示茶叶细节。',$snapshot['scenes'][0]['director_readout']['continuity']);
        $this->assertStringContainsString('展示包装细节',$snapshot['scenes'][1]['director_readout']['motion']);
        $this->assertNotEmpty(json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $this->assertSame($scenes[0]['director'],$snapshot['scenes'][0]['director']);
    }
    public function test_current_readout_uses_current_beats_next_scene_and_duration_without_rewriting_history():void
    {
        $scenes=[['seconds'=>6,'message'=>'产品介绍','director'=>['motion'=>'第3至5秒人物收回手势。','continuity'=>'保持服装。下一场继续内部介绍。','end_state'=>'门打开；内部呈现整洁层架，画面准备衔接下一场内部介绍。','beats'=>[['start'=>0,'end'=>3,'action'=>'指向产品'],['start'=>3,'end'=>6,'action'=>'字幕停留至15秒结束。']]]],['seconds'=>5,'message'=>'咨询报价','visual'=>'回到仓储环境','director'=>['beats'=>[['start'=>0,'end'=>5,'action'=>'咨询收尾']]]]];
        $original=$scenes;
        $out=app(\App\Services\Marketing\MarketingStoryboardDirectorReadout::class)->scenes($scenes);
        $this->assertSame($original,$scenes);
        $this->assertStringContainsString('3–6秒',$out[0]['director_readout']['motion']);
        $this->assertStringNotContainsString('至15秒',$out[0]['director_readout']['beats'][1]['action']);
        $this->assertStringContainsString('咨询报价',$out[0]['director_readout']['continuity']);
        $this->assertStringNotContainsString('下一场内部介绍',$out[0]['director_readout']['end_state']);
        $this->assertStringContainsString('内部呈现整洁层架',$out[0]['director_readout']['end_state']);
        $this->assertSame($original[0]['director'],$out[0]['director']);
    }
}
