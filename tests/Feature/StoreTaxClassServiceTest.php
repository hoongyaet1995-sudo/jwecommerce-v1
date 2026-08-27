<?php

namespace Tests\Feature;

use App\Models\TaxClass;
use App\Services\StoreTaxClassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreTaxClassServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_one_class_atomically_deactivates_the_previous_class(): void
    {
        $sst = TaxClass::factory()->create(['name' => 'SST', 'rate_percent' => '8.0000', 'is_active' => false]);
        $gst = TaxClass::factory()->create(['name' => 'GST', 'rate_percent' => '6.0000', 'is_active' => false]);

        $service = app(StoreTaxClassService::class);
        $service->activate($sst);
        $service->activate($gst);

        $this->assertFalse($sst->fresh()->is_active);
        $this->assertNull($sst->fresh()->active_slot);
        $this->assertTrue($gst->fresh()->is_active);
        $this->assertSame(1, $gst->fresh()->active_slot);
        $this->assertSame(1, TaxClass::query()->where('is_active', true)->count());
    }

    public function test_active_class_cannot_be_deleted_but_inactive_class_can(): void
    {
        $active = TaxClass::factory()->create([
            'is_active' => true,
            'active_slot' => 1,
            'rate_percent' => '8.0000',
        ]);
        $inactive = TaxClass::factory()->create([
            'is_active' => false,
            'active_slot' => null,
            'rate_percent' => '6.0000',
        ]);

        $service = app(StoreTaxClassService::class);

        try {
            $service->delete($active);
            $this->fail('Deleting the active Tax Class must be rejected.');
        } catch (ValidationException) {
            $this->assertModelExists($active);
        }

        $service->delete($inactive);
        $this->assertModelMissing($inactive);
    }
}
