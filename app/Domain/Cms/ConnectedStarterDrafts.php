<?php
namespace App\Domain\Cms;

use App\Models\{Page,PageDocument};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One approved installation of independent drafts; never updates existing authored documents. */
final class ConnectedStarterDrafts
{
    public const RELEASE='connected-starters-20261115';
    private const KEYS=['home','commerce','connected-operations','custom-workflows','inventory-fulfilment','marketing-studio','marketplace','website-builder'];

    public function install(array $bundle,string $brand,array $receipt=[]):array
    {
        if(($bundle['release']??null)!==self::RELEASE||array_column($bundle['pages']??[],'key')!==self::KEYS)throw ValidationException::withMessages(['release'=>'Unexpected starter bundle.']);
        if($receipt!==[]&&(($receipt['release']??null)!==self::RELEASE||count($receipt['pages']??[])!==8))throw ValidationException::withMessages(['receipt'=>'Invalid installation receipt.']);
        return DB::transaction(function()use($bundle,$brand,$receipt){
            $oldIds=Page::query()->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $before=$this->protectedHash($oldIds);
            if($receipt!==[]){
                $rows=[];foreach($receipt['pages'] as $row){$page=Page::query()->findOrFail($row['id']);$rows[]=$this->row($page,$row['key']);}
                return ['release'=>self::RELEASE,'created'=>0,'protectedUnchanged'=>$before===$this->protectedHash($oldIds),'pages'=>$rows];
            }
            $slugs=array_column(array_column($bundle['pages'],'document'),'slug');
            if(PageDocument::query()->whereIn('slug',$slugs)->exists())throw ValidationException::withMessages(['slug'=>'A reserved starter slug already exists. Existing pages were left unchanged.']);
            $documents=[];foreach($bundle['pages'] as $starter){$raw=$this->brand($starter['document'],$brand);$doc=app(PageDocumentJsonCodec::class)->decodeDocument(json_encode($raw,JSON_THROW_ON_ERROR));$documents[$starter['key']]=app(PageDocumentContract::class)->validate($doc);}
            $rows=[];foreach($documents as $key=>$doc){$page=app(PageCrudService::class)->create($doc);$rows[]=$this->row($page,$key);}
            if($before!==$this->protectedHash($oldIds))throw new \RuntimeException('Existing pages, menus or settings changed; installation rolled back.');
            return ['release'=>self::RELEASE,'created'=>count($rows),'protectedUnchanged'=>true,'pages'=>$rows];
        });
    }
    private function row(Page $page,string $key):array
    {
        $draft=$page->draftDocument;return ['key'=>$key,'id'=>$page->id,'title'=>$draft->document['title'],'slug'=>$draft->slug,'status'=>$page->status,'hash'=>$draft->content_hash,'preview'=>'/admin/pages/'.$page->id.'/preview'];
    }
    private function brand(mixed $value,string $brand):mixed
    {
        if(is_string($value))return str_replace('{{brand}}',$brand,$value);
        if(is_array($value)){foreach($value as $key=>$item)$value[$key]=$this->brand($item,$brand);}
        return $value;
    }
    private function protectedHash(array $ids):string
    {
        $snapshot=[];
        foreach(['pages','page_documents','menus','menu_items','store_settings'] as $table){$q=DB::table($table)->orderBy('id');if($table==='pages')$q->whereIn('id',$ids);if($table==='page_documents')$q->whereIn('page_id',$ids);$snapshot[$table]=$q->get()->all();}
        return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));
    }
}
