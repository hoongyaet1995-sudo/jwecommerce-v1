<?php
namespace App\Services\Marketing;
use App\Models\MarketingProject;

/** One canonical field/options contract for chat and summary editors. */
class MarketingPlanningGuide
{
    public const GOALS=['awareness'=>'品牌曝光','introduction'=>'新品介绍','promotion'=>'促销','enquiry'=>'获得询价','appointment'=>'预约','purchase'=>'购买'];
    public function options(string $field):array{return match($field){'goal'=>self::GOALS,'tone'=>array_combine(array_keys(app(MarketingVideoTone::class)->presets()),array_keys(app(MarketingVideoTone::class)->presets())),'language'=>['zh-MY'=>'华语','English'=>'英语','ms-MY'=>'马来语'],'duration_seconds'=>[15=>'15秒',20=>'20秒',30=>'30秒',45=>'45秒',60=>'60秒'],default=>[]};}
    public function next(MarketingProject $p):?string{
        if(($p->planning_state['status']??'')==='confirmed')return null;
        $fields=$p->planning_state['fields']??[];
        $missing=app(MarketingPlanningConfirmation::class)->missing($p);
        foreach(['goal','audience','pain_point','differentiator','tone','market','duration_seconds','language','platforms'] as $key){
            $f=$fields[$key]??[];$answered=($f['status']??'')==='confirmed'||($f['origin']??'')==='ai_conversation';
            if(in_array($key,$missing,true)||(!$answered&&in_array($key,['goal','audience','tone'],true)))return $key;
        }
        return $missing[0]??null;
    }
    public function data(MarketingProject $p):array{
        $field=$this->next($p);$questions=['goal'=>'你最希望观众看完视频后做什么？','offer'=>'这次主要介绍什么商品或服务？','audience'=>'你主要希望哪类人看到这支视频？','pain_point'=>'你希望回应观众的什么需求或感受？','differentiator'=>'你最想突出商品的哪些特点？也可以让我根据商品资料建议。','tone'=>'你希望这支视频呈现哪一种调性？','market'=>'这支视频主要面向哪个市场？','duration_seconds'=>'这支视频希望做多长？也可以用文字让我建议。','language'=>'视频使用哪种语言？','platforms'=>'准备在哪些平台发布？'];
        $options=$field?$this->options($field):[];
        return ['field'=>$field,'question'=>$field?($questions[$field]??'还有什么要求希望补充？'):'摘要已整理好，可以在右侧确认资料并继续。','kind'=>$options?'choice':'text','options'=>array_map(fn($value,$label)=>['value'=>(string)$value,'label'=>$label],array_keys($options),array_values($options))];
    }
}
