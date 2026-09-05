<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopBrandingUploadTest extends TestCase
{
    use RefreshDatabase;

    private function png(): string
    {
        $chunk = fn ($type, $body) => pack('N', strlen($body)).$type.$body.pack('N', crc32($type.$body));
        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 256, 256, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\xff\0\0\xff", 256), 256))).$chunk('IEND', '');
    }

    public function test_logo_and_ico_persist_and_are_shared_by_admin_and_storefront(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $image = 'data:image/png;base64,'.base64_encode($this->png());
        $saved = $this->actingAs($admin)->putJson('/admin/api/shop-branding', [
            'profile' => ['shopName' => 'Brand Test', 'tagline' => '', 'email' => null], 'logoPng' => $image, 'iconPng' => $image,
        ])->assertOk()->json('data');
        $this->getJson('/admin/api/shop-branding')->assertJsonPath('data.shopLogo', $saved['shopLogo']);
        $ico = Storage::disk('public')->get(substr($saved['faviconUrl'], 9));
        $this->assertSame(pack('vvv', 0, 1, 1), substr($ico, 0, 6));
        $this->assertSame($this->png(), substr($ico, 22));
        $this->get('/shop')->assertOk()->assertSee($saved['shopLogo'], false)->assertSee($saved['faviconUrl'], false);
        $this->get('/admin')->assertOk()->assertSee($saved['faviconUrl'], false);
        $this->putJson('/admin/api/shop-branding', ['profile' => ['shopName' => 'Renamed']])->assertOk()->assertJsonPath('data.shopLogo', $saved['shopLogo']);
    }

    public function test_invalid_upload_does_not_change_persisted_brand_and_requires_admin(): void
    {
        $this->putJson('/admin/api/shop-branding', [])->assertUnauthorized();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->putJson('/admin/api/shop-branding', [
            'profile' => ['shopName' => 'Bad'], 'logoPng' => 'data:image/png;base64,'.base64_encode('<script>bad</script>'), 'iconPng' => 'bad',
        ])->assertUnprocessable();
        $this->assertNull(StoreSetting::where('key', 'shop_profile')->first());
    }
}
