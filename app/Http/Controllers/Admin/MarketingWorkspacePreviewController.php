<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingProject;
use App\Services\Marketing\MarketingWorkspaceView;
use Illuminate\Http\Request;

class MarketingWorkspacePreviewController extends Controller
{
    public function show(Request $request, MarketingWorkspaceView $view)
    {
        abort_unless($request->user()?->is_super_admin, 403);
        $input = $request->validate(['project' => 'nullable|uuid', 'screen' => 'nullable|string', 'sample'=>'nullable|in:assistant,material-review']);
        $screen = $input['screen'] ?? 'source';
        abort_unless(in_array($screen, ['source', 'preferences', 'guided', 'brief', 'concepts', 'materials', 'confirmation', 'finished'], true), 404);
        $project = isset($input['project']) ? MarketingProject::where('owner_id', $request->user()->id)->findOrFail($input['project']) : null;

        $data=$view->data($project,$screen);
        $data['assistantSample']=($input['sample']??null)==='assistant';
        $data['materialReviewSample']=($input['sample']??null)==='material-review';
        if($data['materialReviewSample'])abort_unless($screen==='materials',404);
        if($data['assistantSample'])abort_unless(in_array($screen,['guided','concepts'],true),404);
        return response()->view('marketing.workspace.preview', $data)->header('Cache-Control', 'private, no-store');
    }
}
