<?php

namespace App\Http\Controllers\Admin;

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

        $bootstrap = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => null,
            ],
            'csrfToken' => csrf_token(),
            'logoutUrl' => route('logout'),
            'dashboardUrl' => route('admin.api.dashboard'),
            'customersUrl' => route('admin.api.customers'),
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
