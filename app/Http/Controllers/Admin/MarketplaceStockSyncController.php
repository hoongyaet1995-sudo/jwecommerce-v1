<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceStockSyncTaskService;
use App\Services\Marketplace\MarketplaceStockSyncDispatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MarketplaceStockSyncController extends Controller
{
    public function stage(Request $request, int $store, MarketplaceStockSyncTaskService $tasks)
    {
        if (array_diff(array_keys($request->all()), ['inventory_balance_id', 'operation_key', '_token'])) {
            throw ValidationException::withMessages(['stock_sync'=>'Unsupported stock synchronization fields.']);
        }
        $data=$request->validate([
            'inventory_balance_id'=>['required','integer','min:1'],
            'operation_key'=>['required','string','max:128'],
        ]);
        return response()->json($tasks->stage($store, (int) $data['inventory_balance_id'], $data['operation_key'], $request->user()))
            ->header('Cache-Control','no-store');
    }
    public function dispatch(Request $request, int $task, MarketplaceStockSyncDispatchService $dispatcher)
    {
        if (array_diff(array_keys($request->all()), ['_token'])) {
            throw ValidationException::withMessages(['stock_sync'=>'Stock dispatch accepts no request fields.']);
        }
        return response()->json($dispatcher->dispatch($task))->header('Cache-Control','no-store');
    }
    public function show(int $task, MarketplaceStockSyncTaskService $tasks)
    {
        return response()->json($tasks->review($task))->header('Cache-Control','no-store');
    }
}
