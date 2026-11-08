<?php

namespace Tests\Feature;

use App\Models\MarketingAvatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingAvatarHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_hub_and_media_are_readonly_and_draft_post_is_owned(): void
    {
        Http::preventStrayRequests();
        $u = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($u);
        $this->get(route('marketing.assets'))->assertRedirect(route('marketing.preview.content'));
        $this->get(route('marketing.assets.media'))->assertRedirect(route('marketing.preview.content'));
        $this->get(route('marketing.avatars'))->assertOk()->assertSee('Add avatar template');
        $this->post(route('marketing.avatars.draft'), ['request_id' => (string) Str::uuid(), 'name' => 'My host', 'kind' => 'synthetic', 'description' => 'No photo yet'])->assertSessionHasNoErrors()->assertRedirect();
        $a = MarketingAvatar::firstOrFail();
        $this->assertSame('draft', $a->status);
        $this->post(route('marketing.avatars.write', [$a->id, 'reference']), ['file' => UploadedFile::fake()->image('portrait.png', 360, 640), 'consent' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('marketing.avatars.write', [$a->id, 'publish']), ['request_id' => (string) Str::uuid(), 'checked' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('marketing.avatars.image', [$a->id, 1, 'identity']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $version = \App\Models\MarketingAvatarVersion::where('avatar_id', $a->id)->firstOrFail();
        file_put_contents(\Illuminate\Support\Facades\Storage::disk('local')->path($version->references['identity']['path']), 'broken');
        $this->get(route('marketing.avatars'))->assertOk()->assertSee('Reference unavailable');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $this->get(route('marketing.avatars.detail', $a->id))->assertNotFound();
        $this->get(route('marketing.avatars.image', [$a->id, 1, 'identity']))->assertNotFound();
        $this->post(route('marketing.avatars.write', [$a->id, 'publish']), ['request_id' => (string) Str::uuid(), 'checked' => 1])->assertNotFound();
        Http::assertNothingSent();
    }
}
