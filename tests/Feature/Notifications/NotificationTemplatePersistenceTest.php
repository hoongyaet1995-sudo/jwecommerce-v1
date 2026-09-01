<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Models\NotificationDispatch;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationTemplatePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_exposes_seven_automatic_email_events_and_dormant_on_hold(): void
    {
        $this->assertSame([
            'order_created', 'payment_verified', 'payment_proof_rejected',
            'ready_to_ship', 'shipped', 'delivered', 'cancelled',
        ], array_keys(NotificationEventCatalog::automaticEmailEvents()));
        $this->assertFalse(NotificationEventCatalog::get('on_hold')['automatic_email']);
    }

    public function test_defaults_are_idempotently_persisted_without_payment_reupload_claims(): void
    {
        app(NotificationTemplateDefaults::class)->synchronize();
        app(NotificationTemplateDefaults::class)->synchronize();

        $this->assertDatabaseCount('notification_templates', 8);
        $this->assertDatabaseMissing('notification_templates', [
            'email_cta_url' => '{payment_proof_upload_url}',
            'email_enabled' => true,
        ]);
    }

    public function test_snapshot_version_migration_defaults_existing_shape_rows_to_legacy_and_new_factory_rows_to_plain_text(): void
    {
        $this->assertTrue(Schema::hasColumns('notification_dispatches', [
            'snapshot_version',
            'queue_attempt',
            'queue_generation',
        ]));

        DB::table('notification_dispatches')->insert([
            'public_id' => '98f46a4a-5142-4520-b34c-bd3346507941',
            'channel' => 'email',
            'event_key' => 'order_created',
            'recipient_email' => 'legacy@example.test',
            'rendered_subject' => 'J&amp;T legacy',
            'rendered_body' => 'Legacy body',
            'variable_snapshot' => json_encode(['shop_name' => 'J&amp;T'], JSON_THROW_ON_ERROR),
            'state' => NotificationDispatch::STATE_SENT,
            'source' => NotificationDispatch::SOURCE_AUTOMATIC,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacy = NotificationDispatch::query()->where('public_id', '98f46a4a-5142-4520-b34c-bd3346507941')->sole();
        $current = NotificationDispatch::factory()->create();
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED, $legacy->snapshot_version);
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_PLAIN_TEXT, $current->snapshot_version);
    }
}
