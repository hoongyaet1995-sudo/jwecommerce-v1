<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryReadinessService;

class InventoryReadinessController extends Controller
{
    public function __invoke(InventoryReadinessService $service)
    {
        return response()->json($service->inspect())->header('Cache-Control','no-store');
    }
}
