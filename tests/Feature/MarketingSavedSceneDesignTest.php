<?php
namespace Tests\Feature;
use App\Services\Marketing\{MarketingStoryboardSceneDesign,MarketingStoryboardOpenAi};
use Tests\TestCase;
class MarketingSavedSceneDesignTest extends TestCase
{
 public function test_schema_requires_explicit_design_and_frame_state_links():void {
  $s=app(MarketingStoryboardOpenAi::class)->schema()['properties']['scenes']['items'];
  $this->assertContains('scene_design',$s['required']);
  $this->assertContains('state',$s['properties']['frames']['properties']['start_frame']['required']);
 }
 public function test_saved_design_requires_bound_reference_and_matching_states():void {
  $s=['seconds'=>5,'director'=>['start_state'=>'start','end_state'=>'end','beats'=>[['start'=>0,'end'=>5,'action'=>'point']]],'scene_design'=>['environment'=>'warehouse','background'=>'racks','props'=>'plain boxes','product'=>'closed blue container','product_placement'=>'left rear','person_placement'=>'right front','fact_boundary'=>'fictional location only','product_reference_indexes'=>[0]],'frames'=>['start_frame'=>['required'=>true,'state'=>'start','instruction'=>'warehouse start'],'end_frame'=>['required'=>true,'state'=>'end','instruction'=>'warehouse end'],'extra_image'=>['required'=>true,'state'=>'point','instruction'=>'warehouse pointing']]];
  app(MarketingStoryboardSceneDesign::class)->validate($s,1);$this->addToAssertionCount(1);
  $s['scene_design']['product_reference_indexes']=[1];
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
  app(MarketingStoryboardSceneDesign::class)->validate($s,1);
 }
 public function test_state_mismatch_is_rejected():void {
  $s=['seconds'=>5,'director'=>['start_state'=>'A','end_state'=>'B','beats'=>[['start'=>0,'end'=>5,'action'=>'C']]],'scene_design'=>array_fill_keys(['environment','background','props','product','product_placement','person_placement','fact_boundary'],'explicit'),'frames'=>['start_frame'=>['required'=>true,'state'=>'WRONG','instruction'=>'A']]];
  $s['scene_design']['product_reference_indexes']=[];
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
  app(MarketingStoryboardSceneDesign::class)->validate($s,0);
 }
 public function test_optional_start_frame_cannot_bypass_design_validation():void {
  $s=['scene_design'=>array_merge(array_fill_keys(MarketingStoryboardSceneDesign::FIELDS,'explicit none'),['product_reference_indexes'=>[]]),'frames'=>['start_frame'=>['required'=>false,'state'=>'wrong','instruction'=>'']]];
  $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
  app(MarketingStoryboardSceneDesign::class)->validate($s,0);
 }
 public function test_root_qualified_fact_key_resolves_only_to_exact_existing_approved_fact():void {
  $s=['seconds'=>5,'message'=>'message','visual'=>'warehouse','voiceover'=>'Approved exact phrase','subtitle'=>'','fact_refs'=>['facts.scene.abc.voiceover']];
  $service=app(\App\Services\Marketing\MarketingStoryboardSemanticClient::class);
  $out=$service->validateDraft(['scenes'=>[$s]],['scene.abc.voiceover'=>'Approved exact phrase']);
  $this->assertSame(['scene.abc.voiceover'],$out['scenes'][0]['fact_refs']);
  $s['fact_refs']=['facts.scene.unknown.voiceover'];
  $this->expectException(\Illuminate\Validation\ValidationException::class);
  $service->validateDraft(['scenes'=>[$s]],['scene.abc.voiceover'=>'Approved exact phrase']);
 }
}
