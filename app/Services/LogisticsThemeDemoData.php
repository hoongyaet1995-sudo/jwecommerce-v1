<?php
namespace App\Services;

use App\Models\{Category, Product, ProductImage, ProductVariant, ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Protected Admin-only unsaved models. Never provision public stock or catalog. */
final class LogisticsThemeDemoData
{
    public function catalog(Request $request): array
    {
        $category=new Category(['name'=>'Containers & logistics','slug'=>'demo-logistics','description'=>'Container solutions built around your requirements.','image_url'=>asset('images/themes/logistics/hero.png')]);
        $category->id=-9;
        $rows=[['20ft General Purpose Container','20ft','General Purpose',0],['40ft High Cube Container','40ft','High Cube',0],['20ft Refrigerated Container','20ft','Refrigerated',0],['Container Lock Box','Accessory','Locks & Security',12000],['Heavy Duty Padlock','Accessory','Locks & Security',8000],['Container Twist Lock','Accessory','Container Fittings',10000]];
        $products=collect($rows)->map(function($row,$index)use($category){
            $product=new Product(['title'=>$row[0],'handle'=>'theme-demo-logistics-'.$index,'brand'=>'JTG','status'=>'active','subtitle'=>$index<3?'Specifications, condition and delivery subject to confirmation.':'Container accessories for everyday operations.','base_price'=>$row[3],'purchase_mode'=>$index<3?'quote':'buy','price_display'=>$index<3?'hide':'exact','description'=>'<p>Demonstration product. Availability, specification, condition and delivery are subject to confirmation with the team.</p>','tags'=>[$row[1],$row[2]],'options'=>[]]);
            $product->id=-900-$index;
            $variant=new ProductVariant(['title'=>$index<3?$row[1].' / '.$row[2]:'Standard','sku'=>'DEMO-JTG-'.($index+1),'price'=>$row[3],'stock'=>25,'enabled'=>true,'options'=>[]]);
            $variant->id=$product->id*10;
            $variant->setRelation('product',$product);
            $mainImage=['containers','high-cube','refrigerated','lock-box','padlock','twist-lock'][$index];
            $images=collect($index<3?[$mainImage,'hero','delivery','account']:[$mainImage])->map(function($name,$position)use($product){$image=new ProductImage(['path'=>asset('images/themes/logistics/'.$name.'.png'),'alt'=>$product->title.' · Demonstration image','position'=>$position]);$image->id=$product->id*10-$position;return $image;});
            $content=new ProductPdpContent(['highlights'=>[['id'=>'supply','text'=>'Container solutions','icon'=>'package'],['id'=>'delivery','text'=>'Delivery planning','icon'=>'truck'],['id'=>'support','text'=>'Discuss your requirements','icon'=>'check']],'specifications'=>[['id'=>'type','label'=>'Type','value'=>$row[2]],['id'=>'size','label'=>'Size / category','value'=>$row[1]]],'faqs'=>[['id'=>'delivery','question'=>'Can delivery be arranged?','answer'=>'Contact the team with your delivery location and access details so arrangements can be confirmed.'],['id'=>'availability','question'=>'How do I confirm availability?','answer'=>'Submit your requirements. Specification, condition and availability will be confirmed in your quotation.']],'custom_accordions'=>[],'story'=>['heading'=>'Solutions for a stronger tomorrow.','description'=>'From container supply to delivery planning, discuss the right solution for your project with our team.','image'=>asset('images/themes/logistics/delivery.png'),'alt'=>'Container delivery demonstration']]);
            return $product->setRelation('category',$category)->setRelation('variants',collect([$variant]))->setRelation('images',$images)->setRelation('pdpContent',$content)->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
        });
        foreach($products as $product){
            if($request->has('purchaseMode')&&in_array($request->input('purchaseMode'),ProductPurchaseSettings::MODES,true))$product->purchase_mode=$request->input('purchaseMode');
            if($request->has('priceDisplay')&&in_array($request->input('priceDisplay'),ProductPurchaseSettings::PRICE_DISPLAYS,true))$product->price_display=$request->input('priceDisplay');
        }
        $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());
        $request->attributes->set('websiteThemeDemoProducts',$products);
        $buy=$products->filter(fn($p)=>$p->isBuyMode());$hasPriced=$buy->isNotEmpty();$low=$hasPriced?(int)$buy->min('base_price'):0;$high=$hasPriced?(int)$buy->max('base_price'):0;
        $min=min($high,max($low,$request->integer('min_price',$low)));$max=min($high,max($low,$request->integer('max_price',$high)));if($min>$max)[$min,$max]=[$max,$min];
        $priceRequested=$hasPriced&&($request->boolean('price_filter')||$min!==$low||$max!==$high);$inStock=$hasPriced&&$request->boolean('in_stock');$search=$request->string('search')->trim()->value();
        $sort=in_array($request->input('sort'),['price_asc','price_desc','newest'],true)?$request->input('sort'):'';
        $known=$products->flatMap(fn($p)=>$p->tags)->unique()->sort()->values();$requested=$request->input('tag',[]);$requested=is_array($requested)?$requested:[$requested];$tags=array_slice(array_values(array_unique(array_filter($requested,fn($tag)=>is_string($tag)&&$known->containsStrict($tag)))),0,20);
        $other=$products->filter(fn($p)=>($search===''||str_contains(strtolower($p->title.' '.$p->brand),strtolower($search)))&&(!$priceRequested||($p->isBuyMode()&&$p->base_price>=$min&&$p->base_price<=$max))&&(!$inStock||($p->isBuyMode()&&$p->variants->contains(fn($v)=>$v->enabled&&$v->stock>0))));
        $visible=$other->filter(fn($p)=>$tags===[]||array_intersect($tags,$p->tags)!==[]);
        if(in_array($sort,['price_asc','price_desc'],true))$visible=$visible->sort(fn($a,$b)=>$a->isBuyMode()!==$b->isBuyMode()?($a->isBuyMode()?-1:1):($a->isBuyMode()?($sort==='price_desc'?$b->base_price<=>$a->base_price:$a->base_price<=>$b->base_price):0));
        $filters=['search'=>$search,'category'=>$request->input('category')===$category->slug?$category->slug:null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>$low,'boundsMaxPrice'=>$high,'priceRequested'=>$priceRequested,'inStock'=>$inStock,'colors'=>[],'materials'=>[],'tags'=>$tags,'minRating'=>0,'variantFiltered'=>$priceRequested||$inStock,'hasPricedProducts'=>$hasPriced];
        $tagFacets=$known->map(fn($tag)=>['value'=>$tag,'label'=>$tag,'count'=>$other->filter(fn($p)=>in_array($tag,$p->tags,true))->count()])->filter(fn($facet)=>$facet['count']>0)->values()->all();
        $facets=['total'=>$visible->count(),'categoryCounts'=>[-9=>$visible->count()],'hasPricedProducts'=>$hasPriced,'priceBounds'=>['min'=>$low,'max'=>$high],'colors'=>[],'materials'=>[],'tags'=>$tagFacets,'hasRatings'=>false,'ratings'=>[]];
        $page=max(1,$request->integer('page',1));$paginator=new LengthAwarePaginator($visible->values()->slice(($page-1)*6,6)->values(),$visible->count(),6,$page,['path'=>$request->url(),'query'=>$request->query()]);
        return (new CatalogFilterResult($filters,$paginator,$facets))->toViewData()+['demoTheme'=>'logistics','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>$category->name,'description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'logistics','surface'=>'plp'],'demoProducts'=>$products];
    }

    public function account(array $catalog,array $data): array
    {
        // Reuse the shared complete account fixture, then bind logistics items.
        $buyCatalog=$catalog;$buyCatalog['demoProducts']=$catalog['demoProducts']->filter(fn($p)=>$p->isBuyMode())->values();
        if($buyCatalog['demoProducts']->count()<2)$buyCatalog['demoProducts']=$catalog['demoProducts']->slice(3)->values();
        $data=app(BabyThemeDemoData::class)->account($buyCatalog,$data);
        foreach($data['orders'] as &$order){$order['subtotal']=collect($order['items'])->sum('lineTotal');$order['total']=$order['subtotal']+($order['shippingAmount']??0);$order['currency']='MYR';}unset($order);
        $data['dashboard']=['orderCount'=>count($data['orders']),'activeLogisticsCount'=>collect($data['orders'])->whereNotIn('status',['delivered','cancelled','refunded'])->count(),'paidSpend'=>[['amount'=>collect($data['orders'])->sum('total'),'currency'=>'MYR','currencyExchangeRate'=>1]]];
        $data['wishlist']=$catalog['demoProducts']->take(3)->map(fn($p)=>['product'=>['id'=>$p->id,'handle'=>$p->handle,'title'=>$p->title,'images'=>[['path'=>$p->images->first()->path]],'variants'=>[['id'=>$p->variants->first()->id,'title'=>$p->variants->first()->title,'price'=>$p->base_price]],'basePrice'=>$p->base_price,'purchaseMode'=>$p->purchase_mode,'priceDisplay'=>$p->price_display,'priceLabel'=>$p->isBuyMode()?null:'Price on request']])->all();
        $data['vouchers']['eligible'][0]['title']='Container accessories';$data['vouchers']['eligible'][1]['title']='Project essentials';
        $data['quotations']=[];
        return $data;
    }
}
