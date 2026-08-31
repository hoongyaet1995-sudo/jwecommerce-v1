<?php

namespace Tests\Unit\Notifications;

use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationTemplateRenderer;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationTemplateRendererTest extends TestCase
{
    private NotificationTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = app(NotificationTemplateRenderer::class);
    }

    public function test_renderer_escapes_values_and_resolves_only_owned_tracking_url(): void
    {
        $rendered = $this->renderer->render($this->template([
            'email_body' => 'Hello {customer_name}',
            'email_cta_url' => '{tracking_url}',
        ]), [
            'customer_name' => '<script>alert(1)</script>',
            'tracking_url' => url('/order-tracking'),
        ]);

        $this->assertSame('Hello &lt;script&gt;alert(1)&lt;/script&gt;', $rendered->body);
        $this->assertSame(url('/order-tracking'), $rendered->ctaUrl);

        $orderRendered = $this->renderer->render($this->template([
            'email_cta_url' => '{order_url}',
        ]), [
            'order_url' => url('/order-tracking'),
        ]);

        $this->assertSame(url('/order-tracking'), $orderRendered->ctaUrl);
    }

    #[DataProvider('unsafeCtaProvider')]
    public function test_renderer_rejects_unsafe_cta(string $url): void
    {
        $this->expectException(ValidationException::class);

        $this->renderer->render($this->template(['email_cta_url' => $url]), []);
    }

    public static function unsafeCtaProvider(): array
    {
        return [
            'javascript protocol' => ['javascript:alert(1)'],
            'data protocol' => ['data:text/html;base64,PHNjcmlwdD4='],
            'credentials' => ['https://customer:secret@example.test/order-tracking'],
            'username-only credentials' => ['https://customer@example.test/order-tracking'],
            'malformed URL' => ['https://'],
        ];
    }

    public function test_renderer_rejects_unknown_or_missing_template_variables(): void
    {
        foreach ([
            [['email_body' => 'Hello {easyparcel_token}'], []],
            [['email_body' => 'Hello {customer_name}'], []],
        ] as [$changes, $variables]) {
            try {
                $this->renderer->render($this->template($changes), $variables);
                $this->fail('The renderer accepted an unknown or unresolved variable.');
            } catch (ValidationException) {
                // Expected: the frozen snapshot must never contain an unallowlisted or unresolved token.
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_renderer_rejects_retired_payment_proof_upload_token_for_enabled_templates(): void
    {
        $this->expectException(ValidationException::class);

        $this->renderer->render($this->template([
            'email_enabled' => true,
            'email_cta_url' => '{payment_proof_upload_url}',
        ]), []);
    }

    private function template(array $changes = []): NotificationTemplate
    {
        return (new NotificationTemplate)->forceFill(array_merge([
            'event_key' => 'ready_to_ship',
            'email_enabled' => true,
            'email_subject' => 'Order update',
            'email_preheader' => 'Your order update',
            'email_heading' => 'Order update',
            'email_body' => 'Your order is ready.',
            'email_cta_text' => 'Track order',
            'email_cta_url' => '/order-tracking',
        ], $changes));
    }
}
