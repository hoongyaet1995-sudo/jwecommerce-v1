<?php
namespace App\Services;
use App\Models\{Category,Product,ProductImage,ProductVariant,ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
/** Admin-only unsaved presentation objects. Never create public inventory. */
final class WeddingThemeDemoData
{
 public function catalog(Request $request):array {
  $category=new Category(['name'=>'Wedding Photography','slug'=>'demo-wedding','description'=>'Timeless stories, beautifully preserved.','image_url'=>asset('images/themes/wedding/hero.png')]);$category->id=-4;
  $rows=[['Linen wedding album','album',28000],['Fine art print set','prints',9000],['The Wedding Day','hero',320000],['Linen keepsake album','album',35000],['Wedding print collection','prints',15000],['Our story album','album',29000],['Fine art keepsakes','prints',12000],['Wedding photography collection','hero',420000]];
  $products=collect($rows)->map(function($row,$i)use($category){
   $p=new Product(['title'=>$row[0],'handle'=>'theme-demo-wedding-'.$i,'brand'=>'JW STUDIO','status'=>'active','subtitle'=>'Timeless stories, beautifully preserved.','base_price'=>$row[2],'description'=>'<p>A thoughtful keepsake for your most meaningful days.</p>','options'=>[['name'=>'Colour','values'=>['Ivory','Sage'],'displayType'=>'color_palette','valueVisuals'=>['Ivory'=>['visualType'=>'color','colorCode'=>'#eee8dc'],'Sage'=>['visualType'=>'color','colorCode'=>'#7b8778']]],['name'=>'Size','values'=>['25 × 25 cm','30 × 30 cm'],'displayType'=>'pills']]]);$p->id=-400-$i;
   $variants=collect();foreach(['Ivory','Sage'] as $metal)foreach(['25 × 25 cm','30 × 30 cm'] as $length){$v=new ProductVariant(['title'=>$metal.' / '.$length,'sku'=>'ALBUM-'.($metal==='Ivory'?'IVORY':'SAGE').'-'.str_replace(' cm','',$length),'price'=>$row[2],'stock'=>25,'enabled'=>true,'options'=>['Colour'=>$metal,'Size'=>$length],'visual_type'=>'color','color_code'=>$metal==='Ivory'?'#eee8dc':'#7b8778']);$v->id=$p->id*10-$variants->count();$v->setRelation('product',$p);$variants->push($v);}
   $images=collect($i===0?['album','prints','hero','account']:($i===2?['hero','account','hero','account']:[$row[1]]))->map(function($name,$n)use($p){$image=new ProductImage(['path'=>asset('images/themes/wedding/'.$name.'.png'),'alt'=>$p->title,'position'=>$n]);$image->id=$p->id*10-$n;return $image;});
   return $p->setRelation('category',$category)->setRelation('variants',$variants)->setRelation('images',$images)->setRelation('pdpContent',new ProductPdpContent(['highlights'=>[['badge'=>'Beautiful details','text'=>'Thoughtfully crafted.','icon'=>'leaf'],['badge'=>'Timeless stories','text'=>'Made to keep.','icon'=>'gem'],['badge'=>'Gift-ready presentation','text'=>'Arrives beautifully.','icon'=>'gift']],'specifications'=>[['label'=>'Colour','value'=>'Linen'],['label'=>'Size','value'=>'25 × 25 cm / 30 × 30 cm']],'custom_accordions'=>[['title'=>'Care instructions','content'=>'Store clean and dry.'],['title'=>"What's in the box",'content'=>'Your selected album or print collection.']],'story'=>['heading'=>'Timeless stories, beautifully preserved.','description'=>'A thoughtful keepsake for your most meaningful days.','image'=>asset('images/themes/wedding/hero.png')]]))->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
  });
  foreach([2,7] as $index){$products[$index]->purchase_mode='quote';$products[$index]->price_display='hide';}
  $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());$request->attributes->set('websiteThemeDemoProducts',$products);
  // Apply explicit preview purchase choices before deriving public facets.
  foreach($products as $product){
   if($request->has('purchaseMode') && in_array($request->input('purchaseMode'),ProductPurchaseSettings::MODES,true)) $product->purchase_mode=$request->input('purchaseMode');
   if($request->has('priceDisplay') && in_array($request->input('priceDisplay'),ProductPurchaseSettings::PRICE_DISPLAYS,true)) $product->price_display=$request->input('priceDisplay');
  }
  $buy=$products->filter(fn($p)=>$p->isBuyMode());$hasPriced=$buy->isNotEmpty();
  $low=$hasPriced?(int)$buy->min('base_price'):0;$high=$hasPriced?(int)$buy->max('base_price'):0;
  $min=min($high,max($low,$request->integer('min_price',$low)));$max=min($high,max($low,$request->integer('max_price',$high)));if($min>$max)[$min,$max]=[$max,$min];
  $priceRequested=$hasPriced&&($request->boolean('price_filter')||$min!==$low||$max!==$high);$inStock=$hasPriced&&$request->boolean('in_stock');
  $sort=in_array($request->input('sort',''),['price_asc','price_desc','newest'],true)?$request->input('sort',''):'';$search=$request->string('search')->trim()->value();
  $selectedCategory=$request->input('category')===$category->slug?$category->slug:null;
  $visible=$products->filter(fn($p)=>($search===''||str_contains(strtolower($p->title.' '.$p->brand),strtolower($search)))&&(!$priceRequested||($p->isBuyMode()&&$p->base_price>=$min&&$p->base_price<=$max))&&(!$inStock||($p->isBuyMode()&&$p->variants->contains(fn($v)=>$v->enabled&&$v->stock>0))));
  if(in_array($sort,['price_asc','price_desc'],true))$visible=$visible->sort(function($a,$b)use($sort){if($a->isBuyMode()!==$b->isBuyMode())return $a->isBuyMode()?-1:1;return $a->isBuyMode()?($sort==='price_desc'?$b->base_price<=>$a->base_price:$a->base_price<=>$b->base_price):0;});
  $filters=['search'=>$search,'category'=>$selectedCategory,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>$low,'boundsMaxPrice'=>$high,'priceRequested'=>$priceRequested,'inStock'=>$inStock,'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>$priceRequested||$inStock,'hasPricedProducts'=>$hasPriced];
  $facets=['hasPricedProducts'=>$hasPriced,'priceBounds'=>['min'=>$low,'max'=>$high],'total'=>$visible->count(),'categoryCounts'=>[-4=>$visible->count()],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
  $page=max(1,$request->integer('page',1));$paginator=new LengthAwarePaginator($visible->values()->slice(($page-1)*3,3)->values(),$visible->count(),3,$page,['path'=>$request->url(),'query'=>$request->query()]);
  return (new CatalogFilterResult($filters,$paginator,$facets))->toViewData()+['demoTheme'=>'wedding','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>$category->name,'description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'wedding','surface'=>'plp'],'demoProducts'=>$products];
 }
 public function account(array $catalog,array $data):array {
  $data=app(BabyThemeDemoData::class)->account($catalog,$data);
  $data['orders'][0]['items']=[$data['orders'][0]['items'][0]];$data['orders'][0]['subtotal']=28000;$data['orders'][0]['total']=28800;
  $data['orders'][1]['subtotal']=9000;$data['orders'][1]['total']=9800;
  foreach($data['wishlist'] as &$wish){$quote=($wish['product']['id']??0)===$catalog['demoProducts'][2]->id;$wish['product']['purchaseMode']=$quote?'quote':'buy';$wish['product']['priceDisplay']=$quote?'hide':'exact';$wish['product']['priceLabel']=$quote?'Price on request':null;}unset($wish);


  $data['vouchers']['eligible'][0]['title']='Welcome offer';$data['vouchers']['eligible'][1]['title']='A little something';return $data;
 }
}
