<?php
namespace Tests\Feature;

use App\Models\{Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductEnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void {parent::setUp();config(['product_enquiries.enabled'=>true]);}

    private function fixture(): array
    {
        $p=Product::create(['title'=>'Enquiry item','handle'=>'enquiry-item','purchase_mode'=>'quote','price_display'=>'hide','status'=>'active','base_price'=>19900]);
        $v=$p->variants()->create(['sku'=>'ENQUIRY-TEST','title'=>'Stone / 500 ml','enabled'=>true,'price'=>19900,'stock'=>0,'options'=>['Colour'=>'Stone']]);
        return [$p,$v];
    }
    private function input($p,$v): array {return ['product_id'=>$p->id,'variant_id'=>$v->id,'request_key'=>(string)Str::uuid(),'name'=>'Enquiry Customer','email'=>'enquiry@example.test','requirements'=>'Please quote fifty units.'];}

    public function test_guest_submission_is_persistent_idempotent_and_private(): void
    {
        [$p,$v]=$this->fixture();$data=$this->input($p,$v);
        $first=$this->post('/enquiries',$data)->assertRedirect();$url=$first->headers->get('Location');
        $this->assertDatabaseCount('product_enquiries',1);
        $this->post('/enquiries',$data)->assertRedirect($url);$this->assertDatabaseCount('product_enquiries',1);
        $this->get($url)->assertOk()->assertSee('Please quote fifty units.');
        $p->update(['title'=>'Changed title']);
        $this->assertSame('Enquiry item',DB::table('product_enquiries')->first()->product_title);
        $this->flushSession();$this->get($url)->assertNotFound();
        $this->assertDatabaseCount('orders',0);$this->assertSame(0,$v->fresh()->stock);
    }

    public function test_wrong_variant_and_non_quote_products_are_rejected(): void
    {
        [$p,$v]=$this->fixture();$other=Product::create(['handle'=>'other','title'=>'Other','status'=>'active','base_price'=>1]);
        $data=$this->input($p,$v);$data['product_id']=$other->id;
        $this->post('/enquiries',$data)->assertSessionHasErrors('product_id',null,'enquiry');
        $otherV=$other->variants()->create(['sku'=>'OTHER','title'=>'Other','price'=>1,'enabled'=>true]);
        $data=$this->input($p,$otherV);$this->post('/enquiries',$data)->assertSessionHasErrors('variant_id',null,'enquiry');
        $this->assertDatabaseCount('product_enquiries',0);
    }

    public function test_admin_follow_up_requires_permissions_versions_and_close_reason(): void
    {
        [$p,$v]=$this->fixture();$this->post('/enquiries',$this->input($p,$v));
        $id=DB::table('product_enquiries')->value('id');$admin=User::factory()->create(['is_admin'=>true]);
        $this->getJson('/admin/api/enquiries')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson('/admin/api/enquiries')->assertUnauthorized();
        $this->actingAs($admin)->getJson('/admin/api/enquiries')->assertOk()->assertJsonPath('counts.new',1);
        $this->patchJson('/admin/api/enquiries/'.$id,['version'=>1,'status'=>'following_up','assigned_to'=>$admin->id])->assertOk()->assertJsonPath('data.version',2);
        $this->patchJson('/admin/api/enquiries/'.$id,['version'=>1,'status'=>'closed','outcome'=>'won','assigned_to'=>$admin->id])->assertStatus(409);
        $this->patchJson('/admin/api/enquiries/'.$id,['version'=>2,'status'=>'closed','assigned_to'=>$admin->id])->assertUnprocessable();
        $this->postJson('/admin/api/enquiries/'.$id.'/notes',['content'=>'Team-only note'])->assertCreated();
        $this->getJson('/admin/api/enquiries/'.$id)->assertOk()->assertSee('Team-only note');
        $this->get('/enquiries/'.$id)->assertOk()->assertDontSee('Team-only note');
        $this->patchJson('/admin/api/enquiries/'.$id,['version'=>3,'status'=>'closed','outcome'=>'won','assigned_to'=>$admin->id])->assertOk();
    }

    public function test_replays_cannot_change_details_and_guest_email_does_not_claim_an_account(): void
    {
        [$p,$v]=$this->fixture(); $customer=User::factory()->create(['email'=>'enquiry@example.test']);
        $data=$this->input($p,$v); $this->post('/enquiries',$data)->assertRedirect();
        $this->assertNull(DB::table('product_enquiries')->value('customer_user_id'));
        $data['requirements']='Changed after submission';
        $this->post('/enquiries',$data)->assertSessionHasErrors('request_key',null,'enquiry');
        $this->assertDatabaseCount('product_enquiries',1);
        $this->assertSame('Please quote fifty units.',DB::table('product_enquiries')->value('requirements'));
    }

    public function test_disabled_feature_and_unavailable_variants_reject_writes(): void
    {
        [$p,$v]=$this->fixture(); $data=$this->input($p,$v);
        config(['product_enquiries.enabled'=>false]);
        $this->post('/enquiries',$data)->assertStatus(503);
        config(['product_enquiries.enabled'=>true]); $v->update(['enabled'=>false]);
        $this->post('/enquiries',$data)->assertSessionHasErrors('variant_id',null,'enquiry');
        $v->update(['enabled'=>true]); $p->update(['status'=>'draft']);
        $this->post('/enquiries',$data)->assertSessionHasErrors('product_id',null,'enquiry');
        $this->assertDatabaseCount('product_enquiries',0);
    }

    public function test_authenticated_customer_receipt_survives_session_rotation_and_admin_search_is_real(): void
    {
        [$p,$v]=$this->fixture(); $customer=User::factory()->create();
        $response=$this->actingAs($customer,'customer')->post('/enquiries',$this->input($p,$v))->assertRedirect();
        $url=$response->headers->get('Location'); $this->flushSession();
        $this->get($url)->assertOk(); $this->assertSame($customer->id,DB::table('product_enquiries')->value('customer_user_id'));
        $admin=User::factory()->create(['is_admin'=>true]); $nonAdmin=User::factory()->create(['is_admin'=>false]);
        $this->actingAs($admin)->getJson('/admin/api/enquiries?search=missing')->assertOk()->assertJsonPath('pagination.total',0)->assertJsonPath('counts.all',1);
        $this->getJson('/admin/api/enquiries?status=new&search=Enquiry')->assertOk()->assertJsonPath('pagination.total',1);
        $id=DB::table('product_enquiries')->value('id');
        $this->patchJson('/admin/api/enquiries/'.$id,['version'=>1,'status'=>'following_up','assigned_to'=>$nonAdmin->id])->assertUnprocessable();
    }
}
