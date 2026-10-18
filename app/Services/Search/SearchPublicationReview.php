<?php

namespace App\Services\Search;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Domain\Cms\RuntimeAdapterRegistry;
use App\Models\Page;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SearchPublicationReview
{
    public function inspect(Page $page): array
    {
        $page = $page->fresh()->load('draftDocument');
        $draft = $page->draftDocument;
        abort_unless($draft, 404);
        $payload = app(PageRuntimePayloadFactory::class)->forDraft($page);
        $checks = [];
        $blocked = false;
        try {
            app(PageDocumentContract::class)->assertPublishable($draft->document, app(RuntimeAdapterRegistry::class)->factsFor($draft->document));
            $checks[] = ['title' => 'Page contract & internal references', 'passed' => true];
        } catch (ValidationException $e) {
            $blocked = true;
            $checks[] = ['title' => 'Page contract & internal references', 'passed' => false, 'details' => $e->errors()];
        }
        try {
            $html = app(CmsServerRenderer::class)->render($payload);
            $text = trim(strip_tags(preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/si', '', $html)));
            $readable = mb_strlen($text) > 10;
            $blocked = $blocked || ! $readable;
            $checks[] = ['title' => 'Core content in initial HTML', 'passed' => $readable];
            $broken=$this->brokenInternalLinks($html,$draft->slug);
            $blocked=$blocked||count($broken)>0;
            $checks[]=['title'=>'Known internal destinations','passed'=>count($broken)===0,'details'=>$broken];
        } catch (\Throwable) {
            $blocked = true;
            $checks[] = ['title' => 'Core content in initial HTML', 'passed' => false];
        }
        $checks[] = ['title' => 'AI completion is optional', 'passed' => true];

        return ['pageId' => $page->id, 'lockVersion' => $draft->lock_version, 'contentHash' => $draft->content_hash, 'blocked' => $blocked, 'checks' => $checks, 'metadata' => app(SearchMetadata::class)->for('page', $page->id)];
    }

    private function brokenInternalLinks(string $html,string $draftSlug):array
    {
        preg_match_all('/\bhref="([^"]*)"/',$html,$matches);$broken=[];$origin=app(SearchSettings::class)->origin();
        foreach(array_slice(array_unique($matches[1]),0,200) as $encoded){
            $url=html_entity_decode($encoded,ENT_QUOTES|ENT_HTML5,'UTF-8');
            if($url===''||str_starts_with($url,'#')||preg_match('/^(mailto|tel):/i',$url))continue;
            $parts=parse_url($url);if(!$parts)continue;
            if(isset($parts['host'])&&$parts['host']!==parse_url($origin,PHP_URL_HOST))continue;
            $path=$parts['path']??'/';if(!str_starts_with($path,'/'))$path='/'.$path;
            if($path==='/pages/'.$draftSlug||in_array($path,['/','/shop'],true))continue;
            $valid=true;
            if(preg_match('~^/products/([^/]+)$~',$path,$p))$valid=\App\Models\Product::where('handle',$p[1])->where('status','active')->exists();
            elseif(preg_match('~^/categories/([^/]+)$~',$path,$p))$valid=\App\Models\Category::where('slug',$p[1])->where('is_active',true)->exists();
            elseif(preg_match('~^/collections/([^/]+)$~',$path,$p))$valid=\App\Models\Collection::where('slug',$p[1])->where('is_active',true)->exists();
            elseif(preg_match('~^/pages/([^/]+)$~',$path,$p))$valid=\App\Models\PageDocument::where('stage','published')->where('slug',$p[1])->whereHas('page',fn($q)=>$q->where('status','published'))->exists();
            elseif(str_contains(rawurldecode($path),'..'))$valid=false;
            elseif(is_file(public_path(ltrim($path,'/'))))$valid=true;
            else {try{app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path,'GET'));}catch(\Throwable){$valid=false;}}
            if(!$valid)$broken[]=$path;
        }
        return $broken;
    }

    public function confirm(Page $page, int $version, int $actor): array
    {
        $result = $this->inspect($page);
        abort_if($result['lockVersion'] !== $version, 409, 'Draft changed. Run publication review again.');
        abort_if($result['blocked'], 422, 'Resolve the publication blockers first.');
        $id = DB::table('search_publication_reviews')->insertGetId(['page_id' => $page->id, 'lock_version' => $version, 'content_hash' => $result['contentHash'], 'actor_id' => $actor, 'checks' => json_encode($result['checks'], JSON_THROW_ON_ERROR), 'created_at' => now()]);

        return ['token' => Crypt::encryptString(json_encode(['id' => $id, 'page' => $page->id, 'version' => $version, 'hash' => $result['contentHash'], 'actor' => $actor, 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR))];
    }

    public function assertToken(Page $page, int $version, ?string $token, int $actor): void
    {
        try {
            $data = json_decode(Crypt::decryptString($token ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(422, 'Complete the page publication review first.');
        }
        $draft = $page->draftDocument()->firstOrFail();
        abort_if(($data['page'] ?? null) !== $page->id || ($data['actor'] ?? null) !== $actor || ($data['version'] ?? null) !== $version || ($data['expires'] ?? 0) < now()->timestamp || ($data['hash'] ?? null) !== $draft->content_hash, 409, 'Publication review expired or the draft changed.');
    }
}
