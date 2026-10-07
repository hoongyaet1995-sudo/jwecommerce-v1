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
        if($settings['productCatalog']['isEnabled']??false){$tools['product.search']='Published products: name, specifications, authorized prices and availability. Translate product type terms to English; resolve recent product references. For general questions about what the store sells use an empty query. For named products normalize spacing, e.g. KALLAX77x77 means KALLAX 77x77.';if($settings['productCatalog']['canRecommendUpsells']??false)$tools['product.count']='Whole-store published product count.';}
        if($settings['discountKnowledge']['isEnabled']??false){$tools['discount.search']='Authorized official offers. General browsing returns only currently live offers.';$tools['discount.lookup_code']='A specific voucher code supplied by customer: includes expired/inactive status when authorized.';}
        if(($settings['productCatalog']['isEnabled']??false)&&($settings['productCatalog']['canRecommendUpsells']??false))$tools['taxonomy.search']='Visible categories and collections: names, published product counts and related product samples. For general browsing use an empty query; otherwise query only the specific category or collection name.';
        if($settings['orderLookup']['isEnabled']??false)$tools['order.lookup']='Customer order status, tracking, purchased items and payment status. Server verification required; no private order data is sent to LLM.';
        if(($settings['orderLookup']['isEnabled']??false)&&($settings['orderLookup']['provideTrackingEntry']??true))$tools['support.tracking']='Public Order Tracking entry instructions, never private order facts. Use for how/where to track an order.';
        return $tools;
    }
    public function retrieve(string $question,array $settings,array $history=[], ?\App\Models\SupportConversation $conversation=null, string $language="en", bool $withUnderstanding=false):array
    {
        $tools=$this->tools($settings);if(!$tools&&!$withUnderstanding)return [];
        $plan=app(SupportUnderstandingService::class)->understand($question,$tools,$history,$settings);
        if(($plan['negotiation']??false)&&!($settings['guardrails']['priceBargaining']['suggestOfficialVouchersOnly']??true)){
            $plan['tools']=array_values(array_filter($plan['tools'],fn($step)=>!str_starts_with($step['name'],'discount.')));
        }
        $facts=$withUnderstanding?[['id'=>'support:understanding','question'=>'Internal understanding only','answer'=>json_encode($plan,JSON_UNESCAPED_UNICODE)]]:[];
        $language=in_array($plan['language'],['en','ms','zh'],true)?$plan['language']:$language;

        if(($plan['clarification']??'none')!=='none')$facts[]=['id'=>'support:clarification','question'=>'Missing context','answer'=>$plan['clarification']];
        if (($plan['escalation'] ?? 'none') !== 'none') $facts[]=['id'=>'support:intent','question'=>'Internal escalation classification','answer'=>$plan['escalation']];
        foreach($plan['tools'] as $step){
            $query=$step['query'];
            if($step['name']==='order.lookup'){$facts[]=['id'=>'order:private','question'=>'Verified order response','answer'=>app(OrderKnowledgeService::class)->reply($question,$settings['orderLookup'],$conversation,$language)];continue;}
            $result=match($step['name']){
                'support.tracking'=>[['id'=>'support:tracking','question'=>'Public tracking entry','answer'=>'Use the Order Tracking page. Sign in or complete order-email verification before viewing private details.']],
                'taxonomy.search'=>app(TaxonomyKnowledgeService::class)->facts($query,$settings['productCatalog']),
                'faq.search'=>app(FaqKnowledgeSearch::class)->search($query,$settings['faq']),
                'product.count'=>app(ProductKnowledgeService::class)->facts('__catalogue_count__',$settings['productCatalog']),
                'product.search'=>app(ProductKnowledgeService::class)->facts(($settings['productCatalog']['canRecommendUpsells']??false)?($query?:'__catalogue_list__'):$question,$settings['productCatalog']),
                // Code lookup uses only actual customer text, never a model-invented code.
                'discount.lookup_code'=>array_values(array_filter(app(DiscountKnowledgeService::class)->facts($settings['discountKnowledge'],$question),fn($f)=>(json_decode($f['answer'],true)['query_mode']??null)==='specific_code')),
                'discount.search'=>array_slice(app(DiscountKnowledgeService::class)->facts($settings['discountKnowledge']),0,5),
            };
            $facts=array_merge($facts,$result);
        }
        // A product record cannot replace store care/service restrictions. Check
        // the customer's full question even when the planner selects only products.
        $selectedTools=array_column($plan['tools'],'name');
        if(($settings['productCatalog']['includeStorePolicies']??true)&&isset($tools['faq.search'])&&in_array('product.search',$selectedTools,true)&&!in_array('faq.search',$selectedTools,true)){
            $facts=array_merge($facts,app(FaqKnowledgeSearch::class)->search($question,$settings['faq']));
            $selectedTools[]='faq.search';
        }
        $facts=$this->bounded(array_values(array_column($facts,null,'id')));
        \Illuminate\Support\Facades\Log::info('support_knowledge_routed',['tools'=>$selectedTools,'fact_count'=>count($facts),'fact_bytes'=>strlen(json_encode($facts,JSON_UNESCAPED_UNICODE))]);
        return $facts;
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
