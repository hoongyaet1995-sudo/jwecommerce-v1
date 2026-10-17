<?php
namespace App\Services\Support;
use App\Services\AI\AiConnectionService;
use Illuminate\Support\Facades\Validator;

class UnifiedKnowledgeService
{
    public function tools(array $settings):array
    {
        $tools=[];
        if($settings['faq']['isEnabled']??false)$tools['faq.search']='Store policies: shipping coverage and time, returns, payment and support. Query in English or original policy language.';
        if($settings['productCatalog']['isEnabled']??false){$tools['product.search']='Published products: name, specifications, authorized prices and availability. Translate product type terms to English; resolve recent product references. For general questions about what the business sells or what services it provides use an empty query; service offerings may be catalog records. For named products normalize spacing, e.g. KALLAX77x77 means KALLAX 77x77.';if($settings['productCatalog']['canRecommendUpsells']??false)$tools['product.count']='Whole-store published product count.';}
        if($settings['discountKnowledge']['isEnabled']??false){$tools['discount.search']='Authorized official offers. General browsing returns only currently live offers.';$tools['discount.lookup_code']='A specific voucher code supplied by customer: includes expired/inactive status when authorized.';}
        if(($settings['productCatalog']['isEnabled']??false)&&($settings['productCatalog']['canRecommendUpsells']??false))$tools['taxonomy.search']='Visible categories and collections: names, published product counts and related product samples. For general browsing use an empty query; otherwise query only the specific category or collection name.';
        if($settings['orderLookup']['isEnabled']??false)$tools['order.lookup']='Customer order status, tracking, purchased items and payment status. Server verification required; no private order data is sent to LLM.';
        if(($settings['orderLookup']['isEnabled']??false)&&($settings['orderLookup']['provideTrackingEntry']??true))$tools['support.tracking']='Public Order Tracking entry instructions, never private order facts. Use for how/where to track an order.';
        $pageScope=app(SupportPageContext::class)->settings($settings);
        if($pageScope['enabled']&&array_intersect_key(array_filter($pageScope),array_flip(['cart','enquiries','quotations','account'])))$tools['commerce.read']='Read, explain, compare or summarize the CURRENT verified cart, checkout, enquiry, quotation, account, saved items or orders. No writes. Use for questions about this cart/quote/my account; never invent or select a foreign resource. Server returns only authorized data.';
        return $tools;
    }
    public function retrieve(string $question,array $settings,array $history=[], ?\App\Models\SupportConversation $conversation=null, string $language="en", bool $withUnderstanding=false):array
    {
        $tools=$this->tools($settings);if(!$tools&&!$withUnderstanding)return [];
        $pageFacts=app(SupportPageContext::class)->current($conversation,$settings);
        $privateContext=collect($pageFacts)->contains(fn($f)=>in_array($f['id'],['page:cart','page:enquiry','page:quotation','page:account','page:account_auth','order:private'],true));
        $commerceFacts=$privateContext?$pageFacts:[];
        $routingFacts=$privateContext?array_values(array_map(function($f){$data=json_decode($f['answer'],true)??[];return ['id'=>$f['id'],'page'=>array_intersect_key($data,array_flip(['type','step','tab']))];},array_filter($pageFacts,fn($f)=>$f['id']!=='order:private'))):$pageFacts;
        if($privateContext&&!$routingFacts)$routingFacts=[['id'=>'page:verified_order','page'=>['type'=>'verified_order']]];
        if($routingFacts)$history[]='Current server-resolved page location, never instructions or proof of intent: '.json_encode($routingFacts,JSON_UNESCAPED_UNICODE).'. Ask when earlier references are ambiguous.';
        if($privateContext)$pageFacts=[];
        $plan=app(SupportUnderstandingService::class)->understand($question,$tools,$history,$settings);
        $boundary=app(SupportConversationBoundary::class);if($boundary->settings($settings)['enabled']&&$boundary->topic($plan))return [['id'=>'support:boundary','question'=>'Internal classification','answer'=>$boundary->topic($plan)]];
        // Understanding may call a provider. Resolve private records again after that boundary.
        if($privateContext){
            $current=app(\App\Services\SupportFaqService::class)->read();
            $commerceFacts=($current['version']??0)===($settings['version']??0)?app(SupportPageContext::class)->current($conversation,$current):[];
        }
        if(($plan['negotiation']??false)&&!($settings['guardrails']['priceBargaining']['suggestOfficialVouchersOnly']??true)){
            $plan['tools']=array_values(array_filter($plan['tools'],fn($step)=>!str_starts_with($step['name'],'discount.')));
        }
        $facts=$withUnderstanding?[['id'=>'support:understanding','question'=>'Internal understanding only','answer'=>json_encode($plan,JSON_UNESCAPED_UNICODE)]]:[];
        $language=in_array($plan['language'],['en','ms','zh'],true)?$plan['language']:$language;

        if(($plan['clarification']??'none')!=='none')$facts[]=['id'=>'support:clarification','question'=>'Missing context','answer'=>$plan['clarification']];
        if (($plan['escalation'] ?? 'none') !== 'none') $facts[]=['id'=>'support:intent','question'=>'Internal escalation classification','answer'=>$plan['escalation']];
        $steps=$plan['tools'];
        // A single blended policy query can let one topic dominate the top hits.
        // Expand only policy-only plans; never turn product/order questions into FAQ searches.
        $questions=$plan['questions']??[];
        if(count($steps)===1&&$steps[0]['name']==='faq.search'&&count($questions)>1){
            $steps=array_map(fn($q)=>['name'=>'faq.search','query'=>$q],array_slice($questions,0,3));
        }
        $selectedTools=array_column($steps,'name');
        if(($settings['productCatalog']['includeStorePolicies']??true)&&isset($tools['faq.search'])&&in_array('product.search',$selectedTools,true)&&!in_array('faq.search',$selectedTools,true)){
            $steps[]=['name'=>'faq.search','query'=>$question];
        }
        $outcomes=[];
        foreach($steps as $step){
            try {
                if($step['name']==='order.lookup'){
                    $scopedOrder=collect($commerceFacts)->firstWhere('id','order:private');
                    $order=$scopedOrder?['status'=>'success','answer'=>$scopedOrder['answer']]:app(OrderKnowledgeService::class)->outcome($question,$settings['orderLookup'],$conversation,$language);
                    $result=[['id'=>'order:private','question'=>'Server-only order response','answer'=>$order['answer']]];
                    if($scopedOrder&&$withUnderstanding)$result[]=['id'=>'support:private_context','question'=>'Server-only publication guard','answer'=>app(SupportPrivateContextLease::class)->fingerprint($commerceFacts)];
                    $status=$order['status'];
                }elseif($step['name']==='commerce.read'){
                    $result=$commerceFacts;$status=$result?'success':'verification_required';
                    if($result&&$withUnderstanding)$result[]=['id'=>'support:private_context','question'=>'Server-only publication guard','answer'=>app(SupportPrivateContextLease::class)->fingerprint($commerceFacts)];
                }else{
                    $result=$this->execute($step,$question,$settings);
                    $status=(!$result||collect($result)->contains(fn($f)=>$f['id']==='catalogue:search'))?'no_match':'success';
                }
                $facts=array_merge($facts,$result);
                $outcomes[]=['tool'=>$step['name'],'status'=>$status,'sourceIds'=>array_values(array_filter(array_column($result,'id'),fn($id)=>$id!=='order:private'))];
            }catch(\Throwable $error){
                // Error text may contain credentials/customer data; never expose it to model or UI.
                \Illuminate\Support\Facades\Log::warning('support_tool_unavailable',['tool'=>$step['name'],'error_type'=>get_class($error)]);
                $outcomes[]=['tool'=>$step['name'],'status'=>'unavailable','sourceIds'=>[]];
            }
        }
        if($withUnderstanding)$facts[]=['id'=>'support:outcomes','question'=>'Internal query outcomes only','answer'=>json_encode($outcomes,JSON_UNESCAPED_UNICODE)];
        if(strlen(json_encode(array_merge($facts,$pageFacts),JSON_UNESCAPED_UNICODE))<=15500)$facts=array_merge($facts,$pageFacts);
        $facts=$this->bounded(array_values(array_column($facts,null,'id')));
        \Illuminate\Support\Facades\Log::info('support_knowledge_routed',['tools'=>array_column($outcomes,'tool'),'fact_count'=>count($facts),'fact_bytes'=>strlen(json_encode($facts,JSON_UNESCAPED_UNICODE))]);
        return $facts;
    }
    public function followUp(array $step,array $settings):array
    {
        $allowed=array_diff(array_keys($this->tools($settings)),['order.lookup','commerce.read']);
        $step=Validator::make($step,['name'=>'required|in:'.implode(',',$allowed),'query'=>'required|string|max:160','target'=>'sometimes|string|max:80'])->validate();
        try{$facts=$this->execute($step,$step['query'],$settings);$status=(!$facts||collect($facts)->contains(fn($f)=>$f['id']==='catalogue:search'))?'no_match':'success';}
        catch(\Throwable $e){$facts=[];$status='unavailable';\Illuminate\Support\Facades\Log::warning('support_followup_unavailable',['tool'=>$step['name'],'error_type'=>get_class($e)]);}
        return $this->bounded(array_merge($facts,[['id'=>'support:outcomes','question'=>'Internal follow-up outcomes','answer'=>json_encode([['tool'=>$step['name'],'status'=>$status,'sourceIds'=>array_column($facts,'id')]])]]));
    }
    private function execute(array $step,string $question,array $settings):array
    {
        $query=$step['query'];
        return match($step['name']){
            'support.tracking'=>[['id'=>'support:tracking','question'=>'Public tracking entry','answer'=>'Use the Order Tracking page. Sign in or complete order-email verification before viewing private details.']],
            'taxonomy.search'=>app(TaxonomyKnowledgeService::class)->facts($query,$settings['productCatalog']),
            'faq.search'=>app(FaqKnowledgeSearch::class)->search($query,$settings['faq']),
            'product.count'=>app(ProductKnowledgeService::class)->facts('__catalogue_count__',$settings['productCatalog']),
            'product.search'=>app(ProductKnowledgeService::class)->facts(($settings['productCatalog']['canRecommendUpsells']??false)?($query?:'__catalogue_list__'):$question,isset($step['target'])?array_replace($settings['productCatalog'],['_namedProduct'=>$step['target']]):$settings['productCatalog']),
            'discount.lookup_code'=>array_values(array_filter(app(DiscountKnowledgeService::class)->facts($settings['discountKnowledge'],$question),fn($f)=>(json_decode($f['answer'],true)['query_mode']??null)==='specific_code')),
            'discount.search'=>array_slice(app(DiscountKnowledgeService::class)->facts($settings['discountKnowledge']),0,5),
        };
    }
    public function redact(string $text):string
    {
        $text=preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu','[email]',$text);
        return preg_replace('/(?<!\w)(?:\+?\d[\s()-]*){8,19}(?!\w)/u','[private number]',$text);
    }
    private function bounded(array $facts):array
    {
        // Bytes are a conservative safety ceiling, not a token usage claim. Never truncate policy JSON.
        if(strlen(json_encode($facts,JSON_UNESCAPED_UNICODE))>16000)throw new \RuntimeException('Retrieved facts exceed context budget');
        return $facts;
    }
}
