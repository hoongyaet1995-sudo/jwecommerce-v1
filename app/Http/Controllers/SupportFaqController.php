<?php

namespace App\Http\Controllers;

use App\Services\SupportFaqService;
use Illuminate\Http\Request;

class SupportFaqController extends Controller
{
    public function index(Request $request, SupportFaqService $service)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json($service->read())->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, SupportFaqService $service)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json($service->save($request->all()))->header('Cache-Control', 'no-store');
    }

    public function show(SupportFaqService $service)
    {
        return response()->json($service->publicFaq())->header('Cache-Control', 'no-store');
    }
}
