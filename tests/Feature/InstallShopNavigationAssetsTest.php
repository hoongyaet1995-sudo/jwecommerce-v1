<?php

namespace Tests\Feature;

use App\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InstallShopNavigationAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_installs_managed_asset_idempotently(): void
    {
        Storage::fake('public');
        $this->artisan('cms:install-shop-navigation-assets')->assertSuccessful();
        $this->artisan('cms:install-shop-navigation-assets')->assertSuccessful();
        $this->assertSame(6,CmsMediaAsset::count());
        $asset=CmsMediaAsset::first();
        Storage::disk('public')->assertExists($asset->path);
        $photo=json_decode(file_get_contents(resource_path('cms/shop-navigation-assets.json')),true)[0];
        $this->assertSame(hash_file('sha256',public_path('images/cms-samples/'.$photo['file'])),$asset->checksum);
    }

    public function test_preserves_conflicting_existing_asset(): void
    {
        Storage::fake('public');
        $this->artisan('cms:install-shop-navigation-assets')->assertSuccessful();
        CmsMediaAsset::query()->update(['checksum'=>'employee-owned']);
        $this->artisan('cms:install-shop-navigation-assets')->assertFailed();
        $this->assertSame('employee-owned',CmsMediaAsset::first()->checksum);
    }
}
