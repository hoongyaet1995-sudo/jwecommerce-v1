<?php
namespace App\Domain\Cms;
use App\Models\{Page,PageDocument,Menu,MenuItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit www-only release plan; canonical publishing services retain old pages. */
final class ConnectedWwwPublication
{
    public function menus():array
    {
        $menus=Menu::with('items')->whereIn('key',['header','footer'])->orderBy('id')->get()->toArray();
        foreach($menus as &$m){unset($m['created_at'],$m['updated_at']);foreach($m['items'] as &$i)unset($i['created_at'],$i['updated_at']);unset($i);usort($m['items'],fn($a,$b)=>$a['id']<=>$b['id']);}unset($m);return $menus;
    }
    public function ready(array $b):void
    {
        if(($b['release']??'')!=='www-connected-publication-20261115'||count($b['pages']??[])!==8||!in_array($b['homePage'],array_column($b['pages'],'id'),true))$this->fail('Unexpected publication plan.');
        if(app(HomepageSelection::class)->id()!==(string)$b['expectedHome'])$this->fail('Homepage changed since preflight.');
        if(PageDocumentHasher::hash(['menus'=>$this->menus()])!==PageDocumentHasher::hash(['menus'=>$b['expectedMenus']]))$this->fail('Navigation changed since preflight.');
        foreach($b['pages'] as $row){$p=Page::with(['draftDocument','publishedDocument'])->findOrFail($row['id']);$d=$p->draftDocument;if($p->status!=='draft'||$p->publishedDocument||$d->slug!==$row['draftSlug']||$d->lock_version!==$row['version']||$d->content_hash!==$row['draftHash'])$this->fail('A starter changed; review its current version before publishing.');app(PageDocumentContract::class)->assertPublishable($d->document,app(RuntimeAdapterRegistry::class)->factsFor($d->document));}
    }
    public function run(array $b):array
    {
        return DB::transaction(function()use($b){
            app(HomepageSelection::class)->lock();Page::orderBy('id')->lockForUpdate()->get();
            if($this->complete($b))return ['status'=>'already-published','home'=>(string)$b['homePage'],'pages'=>array_column($b['pages'],'id')];
            $this->ready($b);$pageIds=Page::whereNotIn('id',array_column($b['pages'],'id'))->pluck('id')->all();$docIds=PageDocument::pluck('id')->all();$before=$this->protected($pageIds,$docIds);
            foreach($b['pages'] as $row){$p=Page::findOrFail($row['id']);app(PagePublicationService::class)->publish($p,$row['version']);}
            app(HomepageSelection::class)->select(Page::findOrFail($b['homePage']),collect($b['pages'])->firstWhere('id',$b['homePage'])['version']);
            foreach($b['updates'] as $u)MenuItem::findOrFail($u['id'])->update($u['fields']);
            $header=Menu::where('key','header')->firstOrFail();foreach($header->items()->whereNull('parent_id')->get() as $item)$item->update(['position'=>$item->position+1]);
            MenuItem::create(['menu_id'=>$header->id,'parent_id'=>null,'label'=>'Home','url'=>'/','position'=>0,'link_type'=>'custom_url','is_active'=>true,'is_external'=>false,'display_style'=>'simple_link']);
            $footer=Menu::where('key','footer')->firstOrFail();$company=$footer->items()->where('label','Company')->whereNull('parent_id')->firstOrFail();
            MenuItem::create(['menu_id'=>$footer->id,'parent_id'=>$company->id,'label'=>'Home','url'=>'/','position'=>3,'link_type'=>'custom_url','is_active'=>true,'is_external'=>false]);
            if($before!==$this->protected($pageIds,$docIds))throw new \RuntimeException('Protected original pages or settings changed; publication rolled back.');
            if(!$this->complete($b))throw new \RuntimeException('Publication incomplete; transaction rolled back.');
            return ['status'=>'published','home'=>(string)$b['homePage'],'pages'=>array_column($b['pages'],'id'),'oldPagesPreserved'=>true,'menuHash'=>PageDocumentHasher::hash(['menus'=>$this->menus()])];
        });
    }
    private function complete(array $b):bool
    {
        if(($b['release']??'')!=='www-connected-publication-20261115'||app(HomepageSelection::class)->id()!==(string)($b['homePage']??''))return false;
        foreach($b['pages'] as $r){$p=Page::with('publishedDocument')->find($r['id']);if(!$p||$p->status!=='published'||$p->publishedDocument?->content_hash!==$r['draftHash'])return false;}
        foreach($b['updates'] as $u){$i=MenuItem::find($u['id']);if(!$i)return false;foreach($u['fields'] as $k=>$v)if($i->$k!==$v)return false;}
        foreach(['header','footer'] as $key){$menu=Menu::where('key',$key)->first();if(!$menu||$menu->items()->where('label','Home')->where('url','/')->count()!==1)return false;}return true;
    }
    private function protected(array $pageIds,array $docIds):string
    {
        $state=['pages'=>DB::table('pages')->whereIn('id',$pageIds)->orderBy('id')->get()->all(),'documents'=>DB::table('page_documents')->whereIn('id',$docIds)->orderBy('id')->get()->all(),'settings'=>DB::table('store_settings')->where('key','!=',HomepageSelection::KEY)->orderBy('id')->get()->all(),'menus'=>DB::table('menus')->orderBy('id')->get()->all()];return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
    }
    private function fail(string $message):never{throw ValidationException::withMessages(['release'=>$message]);}
}
