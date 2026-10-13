<?php
namespace Database\Seeders;
use App\Models\{Category,Product};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Authorized local visual samples; never invoked by DatabaseSeeder or deployment. */
class FoodThemeLocalSamplesSeeder extends Seeder
{
 public function run(): void
 {
  if (!app()->environment('local') || str_replace('\\','/',base_path()) !== 'C:/xampp/htdocs/jwstudio-ecommerce' || DB::connection()->getDatabaseName() !== 'jw_ecommerce') throw new \RuntimeException('Food samples require the canonical local database.');
  DB::transaction(function () {
   $category=Category::firstOrCreate(['slug'=>'food-design-sample'],[
    'name'=>'Good things for your everyday table.','description'=>'Thoughtfully selected pantry staples for simpler, happier meals at home. Local design samples - not for sale.',
    'image_url'=>'/images/themes/food/hero.png','is_active'=>true,'sort_order'=>999,
   ]);
   foreach([
    ['olive-oil','Tuscan Extra Virgin Olive Oil',6800,'500 ml'],['passata','Classic Tomato Passata',1800,'700 g'],['coffee','House Blend Coffee Beans',4800,'250 g'],
    ['pasta','Penne Rigate Pasta',1200,'500 g'],['honey','Wildflower Honey',3800,'350 g'],['tea','Jasmine Green Tea',2800,'50 g'],
   ] as [$key,$title,$price,$size]) {
    $product=Product::firstOrCreate(['handle'=>'food-design-sample-'.$key],[
     'category_id'=>$category->id,'title'=>$title,'subtitle'=>'Local design sample / fictional product / not for sale','brand'=>'JW STUDIO',
     'description'=>'<p>Fictional local pantry product for theme review. Images and prices are illustrative; not for sale.</p>',
     'base_price'=>$price,'status'=>'active','has_variants'=>true,'is_featured'=>false,'tags'=>['local-design-sample','food-theme'],
     'options'=>[['id'=>'size','name'=>'Size','displayType'=>'pills','values'=>$key==='olive-oil'?['250 ml','500 ml']:[$size]]],
    ]);
    foreach($key==='olive-oil'?['250 ml','500 ml']:[$size] as $position=>$option) $product->variants()->firstOrCreate(['sku'=>'LOCAL-FOOD-'.strtoupper($key).'-'.$position],[
     'title'=>$option,'size'=>$option,'options'=>['Size'=>$option],'price'=>$key==='olive-oil'&&$position===0?4800:$price,'stock'=>0,'enabled'=>true,
     'image_url'=>'/images/themes/food/'.$key.'.png',
    ]);
    foreach($key==='olive-oil'?[$key,'hero','story']:[$key] as $position=>$image) $product->images()->firstOrCreate(['path'=>'/images/themes/food/'.$image.'.png'],[
     'alt'=>$title.' / fictional local design sample','position'=>$position,
    ]);
    $product->pdpContent()->firstOrCreate([],['specifications'=>[['label'=>'Pack size','value'=>$size],['label'=>'Catalog status','value'=>'Local design sample - not for sale']],
     'use_store_default_shipping'=>true,'custom_accordions'=>[],'highlights'=>[],
     'story'=>$key==='olive-oil'?['label'=>'A more thoughtful pantry','heading'=>'Everyday goodness','description'=>'Illustrative editorial story for this local design sample. Replace this text and photo in Product Story for your own brand.','image'=>'/images/themes/food/story.png','alt'=>'Pasta with olive oil / design sample','focus'=>'center','focusY'=>'middle']:null,
    ]);
   }
   $samples=$category->products()->where('handle','like','food-design-sample-%')->get();
   foreach($samples as $sample) foreach($samples->where('id','!=',$sample->id)->values() as $position=>$related) $sample->pdpRelatedProducts()->firstOrCreate(['related_product_id'=>$related->id],['sort_order'=>$position]);
  });
 }
}
