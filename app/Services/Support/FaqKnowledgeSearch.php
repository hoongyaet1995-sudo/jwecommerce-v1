<?php
namespace App\Services\Support;

class FaqKnowledgeSearch
{
    public function exact(string $question, array $scope): array
    {
        $normalize=fn($s)=>trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',mb_strtolower($s)));
        return array_values(array_filter($this->items($scope),fn($i)=>$normalize($i['question'])===$normalize($question)));
    }
    public function search(string $query, array $scope): array
    {
        $items=$this->items($scope);if(!$items)return [];
        $mode=$scope['retrievalDepth']??'strict';
        $crossScript=!$this->exact($query,$scope)&&collect($items)->contains(fn($i)=>(bool)preg_match('/\p{Han}/u',$i['question'])!==(bool)preg_match('/\p{Han}/u',$query));
        $queries=[$query];if(in_array($mode,['semantic','deep'],true)&&!$crossScript)$queries=array_merge($queries,app(FaqQueryExpansion::class)->queries($query));
        $stop=['the','how','what','can','you','for','and','is','to','do','i','my','a'];
        $terms=[];
        foreach(array_unique($queries) as $search){$tokens=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($search),-1,PREG_SPLIT_NO_EMPTY);$tokens=array_filter($tokens,fn($t)=>mb_strlen($t)>=2&&!in_array($t,$stop,true));$terms=array_merge($terms,array_slice(array_unique($tokens),0,12));}
        $terms=array_unique($terms);
        $rank=[];
        foreach($this->items($scope) as $i){
            $score=0;foreach($terms as $t){if(mb_strlen($t)<2||in_array($t,$stop,true))continue;
                if(mb_stripos($i['question'],$t)!==false)$score+=3;
                if(mb_stripos(implode(' ',$i['keywords']??[]),$t)!==false)$score+=4;
                if(mb_stripos($i['answer'],$t)!==false)$score+=1;
            }
            if($score>=3)$rank[]=['item'=>$i,'score'=>$score];
        }
        usort($rank,fn($a,$b)=>$b['score']<=>$a['score']);
        $candidates=array_slice(array_column($rank,'item'),0,10);
        $selected=array_slice($candidates,0,3);
        if($crossScript||(!$selected&&preg_match('/\p{Han}/u',$query))){$ids=app(FaqQueryExpansion::class)->match($query,$items);$byId=array_column($items,null,'id');$selected=array_map(fn($id)=>$byId[$id],array_values(array_intersect($ids,array_keys($byId))));$candidates=$selected;}
        $rankingFacts=array_map(fn($i)=>array_intersect_key($i,array_flip(['id','question','answer'])),$candidates);
        if($mode==='deep'&&$candidates&&strlen(json_encode($rankingFacts,JSON_UNESCAPED_UNICODE))<=12000){$ids=app(FaqQueryExpansion::class)->rank($query,$rankingFacts);$byId=array_column($candidates,null,'id');$selected=array_map(fn($id)=>$byId[$id],$ids);}
        // Include complete policy siblings: exclusions may override a matching general policy.
        $categories=array_unique(array_column($selected,'category'));
        foreach($this->items($scope) as $i)if(in_array($i['category']??null,$categories,true)&&!in_array($i['id'],array_column($selected,'id'),true))$selected[]=$i;
        $facts=array_map(fn($i)=>array_intersect_key($i,array_flip(['id','question','answer'])),$selected);
        if(strlen(json_encode($facts,JSON_UNESCAPED_UNICODE))>12000)throw new \RuntimeException('Related policy group exceeds safe context budget');
        return $facts;
    }
    private function items(array $scope):array
    {
        return ($scope['isEnabled']??false)?array_map(fn($i)=>$i+['id'=>'faq','category'=>'others'],array_values(array_filter($scope['items']??[],fn($i)=>$i['isActive']??false))):[];
    }
}
