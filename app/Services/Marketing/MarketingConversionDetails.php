<?php
namespace App\Services\Marketing;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Read-only drill-down. No customer contacts, session identifiers or provider credentials. */
class MarketingConversionDetails
{
    private function context(Request $r): array
    {
        $report=app(MarketingAnalytics::class)->report($r);
        $f=$report['filters'];
        return [$report, array_column($report['campaigns'],'id'), CarbonImmutable::parse($f['from'],$f['timezone'])->startOfDay()->utc(), CarbonImmutable::parse($f['to'],$f['timezone'])->endOfDay()->utc()];
    }
    private function scoped(string $table,array $ids) {
        return DB::table($table)->whereIn('campaign_id',$ids)->where('merchant_key',app(MarketingPosts::class)->merchant());
    }
    private function restrictLink($query,Request $r) {
        $r->validate(['link'=>'nullable|uuid']);
        return $query->when($r->input('link'),fn($q,$id)=>$q->where('link_id',$id));
    }
    public function listing(Request $r): array
    {
        $v=$r->validate(['kind'=>['required',Rule::in(['orders','refunds','enquiries','events','sources'])],'event'=>['nullable',Rule::in(['arrival','page_view','view_item','add_to_cart','checkout','quotation_sent','quotation_accepted'])],'page'=>'nullable|integer|min:1|max:10000']);
        [$report,$ids,$from,$to]=$this->context($r);$kind=$v['kind'];
        if($kind==='sources')return $this->sources($r,$ids,$from,$to);
        if(in_array($kind,['orders','refunds'],true)) {
            $query=$this->restrictLink($this->scoped('marketing_order_attributions',$ids),$r)->where('state','paid')->whereBetween('paid_at',[$from,$to])->when($kind==='refunds',fn($q)=>$q->where('refunded_minor','>',0));
            $page=$query->orderByDesc('paid_at')->orderByDesc('id')->paginate(20,['order_id','link_id','campaign_id','base_currency','base_minor','refunded_minor','paid_at','policy']);
            $numbers=DB::table('orders')->whereIn('id',$page->pluck('order_id'))->pluck('number','id');
            $rows=$page->map(fn($a)=>['id'=>$a->order_id,'reference'=>$numbers[$a->order_id]??'Order unavailable','kind'=>'order','campaign_id'=>$a->campaign_id,'currency'=>$a->base_currency,'gross_minor'=>(int)$a->base_minor,'refunded_minor'=>(int)$a->refunded_minor,'net_minor'=>(int)$a->base_minor-(int)$a->refunded_minor,'at'=>$a->paid_at,'route'=>$a->policy==='enquiry-source-v1'?'Accepted quotation':'Buy online','source'=>$this->source($a->link_id,$ids)])->all();
        } elseif($kind==='enquiries') {
            $query=$this->restrictLink($this->scoped('marketing_enquiry_attributions',$ids),$r)->whereBetween('submitted_at',[$from,$to]);
            $page=$query->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20,['enquiry_id','link_id','campaign_id','submitted_at']);
            $records=DB::table('product_enquiries')->whereIn('id',$page->pluck('enquiry_id'))->get(['id','reference','product_title','status','outcome'])->keyBy('id');
            $rows=$page->map(function($a)use($records,$ids){$e=$records[$a->enquiry_id]??null;return ['id'=>$a->enquiry_id,'reference'=>$e?->reference??'Enquiry unavailable','kind'=>'enquiry','product'=>$e?->product_title,'status'=>$e?->status??'unavailable','at'=>$a->submitted_at,'source'=>$this->source($a->link_id,$ids)];})->all();
        } else {
            $query=$this->restrictLink($this->scoped('marketing_analytics_events',$ids),$r)->whereBetween('occurred_at',[$from,$to])->where('kind',$v['event']??'page_view');
            if(in_array($v['event']??'', ['quotation_sent','quotation_accepted'],true)) {
                $page=$query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20,['details','link_id','occurred_at']);
                $rows=$page->map(function($a)use($ids){$id=data_get(json_decode($a->details??'{}',true),'enquiry_id');$valid=$id&&$this->scoped('marketing_enquiry_attributions',$ids)->where('enquiry_id',$id)->where('link_id',$a->link_id)->exists();$reference=$valid?DB::table('product_enquiries')->where('id',$id)->value('reference'):null;return ['kind'=>$reference?'enquiry':'activity','id'=>$reference?$id:null,'reference'=>$reference,'events'=>1,'at'=>$a->occurred_at,'source'=>$this->source($a->link_id,$ids)];})->all();
            } else {
                $page=$query->select('link_id')->selectRaw('COUNT(*) AS events, MAX(occurred_at) AS latest_at')->groupBy('link_id')->orderByDesc('latest_at')->orderBy('link_id')->paginate(20);
                $rows=$page->map(fn($a)=>['kind'=>'activity','events'=>(int)$a->events,'at'=>$a->latest_at,'source'=>$this->source($a->link_id,$ids)])->all();
            }
        }
        return ['rows'=>$rows,'total'=>$page->total(),'page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'filters'=>$report['filters']];
    }
    public function record(Request $r): array
    {
        $v=$r->validate(['kind'=>['required',Rule::in(['order','enquiry'])],'id'=>'required|string|max:64']);
        [, $ids]=$this->context($r);
        if($v['kind']==='order') {
            $a=$this->scoped('marketing_order_attributions',$ids)->where('order_id',$v['id'])->where('state','paid')->first();abort_unless($a,404);
            $o=DB::table('orders')->where('id',$a->order_id)->first(['number','status']);abort_unless($o,404);
            $record=['id'=>$a->order_id,'reference'=>$o->number,'status'=>$o->status,'currency'=>$a->base_currency,'gross_minor'=>(int)$a->base_minor,'refunded_minor'=>(int)$a->refunded_minor,'net_minor'=>(int)$a->base_minor-(int)$a->refunded_minor,'at'=>$a->paid_at,'route'=>$a->policy==='enquiry-source-v1'?'Accepted quotation':'Buy online','attribution'=>$a->policy==='enquiry-source-v1'?'Original enquiry source':'Last eligible click'];
        } else {
            $a=$this->scoped('marketing_enquiry_attributions',$ids)->where('enquiry_id',$v['id'])->first();abort_unless($a,404);
            $e=DB::table('product_enquiries')->where('id',$a->enquiry_id)->first(['reference','product_title','status','outcome']);abort_unless($e,404);
            $q=DB::table('enquiry_quotations')->where('product_enquiry_id',$a->enquiry_id)->first(['number','status']);
            $record=['quotation'=>$q?['number'=>$q->number,'status'=>$q->status]:null,'id'=>$a->enquiry_id,'reference'=>$e->reference,'product'=>$e->product_title,'status'=>$e->status,'outcome'=>$e->outcome,'at'=>$a->submitted_at,'attribution'=>'Original enquiry source','detail_url'=>$r->user()->is_super_admin?'/admin/enquiries?enquiry='.rawurlencode($a->enquiry_id):null];
        }
        return ['record'=>$record,'source'=>$this->source($a->link_id,$ids)];
    }
    private function source(string $id,array $ids): array
    {
        $l=$this->scoped('marketing_tracking_links',$ids)->where('id',$id)->first();if(!$l)return ['campaign_name'=>'Source unavailable','post_name'=>'Post unavailable','account_name'=>'Account unavailable','platform'=>null];
        $c=app(MarketingCampaigns::class)->query((int)$l->user_id)->find($l->campaign_id);
        if(($l->channel??'post')==='email'){
            $delivery=$this->scoped('marketing_email_deliveries',$ids)->where('id',$l->destination_id)->first(['snapshot']);$snapshot=$delivery?json_decode($delivery->snapshot,true):[];
            return ['campaign_name'=>$c?->name??'Campaign unavailable','post_name'=>$snapshot['subject']??'Test email','account_name'=>$snapshot['from_name']??'Store email sender','platform'=>'Email','post_url'=>null,'link_id'=>$l->id];
        }
        $p=DB::table('marketing_posts')->where('id',$l->post_id)->where('merchant_key',$l->merchant_key)->where('user_id',$l->user_id)->first(['document']);
        $account=DB::table('marketing_channel_connections')->where('id',$l->destination_id)->where('merchant_key',$l->merchant_key)->first(['name','platform']);
        $doc=$p?json_decode($p->document,true):[];
        return ['campaign_name'=>$c?->name??'Campaign unavailable','post_name'=>$doc['title']??'Post unavailable','account_name'=>$account?->name??'Account unavailable','platform'=>$account?->platform,'post_url'=>$p?'/admin/marketing/posts#post/'.$l->post_id:null,'link_id'=>$l->id];
    }
    private function sources(Request $r,array $ids,$from,$to): array
    {
        $links=$this->scoped('marketing_tracking_links',$ids)->orderBy('id')->paginate(20,['id']);
        $rows=[];
        foreach($links as $l){
            $events=$this->scoped('marketing_analytics_events',$ids)->where('link_id',$l->id)->whereBetween('occurred_at',[$from,$to]);
            $visits=(clone $events)->where('kind','arrival')->distinct()->count('session_key');
            $enquiries=$this->scoped('marketing_enquiry_attributions',$ids)->where('link_id',$l->id)->whereBetween('submitted_at',[$from,$to])->count();
            $money=$this->scoped('marketing_order_attributions',$ids)->where('link_id',$l->id)->where('state','paid')->whereBetween('paid_at',[$from,$to])->select('base_currency')->selectRaw('COUNT(*) as paid_orders, SUM(base_minor) as paid_minor, SUM(refunded_minor) as refunded_minor')->groupBy('base_currency')->get();
            $rows[]=['kind'=>'source','source'=>$this->source($l->id,$ids),'visits'=>$visits,'enquiries'=>$enquiries,'paid_orders'=>$money->sum('paid_orders'),'money'=>$money];
        }
        return ['rows'=>$rows,'page'=>$links->currentPage(),'last_page'=>$links->lastPage(),'total'=>$links->total()];
    }
}
