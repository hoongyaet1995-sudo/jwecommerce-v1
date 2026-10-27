<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingMaterialPackage;
use Illuminate\Validation\ValidationException;
class MarketingMaterialPackageTest extends TestCase
{
 public static function materialOutput():array{return ['summary'=>'先收真实背面，其余AI准备','requirements'=>[['key'=>'product_back','kind'=>'product_view','name'=>'商品背面参考','scene_numbers'=>[1],'reason'=>'需要真实背面结构，不能推断','evidence'=>'Show the real rear','question'=>'请提供商品背面照片','preparation'=>'保留背面结构，制备竖屏场景','required'=>true],['key'=>'virtual_host','kind'=>'synthetic_person','name'=>'虚拟讲解者','scene_numbers'=>[1],'reason'=>'AI创建通用讲解者','evidence'=>'','question'=>'','preparation'=>'深蓝无字工作服，平视自然光','required'=>true]]];}
 public static function facts():array{return ['source_revision_id'=>'r1','source_digest'=>'digest','scenes'=>[['visual'=>'Show the real rear','voiceover'=>'欢迎询价','subtitle'=>'欢迎询价','material'=>'','duration'=>15,'asset_ids'=>[]]],'director_plan'=>['shots'=>[['scene_number'=>1,'subject'=>'Show the real rear','framing'=>'平视','motion'=>'缓推','lighting'=>'柔光','continuity'=>'同一人','purpose'=>'介绍用途']]],'assets'=>[]];}
 public function test_structured_requirements_make_three_planned_outputs_and_synthetic_work_stays_with_ai():void{
  $p=app(MarketingMaterialPackage::class)->build(self::materialOutput(),self::facts());$this->assertCount(3,$p['outputs']);$this->assertSame('employee',$p['requirements'][0]['owner']);$this->assertSame('ai',$p['requirements'][1]['owner']);$this->assertSame('ai_planned',$p['requirements'][1]['status']);$this->assertSame('awaiting_inputs',$p['status']);$this->assertSame(['start_frame','end_frame','extra_image'],array_column($p['outputs'],'role'));foreach($p['outputs'] as $o)$this->assertSame('planned',$o['status']);
 }
 public function test_repeated_requirement_keys_and_unbound_real_fact_requests_are_rejected():void{
  foreach(['duplicate','evidence','synthetic_question'] as $case){$o=self::materialOutput();if($case==='duplicate')$o['requirements'][]=$o['requirements'][0];if($case==='evidence')$o['requirements'][0]['evidence']='Invent unseen structure';if($case==='synthetic_question')$o['requirements'][1]['question']='请上传真人照片';try{app(MarketingMaterialPackage::class)->build($o,self::facts());$this->fail('accepted '.$case);}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}}
 }
 public function test_model_labels_are_not_paths_and_do_not_require_a_paid_retry_for_identifier_style():void{
  $o=self::materialOutput();$o['requirements'][0]['key']='product-back';$o['requirements'][1]['key']='虚拟人物';
  $p=app(MarketingMaterialPackage::class)->build($o,self::facts());$this->assertMatchesRegularExpression('/^need-[a-f0-9]{16}$/',$p['requirements'][0]['id']);$this->assertCount(3,$p['outputs']);
 } public function test_unchanged_reference_is_reused_but_upstream_change_does_not_carry_it():void{
  $service=app(MarketingMaterialPackage::class);$o=self::materialOutput();$f=self::facts();$old=$service->build($o,$f);$old['requirements'][0]['reference']=['type'=>'project_asset','id'=>'existing'];
  $new=$service->build($o,$f,$old);$this->assertSame('reference_provided',$new['requirements'][0]['status']);$this->assertSame('awaiting_image_budget',$new['status']);$this->assertNotEmpty($new['outputs'][0]['source_reference_ids']);$this->assertNull($new['outputs'][0]['asset_id']);
  $f['source_digest']='changed';$new=$service->build($o,$f,$old);$this->assertNull($new['requirements'][0]['reference']);$this->assertSame('awaiting_inputs',$new['status']);
 }}
