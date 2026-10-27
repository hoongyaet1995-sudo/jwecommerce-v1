<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\MarketingPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffMarketingPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $staff = User::query()
            ->where('is_admin', true)
            ->with(['permissions' => fn ($query) => $query->whereIn('permission', MarketingPermission::ALL)])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->payload($user));

        return response()->json(['data' => $staff]);
    }

    public function update(Request $request, User $staff): JsonResponse
    {
        $this->authorizeSuperAdmin($request);
        abort_unless($staff->is_admin && ! $staff->is_super_admin, 422, 'Only non-super-admin staff grants may be changed.');

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(MarketingPermission::ALL)],
        ]);
        $permissions = array_values(array_unique($data['permissions']));
        abort_if(count($permissions) > 0 && !in_array(MarketingPermission::VIEW, $permissions, true), 422, 'Viewing access is required.');
        abort_if(in_array(MarketingPermission::APPROVE_COST, $permissions, true) && !in_array(MarketingPermission::EDIT, $permissions, true), 422, 'Editing access is required to approve production costs.');

        DB::transaction(function () use ($staff, $permissions): void {
            User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $staff->permissions()->whereIn('permission', MarketingPermission::ALL)->delete();
            $staff->permissions()->createMany(array_map(fn (string $permission): array => ['permission' => $permission], $permissions));
        });

        return response()->json(['data' => $this->payload($staff->fresh('permissions'))]);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403);
    }

    private function payload(User $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'permissions' => $staff->permissions
                ->pluck('permission')
                ->filter(fn (string $permission): bool => in_array($permission, MarketingPermission::ALL, true))
                ->values(),
            'isSuperAdmin' => $staff->is_super_admin,
        ];
    }
}
