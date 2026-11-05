<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ReportStaffController extends Controller
{
    public const PERMISSIONS=['reports.sales.view','reports.orders.view','reports.products.view','reports.customers.view','reports.shipping.view','reports.marketing.view','reports.financial.view'];

    public function index(Request $request)
    {
        abort_unless($request->user()->is_super_admin,403);
        return response()->json(['data'=>User::where('is_admin',true)->with('permissions')->orderBy('name')->get()->map(fn($user)=>$this->payload($user))])->header('Cache-Control','no-store');
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($request->user()->is_super_admin,403);
        abort_unless($staff->is_admin && !$staff->is_super_admin,422,'Only staff report access can be changed.');
        $v=$request->validate(['permissions'=>'present|array','permissions.*'=>['string',Rule::in(self::PERMISSIONS)],
            'expected_permissions'=>'present|array','expected_permissions.*'=>['string',Rule::in(self::PERMISSIONS)]]);
        DB::transaction(function () use ($staff,$v) {
            $locked=User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->is_admin && !$locked->is_super_admin,422,'Only staff report access can be changed.');
            $current=$locked->permissions()->whereIn('permission',self::PERMISSIONS)->pluck('permission')->unique()->sort()->values()->all();
            $expected=array_values(array_unique($v['expected_permissions'])); sort($expected);
            abort_unless($current===$expected,409,'Report access changed in another session. Refresh before saving.');
            $staff->permissions()->whereIn('permission',self::PERMISSIONS)->delete();
            $staff->permissions()->createMany(array_map(fn($permission)=>['permission'=>$permission],array_unique($v['permissions'])));
        });
        return response()->json(['data'=>$this->payload($staff->fresh('permissions'))])->header('Cache-Control','no-store');
    }

    private function payload(User $user): array
    {
        return ['id'=>$user->id,'name'=>$user->name,'is_super_admin'=>(bool)$user->is_super_admin,
            'permissions'=>$user->permissions->pluck('permission')->intersect(self::PERMISSIONS)->values()];
    }
}
