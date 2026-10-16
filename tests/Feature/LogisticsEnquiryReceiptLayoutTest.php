<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsEnquiryReceiptLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_has_shared_shell_hero_and_contact_details_without_creating_enquiries(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $response=$this->get('/admin/website-design/acceptance/sample/enquiry-receipt?theme=logistics');
        $response->assertOk()->assertSee('data-logistics-enquiry-receipt',false)->assertSee('data-shell-header',false)->assertSee('lgr-hero',false)->assertSee('Contact details')->assertSee('Your requirements')->assertSee('lgr-footer',false)->assertSee('enquiries/',false);
        $this->assertDatabaseCount('product_enquiries',0);
    }
}
