<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingInformationCards;
class MarketingInformationCardsTest extends TestCase {
 private function card():array{return ['id'=>'c1','type'=>'contact','scene_number'=>1,'start_frame'=>0,'end_frame'=>36,'title'=>'联系我们','body'=>'','step'=>1,'channel'=>'whatsapp','contact'=>'','position'=>'top-left','replace_product_info'=>false];}
 public function test_empty_contact_is_allowed_and_frames_are_bound_to_real_scenes():void{
  $s=app(MarketingInformationCards::class);$rows=[['number'=>1,'frames'=>87]];
  $this->assertSame('', $s->normalize([$this->card()],$rows)[0]['contact']);
  $this->assertSame([], $s->normalize([],$rows));
  $this->expectException(\Illuminate\Validation\ValidationException::class);$c=$this->card();$c['end_frame']=88;$s->normalize([$c],$rows);
 }
 public function test_overlap_is_rejected_but_adjacent_cards_are_allowed():void{
  $s=app(MarketingInformationCards::class);$a=$this->card();$b=$a;$b['id']='c2';$b['start_frame']=36;$b['end_frame']=72;
  $this->assertCount(2,$s->normalize([$a,$b],[['number'=>1,'frames'=>120]]));
  $b['start_frame']=35;$this->expectException(\Illuminate\Validation\ValidationException::class);$s->normalize([$a,$b],[['number'=>1,'frames'=>120]]);
 }
 public function test_multiple_industries_follow_actual_scene_counts_and_reject_unknown_provenance():void{
  $s=app(MarketingInformationCards::class);foreach([['餐饮服务',5],['服装细节',2],['咨询步骤',7]] as [$title,$number]){$c=$this->card();$c['title']=$title;$c['scene_number']=$number;$c['source_refs']=['scene:'.$number];$this->assertSame($number,$s->normalize([$c],[['number'=>$number,'frames'=>64]])[0]['scene_number']);}
  $c['source_refs']=['scene:99'];$this->expectException(\Illuminate\Validation\ValidationException::class);$s->normalize([$c],[['number'=>7,'frames'=>64]]);
 }
 public function test_short_interval_and_uncontrolled_type_are_rejected():void{
  $a=$this->card();$a['end_frame']=35;$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingInformationCards::class)->normalize([$a],[['number'=>1,'frames'=>120]]);
 }
}
