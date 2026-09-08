<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class Marketplace33PreviewTest extends TestCase
{
    use RefreshDatabase;
    public function test_preview_requires_admin_and_is_unavailable_outside_local(): void
    {
        $url='/admin/marketplace/inventory-preview-33';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertNotFound();
    }
}

