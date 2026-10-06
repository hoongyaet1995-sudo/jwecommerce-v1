<?php
/** Approved local-only taxonomy data maintenance; run --preview then --apply. */
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\{Category,Collection,Product};
if(!app()->environment('local')||str_replace('\\','/',base_path())!=='C:/xampp/htdocs/jwstudio-ecommerce'||DB::connection()->getDatabaseName()!=='jw_ecommerce'||DB::connection()->getDriverName()!=='mysql')throw new RuntimeException('Canonical local MySQL required');
if(!in_array($argv[1]??'', ['--preview','--apply'],true))throw new RuntimeException('Choose --preview or --apply');
$apply=$argv[1]==='--apply';
$backup=json_decode(file_get_contents(base_path('tmp/home-taxonomy-backup.json')),true,512,JSON_THROW_ON_ERROR);
if(!is_file($backup['path'])||hash_file('sha256',$backup['path'])!==$backup['sha256'])throw new RuntimeException('Verified backup required');
$fixture=json_decode(file_get_contents(base_path('database/fixtures/ikea-malaysia-home-demo.json')),true,512,JSON_THROW_ON_ERROR);
if(Product::count()!==10||Category::count()!==11||Collection::count()!==4)throw new RuntimeException('Baseline drift; audit required');
foreach($fixture['products'] as $p)Product::where('handle',$p['handle'])->firstOrFail();
Illuminate\Support\Facades\Http::preventStrayRequests();
$categorySpecs=[
 ['tables','Tables','桌几','Side tables and coffee tables for everyday living.','10449909',['LACK']],
 ['shelving-bookcases','Shelving & Bookcases','书架与置物架','Bookcases and shelving units for books, display and organised storage.','70351886',['KALLAX','BILLY']],
 ['storage-organisation','Storage & Organisation','收纳整理','Flexible trolleys and storage boxes to keep everyday essentials organised.','20586788',['RÅSKOG','DRÖNA']],
 ['lighting','Lighting','照明','Task lighting for desks, reading corners and focused work.','10146766',['FORSÅ']],
];
$collectionSpecs=[
 ['living-room-essentials','Living Room Essentials','Tables, shelving and storage for a considered living space.','10449909',['LACK','KALLAX','DRÖNA']],
 ['organised-home','Organised Home','Shelving, bookcases, trolleys and boxes that help everything find its place.','70351886',['KALLAX','BILLY','RÅSKOG','DRÖNA']],
 ['home-office-essentials','Home Office Essentials','Practical book storage, movable organisation and focused task lighting.','10146766',['BILLY','RÅSKOG','FORSÅ']],
];
$image=function($article)use($fixture){foreach($fixture['products'] as $p)if(str_replace('.','',$p['article'])===$article){if(!is_file(public_path(ltrim($p['image_path'],'/'))))throw new RuntimeException('Missing cover');return $p['image_path'];}throw new RuntimeException('Missing photo reference');};
$snapshot=function(){ $out=[];$tables=DB::select('SHOW TABLES');foreach($tables as $row){$t=array_values((array)$row)[0];if(in_array($t,['categories','collections','collection_product'],true))continue;$rows=DB::table($t)->get()->map(function($r)use($t){$v=(array)$r;if($t==='products')unset($v['category_id'],$v['updated_at']);ksort($v);return $v;})->all();$encoded=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($encoded);$out[$t]=hash('sha256',implode("\n",$encoded));}return $out;};
$before=$snapshot();$started=microtime(true);
DB::beginTransaction();
try{
 $products=Product::all();$keep=[];$report=['categories'=>[],'collections'=>[]];
 foreach($categorySpecs as $i=>[$slug,$name,$cn,$description,$cover,$families]){
  $previous=['tables'=>'jw-home-test-tables','storage-organisation'=>'jw-home-test-home-storage','lighting'=>'jw-home-test-lighting'][$slug]??$slug;
  $category=Category::firstOrNew(['slug'=>$previous]);
  $category->fill(['slug'=>$slug,'name'=>$name,'name_cn'=>$cn,'parent_id'=>null,'description'=>$description,'image_url'=>$image($cover),'seo_title'=>$name.' | JW Home','seo_description'=>$description,'seo_keywords'=>implode(', ', $families),'sort_order'=>$i,'is_active'=>true]);$category->save();$keep[]=$category->id;
  $ids=$products->filter(fn($p)=>in_array(explode(' ',$p->title)[0],$families,true))->pluck('id')->all();
  if(!$ids)throw new RuntimeException('Empty category');Product::whereIn('id',$ids)->update(['category_id'=>$category->id]);
  $report['categories'][$name]=count($ids);
 }
 if(Product::whereNotIn('category_id',$keep)->exists())throw new RuntimeException('Unclassified product');
 Category::whereNotIn('id',$keep)->update(['parent_id'=>null]);Category::whereNotIn('id',$keep)->delete();
 $keepCollections=[];
 foreach($collectionSpecs as [$slug,$title,$description,$cover,$families]){
  $collection=Collection::updateOrCreate(['slug'=>$slug],['title'=>$title,'subtitle'=>$description,'description'=>$description.' IKEA Malaysia reference catalogue for local JW Home acceptance; availability requires JW stock verification.','cover_image'=>$image($cover),'badge'=>null,'type'=>'manual','match_type'=>'all','dynamic_rules'=>[],'status'=>'active','is_active'=>true]);
  $ids=$products->filter(fn($p)=>in_array(explode(' ',$p->title)[0],$families,true))->pluck('id')->all();$collection->products()->sync($ids);$keepCollections[]=$collection->id;$report['collections'][$title]=count($ids);
 }
 Collection::whereNotIn('id',$keepCollections)->delete();
 if(Category::count()!==4||Collection::count()!==3||DB::table('collection_product')->count()!==17)throw new RuntimeException('Membership verification failed');
 if($snapshot()!==$before)throw new RuntimeException('Unrelated data changed');
 $report['mode']=$apply?'apply':'preview';$report['seconds']=round(microtime(true)-$started,3);$report['unrelated_checksums']='PASS';$report['backup_sha256']=$backup['sha256'];
 if($apply)DB::commit();else DB::rollBack();
 file_put_contents(base_path('tmp/home-taxonomy-'.($apply?'apply':'preview').'-report.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($report,JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){DB::rollBack();throw $e;}
