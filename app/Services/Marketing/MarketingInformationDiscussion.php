<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoFilm,MarketingProject};
class MarketingInformationDiscussion {
 public function requested(string $s):bool{return (bool)preg_match('/信息卡|卖点卡|步骤提示|联系.{0,8}(卡|提示)|CTA|重点.{0,8}(显示|画面)|information\s*card/iu',$s);}
 public function context(MarketingProject $p,MarketingVideoFilm $f):array{return ['scenes'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['number','title','frames','spoken_text','original_subtitle'])),app(MarketingSubtitleService::class)->scenes($f)),'current_cards'=>$f->input['information_cards']??[],'confirmed_project_facts'=>['brief'=>$p->brief,'snapshot_excerpt'=>mb_substr(json_encode($p->snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),0,2400),'excerpt_limit'=>2400],'limits'=>'Up to6 controlled cards, feature/step/contact. Local scene frames [start,end),24fps,min36frames,no overlap. Position top-left/top-center/top-right. No invented numbers/prices/claims. Empty contact is valid. No auto-generation. Layout positions are presets, not face tracking; user previews before adoption.'];}
 public function suggestion(MarketingProject $p,MarketingVideoFilm $f,array $candidate,string $user):array{
  $cards=app(MarketingInformationCards::class)->normalize($candidate,app(MarketingSubtitleService::class)->scenes($f));$facts=json_encode([$p->brief,$p->snapshot,app(MarketingSubtitleService::class)->scenes($f)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  foreach($cards as &$c){$allowed=['user',...array_map(fn($r)=>'scene:'.$r['number'],app(MarketingSubtitleService::class)->scenes($f))];$c['source_refs']=$c['source_refs']??['user','scene:'.$c['scene_number']];abort_if(array_diff($c['source_refs'],$allowed),422,'信息卡来源引用无效。');if($c['contact']!==''&&!str_contains($facts,$c['contact'])&&!str_contains($user,$c['contact']))throw \Illuminate\Validation\ValidationException::withMessages(['information_cards'=>'联系方式缺少项目资料或本次输入依据，请先补充，或仅使用文字CTA。']);$c['source']='项目资料与本次对话 · AI建议，待采用';}unset($c);
  return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>$cards];
 }
 public static function schema():array{
  $props=['source_refs'=>['type'=>'array','items'=>['type'=>'string']],'id'=>['type'=>'string'],'type'=>['type'=>'string','enum'=>['feature','step','contact']],'scene_number'=>['type'=>'integer'],'start_frame'=>['type'=>'integer'],'end_frame'=>['type'=>'integer'],'title'=>['type'=>'string'],'body'=>['type'=>'string'],'step'=>['type'=>'integer'],'channel'=>['type'=>'string','enum'=>['none','whatsapp','phone']],'contact'=>['type'=>'string'],'position'=>['type'=>'string','enum'=>['top-left','top-center','top-right']],'replace_product_info'=>['type'=>'boolean']];return ['anyOf'=>[['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'required'=>array_keys($props),'properties'=>$props]],['type'=>'null']]];
 }
}
