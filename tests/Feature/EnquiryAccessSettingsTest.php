<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryAccessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_round_trip_validation_and_public_privacy(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
        $payload = ['version' => 0, 'faq' => ['isEnabled' => true, 'retrievalDepth' => 'strict', 'categories' => [['id' => 'others', 'label' => 'Others']], 'items' => []], 'enquiryAccess' => ['isEnabled' => true, 'websiteFaq' => true, 'productFaq' => false, 'fields' => ['quantity' => 'optional', 'location' => 'off']]];
        $this->putJson('/admin/api/support/faq', $payload)->assertOk()->assertJsonPath('enquiryAccess.isEnabled', true)->assertJsonPath('enquiryAccess.fields.quantity', 'optional')->assertJsonPath('enquiryAccess.fields.location', 'off');
        $this->getJson('/admin/api/support/faq')->assertOk()->assertJsonPath('enquiryAccess.productFaq', false)->assertJsonPath('enquiryAccess.fields.product', 'required');
        $this->getJson('/support/faq')->assertOk()->assertJsonMissingPath('enquiryAccess');
        $payload['version'] = 1;
        $payload['enquiryAccess']['fields']['quantity'] = 'anything';
        $this->putJson('/admin/api/support/faq', $payload)->assertUnprocessable();
        $payload['enquiryAccess']['fields'] = ['secret' => 'required'];
        $this->putJson('/admin/api/support/faq', $payload)->assertUnprocessable();
    }
}
