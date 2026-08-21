<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Models\SocialLink;
use App\Services\Admin\CustomerDirectoryDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class OperationsController extends Controller {
    public function customers(Request $request, CustomerDirectoryDataService $customerDirectory): View { $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]); return view('admin.customers', ['customers' => $customerDirectory->customers($filters['search'] ?? null), 'filters' => $filters]); }
    public function settings(): View { return view('admin.settings', ['settings' => StoreSetting::query()->orderBy('key')->get()]); }
    public function storeSetting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['nullable', 'alpha_dash', 'required_without:settings'],
            'value' => ['nullable', 'required_with:key'],
            'settings' => ['nullable', 'array', 'required_without:key'],
            'settings.*' => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($data['settings'])) {
            $aliases = [
                'shipping_threshold' => 'free_shipping_threshold_cents',
                'tax_rate' => 'tax_rate_percent',
            ];
            foreach ($data['settings'] as $key => $value) {
                StoreSetting::query()->updateOrCreate(['key' => $aliases[$key] ?? $key], ['value' => ['data' => $value]]);
            }
        } else {
            StoreSetting::query()->updateOrCreate(['key' => $data['key']], ['value' => ['data' => $data['value']]]);
        }

        return redirect()->route('admin.settings.index');
    }
    public function socialLinks(): View { return view('admin.social-links', ['links' => SocialLink::query()->orderBy('platform')->get()]); }
    public function storeSocialLink(Request $request): RedirectResponse { SocialLink::query()->updateOrCreate(['platform' => $request->validate(['platform'=>['required','alpha_dash'], 'url'=>['required','url']])['platform']], ['url'=>$request->string('url')->value(), 'is_active'=>true]); return redirect()->route('admin.social-links.index'); }
    public function updateSocialLink(Request $request, SocialLink $socialLink): RedirectResponse { $socialLink->update($request->validate(['platform'=>['required','alpha_dash','unique:social_links,platform,'.$socialLink->id], 'url'=>['required','url'], 'is_active'=>['required','boolean']])); return redirect()->route('admin.social-links.index'); }
    public function destroySocialLink(SocialLink $socialLink): RedirectResponse { $socialLink->delete(); return redirect()->route('admin.social-links.index'); }
}
