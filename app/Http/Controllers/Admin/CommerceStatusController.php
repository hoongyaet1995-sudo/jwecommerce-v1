<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CommerceStatusRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommerceStatusController extends Controller
{
    public function index(Request $request, string $scope, CommerceStatusRegistry $registry)
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless(in_array($scope, ['order', 'shipment'], true), 404);

        return response()->json(['data' => $registry->collection($scope)]);
    }

    public function update(Request $request, string $scope, string $code, CommerceStatusRegistry $registry)
    {
        abort_unless($request->user()?->is_super_admin, 403);
        abort_unless(in_array($scope, ['order', 'shipment'], true), 404);
        if (array_diff(array_keys($request->all()), ['name', 'description'])) {
            throw ValidationException::withMessages(['status' => 'Only the status name and description can be changed.']);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['present', 'nullable', 'string', 'max:500']]);
        abort_unless(DB::table('commerce_status_definitions')->where('scope', $scope)->where('code', $code)->exists(), 404);
        DB::table('commerce_status_definitions')->where('scope', $scope)->where('code', $code)->update(['name' => trim($data['name']), 'description' => $data['description'] ?? '', 'updated_at' => now()]);

        return response()->json(['data' => $registry->collection($scope)]);
    }
}
