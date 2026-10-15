<?php
namespace App\Services;
use App\Models\{Category,Product,ProductImage,ProductVariant,ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
/** Protected, unsaved presentation DTOs. Never writes live catalog or customer data. */
final class UniversalThemeDemoData
{
 public function catalog(Request $request):array {
  $category=new Category(['name'=>'Everyday essentials','slug'=>'demo-universal','description'=>'Everyday finds, thoughtfully brought together.','image_url'=>asset('images/themes/universal/hero.png')]);$category->id=-6;
  $rows=[['Everyday Bottle','bottle',7900,'A simple companion for your everyday.'],['Canvas Carry Tote','tote',4900,'A little room for everyday.'],['Pocket Bluetooth Speaker','speaker',12900,'Sound for wherever you go.'],['Soft Cotton Throw','throw',8900,'A softer touch at home.'],['Ceramic Mug','mug',2900,'Your everyday favourite.'],['Daily Notebook','notebook',1900,'Room for your next idea.'],['Compact Desk Lamp','lamp',9900,'A brighter workspace.'],['Travel Pouch','pouch',3900,'Keep the little things close.']];
  $products=collect($rows)->map(function($row,$i)use($category){
   $p=new Product(['title'=>$row[0],'handle'=>'theme-demo-universal-'.$i,'brand'=>'JW STUDIO','status'=>'active','subtitle'=>$row[3],'base_price'=>$row[2],'description'=>'<p>A simple everyday essential. Choose an option to suit your day.</p>','options'=>[['name'=>'Colour','values'=>['Stone','Blue','Charcoal'],'displayType'=>'color_palette','valueVisuals'=>['Stone'=>['visualType'=>'color','colorCode'=>'#d2cec6'],'Blue'=>['visualType'=>'color','colorCode'=>'#2457e6'],'Charcoal'=>['visualType'=>'color','colorCode'=>'#444b54']]],['name'=>$i===0?'Capacity':'Size','values'=>$i===0?['500 ml','750 ml']:['One size'],'displayType'=>'pills']]]);$p->id=-600-$i;
   $variants=collect();foreach(['Stone','Blue','Charcoal'] as $colour)foreach($i===0?['500 ml','750 ml']:['One size'] as $size){$v=new ProductVariant(['title'=>$colour.' / '.$size,'sku'=>strtoupper($row[1].'-'.$colour.'-'.str_replace(' ','',$size)),'price'=>$row[2],'stock'=>25,'enabled'=>true,'options'=>['Colour'=>$colour,($i===0?'Capacity':'Size')=>$size],'visual_type'=>'color','color_code'=>['Stone'=>'#d2cec6','Blue'=>'#2457e6','Charcoal'=>'#444b54'][$colour]]);$v->id=$p->id*100-$variants->count();$v->setRelation('product',$p);$variants->push($v);}
   $images=collect($i===0?['bottle','bottle-cap','bottle-side','story']:[$row[1]])->map(function($name,$n)use($p){$image=new ProductImage(['path'=>asset('images/themes/universal/'.$name.'.png'),'alt'=>$p->title,'position'=>$n]);$image->id=$p->id*10-$n;return $image;});
   return $p->setRelation('category',$category)->setRelation('variants',$variants)->setRelation('images',$images)->setRelation('pdpContent',new ProductPdpContent(['specifications'=>[['label'=>'Material','value'=>$i===0?'Stainless steel':'See product details'],['label'=>'Capacity / Size','value'=>$i===0?'500 ml':'One size']],'custom_accordions'=>[['title'=>'Care instructions','content'=>'Follow the care instructions supplied with your product.'],['title'=>"What's included",'content'=>'One item in your selected option.']],'story'=>['label'=>'EVERYDAY ESSENTIALS','heading'=>'Ready for your daily routine.','description'=>'Keep it close at your desk, in your bag or on the move.','image'=>asset('images/themes/universal/story.png')]]))->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
  });
  $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());$request->attributes->set('websiteThemeDemoProducts',$products);
  $min=max(0,$request->integer('min_price',0));$max=max($min,$request->integer('max_price',20000));$sort=$request->input('sort','');$visible=$products->filter(fn($p)=>$p->base_price>=$min&&$p->base_price<=$max);if($sort==='price_asc')$visible=$visible->sortBy('base_price');if($sort==='price_desc')$visible=$visible->sortByDesc('base_price');
  $filters=['search'=>'','category'=>null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>0,'boundsMaxPrice'=>20000,'priceRequested'=>$min!==0||$max!==20000,'inStock'=>$request->boolean('in_stock'),'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>false];$facets=['priceBounds'=>['min'=>0,'max'=>20000],'total'=>8,'categoryCounts'=>[-6=>8],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
  return (new CatalogFilterResult($filters,new LengthAwarePaginator($visible->values(),$visible->count(),12),$facets))->toViewData()+['demoTheme'=>'universal','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>'All products','description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'universal','surface'=>'plp'],'demoProducts'=>$products];
 }
 public function account(array $catalog,array $data):array {
  $data=app(BabyThemeDemoData::class)->account($catalog,$data);
  $data['orders'][0]['subtotal']=12800;$data['orders'][0]['total']=13600;
  $data['orders'][1]['subtotal']=2900;$data['orders'][1]['total']=2900;
  $data['vouchers']['eligible'][0]['title']='Welcome offer';$data['vouchers']['eligible'][1]['title']='A little saving';
  $data['wishlist']=$catalog['demoProducts']->filter(fn($p,$i)=>in_array($i,[0,1,4]))->map(fn($p)=>['product'=>['id'=>$p->id,'handle'=>$p->handle,'title'=>$p->title,'images'=>[['path'=>$p->images->first()->path]],'variants'=>[['id'=>$p->variants->first()->id,'title'=>$p->variants->first()->title,'price'=>$p->base_price]],'basePrice'=>$p->base_price]])->values()->all();return $data;
 }
}
