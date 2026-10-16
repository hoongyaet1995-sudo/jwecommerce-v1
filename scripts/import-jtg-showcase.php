<?php
/** Explicit operator-only showcase import. Never registered as a seeder or public route. */
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Domain\Cms\{PageCrudService,PageDraftService,PagePublicationService,HomepageSelection,PageDocumentContract};
use App\Models\{Page,StoreSetting};
$file=$argv[1]??'';$mode=$argv[2]??'--check';
if(!in_array($mode,['--check','--apply','--verify'],true)||!is_file($file))throw new RuntimeException('Usage: php scripts/import-jtg-showcase.php payload.json [--check|--apply|--verify]');
if($mode==='--verify'&&!app()->environment('local'))throw new RuntimeException('Verification transaction is local only');
$d=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if(count($d['products'])!==17||count($d['categories'])!==8||count($d['collections'])!==6)throw new RuntimeException('Invalid approved scope');
foreach(['products'=>'handle','categories'=>'slug','collections'=>'slug'] as $t=>$key)foreach($d[$t] as $row)if(!str_starts_with($row[$key],'jtg-'))throw new RuntimeException('Non-JTG row refused');
if(!Illuminate\Support\Facades\Schema::hasColumn('product_pdp_contents','faqs'))throw new RuntimeException('Apply FAQ migration first');
app(PageDocumentContract::class)->validate($d['document']);
$tables=['products','categories','collections','product_variants','product_images','product_pdp_contents','collection_product','store_settings','storefront_shell_settings','menus','menu_items','pages','page_documents','page_sections'];
echo json_encode(['mode'=>$mode,'products'=>17,'categories'=>8,'collections'=>6,'sections'=>count($d['document']['sections']),'existingJtg'=>DB::table('products')->where('handle','like','jtg-%')->count()]).PHP_EOL;
if($mode==='--check')exit;
$backup=[];foreach($tables as $t)$backup[$t]=DB::table($t)->get()->all();
$backupPath=storage_path('app/private/jtg-before-'.date('Ymd-His').'.json');
if(!is_dir(dirname($backupPath)))mkdir(dirname($backupPath),0700,true);
if(file_put_contents($backupPath,json_encode($backup,JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Backup failed');chmod($backupPath,0600);
$safety=[];foreach(['orders','order_items','product_enquiries','customers'] as $t)if(Illuminate\Support\Facades\Schema::hasTable($t))$safety[$t]=hash('sha256',DB::table($t)->orderBy('id')->get()->toJson());
$stockBefore=DB::table('product_variants')->orderBy('id')->get(['id','stock','stock_version'])->toJson();
if($mode==='--verify')DB::beginTransaction();
$result=DB::transaction(function()use($d,$safety,$stockBefore){
 $map=[];
 $upsert=function($table,$row,$key)use(&$map){$old=$row['id'];unset($row['id'],$row['created_at'],$row['updated_at']);$id=DB::table($table)->where($key,$row[$key])->value('id');if($id)DB::table($table)->where('id',$id)->update($row);else $id=DB::table($table)->insertGetId($row);$map[$table][$old]=$id;return $id;};
 foreach($d['categories'] as $r){$r['parent_id']=null;$upsert('categories',$r,'slug');}
 foreach($d['collections'] as $r)$upsert('collections',$r,'slug');
 foreach($d['products'] as $r){$r['category_id']=$map['categories'][$r['category_id']];$r['tax_class_id']=null;$upsert('products',$r,'handle');}
 foreach($d['product_variants'] as $r){$r['product_id']=$map['products'][$r['product_id']];$old=$r['id'];$existing=DB::table('product_variants')->where('sku',$r['sku'])->first();if($existing){if($existing->product_id!==$r['product_id'])throw new RuntimeException('SKU collision');$map['product_variants'][$old]=$existing->id;continue;}unset($r['id'],$r['created_at'],$r['updated_at']);$r['stock']=0;$r['stock_version']=0;$r['classified_by']=null;$r['first_physical_verification_operation']=null;$map['product_variants'][$old]=DB::table('product_variants')->insertGetId($r);}
 foreach(['product_images','product_pdp_contents'] as $t)foreach($d[$t] as $r){unset($r['id'],$r['created_at'],$r['updated_at']);$r['product_id']=$map['products'][$r['product_id']];if($t==='product_images'){$r['product_variant_id']=$r['product_variant_id']?($map['product_variants'][$r['product_variant_id']]??null):null;$key=['product_id'=>$r['product_id'],'position'=>$r['position']];}else $key=['product_id'=>$r['product_id']];DB::table($t)->updateOrInsert($key,$r);}
 foreach($d['collection_product'] as $r)DB::table('collection_product')->updateOrInsert(['product_id'=>$map['products'][$r['product_id']],'collection_id'=>$map['collections'][$r['collection_id']]],[]);
 DB::table('products')->where('handle','not like','jtg-%')->update(['status'=>'draft']);foreach(['categories','collections'] as $t)DB::table($t)->where('slug','not like','jtg-%')->update(['is_active'=>false]);
 $doc=$d['document'];foreach($doc['sections'] as &$s){if(isset($s['productIds']))$s['productIds']=array_map(fn($id)=>(string)$map['products'][$id],$s['productIds']);if(isset($s['items']))foreach($s['items'] as &$i){if(($i['sourceType']??'')==='category')$i['sourceId']=(string)$map['categories'][$i['sourceId']];elseif(($i['sourceType']??'')==='collection')$i['sourceId']=(string)$map['collections'][$i['sourceId']];}unset($i);}unset($s);
 $page=Page::whereHas('draftDocument',fn($q)=>$q->where('slug',$doc['slug']))->first();if(!$page)$page=app(PageCrudService::class)->create($doc);else app(PageDraftService::class)->save($page,$doc,$page->draftDocument->lock_version);
 $page->refresh();$page=app(PagePublicationService::class)->publish($page,$page->draftDocument->lock_version);app(HomepageSelection::class)->select($page,$page->draftDocument->lock_version);
 foreach($d['settings'] as $r){$value=json_decode($r['value'],true,512,JSON_THROW_ON_ERROR);if($r['key']==='shop_profile'){$existing=StoreSetting::where('key','shop_profile')->value('value')??[];data_set($existing,'data.shopLogo',data_get($value,'data.shopLogo'));$value=$existing;}elseif($r['key']==='website_design'){$existing=app(App\Services\WebsiteDesignService::class)->live();$existing['theme']='logistics';$existing['overrides']=[];$existing['customization']['logistics']=data_get($value,'data.customization.logistics',[]);app(App\Services\WebsiteDesignService::class)->save($existing);continue;}StoreSetting::updateOrCreate(['key'=>$r['key']],['value'=>$value]);}
 $payload=json_decode($d['shell']['payload'],true,512,JSON_THROW_ON_ERROR);app(App\Services\StorefrontShellSettingsService::class)->save($payload);
 $menus=[];foreach($d['menus'] as $r){$menus[$r['id']]=DB::table('menus')->where('key',$r['key'])->value('id');if(!$menus[$r['id']])$menus[$r['id']]=DB::table('menus')->insertGetId(['key'=>$r['key'],'name'=>$r['name']]);}
 DB::table('menu_items')->whereIn('menu_id',array_values($menus))->update(['is_active'=>false]);$mi=[];
 $headerSourceId=collect($d['menus'])->firstWhere('key','header')['id'];
 foreach($d['menu_items'] as $r){if(!$r['is_active'])continue;if($r['menu_id']===$headerSourceId&&!$r['parent_id']&&!in_array($r['label'],['Containers','Services','Our Projects','Contact'],true))continue;if($r['parent_id']&&!isset($mi[$r['parent_id']]))continue;$old=$r['id'];unset($r['id'],$r['created_at'],$r['updated_at']);$r['menu_id']=$menus[$r['menu_id']];$r['parent_id']=$r['parent_id']?($mi[$r['parent_id']]??null):null;if($r['target_id']){$t=['category'=>'categories','collection'=>'collections','product'=>'products'][$r['link_type']]??null;if($t)$r['target_id']=$map[$t][$r['target_id']]??null;else $r['target_id']=null;}$existing=DB::table('menu_items')->where('menu_id',$r['menu_id'])->where('url',$r['url'])->where('label',$r['label'])->where('parent_id',$r['parent_id'])->value('id');if($existing){DB::table('menu_items')->where('id',$existing)->update($r);$mi[$old]=$existing;}else $mi[$old]=DB::table('menu_items')->insertGetId($r);}
 foreach($safety as $t=>$hash)if(hash('sha256',DB::table($t)->orderBy('id')->get()->toJson())!==$hash)throw new RuntimeException('Operational data changed: '.$t);
 $oldIds=array_column(json_decode($stockBefore,true),'id');if(DB::table('product_variants')->whereIn('id',$oldIds)->orderBy('id')->get(['id','stock','stock_version'])->toJson()!==$stockBefore)throw new RuntimeException('Existing stock changed');
 return ['pageId'=>$page->id,'products'=>count($map['products']),'categories'=>count($map['categories']),'collections'=>count($map['collections']),'operationalDataPreserved'=>true];
});if($mode==='--verify'){DB::rollBack();echo 'Verified complete import transaction, rolled back local data'.PHP_EOL;}echo json_encode($result).PHP_EOL;echo 'Backup: '.$backupPath.PHP_EOL;
