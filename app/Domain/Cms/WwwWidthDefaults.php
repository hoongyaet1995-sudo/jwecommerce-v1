<?php
namespace App\Domain\Cms;

use App\Models\{Page,PageDocument};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Bounded www release: patch each stage independently; never publish a draft. */
final class WwwWidthDefaults
{
 public function document(array $document):array
 {
  $document['containerWidth']='site';
  return $document;
 }
 public function ready(array $plan):void
 {
  $this->scope($plan);
  if($this->complete($plan))return;
  foreach($plan['documents'] as $row){$d=PageDocument::findOrFail($row['id']);
   if($d->page_id!==$row['pageId']||$d->stage!==$row['stage']||$d->lock_version!==$row['version']||$d->content_hash!==$row['hash'])$this->fail('A page document changed since the release snapshot.');
   if(($d->document['containerWidth']??null)!=='full')$this->fail('Explicit width no longer matches the reviewed template default.');
   $next=app(PageDocumentJsonCodec::class)->canonicalizeDocument(app(PageDocumentContract::class)->validate($this->document($d->document)));
   $unchanged=$next;$unchanged['containerWidth']='full';if(PageDocumentHasher::hash($unchanged)!==$d->content_hash)$this->fail('Non-width data would change.');
   if(isset($row['afterHash'])&&PageDocumentHasher::hash($next)!==$row['afterHash'])$this->fail('Unexpected resulting document.');
  }
 }
 public function run(array $plan):array
 {
  return DB::transaction(function()use($plan){
   $this->scope($plan);app(HomepageSelection::class)->lock();
   PageDocument::whereIn('id',array_column($plan['documents'],'id'))->orderBy('id')->lockForUpdate()->get();
   if($this->complete($plan))return ['status'=>'already-updated','pages'=>$plan['pageIds'],'changed'=>0];
   $this->ready($plan);$before=$this->protected(array_column($plan['documents'],'id'));$changed=0;
   foreach($plan['documents'] as $row){$d=PageDocument::findOrFail($row['id']);$next=$this->document($d->document);$hash=PageDocumentHasher::hash($next);
    if($hash===$d->content_hash)continue;
    if($d->stage==='draft')app(PageDraftService::class)->save(Page::findOrFail($d->page_id),$next,$d->lock_version,'width-cleanup');
    else {$next=app(PageDocumentJsonCodec::class)->canonicalizeDocument(app(PageDocumentContract::class)->validate($next));$d->update(['document'=>$next,'content_hash'=>PageDocumentHasher::hash($next),'lock_version'=>$d->lock_version+1]);}
    $changed++;
   }
   if($before!==$this->protected(array_column($plan['documents'],'id')))$this->fail('Protected data changed; rolled back.');
   if(!$this->complete($plan))$this->fail('Width update incomplete; rolled back.');
    return ['status'=>'updated','pages'=>$plan['pageIds'],'changed'=>$changed,'protectedUnchanged'=>true];
  });
 }
 private function scope(array $plan):void
 {
  if(($plan['release']??null)!=='www-width-default-20261115'||($plan['pageIds']??[])!==range(11,18)||count($plan['documents']??[])!==16)$this->fail('Unexpected release scope.');
  $pairs=[];$ids=[];foreach($plan['documents'] as $d){if(!is_string($d['afterHash']??null)||!preg_match('/^[a-f0-9]{64}$/',$d['afterHash']))$this->fail('Missing resulting document hash.');$pairs[]=$d['pageId'].':'.$d['stage'];$ids[]=$d['id'];}
  $expected=[];foreach(range(11,18) as $id)foreach(['draft','published'] as $stage)$expected[]=$id.':'.$stage;sort($pairs);sort($expected);
  if($pairs!==$expected||count(array_unique($ids))!==16)$this->fail('Unexpected document identities.');
  if(app(HomepageSelection::class)->id()!==(string)$plan['home'])$this->fail('Homepage changed since preflight.');
 }
 private function complete(array $plan):bool
 {
  foreach($plan['documents'] as $row){$d=PageDocument::find($row['id']);if(!$d||$d->page_id!==$row['pageId']||$d->stage!==$row['stage'])return false;
   $hash=$row['afterHash'];$version=$row['version']+($hash===$row['hash']?0:1);if($d->content_hash!==$hash||$d->lock_version!==$version)return false;
  }return true;
 }
 private function protected(array $excluded):string
 {
  $rows=[];foreach(['pages','page_documents','menus','menu_items','store_settings','storefront_shell_settings'] as $table){$q=DB::table($table)->orderBy('id');if($table==='page_documents')$q->whereNotIn('id',$excluded);$rows[$table]=$q->get()->all();}return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
 }
 private function fail(string $message):never {throw ValidationException::withMessages(['release'=>$message]);}
}
