<?php

namespace Database\Seeders;

use App\Models\{Category,Collection,Product,StoreSetting};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicitly requested local catalog records; not part of DatabaseSeeder or release. */
class BabyThemeLocalSamplesSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local') || str_replace('\\','/',base_path()) !== 'C:/xampp/htdocs/jwstudio-ecommerce' || DB::connection()->getDatabaseName() !== 'jw_ecommerce') {
            throw new \RuntimeException('Baby catalog samples require the canonical local database.');
        }

        DB::transaction(function (): void {
            $category=Category::firstOrCreate(['slug'=>'baby-kids'],[
                'name'=>'Baby & Kids','description'=>'Soft textures and thoughtful pieces for little moments.',
                'image_url'=>'/images/themes/baby/hero.png','is_active'=>true,'sort_order'=>999,
            ]);
            $collection=Collection::firstOrCreate(['slug'=>'baby-everyday-essentials'],[
                'title'=>'Little everyday essentials','subtitle'=>'Soft layers, little comforts and everyday joy.',
                'description'=>'Baby & Kids local catalog collection.', 'cover_image'=>'/images/themes/baby/hero.png',
                'type'=>'manual','status'=>'active','is_active'=>true,
            ]);
            $rows=[
                ['blanket','Cloud Cotton Muslin Blanket',8900,'A soft layer for little moments.','Oat','100 x 100 cm','Cotton muslin'],
                ['bibs','Cotton Bib Set',3900,'Everyday bibs for happy meals.','Blush','One size','Cotton'],
                ['booties','Soft Knit Booties',4900,'Warm little steps ahead.','Sage','0–6 months','Cotton knit'],
                ['swaddle','Muslin Swaddle',6900,'Soft textures for everyday cuddles.','Oat','100 x 100 cm','Cotton muslin'],
                ['cushion','Little Cloud Cushion',5900,'A little comfort for their room.','Cream','35 x 25 cm','Boucle fabric'],
                ['rings','Wooden Stacking Rings',7900,'Little shapes, everyday discovery.','Sage','One set','Wood'],
            ];
            $products=collect();
            foreach($rows as [$key,$title,$price,$subtitle,$colour,$size,$material]) {
                $colours=$key==='blanket'?['Oat'=>'#cfc3b3','Sage'=>'#a5ae95']:[$colour=>['Blush'=>'#d6ada0','Sage'=>'#a5ae95','Cream'=>'#e7dfd1','Oat'=>'#cfc3b3'][$colour]];
                $image='/images/themes/baby/'.$key.'.png';
                $product=Product::firstOrCreate(['handle'=>'local-baby-'.$key],[
                    'category_id'=>$category->id,'title'=>$title,'subtitle'=>$subtitle,'brand'=>'JW STUDIO',
                    'description'=>'<p>'.$subtitle.'</p><p>Local design catalog sample. Product details and prices are illustrative.</p>',
                    'base_price'=>$price,'status'=>'active','has_variants'=>true,'is_featured'=>false,'tags'=>['local-design-sample','baby-theme'],
                    'options'=>[
                        ['id'=>'colour','name'=>'Colour','displayType'=>'color_palette','values'=>array_keys($colours),'valueVisuals'=>array_map(fn($hex)=>['visualType'=>'color','colorCode'=>$hex],$colours)],
                        ['id'=>'size','name'=>'Size','displayType'=>'pills','values'=>[$size]],
                    ],
                ]);
                foreach($colours as $name=>$hex) $product->variants()->firstOrCreate(['sku'=>'LOCAL-BABY-'.strtoupper($key).'-'.strtoupper($name)],[
                    'title'=>$name,'options'=>['Colour'=>$name,'Size'=>$size],'size'=>$size,'color_code'=>$hex,'visual_type'=>'color',
                    'price'=>$price,'stock'=>0,'enabled'=>true,'image_url'=>$image,
                ]);
                $gallery=$key==='blanket'?[$image,'/images/themes/baby/blanket-texture.png','/images/themes/baby/blanket-fold.png','/images/themes/baby/blanket-stack.png']:[$image];
                foreach($gallery as $position=>$path) $product->images()->firstOrCreate(['path'=>$path],[
                    'alt'=>$position===0?$title:'Baby & Kids nursery collection','position'=>$position,
                ]);
                $product->pdpContent()->firstOrCreate([], [
                    'highlights'=>[['id'=>'soft','text'=>'Thoughtful textures','icon'=>'leaf'],['id'=>'simple','text'=>'Everyday comfort','icon'=>'heart'],['id'=>'package','text'=>'Little essentials','icon'=>'package']],
                    'specifications'=>[['id'=>'material','label'=>'Material','value'=>$material],['id'=>'size','label'=>'Size','value'=>$size],['id'=>'colour','label'=>'Colour','value'=>$colour]],
                    'custom_accordions'=>[['id'=>'care','title'=>'Care instructions','content'=>'<p>Follow the care instructions supplied with the product.</p>'],['id'=>'box','title'=>"What's in the box",'content'=>'<p>'.$title.'.</p>']],
                    'use_store_default_shipping'=>true,
                    'story'=>['heading'=>'Made for their little moments.','description'=>$subtitle,'image'=>$key==='blanket'?'/images/themes/baby/blanket-story.png':'/images/themes/baby/hero.png','alt'=>'A soft nursery setting','focus'=>'center','focusY'=>'middle'],
                ]);
                $products->push($product);
            }
            $collection->products()->syncWithoutDetaching($products->pluck('id')->all());
            foreach($products as $product) foreach($products->where('id','!=',$product->id)->take(3)->values() as $position=>$related) {
                $product->pdpRelatedProducts()->firstOrCreate(['related_product_id'=>$related->id],['sort_order'=>$position]);
            }
            $setting=StoreSetting::firstOrNew(['key'=>'website_design']);
            $value=$setting->value??['data'=>['theme'=>'default','overrides'=>[],'customization'=>[]]];
            $value['data']['overrides']['category:'.$category->id]='baby';
            $value['data']['overrides']['collection:'.$collection->id]='baby';
            $setting->value=$value;$setting->save();
            $this->command?->info('Baby catalog ready: /categories/baby-kids, /collections/baby-everyday-essentials, 6 products. Stock initialization was not performed.');
        });
    }
}
