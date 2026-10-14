<?php
namespace App\Services;
use App\Models\{Category,Product,ProductImage,ProductVariant,ProductPdpContent};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
/** Admin-only unsaved presentation objects. Never create public inventory. */
final class JewelryThemeDemoData
{
 public function catalog(Request $request):array {
  $category=new Category(['name'=>'Jewelry & Gifts','slug'=>'demo-jewelry','description'=>'Timeless pieces for a brighter you.','image_url'=>asset('images/themes/jewelry/hero.png')]);$category->id=-4;
  $rows=[['Luna Pearl Pendant','pendant',18900],['Pearl Stud Earrings','earrings',12900],['Classic Pearl Bracelet','bracelet',15900],['Golden Ring','rings',9900],['A little gift','gift',22900],['Solstice Necklace','pendant',19900],['Pearl Bracelet','bracelet',17900],['Aurora Pendant','detail',22900]];
  $products=collect($rows)->map(function($row,$i)use($category){
   $p=new Product(['title'=>$row[0],'handle'=>'theme-demo-jewelry-'.$i,'brand'=>'JW STUDIO','status'=>'active','subtitle'=>'A little light, close to your heart.','base_price'=>$row[2],'description'=>'<p>A thoughtful piece for everyday moments.</p>','options'=>[['name'=>'Metal','values'=>['Gold tone','Silver tone'],'displayType'=>'color_palette','valueVisuals'=>['Gold tone'=>['visualType'=>'color','colorCode'=>'#b59b6b'],'Silver tone'=>['visualType'=>'color','colorCode'=>'#c8c8c8']]],['name'=>'Chain length','values'=>['40 cm','45 cm'],'displayType'=>'pills']]]);$p->id=-400-$i;
   $variants=collect();foreach(['Gold tone','Silver tone'] as $metal)foreach(['40 cm','45 cm'] as $length){$v=new ProductVariant(['title'=>$metal.' / '.$length,'sku'=>'LUNA-'.($metal==='Gold tone'?'GOLD':'SILVER').'-'.str_replace(' cm','',$length),'price'=>$row[2],'stock'=>25,'enabled'=>true,'options'=>['Metal'=>$metal,'Chain length'=>$length],'visual_type'=>'color','color_code'=>$metal==='Gold tone'?'#b59b6b':'#c8c8c8']);$v->id=$p->id*10-$variants->count();$v->setRelation('product',$p);$variants->push($v);}
   $images=collect($i===0?['pendant','detail','clasp','gift']:[$row[1]])->map(function($name,$n)use($p){$image=new ProductImage(['path'=>asset('images/themes/jewelry/'.$name.'.png'),'alt'=>$p->title,'position'=>$n]);$image->id=$p->id*10-$n;return $image;});
   return $p->setRelation('category',$category)->setRelation('variants',$variants)->setRelation('images',$images)->setRelation('pdpContent',new ProductPdpContent(['highlights'=>[['badge'=>'Delicate details','text'=>'Thoughtfully crafted.','icon'=>'leaf'],['badge'=>'Everyday elegance','text'=>'A timeless piece.','icon'=>'gem'],['badge'=>'Gift-ready presentation','text'=>'Arrives beautifully.','icon'=>'gift']],'specifications'=>[['label'=>'Metal','value'=>'Gold-tone finish'],['label'=>'Chain length','value'=>'40 cm / 45 cm']],'custom_accordions'=>[['title'=>'Care instructions','content'=>'Store clean and dry.'],['title'=>"What's in the box",'content'=>'One pendant necklace.']],'story'=>['heading'=>'A little light, close to your heart.','description'=>'A thoughtful piece for everyday moments.','image'=>asset('images/themes/jewelry/hero.png')]]))->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
  });
  $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());$request->attributes->set('websiteThemeDemoProducts',$products);
  $min=max(0,$request->integer('min_price',0));$max=$request->integer('max_price',50000);$sort=$request->input('sort','');$visible=$products->filter(fn($p)=>$p->base_price>=$min&&$p->base_price<=$max);if($sort==='price_asc')$visible=$visible->sortBy('base_price');if($sort==='price_desc')$visible=$visible->sortByDesc('base_price');
  $filters=['search'=>'','category'=>null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>0,'boundsMaxPrice'=>50000,'priceRequested'=>$min!==0||$max!==50000,'inStock'=>$request->boolean('in_stock'),'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>false];$facets=['priceBounds'=>['min'=>0,'max'=>50000],'total'=>8,'categoryCounts'=>[-4=>8],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
  return (new CatalogFilterResult($filters,new LengthAwarePaginator($visible->values(),$visible->count(),12),$facets))->toViewData()+['demoTheme'=>'jewelry','categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,'catalogListingSettings'=>['title'=>$category->name,'description'=>$category->description],'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>'jewelry','surface'=>'plp'],'demoProducts'=>$products];
 }
 public function account(array $catalog,array $data):array {
  $data=app(BabyThemeDemoData::class)->account($catalog,$data);
  $data['orders'][0]['subtotal']=31800;$data['orders'][0]['total']=32600;
  $data['orders'][1]['subtotal']=12900;$data['orders'][1]['total']=12900;
  $data['vouchers']['eligible'][0]['title']='Welcome offer';$data['vouchers']['eligible'][1]['title']='A little something';return $data;
 }
}
