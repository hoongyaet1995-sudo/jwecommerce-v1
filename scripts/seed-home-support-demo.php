<?php
/** Approved one-off local data maintenance. Preview rolls back; apply requires verified full backup. */
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;
use App\Models\Category;
use App\Models\Collection;

$production=($argv[2]??'')==='--production-release';
$localVerification=($argv[2]??'')==='--verify-local-release';
if($production){if(!app()->environment('production')||str_replace('\\','/',base_path())!=='/home/u762348670/apps/jwecommerce'||parse_url(config('app.url'),PHP_URL_HOST)!=='ecom.jwaistudio.online'||!app()->isDownForMaintenance())throw new RuntimeException('Approved production root in maintenance required');}
elseif(!app()->environment('local') || str_replace('\\','/',base_path())!=='C:/xampp/htdocs/jwstudio-ecommerce')throw new RuntimeException('Canonical local environment required.');
if(!in_array($argv[1]??'', ['--preview','--apply'],true))throw new RuntimeException('Choose --preview or --apply.');
$apply=$argv[1]==='--apply';$db=DB::connection();
if($db->getDriverName()!=='mysql'||$db->getDatabaseName()!==($production?'u762348670_jwstudio_ecom':'jw_ecommerce'))throw new RuntimeException('Database identity mismatch.');
$backup=json_decode(file_get_contents($production?storage_path('app/private/home-release-backup.json'):base_path('tmp/home-demo-backup.json')),true,512,JSON_THROW_ON_ERROR);
$fixture=json_decode(file_get_contents(base_path('database/fixtures/ikea-malaysia-home-demo.json')),true,512,JSON_THROW_ON_ERROR);
if(count($fixture['products'])!==10||count($fixture['faq']['items'])!==30)throw new RuntimeException('Fixture size mismatch.');
foreach($fixture['products'] as $product){if(!str_starts_with($product['source_url'],'https://www.ikea.com/my/en/p/')||!is_file(public_path(ltrim($product['image_path'],'/')))||$product['price_cents']<=0)throw new RuntimeException('Invalid product evidence.');}
$jobs=DB::table('jobs')->get();
foreach($jobs as $job)if(!$production||$job->queue!=='easyparcel'||(json_decode($job->payload,true)['displayName']??null)!=='App\\Jobs\\ReconcileEasyParcelTask'||$job->reserved_at!==null)throw new RuntimeException('Unreviewed or active queued work');
Illuminate\Support\Facades\Http::preventStrayRequests();
$lock=DB::selectOne("SELECT GET_LOCK('jw-home-demo-reset',0) AS acquired");if((int)$lock->acquired!==1)throw new RuntimeException('Another reset is active.');
$preserved=['users','customers','orders','order_items','order_addresses','order_payments','order_status_histories','marketplace_orders','marketplace_stores','marketplace_store_tokens','support_conversations','support_messages','support_staff_notes','inventory_current_stock_historical_orders','jobs'];
$snapshot=function()use($preserved){$out=[];foreach($preserved as $t){if(!Schema::hasTable($t))continue;$rows=DB::table($t)->orderBy('id')->get()->map(function($r)use($t){$v=(array)$r;if($t==='order_items'){unset($v['product_variant_id'],$v['inventory_order_activation_id']);}return $v;})->all();$out[$t]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];}return $out;};
$before=$snapshot();
$expected=$production?['products'=>26,'product_variants'=>94,'marketplace_listing_variants'=>4219,'marketplace_variant_mappings'=>126]:($localVerification?['products'=>10,'product_variants'=>10,'marketplace_listing_variants'=>0,'marketplace_variant_mappings'=>0]:['products'=>22,'product_variants'=>84,'marketplace_listing_variants'=>757,'marketplace_variant_mappings'=>24]);
if($localVerification&&$apply)throw new RuntimeException('Local release validation is preview-only');
foreach($expected as $t=>$count)if(DB::table($t)->count()!==$count)throw new RuntimeException('Baseline changed: '.$t.'. Re-audit before resetting.');
$started=microtime(true);
DB::beginTransaction();
try{
 // Existing reset implementation preserves order replay protection and clears stock effects with FKs enforced.
 app(App\Services\Inventory\CurrentStockReset::class)->execute($backup['path'],$backup['sha256'],$db->getDatabaseName(),'home-demo-20261006-'.($production?'production-':($localVerification?'validation-':'')).($apply?'apply':'preview'),$production);
 $delete=['products','product_variants','product_images','product_handle_redirects','product_pdp_contents','product_pdp_reviews','product_pdp_related_products','product_reviews','product_review_events','product_review_media','collection_product','cart_items','wishlist_items','inventory_balances','marketplace_listing_variants','marketplace_variant_mappings','marketplace_mapping_events','marketplace_variant_imports','marketplace_variant_transfers','marketplace_product_imports','marketplace_reviewed_imports','marketplace_image_receipts','marketplace_publishing_tasks','marketplace_publishing_recovery_authorizations','marketplace_stock_retrieval_items','marketplace_stock_retrieval_runs','marketplace_catalog_runs','marketplace_variant_facility_scope','marketplace_facility_binding_reviews','inventory_sync_batch_attempts','inventory_sync_batch_items','inventory_sync_batch_reviews','inventory_sync_batches','inventory_sync_destination_claims','marketplace_inventory_items','marketplace_inventory_policies'];
 $delete=array_values(array_filter($delete,fn($t)=>Schema::hasTable($t)));
 $refs=DB::select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$db->getDatabaseName()]);
 // Stop on an unreviewed child table; never disable FK checks to force deletion.
 DB::table('order_items')->whereNotNull('product_variant_id')->update(['product_variant_id'=>null]);
 foreach($refs as $ref)if(in_array($ref->REFERENCED_TABLE_NAME,$delete,true)&&!in_array($ref->TABLE_NAME,$delete,true)&&DB::table($ref->TABLE_NAME)->whereNotNull($ref->COLUMN_NAME)->exists())throw new RuntimeException('Unreviewed reference: '.$ref->TABLE_NAME.'.'.$ref->COLUMN_NAME);
 DB::table('catalog_media_references')->where('owner_type','product')->delete();
 $remaining=$delete;$deleted=[];
 // Empty tables have no live FK dependencies, including the nullable batch review cycles.
 foreach($remaining as $table)if(DB::table($table)->count()===0){$deleted[$table]=0;$remaining=array_values(array_diff($remaining,[$table]));}
 while($remaining){$ready=array_values(array_filter($remaining,function($parent)use($refs,$remaining){foreach($refs as $r)if($r->REFERENCED_TABLE_NAME===$parent&&$r->TABLE_NAME!==$parent&&in_array($r->TABLE_NAME,$remaining,true))return false;return true;}));if(!$ready)throw new RuntimeException('Delete dependency cycle detected.');foreach($ready as $table){$deleted[$table]=DB::table($table)->delete();$remaining=array_values(array_diff($remaining,[$table]));}}
 $categories=[];$collections=[];
 $categoryDetails=[
 'tables'=>['桌几','Side tables and coffee tables for everyday living.','10449909'],
 'shelving-bookcases'=>['书架与置物架','Bookcases and shelving units for books, display and organised storage.','70351886'],
 'storage-organisation'=>['收纳整理','Flexible trolleys and storage boxes to keep everyday essentials organised.','20586788'],
 'lighting'=>['照明','Task lighting for desks, reading corners and focused work.','10146766']];
 $collectionDetails=[
 'living-room-essentials'=>['Tables, shelving and storage for a considered living space.','10449909'],
 'organised-home'=>['Shelving, bookcases, trolleys and boxes that help everything find its place.','70351886'],
 'home-office-essentials'=>['Practical book storage, movable organisation and focused task lighting.','10146766']];
 $cover=function($article)use($fixture){foreach($fixture['products'] as $p)if(str_replace('.','',$p['article'])===$article)return $p['image_path'];throw new RuntimeException('Missing cover');};
 foreach($fixture['taxonomy']['categories'] as $slug=>$name){[$cn,$description,$article]=$categoryDetails[$slug];$categories[$name]=Category::updateOrCreate(['slug'=>$slug],['name'=>$name,'name_cn'=>$cn,'parent_id'=>null,'description'=>$description,'image_url'=>$cover($article),'seo_title'=>$name.' | JW Home','seo_description'=>$description,'sort_order'=>count($categories),'is_active'=>true]);}
 foreach($fixture['taxonomy']['collections'] as $slug=>$definition){[$description,$article]=$collectionDetails[$slug];$collections[$slug]=Collection::updateOrCreate(['slug'=>$slug],['title'=>$definition['title'],'subtitle'=>$description,'description'=>$description.' IKEA Malaysia reference catalogue for JW Home acceptance; availability requires JW stock verification.','cover_image'=>$cover($article),'badge'=>null,'type'=>'manual','match_type'=>'all','dynamic_rules'=>[],'status'=>'active','is_active'=>true]);}
 foreach($fixture['products'] as $i=>$p){
  $category=$categories[$p['category']];
  $product=Product::create(['category_id'=>$category->id,'handle'=>$p['handle'],'title'=>$p['title'],'subtitle'=>'Local AI support test · IKEA Malaysia reference','brand'=>'IKEA','description'=>$p['description'],'base_price'=>$p['price_cents'],'features'=>$p['features'],'status'=>'active','is_featured'=>$i<4,'has_variants'=>false,'tags'=>['jw-home-test','ikea-malaysia-reference'],'seo_title'=>$p['title'],'seo_description'=>'Local test catalogue reference; stock requires First Count.']);
  $product->variants()->create(['sku'=>$p['sku'],'title'=>$p['title'],'color_name'=>$p['colour'],'size'=>$p['dimensions'],'material'=>$p['material'],'price'=>$p['price_cents'],'stock'=>0,'enabled'=>true,'image_url'=>$p['image_path']]);
  $product->images()->create(['path'=>$p['image_path'],'alt'=>$p['title'].' — IKEA Malaysia reference','position'=>0]);
  $product->pdpContent()->create(['specifications'=>array_map(fn($label,$value)=>['label'=>$label,'value'=>$value],array_keys($p['specifications']),array_values($p['specifications'])),'highlights'=>[],'custom_accordions'=>[]]);
  foreach($fixture['taxonomy']['collections'] as $slug=>$definition)if(in_array(explode(' ',$p['title'])[0],$definition['families'],true))$product->collections()->attach($collections[$slug]->id);
 }
 $categoryIds=array_map(fn($c)=>$c->id,$categories);Category::whereNotIn('id',$categoryIds)->update(['parent_id'=>null]);Category::whereNotIn('id',$categoryIds)->delete();
 Collection::whereNotIn('id',array_map(fn($c)=>$c->id,$collections))->delete();
 if(Category::count()!==4||Collection::count()!==3||DB::table('collection_product')->count()!==17)throw new RuntimeException('Taxonomy verification failed');
 $settings=app(App\Services\SupportFaqService::class)->read();$settings['faq']=$fixture['faq'];app(App\Services\SupportFaqService::class)->save($settings);
 if($before!==$snapshot())throw new RuntimeException('Protected records changed; rolling back.');
 $checks=['products'=>Product::count(),'sku'=>DB::table('product_variants')->count(),'faq'=>count(app(App\Services\SupportFaqService::class)->read()['faq']['items']),'marketplace_listings'=>DB::table('marketplace_listing_variants')->count(),'marketplace_mappings'=>DB::table('marketplace_variant_mappings')->count(),'balances'=>DB::table('inventory_balances')->count(),'jobs'=>DB::table('jobs')->count(),'counted_variants'=>DB::table('product_variants')->whereNotNull('inventory_initialized_at')->count()];
 if($checks!==['products'=>10,'sku'=>10,'faq'=>30,'marketplace_listings'=>0,'marketplace_mappings'=>0,'balances'=>0,'jobs'=>$jobs->count(),'counted_variants'=>0])throw new RuntimeException('Acceptance counts failed.');
 $report=['mode'=>$apply?'apply':'preview-rolled-back','database'=>$db->getDatabaseName(),'backup_sha256'=>$backup['sha256'],'deleted'=>$deleted,'after'=>$checks,'protected'=>$before,'seconds'=>round(microtime(true)-$started,2)];
 if($apply)DB::commit();else DB::rollBack();
 file_put_contents($production?storage_path('app/private/home-demo-'.($apply?'apply':'preview').'-report.json'):base_path('tmp/home-demo-'.($localVerification?'validation-':'').($apply?'apply':'preview').'-report.json'),json_encode($report,JSON_PRETTY_PRINT));echo json_encode(['mode'=>$report['mode'],'after'=>$checks,'protected_records_unchanged'=>true,'seconds'=>$report['seconds']])."\n";
}catch(Throwable $e){DB::rollBack();throw $e;}finally{DB::select("SELECT RELEASE_LOCK('jw-home-demo-reset')");}
