<?php
namespace App\Services;
use App\Models\{Category,Product,ProductImage,ProductVariant,ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
final class HomeThemeDemoData
{
 public function catalog(Request $request): array {
  $category=new Category(['name'=>'Objects worth coming home to','slug'=>'demo-home','description'=>'Thoughtful pieces for everyday living.','image_url'=>asset('images/cms-samples/home-editorial-hero.png')]);$category->id=-2;
  $rows=[['Oak Ridge Coffee Table','table',129900,'Natural oak / 90 cm'],['Rhea Ceramic Vase','vase',34900,'Sand / Large'],['Linden Lounge Chair','chair',159900,'Natural oak / Cream'],['Astra Table Lamp','lamp',49900,'Sand / Linen']];
  $products=collect($rows)->map(function($r,$i)use($category){
   $image=asset('images/themes/home-deco/'.$r[1].'.png');
   $p=new Product(['title'=>$r[0],'handle'=>'theme-demo-home-'.$r[1],'brand'=>'JW STUDIO','status'=>'active','subtitle'=>'','base_price'=>$r[2],'description'=>'Thoughtful materials and considered proportions.','options'=>[['name'=>'Size','values'=>[$r[3]],'displayType'=>'pills']]]);$p->id=-200-$i;
   $im=new ProductImage(['path'=>$image,'alt'=>$r[0],'position'=>0]);$im->id=-2000-$i;
   $v=new ProductVariant(['title'=>$r[3],'sku'=>'DEMO-HOME-'.$i,'price'=>$r[2],'stock'=>25,'enabled'=>true,'options'=>['Size'=>$r[3]]]);$v->id=-2000-$i;$v->setRelation('product',$p);
   $variants=collect([$v]);$images=collect([$im]);
   if($i===0){$p->options=[['name'=>'Finish','values'=>['Natural oak','Dark oak'],'displayType'=>'swatches','valueVisuals'=>['Natural oak'=>['color'=>'#c7a77b'],'Dark oak'=>['color'=>'#574333']]],['name'=>'Size','values'=>['90 cm','110 cm'],'displayType'=>'pills']];$variants=collect();foreach(['Natural oak','Dark oak'] as $finish)foreach(['90 cm','110 cm'] as $size){$variant=new ProductVariant(['title'=>$finish.' / '.$size,'sku'=>'DEMO-HOME-TABLE-'.$variants->count(),'price'=>$size==='90 cm'?129900:149900,'stock'=>25,'enabled'=>true,'options'=>['Finish'=>$finish,'Size'=>$size]]);$variant->id=-2100-$variants->count();$variant->setRelation('product',$p);$variants->push($variant);}foreach(['images/themes/home-deco/account-room.png','images/cms-samples/home-shop-decor.png'] as $j=>$path){$extra=new ProductImage(['path'=>asset($path),'alt'=>$r[0],'position'=>$j+1]);$extra->id=-2200-$j;$images->push($extra);}}

   return $p->setRelation('category',$category)->setRelation('images',$images)->setRelation('variants',$variants)->setRelation('pdpContent',new ProductPdpContent(['specifications'=>[['id'=>'material','label'=>'Material','value'=>$i===0?'Oak':'Ceramic']],'story'=>[]]))->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
  });
  $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());$request->attributes->set('websiteThemeDemoProducts',$products);
  $min=max(34900,$request->integer('min_price',34900));$max=min(159900,$request->integer('max_price',159900));$sort=$request->input('sort','');$visible=$products->filter(fn($p)=>$p->base_price>=$min&&$p->base_price<=$max);if($sort==='price_asc')$visible=$visible->sortBy('base_price');if($sort==='price_desc')$visible=$visible->sortByDesc('base_price');
  $filters=['search'=>'','category'=>null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>34900,'boundsMaxPrice'=>159900,'priceRequested'=>$min!==34900||$max!==159900,'inStock'=>false,'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>false];
  $facets=['priceBounds'=>['min'=>34900,'max'=>159900],'total'=>4,'categoryCounts'=>[-2=>4],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
  return (new CatalogFilterResult($filters,new LengthAwarePaginator($visible->values(),$visible->count(),12),$facets))->toViewData()+['demoTheme'=>'home_deco','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>$category->name,'description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'home_deco','surface'=>'plp'],'demoProducts'=>$products];
 }
 public function account(array $catalog,array $data):array {
  $data['user']=array_replace($data['user'],['name'=>'Alex Tan','firstName'=>'Alex','lastName'=>'Tan','email'=>'alex@example.com','phone'=>'012 345 6789']);
  $items=$catalog['demoProducts']->take(2)->map(fn($p)=>['id'=>$p->id,'productVariantId'=>$p->variants->first()->id,'title'=>$p->title,'variantTitle'=>$p->variants->first()->title,'imageUrl'=>$p->images->first()->path,'quantity'=>1,'unitPrice'=>$p->base_price,'lineTotal'=>$p->base_price,'sku'=>$p->variants->first()->sku,'reviews'=>[],'reviewStatus'=>null,'returnRequest'=>null,'returnEligibleUntil'=>'2099-01-01'])->all();
  $first=array_replace($data['orders'][0],['number'=>'JW100328','createdAt'=>'2026-10-13T10:00:00','status'=>'processing','subtotal'=>164800,'total'=>165600,'items'=>$items,'trackingCarrier'=>null,'trackingNumber'=>null,'shippedAt'=>null,'payments'=>[['id'=>-1,'paymentMethod'=>'bank_transfer','gatewayStatus'=>'paid']]]);
  $second=array_replace($first,['id'=>-2,'number'=>'JW100271','createdAt'=>'2026-10-02T10:00:00','status'=>'delivered','items'=>[$items[1]],'subtotal'=>34900,'total'=>34900]);$data['orders']=[$first,$second];
  $data['addresses'][0]=array_replace($data['addresses'][0],['firstName'=>'Alex','lastName'=>'Tan','line1'=>'12 Jalan Harmoni','street'=>'12 Jalan Harmoni','phone'=>'012 345 6789']);
  $data['addresses'][]=array_replace($data['addresses'][0],['id'=>-2,'label'=>'Office','line1'=>'8 Jalan Ampang','street'=>'8 Jalan Ampang','postalCode'=>'50450','isDefault'=>false,'isDefaultShipping'=>false]);
  $data['orders'][0]['addresses']=[array_merge($data['addresses'][0],['type'=>'shipping'])];
  $data['wishlist']=$catalog['demoProducts']->map(fn($p)=>['product'=>['id'=>$p->id,'handle'=>$p->handle,'title'=>$p->title,'images'=>[['path'=>$p->images->first()->path]],'variants'=>[['id'=>$p->variants->first()->id,'title'=>$p->variants->first()->title,'price'=>$p->base_price]],'basePrice'=>$p->base_price]])->all();
  $data['vouchers']['eligible'][0]=array_replace($data['vouchers']['eligible'][0],['code'=>'HOME10','description'=>'10% off','minSpendDisplay'=>'Minimum spend RM 500.00','minSpend'=>50000]);$data['vouchers']['eligible'][1]=array_replace($data['vouchers']['eligible'][0],['id'=>-2,'code'=>'WELCOME50','description'=>'RM 50.00 off','discountType'=>'fixed','minSpendDisplay'=>'Minimum spend RM 800.00','minSpend'=>80000]);return $data;
 }
}
