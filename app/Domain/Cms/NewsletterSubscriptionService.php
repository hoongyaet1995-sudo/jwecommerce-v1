<?php

namespace App\Domain\Cms;

use App\Models\Coupon;
use App\Models\NewsletterSubscription;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NewsletterSubscriptionService
{
    public function __construct(private readonly CanonicalPublishedPagePolicy $publishedPages) {}

    /** @return array{subscribed: true, alreadySubscribed?: true, couponCode?: string} */
    public function subscribe(Request $request, Page $boundPage, string $stage, string $sectionId, string $email): array
    {
        try {
            return DB::transaction(function () use ($request, $boundPage, $stage, $sectionId, $email): array {
                $page = Page::query()->lockForUpdate()->findOrFail($boundPage->getKey());
                $section = $this->authoritativeSection($request, $page, $stage, $sectionId);
                $existing = NewsletterSubscription::query()->where('email', $email)->lockForUpdate()->first();
                if ($existing !== null) {
                    return ['subscribed' => true, 'alreadySubscribed' => true];
                }

                $couponId = $this->canonicalCouponId($section['newsletterCouponId'] ?? null);
                $coupon = $couponId === null ? null : Coupon::query()->currentlyValid()->lockForUpdate()->find($couponId);
                if ($coupon === null) {
                    throw ValidationException::withMessages(['section' => ['The selected subscriber coupon is unavailable.']]);
                }

                NewsletterSubscription::query()->create([
                    'page_id' => $page->getKey(),
                    'page_id_snapshot' => $page->getKey(),
                    'coupon_id' => $coupon->getKey(),
                    'coupon_id_snapshot' => $coupon->getKey(),
                    'coupon_code_snapshot' => $coupon->code,
                    'document_stage' => $stage,
                    'section_id' => $sectionId,
                    'section_type' => 'newsletter_signup',
                    'email' => $email,
                ]);

                // Issuance is not an order redemption: coupon usage_count remains unchanged.
                return ['subscribed' => true, 'couponCode' => $coupon->code];
            }, attempts: 3);
        } catch (QueryException $exception) {
            // A competing request can win after our first read. Once the transaction has
            // rolled back, a durable unique-email row is the same idempotent outcome.
            if ($this->isUniqueEmailViolation($exception)
                && NewsletterSubscription::query()->where('email', $email)->exists()) {
                return ['subscribed' => true, 'alreadySubscribed' => true];
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function authoritativeSection(Request $request, Page $page, string $stage, string $sectionId): array
    {
        $document = PageDocument::query()->where('page_id', $page->getKey())->where('stage', $stage)->lockForUpdate()->first();
        if ($document === null) {
            throw ValidationException::withMessages(['section' => ['The submitted page form is unavailable.']]);
        }
        $audience = $request->query('audience');
        abort_unless(PageRuntimeAudience::isKnown($audience), 403);
        abort_unless($this->publishedPages->maySubmit($page, $document, $audience, $request->user()), 403);
        if ($request->query('hash') !== $document->content_hash || (string) $request->query('version') !== (string) $document->lock_version || $request->query('type') !== 'newsletter_signup') {
            throw ValidationException::withMessages(['contract' => ['The submitted form contract is stale.']]);
        }
        foreach ($document->document['sections'] ?? [] as $section) {
            if (is_array($section) && ($section['id'] ?? null) === $sectionId && ($section['type'] ?? null) === 'newsletter_signup' && ($section['isVisible'] ?? false) === true) {
                return $section;
            }
        }
        throw ValidationException::withMessages(['section' => ['The submitted form does not match the current page section.']]);
    }

    private function canonicalCouponId(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    private function isUniqueEmailViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'newsletter_subscriptions.email')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry');
    }
}
