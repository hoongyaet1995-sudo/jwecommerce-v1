<?php
namespace App\Http\Controllers\Admin;

use App\Domain\Cms\{HomepageSelection,PageRuntimePayloadFactory,VisiblePageRuntimePayload,ShellDesign};
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\ViteManifestEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ShellDesignPreviewController extends Controller
{
    public function __invoke(Request $request)
    {
        $input=$request->validate([
            ...ShellDesign::rules(),
            'shellDesign'=>array_merge(['required'],ShellDesign::rules()['shellDesign']),
            'headerPresentation'=>['required','array:mode,tone'],
            'headerPresentation.mode'=>['required',Rule::in(['normal','transparent','translucent'])],
            'headerPresentation.tone'=>['required',Rule::in(['light','dark'])],
        ]);
        abort_unless(ShellDesign::safeAction((string)($input['shellDesign']['ctaUrl']??'')),422);
        $id=app(HomepageSelection::class)->id();
        abort_unless($id,422,'Set a CMS page as Home to preview your header and footer.');
        $page=Page::findOrFail($id);
        $payload=app(VisiblePageRuntimePayload::class)->filter(app(PageRuntimePayloadFactory::class)->forCanary($page));
        // Preview the proposed global settings even when Home has its own overrides.
        // These changes stay in memory; published and draft documents are untouched.
        $payload['page']['document']['shellDesign']=ShellDesign::resolve($input);
        $payload['page']['document']['headerPresentation']=$input['headerPresentation'];
        $entry=app(ViteManifestEntry::class)->resolve('src/public-page.tsx');
        return response()->view('storefront.cms-page-react',[
            'entry'=>$entry,'payload'=>$payload,
            'quickAddProducts'=>app(\App\Domain\Cms\ProductListingPresentation::class)->prepare($payload),
            'title'=>'Header & Footer preview','description'=>null,
        ],$entry?200:503)->header('Cache-Control','no-store');
    }
}
