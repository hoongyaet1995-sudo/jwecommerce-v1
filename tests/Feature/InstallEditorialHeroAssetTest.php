<?php

namespace Tests\Feature;

use App\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InstallEditorialHeroAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_installs_managed_asset_idempotently(): void
    {
        Storage::fake('public');
        $this->artisan('cms:install-editorial-hero-asset')->assertSuccessful();
        $this->artisan('cms:install-editorial-hero-asset')->assertSuccessful();
        $this->assertSame(1,CmsMediaAsset::count());
        $asset=CmsMediaAsset::first();
        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame(hash_file('sha256',public_path('images/cms-samples/home-editorial-hero.png')),$asset->checksum);
    }

    public function test_preserves_conflicting_existing_asset(): void
    {
        Storage::fake('public');
        $this->artisan('cms:install-editorial-hero-asset')->assertSuccessful();
        CmsMediaAsset::query()->update(['checksum'=>'employee-owned']);
        $this->artisan('cms:install-editorial-hero-asset')->assertFailed();
        $this->assertSame('employee-owned',CmsMediaAsset::first()->checksum);
    }
}
