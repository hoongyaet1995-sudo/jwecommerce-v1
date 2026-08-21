<?php

namespace Tests\Feature\Cms;

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Models\CmsContactSubmission;
use App\Models\Coupon;
use App\Models\NewsletterSubscription;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class CmsFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submission_persists_only_the_rendered_form_payload_and_survives_a_fresh_read(): void
    {
        $page = $this->pageWithForms();

        $this->postJson($this->contactEndpoint($page), $this->contactPayload())
            ->assertCreated()
            ->assertJsonPath('data.submitted', true);

        $submission = CmsContactSubmission::query()->firstOrFail();

        $this->assertSame((int) $page->id, $submission->page_id);
        $this->assertSame('published', $submission->document_stage);
        $this->assertSame('contact-section', $submission->section_id);
        $this->assertSame('contact_form', $submission->section_type);
        $this->assertSame('Soren Lindqvist', $submission->name);
        $this->assertSame('soren@example.test', $submission->email);
        $this->assertSame('Product Enquiry', $submission->category);
        $this->assertSame('Need a custom studio monitor setup.', $submission->message);
    }

    public function test_contact_rejects_unknown_sensitive_and_mismatched_form_identity_without_persistence(): void
    {
        $page = $this->pageWithForms();
        $this->postJson($this->contactEndpoint($page), $this->contactPayload([
            'sectionId' => 'newsletter-section',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sectionId');

        $payload = $this->contactPayload([
            'cardNumber' => '4111111111111111',
            'cvv' => '123',
        ]);

        $this->postJson($this->contactEndpoint($page), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cardNumber', 'cvv']);

        $this->assertSame(0, CmsContactSubmission::query()->count());
    }

    public function test_newsletter_persists_a_normalized_email_and_releases_only_the_authoritative_eligible_coupon_code(): void
    {
        $coupon = $this->coupon('WELCOME15');
        $page = $this->pageWithForms($coupon);

        $this->postJson($this->newsletterEndpoint($page), $this->newsletterPayload(['email' => ' VIP@Example.Test ']))
            ->assertCreated()
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.couponCode', 'WELCOME15');

        $subscription = NewsletterSubscription::query()->firstOrFail();

        $this->assertSame('vip@example.test', $subscription->email);
        $this->assertSame((int) $coupon->id, $subscription->coupon_id);
        $this->assertSame('newsletter-section', $subscription->section_id);
        $this->assertSame(0, $coupon->fresh()->usage_count);
    }

    public function test_newsletter_duplicate_is_idempotent_without_a_second_row_or_coupon_code_reissue(): void
    {
        $coupon = $this->coupon('WELCOME15');
        $page = $this->pageWithForms($coupon);
        $payload = $this->newsletterPayload();

        $this->postJson($this->newsletterEndpoint($page), $payload)->assertCreated();

        $this->postJson($this->newsletterEndpoint($page), $payload)
            ->assertOk()
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.alreadySubscribed', true)
            ->assertJsonMissingPath('data.couponCode');

        $this->assertSame(1, NewsletterSubscription::query()->count());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_invalid_or_exhausted_authoritative_coupon_never_persists_or_releases_a_code(): void
    {
        $ipSuffix = 30;
        foreach ([
            'inactive' => $this->coupon('INACTIVE15', ['is_active' => false]),
            'future' => $this->coupon('FUTURE15', ['starts_at' => now()->addDay()]),
            'expired' => $this->coupon('EXPIRED15', ['ends_at' => now()->subSecond()]),
            'exhausted' => $this->coupon('EXHAUSTED15', ['usage_limit' => 1, 'usage_count' => 1]),
        ] as $label => $coupon) {
            $page = $this->pageWithForms($coupon, "newsletter-{$label}");

            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$ipSuffix++])
                ->postJson($this->newsletterEndpoint($page, "newsletter-{$label}"), $this->newsletterPayload([
                    'email' => "{$label}@example.test",
                ]))
                ->assertUnprocessable()
                ->assertJsonMissingPath('data.couponCode');
        }

        $this->assertSame(0, NewsletterSubscription::query()->count());
    }

    public function test_invalid_requests_and_rate_limited_requests_do_not_persist(): void
    {
        $page = $this->pageWithForms();
        $payload = $this->contactPayload(['message' => '']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.72'])
                ->postJson($this->contactEndpoint($page), $payload)
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.72'])
            ->postJson($this->contactEndpoint($page), $payload)
            ->assertTooManyRequests();

        $this->assertSame(0, CmsContactSubmission::query()->count());
    }

    public function test_both_named_throttles_return_json_without_writing_rows(): void
    {
        $page = $this->pageWithForms();

        foreach ([
            ['contact', $this->contactEndpoint($page)],
            ['newsletter', $this->newsletterEndpoint($page)],
        ] as $offset => [$kind, $endpoint]) {
            $ip = '198.51.100.'.(80 + $offset);
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])
                    ->postJson($endpoint, $kind === 'contact'
                        ? $this->contactPayload(['message' => ''])
                        : $this->newsletterPayload(['email' => 'not-an-email']))
                    ->assertUnprocessable();
            }

            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson($endpoint, $kind === 'contact'
                    ? $this->contactPayload(['message' => ''])
                    : $this->newsletterPayload(['email' => 'not-an-email']))
                ->assertTooManyRequests()
                ->assertHeader('content-type', 'application/json');
        }

        $this->assertSame(0, CmsContactSubmission::query()->count());
        $this->assertSame(0, NewsletterSubscription::query()->count());
    }

    public function test_form_routes_declare_signed_and_named_throttle_middleware_while_testing_mode_bypasses_csrf(): void
    {
        $contactMiddleware = Route::getRoutes()->getByName('cms.forms.contact.submit')->gatherMiddleware();
        $newsletterMiddleware = Route::getRoutes()->getByName('cms.forms.newsletter.submit')->gatherMiddleware();

        $this->assertContains('signed:relative', $contactMiddleware);
        $this->assertContains('throttle:cms-contact', $contactMiddleware);
        $this->assertContains('signed:relative', $newsletterMiddleware);
        $this->assertContains('throttle:cms-newsletter', $newsletterMiddleware);
        $this->assertTrue(app()->environment('testing'));
    }

    public function test_contact_uses_renderer_default_categories_when_configured_categories_are_missing_or_empty(): void
    {
        foreach ([null, []] as $categories) {
            $page = $this->pageWithForms();
            $document = $page->publishedDocument;
            $payload = $document->document;
            if ($categories === null) {
                unset($payload['sections'][0]['inquiryCategories']);
            } else {
                $payload['sections'][0]['inquiryCategories'] = $categories;
            }
            $document->update([
                'document' => $payload,
                'content_hash' => PageDocumentHasher::hash($payload),
            ]);

            $this->postJson($this->contactEndpoint($page), $this->contactPayload())
                ->assertCreated();
        }

        $this->assertSame(2, CmsContactSubmission::query()->count());
    }

    public function test_runtime_payload_exposes_only_post_endpoints_and_no_coupon_code_before_success(): void
    {
        $coupon = $this->coupon('PRIVATE15');
        $page = $this->pageWithForms($coupon);
        $payload = app(PageRuntimePayloadFactory::class)->forPublished($page->fresh());

        $this->assertInstanceOf(\stdClass::class, $payload['forms']['contact']);
        $this->assertInstanceOf(\stdClass::class, $payload['forms']['newsletter']);
        $contact = $payload['forms']['contact']->{'contact-section'};
        $newsletter = $payload['forms']['newsletter']->{'newsletter-section'};
        $this->assertSame('/cms/forms/contact/'.$page->id.'/published/contact-section', parse_url($contact['endpoint'], PHP_URL_PATH));
        $this->assertSame('/cms/forms/newsletter/'.$page->id.'/published/newsletter-section', parse_url($newsletter['endpoint'], PHP_URL_PATH));
        $this->assertSame($payload['page']['contentHash'], $contact['documentHash']);
        $this->assertSame('contact_form', $contact['sectionType']);
        $this->assertSame('newsletter_signup', $newsletter['sectionType']);
        $this->assertStringNotContainsString('PRIVATE15', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_signed_submission_contract_rejects_copies_tampering_expiry_and_uncutover_tuple_targeting(): void
    {
        $coupon = $this->coupon('SAFE15');
        $page = $this->pageWithForms($coupon);
        $contact = $this->contactEndpoint($page);
        $newsletter = $this->newsletterEndpoint($page);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.180'])->postJson(str_replace('/contact/', '/newsletter/', $contact), $this->contactPayload())
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.181'])->postJson(str_replace('/contact-section', '/newsletter-section', $contact), $this->contactPayload())
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.181'])->postJson(str_replace('/published/', '/draft/', $contact), $this->contactPayload())
            ->assertForbidden();
        $otherPage = $this->pageWithForms();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.181'])->postJson(str_replace('/'.$page->id.'/published/', '/'.$otherPage->id.'/published/', $contact), $this->contactPayload())
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.182'])->postJson('/cms/forms/contact/'.$page->id.'/published/contact-section', $this->contactPayload())
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.183'])->postJson($this->expiredContract('contact', $page, 'contact-section', 'contact_form'), $this->contactPayload())
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.184'])->postJson($this->mismatchedHashContract('newsletter', $page, 'newsletter-section', 'newsletter_signup'), $this->newsletterPayload())
            ->assertUnprocessable();

        $uncutover = $this->pageWithForms(null);
        $uncutover->update(['status' => 'draft', 'is_published' => false, 'published_at' => null]);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.185'])->postJson($this->freshContract('contact', $uncutover, 'contact-section', 'contact_form'), $this->contactPayload())
            ->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.186'])->postJson($newsletter, $this->newsletterPayload())->assertCreated();

        $this->assertSame(0, CmsContactSubmission::query()->count());
        $this->assertSame(1, NewsletterSubscription::query()->count());
    }

    public function test_draft_contract_requires_admin_and_history_survives_page_and_coupon_deletion(): void
    {
        $coupon = $this->coupon('LIFECYCLE15');
        $page = $this->pageWithForms($coupon, draft: true);
        $draftPayload = app(PageRuntimePayloadFactory::class)->forDraft($page->fresh());
        $this->assertInstanceOf(\stdClass::class, $draftPayload['forms']['contact']);
        $this->assertInstanceOf(\stdClass::class, $draftPayload['forms']['newsletter']);
        $contactEndpoint = $draftPayload['forms']['contact']->{'contact-section'}['endpoint'];
        $newsletterEndpoint = $draftPayload['forms']['newsletter']->{'newsletter-section'}['endpoint'];

        $this->postJson($contactEndpoint, $this->contactPayload())->assertForbidden();
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('password'), 'is_admin' => true]);
        $this->actingAs($admin)->postJson($contactEndpoint, $this->contactPayload())->assertCreated();
        $this->actingAs($admin)->postJson($newsletterEndpoint, $this->newsletterPayload())->assertCreated();

        $pageId = (int) $page->id;
        $couponId = (int) $coupon->id;
        $page->delete();
        $coupon->delete();

        $contact = CmsContactSubmission::query()->firstOrFail();
        $subscription = NewsletterSubscription::query()->firstOrFail();
        $this->assertNull($contact->page_id);
        $this->assertSame($pageId, $contact->page_id_snapshot);
        $this->assertNull($subscription->page_id);
        $this->assertSame($pageId, $subscription->page_id_snapshot);
        $this->assertNull($subscription->coupon_id);
        $this->assertSame($couponId, $subscription->coupon_id_snapshot);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Soren Lindqvist',
            'email' => 'soren@example.test',
            'subject' => 'Custom setup',
            'category' => 'Product Enquiry',
            'message' => 'Need a custom studio monitor setup.',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function newsletterPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'vip@example.test',
        ], $overrides);
    }

    private function coupon(string $code, array $overrides = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'code' => $code,
            'discount_type' => 'percentage',
            'value' => 15,
            'min_spend' => null,
            'usage_limit' => 100,
            'usage_count' => 0,
            'per_customer_limit' => 1,
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
        ], $overrides));
    }

    private function pageWithForms(?Coupon $coupon = null, string $newsletterSectionId = 'newsletter-section', bool $draft = false): Page
    {
        $page = Page::query()->create([
            'slug' => 'operational-forms-'.str()->uuid(),
            'title' => 'Operational Forms',
            'is_published' => ! $draft,
            'status' => $draft ? 'draft' : 'published',
            'published_at' => $draft ? null : now(),
        ]);
        $document = [
            'title' => 'Operational Forms',
            'slug' => $page->slug,
            'description' => '',
            'template' => 'landing',
            'heroImage' => null,
            'seoTitle' => '',
            'seoDescription' => '',
            'sections' => [
                [
                    'id' => 'contact-section',
                    'type' => 'contact_form',
                    'title' => 'Contact',
                    'isVisible' => true,
                    'inquiryCategories' => [
                        ['id' => 'product', 'label' => 'Product Enquiry'],
                    ],
                ],
                [
                    'id' => $newsletterSectionId,
                    'type' => 'newsletter_signup',
                    'title' => 'Newsletter',
                    'isVisible' => true,
                    'newsletterCouponId' => $coupon === null ? '' : (string) $coupon->id,
                ],
            ],
        ];
        $page->documents()->create([
            'stage' => $draft ? 'draft' : 'published',
            'slug' => $document['slug'],
            'schema_version' => PageDocumentContract::VERSION,
            'lock_version' => 1,
            'content_hash' => PageDocumentHasher::hash($document),
            'document' => $document,
        ]);

        return $page;
    }

    private function contactEndpoint(Page $page, string $section = 'contact-section'): string
    {
        $forms = app(PageRuntimePayloadFactory::class)->forPublished($page->fresh())['forms']['contact'];
        $this->assertInstanceOf(\stdClass::class, $forms);

        return $forms->{$section}['endpoint'];
    }

    private function newsletterEndpoint(Page $page, string $section = 'newsletter-section'): string
    {
        $forms = app(PageRuntimePayloadFactory::class)->forPublished($page->fresh())['forms']['newsletter'];
        $this->assertInstanceOf(\stdClass::class, $forms);

        return $forms->{$section}['endpoint'];
    }

    private function expiredContract(string $endpoint, Page $page, string $section, string $type): string
    {
        return URL::temporarySignedRoute('cms.forms.'.$endpoint.'.submit', now()->subSecond(), [
            'page' => $page->id, 'stage' => 'published', 'section' => $section,
            'hash' => $page->publishedDocument->content_hash, 'version' => $page->publishedDocument->lock_version, 'type' => $type,
        ], absolute: false);
    }

    private function mismatchedHashContract(string $endpoint, Page $page, string $section, string $type): string
    {
        return URL::temporarySignedRoute('cms.forms.'.$endpoint.'.submit', now()->addMinutes(5), [
            'page' => $page->id, 'stage' => 'published', 'section' => $section,
            'hash' => str_repeat('a', 64), 'version' => $page->publishedDocument->lock_version, 'type' => $type,
        ], absolute: false);
    }

    private function freshContract(string $endpoint, Page $page, string $section, string $type): string
    {
        return URL::temporarySignedRoute('cms.forms.'.$endpoint.'.submit', now()->addMinutes(5), [
            'page' => $page->id, 'stage' => 'published', 'section' => $section,
            'hash' => $page->publishedDocument->content_hash, 'version' => $page->publishedDocument->lock_version, 'type' => $type,
        ], absolute: false);
    }
}
