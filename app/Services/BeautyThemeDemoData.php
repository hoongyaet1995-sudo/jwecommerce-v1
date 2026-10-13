<?php
namespace App\Services;
use App\Models\{Category,Product,ProductImage,ProductVariant,ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
final class BeautyThemeDemoData
{
 public function catalog(Request $request): array {
  $category=new Category(['name'=>'Daily essentials','slug'=>'demo-beauty','description'=>'Skincare for your daily ritual.','image_url'=>asset('images/themes/beauty/daily-essentials-hero.png')]);$category->id=-2;
  $rows=[['Daily Serum','serum-front',12800,'30ml'],['Barrier Cream','cream-front',9800,'50ml'],['Gentle Cleanser','cleanser-front',6800,'150ml']];
  $products=collect($rows)->map(function($r,$i)use($category){
   $image=asset('images/themes/beauty/'.$r[1].'.png');
   $p=new Product(['title'=>$r[0],'handle'=>'theme-demo-beauty-'.$r[1],'brand'=>'JW STUDIO','status'=>'active','subtitle'=>'','base_price'=>$r[2],'description'=>'','options'=>[['name'=>'Size','values'=>[$r[3]],'displayType'=>'pills']]]);$p->id=-200-$i;
   $im=new ProductImage(['path'=>$image,'alt'=>$r[0],'position'=>0]);$im->id=-2000-$i;
   $v=new ProductVariant(['title'=>$r[3],'sku'=>'DEMO-BEAUTY-'.$i,'price'=>$r[2],'stock'=>25,'enabled'=>true,'options'=>['Size'=>$r[3]]]);$v->id=-2000-$i;$v->setRelation('product',$p);
   $variants=collect([$v]);$images=collect([$im]);
   if($i===0){$p->options=[['name'=>'Size','values'=>['30ml','50ml'],'displayType'=>'pills']];$variants=collect();foreach(['30ml','50ml'] as $size){$variant=new ProductVariant(['title'=>$size,'sku'=>'DEMO-BEAUTY-SERUM-'.$size,'price'=>$size==='30ml'?12800:16800,'stock'=>25,'enabled'=>true,'options'=>['Size'=>$size]]);$variant->id=-2100-$variants->count();$variant->setRelation('product',$p);$variants->push($variant);}foreach(['serum-angle','serum-detail'] as $j=>$name){$extra=new ProductImage(['path'=>asset('images/themes/beauty/'.$name.'.png'),'alt'=>$r[0],'position'=>$j+1]);$extra->id=-2200-$j;$images->push($extra);}}
   return $p->setRelation('category',$category)->setRelation('images',$images)->setRelation('variants',$variants)->setRelation('pdpContent',new ProductPdpContent(['specifications'=>[['id'=>'material','label'=>'Size','value'=>$r[3]]],'story'=>[]]))->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
  });
  $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());$request->attributes->set('websiteThemeDemoProducts',$products);
  $min=max(6800,$request->integer('min_price',6800));$max=min(16800,$request->integer('max_price',16800));$sort=$request->input('sort','');$visible=$products->filter(fn($p)=>$p->base_price>=$min&&$p->base_price<=$max);if($sort==='price_asc')$visible=$visible->sortBy('base_price');if($sort==='price_desc')$visible=$visible->sortByDesc('base_price');
  $filters=['search'=>'','category'=>null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>6800,'boundsMaxPrice'=>16800,'priceRequested'=>$min!==6800||$max!==16800,'inStock'=>false,'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>false];
  $facets=['priceBounds'=>['min'=>6800,'max'=>16800],'total'=>3,'categoryCounts'=>[-2=>3],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
  return (new CatalogFilterResult($filters,new LengthAwarePaginator($visible->values(),$visible->count(),12),$facets))->toViewData()+['demoTheme'=>'beauty','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>$category->name,'description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'beauty','surface'=>'plp'],'demoProducts'=>$products];
 }
 public function account(array $catalog,array $data):array {
  $data['user']=array_replace($data['user'],['name'=>'Alex Tan','firstName'=>'Alex','lastName'=>'Tan','email'=>'alex.tan@example.com','phone'=>'012 345 6789']);
  $items=$catalog['demoProducts']->take(2)->map(fn($p)=>['id'=>$p->id,'productVariantId'=>$p->variants->first()->id,'title'=>$p->title,'variantTitle'=>$p->variants->first()->title,'imageUrl'=>$p->images->first()->path,'quantity'=>1,'unitPrice'=>$p->base_price,'lineTotal'=>$p->base_price,'sku'=>$p->variants->first()->sku,'reviews'=>[],'reviewStatus'=>null,'returnRequest'=>null,'returnEligibleUntil'=>'2099-01-01'])->all();
  $first=array_replace($data['orders'][0],['number'=>'JW100328','createdAt'=>'2026-10-13T10:00:00','status'=>'processing','subtotal'=>22600,'total'=>23400,'items'=>$items,'trackingCarrier'=>null,'trackingNumber'=>null,'shippedAt'=>null,'payments'=>[['id'=>-1,'paymentMethod'=>'bank_transfer','gatewayStatus'=>'paid']]]);
  $second=array_replace($first,['id'=>-2,'number'=>'JW100271','createdAt'=>'2026-10-02T10:00:00','status'=>'delivered','items'=>[$items[1]],'subtotal'=>9800,'total'=>9800]);$data['orders']=[$first,$second];
  $data['addresses'][0]=array_replace($data['addresses'][0],['firstName'=>'Alex','lastName'=>'Tan','line1'=>'12 Jalan Harmoni','street'=>'12 Jalan Harmoni','phone'=>'012 345 6789']);
  $data['addresses'][]=array_replace($data['addresses'][0],['id'=>-2,'label'=>'Office','line1'=>'8 Jalan Ampang','street'=>'8 Jalan Ampang','postalCode'=>'50450','isDefault'=>false,'isDefaultShipping'=>false]);
  $data['orders'][0]['addresses']=[array_merge($data['addresses'][0],['type'=>'shipping'])];
  $data['wishlist']=$catalog['demoProducts']->map(fn($p)=>['product'=>['id'=>$p->id,'handle'=>$p->handle,'title'=>$p->title,'images'=>[['path'=>$p->images->first()->path]],'variants'=>[['id'=>$p->variants->first()->id,'title'=>$p->variants->first()->title,'price'=>$p->base_price]],'basePrice'=>$p->base_price]])->all();
  $data['vouchers']['eligible'][0]=array_replace($data['vouchers']['eligible'][0],['code'=>'GLOW10','description'=>'10% off','minSpendDisplay'=>'Minimum spend RM 100.00','minSpend'=>10000,'description'=>'10% off eligible skincare products.']);$data['vouchers']['eligible'][1]=array_replace($data['vouchers']['eligible'][0],['id'=>-2,'code'=>'CARE20','description'=>'RM20 off eligible skincare products.','discountType'=>'fixed','discountDisplay'=>'RM20','value'=>20,'minSpendDisplay'=>'Minimum spend RM 200.00','minSpend'=>20000]);return $data;
 }
}
