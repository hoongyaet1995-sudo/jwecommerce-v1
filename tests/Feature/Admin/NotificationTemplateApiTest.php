<?php

namespace Tests\Feature\Admin;

use App\Domain\Notifications\NotificationPermission;
use App\Jobs\SendOrderNotificationJob;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_routes_require_authentication_and_an_exact_staff_permission(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        $this->getJson('/admin/api/notification-templates')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/admin/api/notification-templates')->assertForbidden();

        $staff->permissions()->create(['permission' => NotificationPermission::TEMPLATES_VIEW.'.extra']);
        $this->actingAs($staff)->getJson('/admin/api/notification-templates')->assertForbidden();

        $staff->permissions()->create(['permission' => NotificationPermission::TEMPLATES_VIEW]);
        $this->actingAs($staff)->getJson('/admin/api/notification-templates')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    public function test_non_admin_cannot_use_notification_permission_and_super_admin_bypasses_permission_rows(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false, 'is_super_admin' => false]);
        $nonAdmin->permissions()->create(['permission' => NotificationPermission::TEMPLATES_VIEW]);

        $this->actingAs($nonAdmin)->getJson('/admin/api/notification-templates')->assertUnauthorized();
        $this->actingAs($this->superAdmin())->getJson('/admin/api/notification-templates')->assertOk();
    }

    public function test_template_collection_synchronizes_missing_defaults_without_overwriting_operator_edits(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/admin/api/notification-templates')->assertOk();
        $template = NotificationTemplate::query()->where('event_key', 'order_created')->firstOrFail();
        $template->update(['email_subject' => 'Operator-owned subject', 'version' => 7, 'updated_by' => $admin->id]);
        NotificationTemplate::query()->where('event_key', 'on_hold')->delete();

        $response = $this->actingAs($admin)->getJson('/admin/api/notification-templates')->assertOk();

        $response->assertJsonPath('data.0.eventKey', 'order_created');
        $this->assertDatabaseHas('notification_templates', [
            'event_key' => 'order_created',
            'email_subject' => 'Operator-owned subject',
            'version' => 7,
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('notification_templates', ['event_key' => 'on_hold', 'version' => 1]);
    }

    public function test_manage_permission_is_required_and_event_key_must_match_the_route(): void
    {
        app(NotificationTemplateDefaults::class)->synchronize();
        $viewer = $this->staffWith([NotificationPermission::TEMPLATES_VIEW]);
        $manager = $this->staffWith([NotificationPermission::TEMPLATES_MANAGE]);

        $this->actingAs($viewer)->putJson('/admin/api/notification-templates/order_created', [
            'eventKey' => 'order_created',
            'emailSubject' => 'Denied edit',
        ])->assertForbidden();

        $this->actingAs($manager)->putJson('/admin/api/notification-templates/order_created', [
            'eventKey' => 'shipped',
            'emailSubject' => 'Mismatched edit',
        ])->assertUnprocessable()->assertJsonValidationErrors('eventKey');
    }

    public function test_unknown_template_event_returns_a_stable_not_found_response(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->putJson('/admin/api/notification-templates/not-a-real-event', [
            'emailSubject' => 'Unknown event',
        ])->assertNotFound();

        $this->actingAs($admin)->postJson('/admin/api/notification-templates/not-a-real-event/test', [
            'recipientEmail' => 'qa@example.com',
        ])->assertNotFound();
    }

    public function test_enabled_template_rejects_unavailable_variables_and_unsafe_cta_urls(): void
    {
        app(NotificationTemplateDefaults::class)->synchronize();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->putJson('/admin/api/notification-templates/payment_proof_rejected', [
            'emailEnabled' => true,
            'emailCtaUrl' => '{payment_proof_upload_url}',
        ])->assertUnprocessable()->assertJsonValidationErrors('emailCtaUrl');

        $this->actingAs($admin)->putJson('/admin/api/notification-templates/payment_proof_rejected', [
            'emailEnabled' => true,
            'emailCtaUrl' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors('emailCtaUrl');

        $this->assertDatabaseHas('notification_templates', [
            'event_key' => 'payment_proof_rejected',
            'email_cta_url' => '{order_url}',
            'version' => 1,
        ]);
    }

    public function test_template_update_uses_optimistic_versioning_and_records_the_actor(): void
    {
        app(NotificationTemplateDefaults::class)->synchronize();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->putJson('/admin/api/notification-templates/order_created', [
            'version' => 1,
            'emailSubject' => 'Saved subject for #{order_number}',
        ])->assertOk()
            ->assertJsonPath('data.emailSubject', 'Saved subject for #{order_number}')
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('notification_templates', [
            'event_key' => 'order_created',
            'version' => 2,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->putJson('/admin/api/notification-templates/order_created', [
            'version' => 1,
            'emailSubject' => 'Stale overwrite',
        ])->assertConflict();
    }

    public function test_explicit_reset_restores_defaults_in_place_and_audits_a_new_version(): void
    {
        app(NotificationTemplateDefaults::class)->synchronize();
        $admin = $this->superAdmin();
        $template = NotificationTemplate::query()->where('event_key', 'order_created')->firstOrFail();
        $templateId = $template->id;
        $template->update(['email_subject' => 'Operator edit', 'version' => 4]);

        $this->actingAs($admin)->postJson('/admin/api/notification-templates/reset')
            ->assertOk()
            ->assertJsonPath('data.0.eventKey', 'order_created')
            ->assertJsonPath('data.0.version', 5);

        $reset = NotificationTemplate::query()->where('event_key', 'order_created')->firstOrFail();
        $this->assertSame($templateId, $reset->id);
        $this->assertSame('[{shop_name}] Order #{order_number} Received - Please Complete Payment', $reset->email_subject);
        $this->assertSame(5, $reset->version);
        $this->assertSame($admin->id, $reset->updated_by);
    }

    public function test_email_test_is_permissioned_queued_rate_limited_and_excludes_private_fields_from_its_dto(): void
    {
        Queue::fake();
        app(NotificationTemplateDefaults::class)->synchronize();
        $template = NotificationTemplate::query()->where('event_key', 'order_created')->firstOrFail();
        $unauthorised = $this->staffWith([NotificationPermission::TEMPLATES_VIEW]);
        $tester = $this->staffWith([NotificationPermission::TEST_SEND]);

        $this->actingAs($unauthorised)->postJson('/admin/api/notification-templates/order_created/test', [
            'recipientEmail' => 'qa@example.com',
        ])->assertForbidden();

        $response = $this->actingAs($tester)->postJson('/admin/api/notification-templates/order_created/test', [
            'recipientEmail' => 'qa@example.com',
        ])->assertCreated()
            ->assertJsonPath('data.eventKey', 'order_created')
            ->assertJsonPath('data.source', NotificationDispatch::SOURCE_TEST)
            ->assertJsonPath('data.state', NotificationDispatch::STATE_QUEUED);

        $payload = $response->json('data');
        $this->assertArrayNotHasKey('recipientEmail', $payload);
        $this->assertArrayNotHasKey('body', $payload);
        $this->assertArrayNotHasKey('providerMessageId', $payload);
        $this->assertDatabaseHas('notification_dispatches', [
            'notification_template_id' => $template->id,
            'order_id' => null,
            'recipient_email' => 'qa@example.com',
            'source' => NotificationDispatch::SOURCE_TEST,
            'requested_by' => $tester->id,
        ]);
        Queue::assertPushed(SendOrderNotificationJob::class, 1);

        for ($request = 2; $request <= 6; $request++) {
            $this->actingAs($tester)->postJson('/admin/api/notification-templates/order_created/test', [
                'recipientEmail' => "qa{$request}@example.com",
            ])->assertCreated();
        }

        $this->actingAs($tester)->postJson('/admin/api/notification-templates/order_created/test', [
            'recipientEmail' => 'rate-limited@example.com',
        ])->assertTooManyRequests();
    }

    public function test_admin_bootstrap_exposes_only_effective_notification_permissions_and_safe_base_urls(): void
    {
        $staff = $this->staffWith([
            NotificationPermission::TEMPLATES_VIEW,
            NotificationPermission::DELIVERIES_VIEW,
        ]);

        $html = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();
        preg_match('/window\.__JW_ADMIN__\s*=\s*(\{.*\});/sU', $html, $matches);
        $bootstrap = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(route('admin.api.notification-templates.index'), $bootstrap['notificationTemplatesUrl']);
        $this->assertSame(url('/admin/api/orders'), $bootstrap['orderNotificationsUrl']);
        $this->assertSame([
            NotificationPermission::TEMPLATES_VIEW,
            NotificationPermission::DELIVERIES_VIEW,
        ], $bootstrap['notificationPermissions']);
        $this->assertSame($bootstrap['notificationPermissions'], $bootstrap['user']['notificationPermissions']);
        $this->assertArrayNotHasKey('smtp', $bootstrap);
        $this->assertArrayNotHasKey('notificationTemplates', $bootstrap);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    /** @param list<string> $permissions */
    private function staffWith(array $permissions): User
    {
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        foreach ($permissions as $permission) {
            $staff->permissions()->create(['permission' => $permission]);
        }

        return $staff;
    }
}
