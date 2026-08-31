<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
