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
            'quote-bearing relative path' => ['/order-tracking" onclick="alert(1)'],
            'backslash relative path' => ['/\\evil.example/order-tracking'],
            'network-path variant' => ['//evil.example/order-tracking'],
        ];
    }

    #[DataProvider('foreignOwnedLinkProvider')]
    public function test_renderer_rejects_foreign_tracking_and_order_urls_wherever_they_are_referenced(string $field, string $token): void
    {
        $this->expectException(ValidationException::class);

        $this->renderer->render($this->template([
            $field => $token,
        ]), [
            trim($token, '{}') => 'https://easyparcel.example.test/private-token',
        ]);
    }

    public static function foreignOwnedLinkProvider(): array
    {
        return [
            'foreign tracking CTA' => ['email_cta_url', '{tracking_url}'],
            'foreign tracking body' => ['email_body', '{tracking_url}'],
            'foreign order CTA' => ['email_cta_url', '{order_url}'],
            'foreign order body' => ['email_body', '{order_url}'],
        ];
    }

    #[DataProvider('easyParcelVisibleFieldProvider')]
    public function test_renderer_rejects_easyparcel_urls_in_every_customer_visible_field(string $field, string $url): void
    {
        $this->expectException(ValidationException::class);

        $this->renderer->render($this->template([
            $field => $url,
        ]), []);
    }

    public static function easyParcelVisibleFieldProvider(): array
    {
        return [
            'subject' => ['email_subject', 'Update at https://easyparcel.com/private'],
            'preheader subdomain' => ['email_preheader', 'Update at https://tracking.easyparcel.com/private'],
            'heading' => ['email_heading', 'https://www.easyparcel.com/private'],
            'body' => ['email_body', 'Private link: https://easyparcel.com/private?token=secret'],
            'cta text' => ['email_cta_text', 'https://easyparcel.com/private'],
            'cta URL subdomain' => ['email_cta_url', 'https://api.easyparcel.com/private'],
            'protocol relative' => ['email_body', 'Private link: //tracking.easyparcel.com/private'],
            'entity obfuscated' => ['email_heading', 'Private link: easyparcel&#46;com/private'],
            'hex entity obfuscated' => ['email_preheader', 'Private link: easyparcel&#x2e;com/private'],
            'nested entity obfuscated' => ['email_body', 'Private link: easyparcel&amp;#46;com/private'],
            'percent obfuscated' => ['email_subject', 'Private link: easyparcel%252Ecom/private'],
            'bare domain' => ['email_cta_text', 'Private link: easyparcel.com/private'],
        ];
    }

    public function test_renderer_escapes_static_template_html_and_variable_html_exactly_once(): void
    {
        $rendered = $this->renderer->render($this->template([
            'email_body' => "<a href=\"https://example.test\" onclick=\"alert(1)\">Open</a>\n<img src=x onerror=alert(2)><script>alert(3)</script> {customer_name}",
        ]), [
            'customer_name' => '<strong>A&B</strong>',
        ]);

        $this->assertSame(
            "&lt;a href=&quot;https://example.test&quot; onclick=&quot;alert(1)&quot;&gt;Open&lt;/a&gt;\n&lt;img src=x onerror=alert(2)&gt;&lt;script&gt;alert(3)&lt;/script&gt; &lt;strong&gt;A&amp;B&lt;/strong&gt;",
            $rendered->body,
        );
        $this->assertStringNotContainsString('&amp;lt;strong&amp;gt;', $rendered->body);
        $this->assertStringNotContainsString('<script>', $rendered->body);
        $this->assertStringNotContainsString('<img', $rendered->body);
        $this->assertStringNotContainsString('<a ', $rendered->body);
    }

    public function test_renderer_rejects_an_empty_referenced_variable(): void
    {
        $this->expectException(ValidationException::class);

        $this->renderer->render($this->template([
            'email_body' => 'Carrier: {carrier_name}',
        ]), [
            'carrier_name' => '',
        ]);
    }

    public function test_renderer_escapes_cta_variable_content_after_raw_url_validation(): void
    {
        $rendered = $this->renderer->render($this->template([
            'email_cta_url' => 'https://example.test/contact?note={staff_note}',
        ]), [
            'staff_note' => 'Warehouse&support',
        ]);

        $this->assertSame('https://example.test/contact?note=Warehouse&amp;support', $rendered->ctaUrl);
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
