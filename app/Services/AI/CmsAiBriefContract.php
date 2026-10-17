<?php
namespace App\Services\AI;

use Illuminate\Validation\ValidationException;

final class CmsAiBriefContract
{
    public function normalize(array $data, array $sectionIds): array
    {
        $summary=$this->text($data['summary']??'',6000);
        $questions=$data['questions']??[];
        if(!is_array($questions)||!array_is_list($questions)||count($questions)>24)$this->invalid('AI returned an invalid question list.');
        $seen=[];$normalized=[];
        foreach($questions as $index=>$question){
            if(is_string($question))$question=['id'=>'q-'.substr(hash('sha256',$question),0,12),'type'=>'text','question'=>$question,'required'=>true];
            if(!is_array($question))$this->invalid('AI returned an invalid question.');
            $id=$question['id']??'q-'.($index+1);$type=$question['type']??'text';
            if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$id)||isset($seen[$id])||!in_array($type,['text','select','radio','multiple_select'],true))$this->invalid('AI returned an unsupported question type or identifier.');
            $seen[$id]=true;$options=$question['options']??[];
            if(!is_array($options)||!array_is_list($options)||count($options)>12)$this->invalid('AI returned invalid answer choices.');
            $options=array_map(fn($o)=>$this->text($o,300),$options);
            if($type!=='text'&&(count($options)<2||count($options)!==count(array_unique($options))))$this->invalid('Choice questions need distinct answer options.');
            $normalized[]=['id'=>$id,'type'=>$type,'question'=>$this->text($question['question']??'',1000),'help'=>$this->text($question['help']??'',1500),'required'=>(bool)($question['required']??true),'options'=>$type==='text'?[]:$options];
        }
        $sections=$data['sections']??[];
        $provisional=$sections===[]&&count($normalized)>0;
        if($provisional)$sections=array_map(fn($id)=>['id'=>$id,'purpose'=>'Provisional section — refined after your answers.'],$sectionIds);
        if(!is_array($sections)||!array_is_list($sections)||!$sections)$this->invalid('AI returned no section plan.');
        $seen=[];
        foreach($sections as &$section){
            if(!is_array($section)||!in_array($section['id']??null,$sectionIds,true)||isset($seen[$section['id']])){\Illuminate\Support\Facades\Log::notice('cms_ai_invalid_section_plan',['allowed'=>$sectionIds,'returnedIds'=>array_map(fn($s)=>is_array($s)?($s['id']??null):null,$sections)]);$this->invalid('AI returned an invalid section plan.');}
            $layout=$section['layout']??null;if($layout!==null&&(!is_string($layout)||!preg_match('/^[a-z-]{1,60}$/D',$layout)))$this->invalid('AI returned an invalid layout.');$surface=$section['surface']??null;if($surface!==null&&!in_array($surface,['default','light','contrast'],true))$this->invalid('AI returned an unsupported section surface.');
            $seen[$section['id']]=true;$section=['id'=>$section['id'],'purpose'=>$this->text($section['purpose']??'',2000)]+($surface!==null?['surface'=>$surface]:[])+($layout!==null?['layout'=>$layout]:[]);
        }
        $warnings=$data['warnings']??[];
        if(!is_array($warnings)||!array_is_list($warnings)||count($warnings)>24)$this->invalid('AI returned invalid warnings.');
        return ['summary'=>$summary,'questions'=>$normalized,'warnings'=>array_map(fn($w)=>$this->text($w,1500),$warnings),'sections'=>$sections,'provisional'=>$provisional];
    }

    public function answers(array $questions,array $answers): array
    {
        $result=[];$known=array_column($questions,'id');
        foreach(array_keys($answers) as $id)if(!in_array($id,$known,true))$this->invalid('Answer does not belong to this Brief.');
        foreach($questions as $q){
            $v=$answers[$q['id']]??($q['type']==='multiple_select'?[]:'');
            if($q['type']==='multiple_select'){
                if(!is_array($v)||!array_is_list($v)||count($v)!==count(array_unique($v,SORT_REGULAR)))$this->invalid('Choose valid answers.');
                foreach($v as $choice)if(!is_string($choice)||!in_array($choice,$q['options'],true))$this->invalid('Choose an available answer.');
            }else{
                $v=$this->text($v,6000);
                if($q['type']!=='text'&&$v!==''&&!in_array($v,$q['options'],true))$this->invalid('Choose an available answer.');
            }
            if(($q['required']??true)&&($v===''||$v===[]))throw ValidationException::withMessages(['answers.'.$q['id']=>'Please answer this question.']);
            $result[$q['id']]=$v;
        }
        return $result;
    }
    private function text(mixed $v,int $max): string
    {
        if(!is_string($v)||mb_strlen($v)>$max||strip_tags($v)!==$v)$this->invalid('AI returned unsupported Brief content.');
        return trim($v);
    }
    private function invalid(string $message): never {throw ValidationException::withMessages(['brief'=>$message]);}
}
