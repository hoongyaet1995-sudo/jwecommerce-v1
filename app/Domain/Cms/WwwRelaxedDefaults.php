<?php
namespace App\Domain\Cms;

use App\Models\{Page,PageDocument};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Bounded www release: patch each stage independently; never publish a draft. */
final class WwwRelaxedDefaults
{
 public function document(array $document):array
 {
  // Legacy pages without page style inherit the new runtime default. Do not add a palette.
  if(isset($document['style']))$document['style']['motionPace']='relaxed';
  return $document;
 }
 public function ready(array $plan):void
 {
  $this->scope($plan);
  if($this->complete($plan))return;
  foreach($plan['documents'] as $row){$d=PageDocument::findOrFail($row['id']);
   if($d->page_id!==$row['pageId']||$d->stage!==$row['stage']||$d->lock_version!==$row['version']||$d->content_hash!==$row['hash'])$this->fail('A page document changed since the release snapshot.');
   $next=app(PageDocumentJsonCodec::class)->canonicalizeDocument(app(PageDocumentContract::class)->validate($this->document($d->document)));
   if($next['sections']!==$d->document['sections'])$this->fail('Section data would change.');
   if(isset($row['afterHash'])&&PageDocumentHasher::hash($next)!==$row['afterHash'])$this->fail('Unexpected resulting document.');
  }
  $this->counts($plan);
 }
 public function run(array $plan):array
 {
  return DB::transaction(function()use($plan){
   $this->scope($plan);app(HomepageSelection::class)->lock();
   PageDocument::whereIn('id',array_column($plan['documents'],'id'))->orderBy('id')->lockForUpdate()->get();
   if($this->complete($plan))return ['status'=>'already-updated','pages'=>$plan['pageIds'],'changed'=>0,'staticSections'=>10];
   $this->ready($plan);$before=$this->protected(array_column($plan['documents'],'id'));$changed=0;
   foreach($plan['documents'] as $row){$d=PageDocument::findOrFail($row['id']);$next=$this->document($d->document);$hash=PageDocumentHasher::hash($next);
    if($hash===$d->content_hash)continue;
    if($d->stage==='draft')app(PageDraftService::class)->save(Page::findOrFail($d->page_id),$next,$d->lock_version,'motion-default');
    else {$next=app(PageDocumentJsonCodec::class)->canonicalizeDocument(app(PageDocumentContract::class)->validate($next));$d->update(['document'=>$next,'content_hash'=>PageDocumentHasher::hash($next),'lock_version'=>$d->lock_version+1]);}
    $changed++;
   }
   if($before!==$this->protected(array_column($plan['documents'],'id')))$this->fail('Protected data changed; rolled back.');
   if(!$this->complete($plan))$this->fail('Pace update incomplete; rolled back.');
   $this->counts($plan);
   return ['status'=>'updated','pages'=>$plan['pageIds'],'changed'=>$changed,'staticSections'=>10,'protectedUnchanged'=>true];
  });
 }
 private function scope(array $plan):void
 {
  if(($plan['release']??null)!=='www-relaxed-default-20261115'||($plan['pageIds']??[])!==range(2,18)||count($plan['documents']??[])!==34||($plan['publishedSectionCount']??null)!==123||($plan['publishedStaticCount']??null)!==10)$this->fail('Unexpected release scope.');
  $pairs=[];$ids=[];foreach($plan['documents'] as $d){if(!is_string($d['afterHash']??null)||!preg_match('/^[a-f0-9]{64}$/',$d['afterHash']))$this->fail('Missing resulting document hash.');$pairs[]=$d['pageId'].':'.$d['stage'];$ids[]=$d['id'];}
  $expected=[];foreach(range(2,18) as $id)foreach(['draft','published'] as $stage)$expected[]=$id.':'.$stage;sort($pairs);sort($expected);
  if($pairs!==$expected||count(array_unique($ids))!==34)$this->fail('Unexpected document identities.');
  if(app(HomepageSelection::class)->id()!==(string)$plan['home'])$this->fail('Homepage changed since preflight.');
 }
 private function complete(array $plan):bool
 {
  foreach($plan['documents'] as $row){$d=PageDocument::find($row['id']);if(!$d||$d->page_id!==$row['pageId']||$d->stage!==$row['stage'])return false;
   $hash=$row['afterHash'];$version=$row['version']+($hash===$row['hash']?0:1);if($d->content_hash!==$hash||$d->lock_version!==$version)return false;
  }return true;
 }
 private function counts(array $plan):void
 {
  $sections=0;$static=0;foreach(PageDocument::whereIn('page_id',$plan['pageIds'])->where('stage','published')->get() as $d)foreach($d->document['sections'] as $s){$sections++;if(($s['motionPreset']??'subtle-lift')==='none')$static++;}
  if($sections!==123||$static!==10)$this->fail('Published Section/static counts changed.');
 }
 private function protected(array $excluded):string
 {
  $rows=[];foreach(['pages','page_documents','menus','menu_items','store_settings'] as $table){$q=DB::table($table)->orderBy('id');if($table==='page_documents')$q->whereNotIn('id',$excluded);$rows[$table]=$q->get()->all();}return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
 }
 private function fail(string $message):never {throw ValidationException::withMessages(['release'=>$message]);}
}
