<?php
namespace Database\Seeders;
use App\Models\{Category,Product};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicit local visual fixtures. Never included in DatabaseSeeder or production deployment. */
class BeautyThemeLocalSamplesSeeder extends Seeder
{
 public function run(): void
 {
  if (!app()->environment('local')) throw new \RuntimeException('Beauty design samples are local only.');
  DB::transaction(function () {
   $category=Category::updateOrCreate(['slug'=>'beauty-design-sample'],[
    'name'=>'Daily essentials','description'=>"Explore the collection.\nThoughtfully selected skincare essentials\nfor a simpler everyday.\nLocal design sample — not for sale.",
    'image_url'=>'/images/themes/beauty/daily-essentials-hero.png','is_active'=>true,'sort_order'=>999,
   ]);
   foreach([
    ['serum','Daily Serum',12900,'30 ml'],['cleanser','Soft Cleanser',8900,'150 ml'],['cream','Face Cream',14900,'50 g'],
   ] as [$key,$title,$price,$size]) {
    $product=Product::updateOrCreate(['handle'=>'beauty-design-sample-'.$key],[
     'category_id'=>$category->id,'title'=>$title,'subtitle'=>'Local design sample · fictional product · not for sale','brand'=>'JW STUDIO',
     'description'=>'<p>Fictional local product used to review the Beauty &amp; Skincare theme. Images and prices are illustrative. This is not a saleable formulation.</p>',
     'base_price'=>$price,'status'=>'active','has_variants'=>true,'is_featured'=>false,'tags'=>['local-design-sample','beauty-theme'],
     'options'=>[['id'=>'size','name'=>'Size','displayType'=>'pills','values'=>[$size]]],
    ]);
    $variant=$product->variants()->updateOrCreate(['sku'=>'LOCAL-BEAUTY-'.strtoupper($key)],[
     'title'=>$size,'size'=>$size,'options'=>['Size'=>$size],'price'=>$price,'stock'=>0,'enabled'=>true,
     'image_url'=>'/images/themes/beauty/'.$key.'-front.png',
    ]);
    foreach(['front','detail','angle'] as $position=>$view) $product->images()->updateOrCreate(['path'=>'/images/themes/beauty/'.$key.'-'.$view.'.png'],[
     'alt'=>$title.' · '.$view.' · fictional local design sample','position'=>$position,
    ]);
    $product->pdpContent()->updateOrCreate([],['specifications'=>[['label'=>'Size','value'=>$size],['label'=>'Catalog status','value'=>'Local design sample — not for sale']],
     'use_store_default_shipping'=>true,'custom_accordions'=>[],'highlights'=>[],
    ]);
   }
   $samples=$category->products()->where('handle','like','beauty-design-sample-%')->get();
   foreach($samples as $sample) foreach($samples->where('id','!=',$sample->id)->values() as $position=>$related) {
    $sample->pdpRelatedProducts()->updateOrCreate(['related_product_id'=>$related->id],['sort_order'=>$position]);
   }
  });
 }
}
