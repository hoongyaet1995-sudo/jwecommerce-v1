<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Models\NotificationDispatch;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

    public function test_snapshot_version_migrations_default_fresh_rows_and_new_factory_rows_to_plain_text(): void
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
            'rendered_subject' => 'J&T plain',
            'rendered_body' => 'Plain body',
            'variable_snapshot' => json_encode(['shop_name' => 'J&T'], JSON_THROW_ON_ERROR),
            'state' => NotificationDispatch::STATE_SENT,
            'source' => NotificationDispatch::SOURCE_AUTOMATIC,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaulted = NotificationDispatch::query()->where('public_id', '98f46a4a-5142-4520-b34c-bd3346507941')->sole();
        $current = NotificationDispatch::factory()->create();
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_PLAIN_TEXT, $defaulted->snapshot_version);
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_PLAIN_TEXT, $current->snapshot_version);
    }

    public function test_snapshot_v2_default_correction_accepts_an_empty_dispatch_table(): void
    {
        $this->assertDatabaseCount('notification_dispatches', 0);

        $this->snapshotCompatibilityCorrection()->up();

        DB::table('notification_dispatches')->insert([
            'public_id' => '42802e81-51b7-4aef-9d7f-a4bef785375d',
            'channel' => 'email',
            'event_key' => 'order_created',
            'recipient_email' => 'fresh@example.test',
            'rendered_subject' => 'J&T fresh',
            'rendered_body' => 'Fresh body',
            'variable_snapshot' => json_encode(['shop_name' => 'J&T'], JSON_THROW_ON_ERROR),
            'state' => NotificationDispatch::STATE_QUEUED,
            'source' => NotificationDispatch::SOURCE_AUTOMATIC,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            NotificationDispatch::SNAPSHOT_VERSION_PLAIN_TEXT,
            NotificationDispatch::query()->where('public_id', '42802e81-51b7-4aef-9d7f-a4bef785375d')->sole()->snapshot_version,
        );
    }

    public function test_snapshot_v2_default_correction_aborts_before_reclassifying_an_ambiguous_existing_row(): void
    {
        DB::table('notification_dispatches')->insert([
            'public_id' => '86a91eac-eea4-4ea0-8fb8-f9530f7cfbef',
            'channel' => 'email',
            'event_key' => 'order_created',
            'recipient_email' => 'ambiguous@example.test',
            'rendered_subject' => 'J&T plain from d271',
            'rendered_body' => 'Ambiguous unchanged body',
            'snapshot_version' => NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED,
            'variable_snapshot' => json_encode(['shop_name' => 'J&T'], JSON_THROW_ON_ERROR),
            'state' => NotificationDispatch::STATE_QUEUED,
            'source' => NotificationDispatch::SOURCE_AUTOMATIC,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = DB::table('notification_dispatches')
            ->where('public_id', '86a91eac-eea4-4ea0-8fb8-f9530f7cfbef')
            ->first();

        $error = null;
        try {
            $this->snapshotCompatibilityCorrection()->up();
        } catch (RuntimeException $caught) {
            $error = $caught;
        }
        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertStringContainsString('cannot be inferred', $error->getMessage());
        $this->assertStringContainsString('notification_dispatches must be empty', $error->getMessage());

        $after = DB::table('notification_dispatches')
            ->where('public_id', '86a91eac-eea4-4ea0-8fb8-f9530f7cfbef')
            ->first();
        $this->assertEquals($before, $after);
        $this->assertSame('J&T plain from d271', $after?->rendered_subject);
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED, (int) $after?->snapshot_version);
    }

    public function test_initial_lifecycle_migration_aborts_before_classifying_an_existing_unversioned_row(): void
    {
        $initial = $this->deliveryLifecycleMigration();
        $initial->down();

        try {
            DB::table('notification_dispatches')->insert([
                'public_id' => '6e315d83-ecfd-42f4-a48a-2111e709a9ce',
                'channel' => 'email',
                'event_key' => 'order_created',
                'recipient_email' => 'pre-version@example.test',
                'rendered_subject' => 'J&T unversioned from d271',
                'rendered_body' => 'Unversioned unchanged body',
                'variable_snapshot' => json_encode(['shop_name' => 'J&T'], JSON_THROW_ON_ERROR),
                'state' => NotificationDispatch::STATE_QUEUED,
                'source' => NotificationDispatch::SOURCE_AUTOMATIC,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $before = DB::table('notification_dispatches')
                ->where('public_id', '6e315d83-ecfd-42f4-a48a-2111e709a9ce')
                ->first();

            $error = null;
            try {
                $initial->up();
            } catch (RuntimeException $caught) {
                $error = $caught;
            }
            $this->assertInstanceOf(RuntimeException::class, $error);
            $this->assertStringContainsString('cannot be inferred', $error->getMessage());

            $this->assertFalse(Schema::hasColumn('notification_dispatches', 'snapshot_version'));
            $this->assertFalse(Schema::hasColumn('notification_dispatches', 'queue_attempt'));
            $this->assertFalse(Schema::hasColumn('notification_dispatches', 'queue_generation'));
            $after = DB::table('notification_dispatches')
                ->where('public_id', '6e315d83-ecfd-42f4-a48a-2111e709a9ce')
                ->first();
            $this->assertEquals($before, $after);
            $this->assertSame('J&T unversioned from d271', $after?->rendered_subject);
        } finally {
            DB::table('notification_dispatches')
                ->where('public_id', '6e315d83-ecfd-42f4-a48a-2111e709a9ce')
                ->delete();
            if (! Schema::hasColumn('notification_dispatches', 'snapshot_version')) {
                $initial->up();
            }
            $this->snapshotCompatibilityCorrection()->up();
        }
    }

    private function snapshotCompatibilityCorrection(): object
    {
        $path = database_path('migrations/2026_09_01_000100_require_empty_dispatches_before_snapshot_v2_default.php');
        $this->assertFileExists($path);

        return require $path;
    }

    private function deliveryLifecycleMigration(): object
    {
        return require database_path('migrations/2026_09_01_000000_add_delivery_lifecycle_and_snapshot_version_to_notification_dispatches.php');
    }
}
