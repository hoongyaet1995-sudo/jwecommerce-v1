<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingResearchWorkflow;
use Illuminate\Http\Request;

class MarketingResearchController extends Controller
{
    public function store(Request $r, string $project, MarketingResearchWorkflow $flow)
    {
        $d = $r->validate(['version' => 'required|integer|min:1', 'request_id' => 'required|uuid']);
        $run = $flow->start($project, (int) $r->user()->id, $d['version'], $d['request_id']);
        return response()->json($flow->view($project, $r->user()->id, $run->id), 201);
    }

    public function show(Request $r, string $project, string $research, MarketingResearchWorkflow $flow)
    {
        return response()->json($flow->view($project, (int) $r->user()->id, $research));
    }

    public function interrupted(Request $r, string $project, string $research, MarketingResearchWorkflow $flow)
    {
        $d = $r->validate(['version' => 'required|integer|min:1', 'acknowledge' => 'required|accepted']);
        $flow->interrupted($project, (int) $r->user()->id, $d['version'], $research);
        return response()->json($flow->view($project, $r->user()->id, $research));
    }
}
