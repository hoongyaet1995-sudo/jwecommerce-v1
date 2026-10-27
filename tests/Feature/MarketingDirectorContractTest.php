<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingDirectorContract;
use Illuminate\Validation\ValidationException;
class MarketingDirectorContractTest extends TestCase
{
 public static function facts():array{return ['source_revision_id'=>'revision-1','source_digest'=>'digest-1','duration'=>15,'scenes'=>[['duration'=>15,'voiceover'=>'先看用途，欢迎询价。','subtitle'=>'欢迎询价','asset_ids'=>[]]],'assets'=>[]];}
 public static function directorOutput():array{return ['summary'=>'自然人物讲解','shots'=>[['scene_number'=>1,'start'=>0,'end'=>15,'purpose'=>'说明用途并邀请咨询','subject'=>'虚拟讲解者，无真实身份声称','framing'=>'平视腰部以上，双手在画内','motion'=>'前1秒固定，随后缓推，第9秒停稳','lighting'=>'左前方柔光，中性肤色','continuity'=>'同一脸、衣服和背景','voiceover'=>'先看用途，欢迎询价。','subtitle'=>'欢迎询价','beats'=>[['start'=>0,'end'=>5,'action'=>'看镜头，轻点头一次'],['start'=>5,'end'=>10,'action'=>'开放掌心一次后归位'],['start'=>10,'end'=>15,'action'=>'微笑，保持自然停留']],'checks'=>['无变脸，字幕随声音对齐']]],'materials'=>[['scene_number'=>1,'name'=>'虚拟人物设定','owner'=>'ai','status'=>'missing','asset_id'=>'','evidence'=>'未确认人物外观','alternative'=>'员工确认虚拟角色，无需真人照片']],'notes'=>['口播需实测，不强行加速']];}
 public function test_continuous_shot_with_three_beats_is_preparation_only():void{$r=app(MarketingDirectorContract::class)->validate(self::directorOutput(),self::facts());$this->assertSame('revision-1',$r['source_revision_id']);$this->assertSame('needs_materials',$r['review_status']);$this->assertSame(15,$r['duration']);}
 public function test_time_gaps_and_changed_speech_are_rejected():void{foreach(['time','speech','asset','beat'] as $kind){$d=self::directorOutput();if($kind==='time')$d['shots'][0]['start']=1;if($kind==='speech')$d['shots'][0]['voiceover']='保证现货';if($kind==='asset')$d['materials'][0]['asset_id']='foreign';if($kind==='beat')$d['shots'][0]['beats'][1]['start']=6;try{app(MarketingDirectorContract::class)->validate($d,self::facts());$this->fail($kind.' accepted');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}}}
 public function test_no_authorized_media_means_schema_cannot_claim_ready_assets():void{
  $schema=app(MarketingDirectorContract::class)->schema(self::facts());
  $this->assertSame(['missing','optional'],$schema['properties']['materials']['items']['properties']['status']['enum']);
 }}
