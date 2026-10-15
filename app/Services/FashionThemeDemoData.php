<?php
namespace App\Services;
use App\Models\{Category,Product,ProductImage,ProductVariant,ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
/** Unsaved objects available only through authenticated theme demo identity guards. */
final class FashionThemeDemoData
{
 public function catalog(Request $request):array {
  $category=new Category(['name'=>'Fashion & Design','slug'=>'demo-fashion','description'=>'Everyday pieces. A stronger point of view.','image_url'=>asset('images/themes/fashion/hero.png?v=fashion3')]);$category->id=-5;
  $rows=[['Urban Utility Jacket','jacket',24900],['Heavyweight Tee','tee',8900],['Wide Leg Cargo','trousers',17900],['Boxy Overshirt','overshirt',15900],['Studio Cap','cap',5900],['Crossbody Bag','bag',12900]];
  $products=collect($rows)->map(function($row,$i)use($category){
   $p=new Product(['title'=>$row[0],'handle'=>'theme-demo-fashion-'.$i,'brand'=>'JW STUDIO','status'=>'active','subtitle'=>'Built for everyday movement.','base_price'=>$row[2],'description'=>'<p>A relaxed everyday essential.</p>','options'=>[['name'=>'Colour','values'=>['Charcoal','Stone','Olive'],'displayType'=>'color_palette','valueVisuals'=>['Charcoal'=>['visualType'=>'color','colorCode'=>'#303030'],'Stone'=>['visualType'=>'color','colorCode'=>'#bebbb3'],'Olive'=>['visualType'=>'color','colorCode'=>'#606548']]],['name'=>'Size','values'=>['XS','S','M','L','XL'],'displayType'=>'pills']]]);$p->id=-500-$i;
   $variants=collect();foreach(['Charcoal','Stone','Olive'] as $colour)foreach(['XS','S','M','L','XL'] as $size){$v=new ProductVariant(['title'=>$colour.' / '.$size,'sku'=>'URBAN-'.strtoupper($colour).'-'.$size,'price'=>$row[2],'stock'=>25,'enabled'=>true,'options'=>['Colour'=>$colour,'Size'=>$size],'visual_type'=>'color','color_code'=>['Charcoal'=>'#303030','Stone'=>'#bebbb3','Olive'=>'#606548'][$colour]]);$v->id=$p->id*100-$variants->count();$v->setRelation('product',$p);$variants->push($v);}
   $images=collect($i===0?['jacket','jacket-back','fabric','pocket']:[$row[1]])->map(function($name,$n)use($p){$image=new ProductImage(['path'=>asset('images/themes/fashion/'.$name.'.png?v=fashion3'),'alt'=>$p->title,'position'=>$n]);$image->id=$p->id*10-$n;return $image;});
   return $p->setRelation('category',$category)->setRelation('variants',$variants)->setRelation('images',$images)->setRelation('pdpContent',new ProductPdpContent(['specifications'=>[['label'=>'Composition','value'=>'100% cotton'],['label'=>'Fit','value'=>'Relaxed']],'custom_accordions'=>[['title'=>'Size & fit','content'=>'Relaxed fit. Choose your usual size.'],['title'=>'Care instructions','content'=>'Follow the garment care label.']],'story'=>['heading'=>'Made to move.','description'=>'A modern everyday essential.','image'=>asset('images/themes/fashion/hero.png?v=fashion3')]]))->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
  });
  $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());$request->attributes->set('websiteThemeDemoProducts',$products);
  $min=max(0,$request->integer('min_price',0));$max=$request->integer('max_price',30000);$sort=$request->input('sort','');$visible=$products->filter(fn($p)=>$p->base_price>=$min&&$p->base_price<=$max);if($sort==='price_asc')$visible=$visible->sortBy('base_price');if($sort==='price_desc')$visible=$visible->sortByDesc('base_price');
  $filters=['search'=>'','category'=>null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>0,'boundsMaxPrice'=>30000,'priceRequested'=>$min!==0||$max!==30000,'inStock'=>$request->boolean('in_stock'),'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>false];$facets=['priceBounds'=>['min'=>0,'max'=>30000],'total'=>6,'categoryCounts'=>[-5=>6],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
  return (new CatalogFilterResult($filters,new LengthAwarePaginator($visible->values(),$visible->count(),12),$facets))->toViewData()+['demoTheme'=>'fashion','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>$category->name,'description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'fashion','surface'=>'plp'],'demoProducts'=>$products];
 }
 public function account(array $catalog,array $data):array {
  $data=app(BabyThemeDemoData::class)->account($catalog,$data);
  $data['orders'][0]['subtotal']=33800;$data['orders'][0]['total']=34600;
  $data['orders'][1]['subtotal']=12900;$data['orders'][1]['total']=12900;
  $data['vouchers']['eligible'][0]['title']='Welcome offer';
  $data['vouchers']['eligible'][1]['title']='A little saving';
  $data['wishlist']=$catalog['demoProducts']->map(fn($p)=>['product'=>['id'=>$p->id,'handle'=>$p->handle,'title'=>$p->title,'images'=>[['path'=>$p->images->first()->path]],'variants'=>[['id'=>$p->variants->first()->id,'title'=>$p->variants->first()->title,'price'=>$p->base_price]],'basePrice'=>$p->base_price]])->all();
  return $data;
 }
}
