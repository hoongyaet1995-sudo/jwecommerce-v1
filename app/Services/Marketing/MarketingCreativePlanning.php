<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingResearchRun,MarketingAiRun,MarketingAsset,StoreSetting};
use Illuminate\Support\Facades\{Cache,DB,Storage};
use Illuminate\Validation\ValidationException;

/** Bounded local acceptance of saved research -> concepts -> the existing six-scene recipe. */
class MarketingCreativePlanning
{
    public function enabled(string $project): bool
    {
        return (bool) config('marketing.creative.enabled') && config('marketing.creative.project') === $project
            && (app()->runningUnitTests() || app()->environment('local'));
    }

    public function company(): array
    {
        $profile=data_get(StoreSetting::where('key','shop_profile')->value('value'),'data',[]);
        $public=\Illuminate\Support\Arr::only(is_array($profile)?$profile:[],['shopName','tagline','country','city','state']);
        foreach($public as &$value){$value=is_scalar($value)?mb_substr(strip_tags((string)$value),0,300):'';}unset($value);
        return $public+['shopName'=>app(\App\Support\ShopBrand::class)->name()];
    }

    public function research(MarketingProject $p): ?array
    {
        $query=MarketingResearchRun::where('actor_id',$p->owner_id)->whereIn('status',['available','partial','insufficient'])
            ->whereIn('project_id',MarketingProject::where('owner_id',$p->owner_id)->when($p->product_id,fn($q)=>$q->where('product_id',$p->product_id),fn($q)=>$q->whereKey($p->id))->select('id'))->latest();
        $service=app(MarketingResearchSources::class);
        foreach($query->limit(10)->get() as $run){
            if(\Illuminate\Support\Arr::only($run->input_snapshot['snapshot']??[],['title','description','purchase_mode'])!==\Illuminate\Support\Arr::only($p->snapshot,['title','description','purchase_mode']))continue;
            $saved=array_column($run->sources??[],null,'id');
            $raw=array_map(fn($s)=>['url'=>$s['canonical_url'],'title'=>$s['title'],'raw_content'=>($s['extraction_status']??'')==='content_available'?$s['bounded_excerpt']:null,'content'=>($s['extraction_status']??'')==='content_available'?'':$s['bounded_excerpt'],'_role'=>$s['reference_role'],'_request_id'=>$s['request_id']??null,'_extraction_status'=>$s['extraction_status']??'not_attempted','_extraction_error'=>$s['extraction_error_code']??null],$run->sources??[]);
            $sources=$service->analysisSources($service->normalize($raw));
            if(!$sources)continue;
            foreach($sources as &$s){$s['retrieved_at']=$saved[$s['id']]['retrieved_at'];$s['bounded_excerpt']=mb_substr($s['bounded_excerpt'],0,2000);}unset($s);
            return ['run_id'=>$run->id,'origin_project_id'=>$run->project_id,'sources'=>$sources,'coverage'=>$service->coverage($sources),'creative_readiness'=>app(MarketingReferenceReadiness::class)->assess($sources),'digest'=>hash('sha256',json_encode($sources,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),'limitations'=>['Saved transcript text only. No new search, playback or current viral-performance verification. Reference facts never become merchant product claims.']];
        }
        return null;
    }

    public function reuseClip(MarketingProject $p): void
    {
        if(!$p->product_id||$p->assets()->where('authorized',true)->where('mime','video/mp4')->exists())return;
        $candidate=MarketingAsset::where('authorized',true)->where('mime','video/mp4')->whereNotIn('id',DB::table('marketing_media_reuses')->select('project_asset_id'))->whereIn('project_id',MarketingProject::where('owner_id',$p->owner_id)->where('product_id',$p->product_id)->where('id','!=',$p->id)->select('id'))->latest()->first();
        if(!$candidate)return;
        $disk=Storage::disk('local');if(!$disk->exists($candidate->path))return;
        $sha=hash_file('sha256',$disk->path($candidate->path));
        $source=app(MarketingProductionCompiler::class)->sourcePath(['source'=>'private','asset_id'=>$candidate->id,'path'=>$candidate->path,'mime'=>'video/mp4','sha256'=>$sha]);
        DB::transaction(function()use($p,$candidate,$sha,$source,$disk){
            MarketingProject::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $current=MarketingAsset::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if(!$current->authorized)throw ValidationException::withMessages(['generation'=>'Saved material permission changed.']);
            if($p->assets()->where('authorized',true)->where('mime','video/mp4')->exists())return;
            $path='marketing/'.$p->id.'/reused-'.$sha.'.mp4';$disk->makeDirectory('marketing/'.$p->id);
            if(!copy($source,$disk->path($path))||!hash_equals($sha,hash_file('sha256',$disk->path($path))))throw ValidationException::withMessages(['generation'=>'The saved clip changed.']);
            MarketingAsset::create(['project_id'=>$p->id,'name'=>mb_substr('Reused authorized illustration · '.$candidate->name,0,180),'path'=>$path,'mime'=>'video/mp4','size'=>$disk->size($path),'authorized'=>true]);
        });
    }

    public function reserve(string $runId,string $project): void
    {
        if(!$this->enabled($project))throw ValidationException::withMessages(['generation'=>'This project has no current creative-test authorization.']);
        Cache::lock('marketing-creative-budget-'.$project,10)->block(3,function()use($runId,$project){
            $r=MarketingAiRun::where('project_id',$project)->findOrFail($runId);
            $q=MarketingAiRun::where('project_id',$project)->where('reserved_usd','>',0);
            $cap=(float)config('marketing.creative.budget_usd',0);$max=(int)config('marketing.creative.max_calls',0);
            $grant=config('marketing.creative.authorization');
            if($grant){
                // A new explicit local revision-test authorization never clears earlier usage.
                if(!\Illuminate\Support\Str::isUuid($grant)||$r->action!=='revise'||$cap>0.04||$max>2)throw ValidationException::withMessages(['generation'=>'This authorization only covers the bounded revision retest.']);
                $q->where('usage->creative_authorization->id',$grant);
                $previous=(clone $q)->first();
                if($previous && (($previous->usage['creative_authorization']['budget_usd']??null)!==number_format($cap,2,'.','')||($previous->usage['creative_authorization']['max_calls']??null)!==$max))throw ValidationException::withMessages(['generation'=>'An existing authorization cannot be expanded or changed.']);
            }
            if($cap<=0||$cap>0.20||$max<1||$max>5||(float)$r->reserved_usd>0||$q->count()>=$max||(float)(clone $q)->sum('reserved_usd')+0.02>$cap+0.000001)throw ValidationException::withMessages(['generation'=>'The separate creative-test budget or call limit is exhausted.']);
            $updates=['reserved_usd'=>0.02];
            if($grant)$updates['usage']=($r->usage??[])+['creative_authorization'=>['id'=>$grant,'budget_usd'=>number_format($cap,2,'.',''),'max_calls'=>$max]];
            $r->update($updates);
        });
    }
}
