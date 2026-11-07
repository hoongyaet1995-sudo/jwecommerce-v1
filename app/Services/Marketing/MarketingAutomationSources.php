<?php
namespace App\Services\Marketing;

use App\Models\{Product,Category,Collection,Coupon,Page};
use Illuminate\Support\Facades\Validator;

/** Read-only source resolution. Never changes catalog, collection membership or vouchers. */
class MarketingAutomationSources
{
 public const KINDS=['products','categories','collections','promotions','pages'];
 public function rangeInput(array $input):array {
  $rules=['source_scope'=>'sometimes|in:all,selected','sources'=>'required|array:'.implode(',',self::KINDS),'source_modes'=>'sometimes|array:'.implode(',',self::KINDS),'preview_page'=>'sometimes|array:'.implode(',',self::KINDS)];
  foreach(self::KINDS as $k){$rules['sources.'.$k]='present|array';$rules['sources.'.$k.'.*']='integer|distinct';$rules['source_modes.'.$k]='required_with:source_modes|in:none,all,items';$rules['preview_page.'.$k]='sometimes|integer|min:0|max:1000000';}
  return Validator::make($input,$rules)->validate()+['source_scope'=>'selected'];
 }
 public function modes(array $settings,bool $effective=true):array {
  $out=[];foreach(self::KINDS as $k)$out[$k]=$effective&&($settings['source_scope']??'selected')==='all'?'all':($settings['source_modes'][$k]??(!empty($settings['sources'][$k])?'items':'none'));
  return $out;
 }
 public function validateSettings(array $settings):void {
  $settings=$this->rangeInput($settings);$modes=$this->modes($settings);$included=false;
  foreach($modes as $kind=>$mode){if($mode==='none')continue;$included=true;if($mode!=='items')continue;$ids=$settings['sources'][$kind];abort_unless($ids&&$this->query($kind)->whereIn('id',$ids)->count()===count($ids),422,'A selected '.$kind.' source is missing or unavailable.');}
  abort_unless($included,422,'Choose at least one data source.');
 }
 public function query(string $kind) {
  return match($kind){
   'products'=>Product::where('status','active'),
   'categories'=>Category::where('is_active',true),
   'collections'=>Collection::where('is_active',true),
   'promotions'=>Coupon::currentlyValid(),
   'pages'=>Page::where('is_published',true)->whereNull('archived_at'),
   default=>abort(422,'Unknown source type.')
  };
 }
 public function label(string $kind):string {return match($kind){'products','collections','pages'=>'title',default=>'name'};}
 public function labelValue(string $kind,$row):string {return (string)($row->{$this->label($kind)}?:($kind==='promotions'?$row->code:'Unnamed source'));}
 public function search(array $input):array {
  $v=Validator::make($input,['kind'=>'required|in:'.implode(',',self::KINDS),'q'=>'nullable|string|max:100'])->validate();$q=trim($v['q']??'');if($q==='')return ['items'=>[],'more'=>false];
  $field=$this->label($v['kind']);
  // Explicit ESCAPE keeps wildcard input literal across SQLite and MySQL.
  $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$kind=$v['kind'];
  $rows=$this->query($kind)->where(function($where)use($field,$pattern,$kind){$where->whereRaw($field." LIKE ? ESCAPE '!'",[$pattern]);if($kind==='promotions')$where->orWhereRaw("code LIKE ? ESCAPE '!'",[$pattern]);})->orderBy($field)->limit(21)->get();
  return ['items'=>$rows->take(20)->map(fn($r)=>['id'=>$r->id,'label'=>$this->labelValue($kind,$r)])->values()->all(),'more'=>$rows->count()>20];
 }
 public function validate(array $sources):void {
  foreach(self::KINDS as $kind){$ids=$sources[$kind]??[];abort_unless($this->query($kind)->whereIn('id',$ids)->count()===count($ids),422,'A selected '.$kind.' source is missing or unavailable.');}
  abort_unless(array_sum(array_map('count',$sources))>0,422,'Choose at least one data source.');
 }
 public function labels(array $sources):array {
  $result=[];foreach(self::KINDS as $kind){$rows=$this->query($kind)->whereIn('id',$sources[$kind]??[])->get()->keyBy('id');$result[$kind]=array_map(fn($id)=>['id'=>$id,'label'=>isset($rows[$id])?$this->labelValue($kind,$rows[$id]):'Unavailable '.$kind.' #'.$id,'available'=>isset($rows[$id])],$sources[$kind]??[]);}return $result;
 }
 private function products(array $s,?array $modes=null) {
  $modes??=$this->modes(['sources'=>$s]);if($modes['products']==='all')return $this->query('products');
  $dynamicQuery=Collection::where('is_active',true)->where('type','dynamic');if($modes['collections']!=='all')$dynamicQuery->whereIn('id',$modes['collections']==='items'?$s['collections']:[]);
  $dynamic=$dynamicQuery->get();$dynamicIds=[];
  if($dynamic->isNotEmpty())Product::where('status','active')->with(['category:id,slug,name','variants:id,product_id,stock'])->chunkById(200,function($products)use($dynamic,&$dynamicIds){foreach($products as $product)foreach($dynamic as $collection){$rules=collect($collection->dynamic_rules??[]);if($rules->isEmpty())continue;$matches=$rules->map(fn($rule)=>app(\App\Services\DynamicCollectionService::class)->matches($product,$rule));if($collection->match_type==='any'?$matches->contains(true):$matches->every(fn($v)=>$v)){$dynamicIds[]=$product->id;break;}}});
  $categories=$this->query('categories');if($modes['categories']!=='all')$categories->whereIn('id',$modes['categories']==='items'?$s['categories']:[]);
  return Product::where('status','active')->where(function($q)use($s,$modes,$categories,$dynamicIds){$q->whereIn('id',$modes['products']==='items'?$s['products']:[])->orWhereIn('category_id',$categories->select('id'))->orWhereIn('id',array_unique($dynamicIds))->orWhereHas('collections',function($c)use($s,$modes){if($modes['collections']!=='all')$c->whereIn('collections.id',$modes['collections']==='items'?$s['collections']:[]);$c->where('is_active',true)->where(fn($x)=>$x->whereNull('type')->orWhere('type','!=','dynamic'));});});
 }
 public function pool(array $settings):array {
  $this->validateSettings($settings);$s=$settings['sources'];$modes=$this->modes($settings);
  $products=$this->products($s,$modes);
  $total=(clone $products)->count();
  // Bounded model candidate window, not a cap on configured sources. Rank by prompt terms.
  $terms=array_values(array_filter(preg_split('/[^\pL\pN]+/u',$settings['prompt']),fn($x)=>mb_strlen($x)>=3));
  if($terms){$rank=[];$bindings=[];foreach(array_slice(array_unique($terms),0,12) as $term){$rank[]="CASE WHEN title LIKE ? ESCAPE '!' THEN 1 ELSE 0 END";$bindings[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';}$products->orderByRaw('('.implode('+',$rank).') DESC',$bindings);}
  $recent=$settings['_recent_product_ids']??[];if($recent)$products->orderByRaw('CASE WHEN id IN ('.implode(',',array_fill(0,count($recent),'?')).') THEN 1 ELSE 0 END',$recent);
  $products->orderBy('id');$pinned=collect();
  // Reserve relevant candidates before rotation so explicit direction cannot be displaced by novelty.
  if($terms)$pinned=(clone $products)->whereRaw('('.implode('+',$rank).') > 0',$bindings)->limit(20)->get();
  $remaining=(clone $products)->whereNotIn('id',$pinned->pluck('id'));
  $rows=$pinned->concat($this->window($remaining,60-$pinned->count(),(int)($settings['_rotation']??0)));
  $candidates=$rows->map(fn($p)=>['id'=>$p->id,'title'=>$p->title,'facts'=>mb_substr(strip_tags($p->description??''),0,700)])->all();
  $pageQuery=$this->rangeQuery('pages',$settings)->with('publishedDocument');if($modes['pages']==='all')$pageQuery->whereHas('publishedDocument');$pageQuery->orderBy('id');
  $pages=[];$pageRows=$modes['pages']==='all'?$this->window($pageQuery,10,(int)($settings['_rotation']??0)):$pageQuery->get();foreach($pageRows as $p){$doc=$p->publishedDocument;abort_unless($doc,422,'Publish the selected CMS page before using it as a source.');$text=$this->pageText($doc->document,$modes['pages']==='all');$pages[]=['id'=>$p->id,'title'=>$p->title,'facts'=>$text,'hash'=>$doc->content_hash];}
  $promotionQuery=$this->rangeQuery('promotions',$settings)->orderBy('id');
  $promotions=($modes['promotions']==='all'?$this->window($promotionQuery,10,(int)($settings['_rotation']??0)):$promotionQuery->get())->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'code'=>$p->code,'discount_type'=>$p->discount_type,'value'=>$p->value,'min_spend'=>$p->min_spend,'per_customer_limit'=>$p->per_customer_limit,'usage_limit'=>$p->usage_limit,'starts_at'=>$p->starts_at?->toIso8601String(),'ends_at'=>$p->ends_at?->toIso8601String(),'description'=>$p->description])->all();
  $pool=['products'=>$candidates,'pages'=>$pages,'promotions'=>$promotions,'eligible_products'=>$total,'candidate_rule'=>'Up to 60 active product candidates, reserving direction-relevant products first, then using memory and rotating eligible-row windows. All mode also retrieves up to 10 published pages and 10 valid promotions; these are per-run retrieval windows, not configuration limits.'];
  abort_if(strlen(json_encode($pool,JSON_UNESCAPED_UNICODE))>65000,422,'The source context exceeds the AI selection capacity. Narrow this automation source pool.');
  abort_if(!$candidates&&!$pages&&!$promotions,422,'These sources currently contain no usable content.');return $pool;
 }
 private function window($query,int $size,int $rotation) {
  $total=(clone $query)->reorder()->count();if(!$total||!$size)return collect();$size=min($size,$total);$offset=($rotation*$size)%$total;
  $rows=(clone $query)->offset($offset)->limit($size)->get();
  if($rows->count()<$size)$rows=$rows->concat((clone $query)->limit($size-$rows->count())->get());
  return $rows;
 }
 public function rangeQuery(string $kind,array $settings) {
  $mode=$this->modes($settings)[$kind];$q=$this->query($kind);if($mode!=='all')$q->whereIn('id',$mode==='items'?$settings['sources'][$kind]:[]);return $q;
 }
 private function pageText(array $doc,bool $bounded=false):string {
  // Copy-only fields; never export forms, tracking, metadata, private settings or arbitrary document JSON.
  $text=[];$walk=function($node)use(&$walk,&$text){if(!is_array($node))return;if(($node['isVisible']??true)===false)return;foreach(['text','title','heading','subheading','subtitle','description','body','content','label','html'] as $key){if(!is_string($node[$key]??null))continue;$clean=trim(html_entity_decode(strip_tags($node[$key]),ENT_QUOTES|ENT_HTML5,'UTF-8'));if($clean!=='')$text[]=$clean;}foreach(['sections','items','columns','blocks','slides'] as $key)foreach(is_array($node[$key]??null)?$node[$key]:[] as $child)$walk($child);};$walk($doc);
  $value=implode("\n",array_unique($text));if($bounded)return mb_substr($value,0,1200);abort_if(mb_strlen($value)>10000,422,'A CMS page exceeds the source context capacity. Choose a more focused page.');return $value;
 }
 public function selected(array $pool,array $selection):array {
  $v=Validator::make($selection,['product_ids'=>'present|array','product_ids.*'=>'integer|distinct','page_ids'=>'present|array','page_ids.*'=>'integer|distinct','promotion_ids'=>'present|array','promotion_ids.*'=>'integer|distinct','reason'=>'required|string|max:1000'])->validate();
  $out=[];foreach(['products'=>'product_ids','pages'=>'page_ids','promotions'=>'promotion_ids'] as $kind=>$key){abort_unless(count(array_diff($v[$key],array_column($pool[$kind],'id')))===0,422,'AI selected a source outside the allowed pool.');$out[$kind]=array_values(array_filter($pool[$kind],fn($r)=>in_array($r['id'],$v[$key],true)));}
  abort_if(!array_sum(array_map('count',$out)),422,'AI did not select usable source content.');return $out;
 }
 public function verify(array $context):void {
  if(isset($context['allowed_sources'])){$settings=['source_scope'=>$context['source_scope']??'selected','sources'=>$context['allowed_sources']];if(isset($context['source_modes']))$settings['source_modes']=$context['source_modes'];$this->validateSettings($settings);$ids=array_column($context['products']??[],'id');$query=$this->products($context['allowed_sources'],$this->modes($settings));abort_unless($query->whereIn('id',$ids)->count()===count($ids),409,'A selected product left the allowed source pool.');}
  foreach($context['pages']??[] as $old){$p=$this->query('pages')->with('publishedDocument')->find($old['id']);abort_unless($p&&$p->title===$old['title']&&$p->publishedDocument&&$p->publishedDocument->content_hash===$old['hash'],409,'A selected CMS page changed. Start a new generation.');}
  foreach($context['promotions']??[] as $old){$p=$this->query('promotions')->find($old['id']);abort_unless($p,409,'A voucher is no longer available.');foreach($old as $key=>$value){$actual=$p->$key;if($actual instanceof \DateTimeInterface)$actual=$actual->format('Y-m-d\TH:i:sP');abort_unless((string)$actual===(string)$value,409,'Voucher terms changed. Start a new generation.');}}
 }
 public function voucherTerms(array $promotions):string {
  $terms=[];foreach($promotions as $p){$benefit=match($p['discount_type']){'percentage'=>$p['value'].'% off','fixed_amount'=>'RM '.number_format($p['value']/100,2,'.','').' off','free_shipping'=>'Free shipping',default=>'See voucher terms'};$line='Voucher '.$p['code'].': '.$benefit.'.';if(($p['min_spend']??0)>0)$line.=' Minimum spend RM '.number_format($p['min_spend']/100,2,'.','').'.';if($p['per_customer_limit']??null)$line.=' Limit '.$p['per_customer_limit'].' use(s) per customer.';if($p['starts_at'])$line.=' Starts '.\Carbon\Carbon::parse($p['starts_at'])->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i').' (UTC+8).';if($p['ends_at'])$line.=' Ends '.\Carbon\Carbon::parse($p['ends_at'])->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i').' (UTC+8).';if($p['usage_limit']??null)$line.=' Total redemption limit '.$p['usage_limit'].'.';$terms[]=$line;}return implode("\n",$terms);
 }
}
