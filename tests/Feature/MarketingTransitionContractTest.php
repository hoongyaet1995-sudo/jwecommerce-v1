<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\{MarketingTransitionContract,MarketingPlanningContract};
use Illuminate\Validation\ValidationException;
class MarketingTransitionContractTest extends TestCase
{
 public static function transitionOutput():array{return ['summary'=>'用途解释切至产品细节','boundaries'=>[['from'=>1,'to'=>2,'outgoing_end'=>'产品在画面右侧，动作停稳','incoming_start'=>'同侧产品细节近景，保持朝向','method'=>'cut','reason'=>'从整体认识切入细节说明','transition_ms'=>0,'continuity'=>array_map(fn($d)=>['dimension'=>$d,'applicable'=>!in_array($d,['subject','action_state']),'requirement'=>in_array($d,['subject','action_state'])?'没有人物或操作，无此要求':'保持产品方向与信息顺序'],MarketingTransitionContract::DIMENSIONS),'audio'=>['strategy'=>'pause','instruction'=>'前句结束后再开始下一句，真实间隔待音频核实'],'api'=>['outgoing'=>'结束时产品停稳','incoming'=>'从已准备的细节构图开始'],'postproduction'=>'使用直接切镜，不增加片长','checks'=>['产品方向一致、信息递进明确'],'frame_reviews'=>[['side'=>'outgoing_end','status'=>'reuse_candidate','reason'=>'保存的尾帧设计为停稳状态，仍需看图核对'],['side'=>'incoming_start','status'=>'reuse_candidate','reason'=>'保存的首帧为同侧细节，仍需看图核对']]]],'notes'=>['未检查真实图片像素，复用只是候选']];}
 public function test_all_six_forms_share_contract_without_forcing_presenter_fields():void{
  foreach(MarketingPlanningContract::TYPES as $type){$r=app(MarketingTransitionContract::class)->validate(self::transitionOutput(),['scenes'=>[[],[]],'video_type'=>$type]);$this->assertSame('cut',$r['boundaries'][0]['method']);$this->assertFalse($r['boundaries'][0]['continuity'][0]['applicable']);}
 }
 public function test_missing_duplicate_and_nonadjacent_boundaries_or_fake_duration_are_rejected():void{
  foreach(['missing','duplicate','skip','timing'] as $kind){$d=self::transitionOutput();if($kind==='missing')$d['boundaries']=[];if($kind==='duplicate')$d['boundaries'][]=$d['boundaries'][0];if($kind==='skip')$d['boundaries'][0]['to']=3;if($kind==='timing')$d['boundaries'][0]['transition_ms']=100;try{app(MarketingTransitionContract::class)->validate($d,['scenes'=>[[],[]]]);$this->fail($kind);}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}}
 }
 public function test_one_scene_requires_no_boundary_and_no_unused_people():void{
  $r=app(MarketingTransitionContract::class)->validate(['summary'=>'单段无需衔接','boundaries'=>[],'notes'=>[]],['scenes'=>[[]]]);$this->assertSame([],$r['boundaries']);$schema=app(MarketingTransitionContract::class)->schema(['scenes'=>[[],[],[]]]);$this->assertSame(2,$schema['properties']['boundaries']['minItems']);
 }
}
