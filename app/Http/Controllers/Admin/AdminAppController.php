<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reviews\ReviewPermission;
use App\Http\Controllers\Controller;
use App\Support\ViteManifestEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminAppController extends Controller
{
    public function __construct(private readonly ViteManifestEntry $manifest) {}

    public function __invoke(Request $request): Response
    {
        $entry = $this->manifest->resolve('src/main.tsx');
        $user = $request->user();
        $reviewPermissions = $user->is_super_admin
            ? ReviewPermission::ALL
            : collect(ReviewPermission::ALL)
                ->filter(fn (string $permission): bool => $user->permissions()->where('permission', $permission)->exists())
                ->values();

        $bootstrap = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => null,
                'isSuperAdmin' => $user->is_super_admin,
                'reviewPermissions' => $reviewPermissions,
            ],
            'reviewPermissions' => $reviewPermissions,
            'csrfToken' => csrf_token(),
            'logoutUrl' => route('logout'),
            'dashboardUrl' => route('admin.api.dashboard'),
            'customersUrl' => route('admin.api.customers'),
            'reviewsUrl' => route('admin.api.reviews.index'),
            'reviewBulkModerateUrl' => route('admin.api.reviews.bulk-moderate'),
            'staffReviewPermissionsUrl' => route('admin.api.staff.review-permissions.index'),
            'pagesUrl' => route('admin.api.pages.index'),
            'mediaAssetsUrl' => route('admin.api.media-assets.store'),
            'catalogUrl' => route('admin.api.catalog.index'),
            'menusUrl' => route('admin.api.menus.index'),
            'ordersUrl' => route('admin.api.orders.index'),
            'couponsUrl' => route('admin.api.coupons.index'),
            'commerceSettingsUrl' => route('admin.api.commerce-settings.show'),
        ];

        return response()->view('admin-app', [
            'bootstrap' => $bootstrap,
            'entry' => $entry,
        ], $entry === null ? 503 : 200);
    }
}
