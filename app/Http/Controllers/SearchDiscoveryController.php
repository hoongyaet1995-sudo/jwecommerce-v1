<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use App\Models\SearchEntry;
use App\Services\Search\SearchMetadata;
use App\Services\Search\SearchSettings;
use Illuminate\Support\Facades\Cache;

class SearchDiscoveryController extends Controller
{
    public function sitemap(SearchSettings $settings, SearchMetadata $metadata)
    {
        $xml = Cache::remember('search:sitemap', 60, function () use ($settings, $metadata) {
            $urls = [];
            $add = function ($path, $date = null) use (&$urls, $settings) {
                $loc = htmlspecialchars($settings->origin().$path, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $urls[$path] = '<url><loc>'.$loc.'</loc>'.($date ? '<lastmod>'.$date->toAtomString().'</lastmod>' : '').'</url>';
            };
            if (! $settings->read()['preview']) {
                $add('/shop');
                $excluded = SearchEntry::where('excluded', true)->get()->map(fn ($e) => $e->kind.':'.$e->entity_id.':'.$e->stage)->flip();
                foreach (['product' => Product::where('status', 'active'), 'category' => Category::where('is_active', true), 'collection' => Collection::where('is_active', true), 'page' => Page::where('status', 'published')->whereHas('publishedDocument')->with('publishedDocument')] as $kind => $query) {
                    $query->chunkById(200, function ($rows) use ($excluded, $kind, $metadata, $add) {
                        foreach ($rows as $row) {
                            $stage = $kind === 'page' ? 'published' : 'live';
                            if (! $excluded->has($kind.':'.$row->id.':'.$stage)) {
                                $add($metadata->path($kind, $row->id), $kind === 'page' ? $row->publishedDocument->updated_at : $row->updated_at);
                            }
                        }
                    });
                }
            }

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.implode('', $urls).'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(SearchSettings $settings)
    {
        return response("User-agent: *\nDisallow:\nSitemap: ".$settings->origin()."/sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
