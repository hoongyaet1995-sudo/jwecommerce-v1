# Buyer Review Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a source-faithful buyer-review flow with private customer photos, server-enforced review permissions, bulk moderation, immutable audit history, and published-only PDP social proof.

**Architecture:** Laravel owns every eligibility, storage, authorization, moderation, aggregate, and audit transition in MySQL. The supplied Google AI Studio components are adapted at the Laravel data/event boundary only, first on isolated preview routes; the existing official Account and Admin routes are replaced only after screenshot acceptance.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade/Alpine, React Admin, Tailwind, Laravel feature tests, browser acceptance.

**Spec:** `docs/superpowers/specs/2026-08-23-buyer-review-operational-closure-design.md`

## Global Constraints

- Google AI Studio ZIP DOM, Tailwind classes, copy, assets, responsive behavior, and interactions are the visual source of truth.
- MySQL is the sole operational owner; ZIP/React mock data and manual ratings are never runtime truth.
- Only delivered order items owned by the authenticated customer may start a review attempt.
- One order item may have many rejected attempts but only one active `pending` or `published` attempt.
- Originals are private; only publication derivatives may become public.
- Every submission/media/moderation/bulk transition creates an append-only audit event.
- Do not commit from this shared dirty workspace; preserve unrelated changes and report the exact touched files.
- Capture before/after browser screenshots for every user-facing surface changed.

---

## File structure

| Path | Responsibility |
| --- | --- |
| `database/migrations/*_create_product_review_tables.php` | review attempt, private media, append-only events, staff permissions tables/indexes |
| `app/Models/ProductReview.php` | review attempt relationships and published scope |
| `app/Models/ProductReviewMedia.php` | private/public media metadata |
| `app/Models/ProductReviewEvent.php` | immutable audit ledger model |
| `app/Models/UserPermission.php` | persisted staff permission relation |
| `app/Http/Controllers/Admin/StaffReviewPermissionController.php` | staff grant listing and mutation adapter |
| `app/Domain/Reviews/ReviewPermission.php` | canonical permission keys and grant checks |
| `app/Domain/Reviews/ProductReviewEligibilityService.php` | owner/delivered/active-attempt eligibility query |
| `app/Domain/Reviews/ProductReviewSubmissionService.php` | locked submission, private media, event creation |
| `app/Domain/Reviews/ProductReviewModerationService.php` | single/bulk state transitions and per-item outcomes |
| `app/Domain/Reviews/ProductReviewMediaService.php` | secure validation, private storage, derivative publication |
| `app/Policies/ProductReviewPolicy.php` | customer ownership and staff permission policies |
| `app/Http/Controllers/CustomerProductReviewController.php` | customer API adapters |
| `app/Http/Controllers/Admin/ProductReviewApiController.php` | Admin list/detail/moderation/audit adapters |
| `routes/web.php` | protected customer/Admin API routes and isolated preview routes |
| `resources/views/storefront/account-review-preview.blade.php` | source-faithful isolated customer review region |
| `resources/admin/src/components/catalog/ProductReviewsAdmin.tsx` | source-faithful moderation UI adapter |
| `resources/admin/src/components/catalog/ProductExtraInfoEditor.tsx` | read-only computed review summary adapter |
| `resources/admin/src/components/catalog/CatalogHub.tsx` | source catalog hub review card adapter |
| `resources/admin/src/components/layout/AdminSidebar.tsx` | review navigation/badge adapter |
| `resources/admin/src/App.tsx` | review route-section adapter |
| `resources/admin/src/components/settings/UserLoginSettingAdmin.tsx` | existing-source staff-permission surface bound to persisted grants |
| `tests/Feature/ProductReviewOperationalClosureTest.php` | persistence, ownership, media, audit, aggregate and API coverage |
| `tests/Feature/Admin/ProductReviewAuthorizationTest.php` | role/permission and bulk moderation coverage |

## Task 1: Persist review attempts, media, audit events, and staff grants

**Files:**
- Create: `database/migrations/*_create_product_review_tables.php`
- Create: `app/Models/ProductReview.php`
- Create: `app/Models/ProductReviewMedia.php`
- Create: `app/Models/ProductReviewEvent.php`
- Create: `app/Models/UserPermission.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/OrderItem.php`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Produces `ProductReview::published()`, `ProductReview::active()`, `User::permissions()`, and durable database uniqueness for `(order_item_id, attempt_number)`.

- [ ] **Step 1: Write the failing persistence tests**

```php
public function test_review_attempts_preserve_rejected_history_but_only_one_active_attempt(): void
{
    $item = $this->deliveredItemFor($customer);
    $this->createReviewAttempt($item, ['status' => 'rejected', 'attempt_number' => 1]);
    $this->createReviewAttempt($item, ['status' => 'pending', 'attempt_number' => 2]);

    $this->assertDatabaseCount('product_reviews', 2);
    $this->assertDatabaseHas('product_reviews', ['order_item_id' => $item->id, 'attempt_number' => 2, 'status' => 'pending']);
}
```

- [ ] **Step 2: Run the focused test and confirm it fails because the tables/models do not exist**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=review_attempts_preserve`

Expected: failure naming the missing `product_reviews` table or `ProductReview` model.

- [ ] **Step 3: Create the migration and models**

```php
$table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
$table->unsignedSmallInteger('attempt_number');
$table->enum('status', ['pending', 'published', 'rejected'])->index();
$table->unique(['order_item_id', 'attempt_number']);
```

Define `ProductReview::active()` as `whereIn('status', ['pending', 'published'])` and `published()` as `where('status', 'published')`; define media/event foreign keys with `cascadeOnDelete`; define `user_permissions` with a unique `(user_id, permission)` key.

- [ ] **Step 4: Run migration and focused test**

Run: `C:\xampp\php\php.exe artisan migrate --force; C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=review_attempts_preserve`

Expected: migration succeeds; test passes.

- [ ] **Step 5: Record the scoped file list; do not commit the shared dirty tree**

Run: `git diff --name-only -- database/migrations app/Models/User.php app/Models/ProductReview.php app/Models/ProductReviewMedia.php app/Models/ProductReviewEvent.php app/Models/UserPermission.php tests/Feature/ProductReviewOperationalClosureTest.php`

## Task 2: Server-side grant model and review policies

**Files:**
- Create: `app/Domain/Reviews/ReviewPermission.php`
- Create: `app/Policies/ProductReviewPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Admin/ProductReviewAuthorizationTest.php`

**Interfaces:**
- Consumes `UserPermission` from Task 1.
- Produces `ReviewPermission::allows(User $user, string $permission): bool` and policy methods `view`, `moderate`, `bulkModerate`, `viewAudit`, `viewPrivateMedia`.

- [ ] **Step 1: Write failing permission tests**

```php
public function test_review_viewer_cannot_publish_but_moderator_can(): void
{
    $viewer = $this->staffWith(['reviews.view']);
    $moderator = $this->staffWith(['reviews.view', 'reviews.moderate']);
    $review = ProductReview::factory()->pending()->create();

    $this->assertFalse($viewer->can('moderate', $review));
    $this->assertTrue($moderator->can('moderate', $review));
}
```

- [ ] **Step 2: Run the focused test and confirm it fails because no policy/grant exists**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductReviewAuthorizationTest.php --filter=review_viewer_cannot_publish`

Expected: failure because the `moderate` policy/grant is absent.

- [ ] **Step 3: Implement canonical grants and policy registration**

```php
final class ReviewPermission
{
    public const VIEW = 'reviews.view';
    public const MODERATE = 'reviews.moderate';
    public const BULK_MODERATE = 'reviews.bulk_moderate';
    public const AUDIT_VIEW = 'reviews.audit.view';
}
```

Super admins retain every review grant; all other staff must possess the exact database permission. Register `ProductReviewPolicy` for `ProductReview::class`.

- [ ] **Step 4: Run authorization tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductReviewAuthorizationTest.php`

Expected: viewer denial, moderator approval, bulk-only denial, audit-read denial, and super-admin approval all pass.

- [ ] **Step 5: Record scoped changes without committing**

Run: `git diff --name-only -- app/Domain/Reviews app/Policies app/Providers/AppServiceProvider.php app/Models/User.php tests/Feature/Admin/ProductReviewAuthorizationTest.php`

## Task 2A: Persisted staff-grant administration and isolated Admin route state

**Files:**
- Create: `app/Http/Controllers/Admin/StaffReviewPermissionController.php`
- Modify: `app/Http/Controllers/Admin/AdminAppController.php`
- Modify: `routes/web.php`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/components/settings/UserLoginSettingAdmin.tsx`
- Modify: `resources/admin/src/App.tsx`
- Test: `tests/Feature/Admin/ProductReviewAuthorizationTest.php`

**Interfaces:**
- Consumes the Task 1 `user_permissions` rows and Task 2 grant checks.
- Produces an admin-only staff-grant API and bootstrap payload. The existing AI Studio staff-permission structure remains intact; its review-grant rows read and write persisted grants rather than browser-local mock state.

- [ ] **Step 1: Write failing staff-grant and preview-route-state tests**

```php
public function test_super_admin_can_grant_review_permissions_but_a_moderator_cannot(): void
{
    $this->actingAs($moderator)->putJson(route('admin.api.staff.review-permissions.update', $staff), [
        'permissions' => ['reviews.view'],
    ])->assertForbidden();

    $this->actingAs($superAdmin)->putJson(route('admin.api.staff.review-permissions.update', $staff), [
        'permissions' => ['reviews.view', 'reviews.moderate'],
    ])->assertOk();
}
```

- [ ] **Step 2: Run the focused test and confirm the canonical grant API is absent**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductReviewAuthorizationTest.php --filter=super_admin_can_grant_review_permissions`

Expected: missing route/controller failure.

- [ ] **Step 3: Implement the grant boundary and preview-only Admin section**

Expose only the four canonical review grants through the dedicated staff API, authorize changes with `is_admin`, validate the exact allow-list, and return persisted rows. Extend the Admin bootstrap with the current actor's review grants. Add a non-navigable `review-preview` section mapped to `/admin/preview/product-reviews`; it must render the review adapter without changing the official Catalog navigation until screenshot cutover is accepted.

- [ ] **Step 4: Bind the existing staff UI adapter without local persistence**

Retain the Google AI Studio component DOM/classes. Replace only the staff-review permission data source and save handlers with the canonical API; do not present any mock staff member or mock permission as an operational truth.

- [ ] **Step 5: Run authorization and Admin build checks**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductReviewAuthorizationTest.php; npm run build`

Expected: only super admins can change persisted grants; refreshed Admin state reads those grants; build exits 0.

## Task 3: Private customer media validation and access

**Files:**
- Create: `app/Domain/Reviews/ProductReviewMediaService.php`
- Create: `app/Http/Controllers/ProductReviewMediaController.php`
- Modify: `config/filesystems.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Consumes `ProductReviewPolicy` from Task 2.
- Produces `ProductReviewMediaService::storePending(ProductReview $review, UploadedFile $file, int $position): ProductReviewMedia` and authorized private-media responses.

- [ ] **Step 1: Write failing private-media tests**

```php
public function test_customer_media_is_private_and_non_owner_is_denied(): void
{
    Storage::fake('review-private');
    $media = $this->submitReviewWithImage($owner);

    $this->actingAs($otherCustomer->user, 'customer')
        ->get(route('customer.reviews.media.show', $media))
        ->assertForbidden();
    $this->assertFalse(Storage::disk('public')->exists($media->original_path));
}
```

- [ ] **Step 2: Run the focused test and confirm it fails because no private-media service/route exists**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=customer_media_is_private`

Expected: route or service missing.

- [ ] **Step 3: Implement private storage and secure validation**

```php
'review-private' => ['driver' => 'local', 'root' => storage_path('app/review-private'), 'throw' => true],
```

Accept JPEG/PNG/WebP only; verify MIME, matching extension, image dimensions, five MiB size, twenty megapixel limit, SHA-256 checksum, and a maximum of five media rows per review. Store originals on `review-private`; return media bytes only after policy authorization.

- [ ] **Step 4: Run private-media tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=media`

Expected: valid owner upload passes; type, size, count, corrupt content, non-owner, and public-path checks pass.

- [ ] **Step 5: Record scoped changes without committing**

Run: `git diff --name-only -- app/Domain/Reviews/ProductReviewMediaService.php app/Http/Controllers/ProductReviewMediaController.php config/filesystems.php routes/web.php tests/Feature/ProductReviewOperationalClosureTest.php`

## Task 4: Customer eligibility, submission, and audit creation

**Files:**
- Create: `app/Domain/Reviews/ProductReviewEligibilityService.php`
- Create: `app/Domain/Reviews/ProductReviewSubmissionService.php`
- Create: `app/Http/Controllers/CustomerProductReviewController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Consumes private media from Task 3.
- Produces `eligibleItems(Customer $customer): Collection`, `submit(Customer $customer, int $orderItemId, array $payload, array $files): ProductReview`, and customer review API read/write routes.

- [ ] **Step 1: Write failing customer-flow tests**

```php
public function test_delivered_owner_can_submit_once_and_event_is_appended(): void
{
    $item = $this->deliveredItemFor($customer);
    $response = $this->actingAs($customer->user, 'customer')->postJson(
        route('customer.reviews.store'),
        ['orderItemId' => $item->id, 'rating' => 5, 'title' => 'Excellent', 'comment' => 'Test review'],
    );

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
    $this->assertDatabaseHas('product_review_events', ['event_type' => 'submitted']);
}
```

- [ ] **Step 2: Run the focused test and confirm it fails because the customer review endpoint is missing**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=delivered_owner_can_submit`

Expected: route-not-found or 404 failure.

- [ ] **Step 3: Implement locked submission**

```php
return DB::transaction(function () use ($customer, $orderItemId, $payload, $files): ProductReview {
    $item = OrderItem::query()->lockForUpdate()->with('order')->findOrFail($orderItemId);
    $this->eligibility->assertCanSubmit($customer, $item);
    $attempt = ProductReview::query()->where('order_item_id', $item->id)->max('attempt_number') + 1;
    // create pending attempt, attach validated private media, append submitted/media_attached events
});
```

The service derives product/order/customer snapshots from locked records, denies non-owned/non-delivered/active attempts, and permits a new attempt only after rejection.

- [ ] **Step 4: Run customer-flow tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=delivered_owner_can_submit`

Expected: successful submission, duplicate-active conflict, rejected resubmission, non-owner denial, and non-delivered denial pass.

- [ ] **Step 5: Record scoped changes without committing**

Run: `git diff --name-only -- app/Domain/Reviews/ProductReviewEligibilityService.php app/Domain/Reviews/ProductReviewSubmissionService.php app/Http/Controllers/CustomerProductReviewController.php routes/web.php tests/Feature/ProductReviewOperationalClosureTest.php`

## Task 5: Single and bulk moderation with immutable audit ledger

**Files:**
- Create: `app/Domain/Reviews/ProductReviewModerationService.php`
- Create: `app/Http/Controllers/Admin/ProductReviewApiController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/ProductReviewAuthorizationTest.php`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Consumes Task 2 grants and Task 4 review attempts.
- Produces `moderate(User $actor, ProductReview $review, string $action, ?string $reason): ProductReview` and `bulkModerate(User $actor, array $reviewIds, string $action, ?string $reason): array`.

- [ ] **Step 1: Write failing moderation/audit tests**

```php
public function test_bulk_publish_returns_per_review_results_and_appends_a_batch_event(): void
{
    [$first, $second] = ProductReview::factory()->count(2)->pending()->create();
    $response = $this->actingAs($this->staffWith(['reviews.bulk_moderate']))
        ->postJson(route('admin.api.reviews.bulk-moderate'), ['reviewIds' => [$first->id, $second->id], 'action' => 'publish']);

    $response->assertOk()->assertJsonCount(2, 'data.results');
    $this->assertDatabaseHas('product_review_events', ['event_type' => 'bulk_published']);
}
```

- [ ] **Step 2: Run the focused test and confirm it fails because the moderation route/service is missing**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductReviewAuthorizationTest.php --filter=bulk_publish_returns`

Expected: missing route/service failure.

- [ ] **Step 3: Implement state machine and result envelope**

```php
foreach ($reviewIds as $reviewId) {
    try {
        $results[] = ['reviewId' => $reviewId, 'status' => 'published', 'ok' => true];
    } catch (AuthorizationException|ValidationException $exception) {
        $results[] = ['reviewId' => $reviewId, 'ok' => false, 'reason' => $exception->getMessage()];
    }
}
```

Lock each review before rechecking status/permission. Publish generates public derivatives before changing status; reject/withdraw removes public derivatives; each item appends its transition event and each successful batch member records the shared `batch_id`.

- [ ] **Step 4: Run moderation and audit tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/ProductReviewAuthorizationTest.php tests/Feature/ProductReviewOperationalClosureTest.php --filter=moderation`

Expected: single publish/reject/withdraw, grant denials, mixed bulk outcomes, audit read-only behavior, and public-derivative lifecycle pass.

- [ ] **Step 5: Record scoped changes without committing**

Run: `git diff --name-only -- app/Domain/Reviews/ProductReviewModerationService.php app/Http/Controllers/Admin/ProductReviewApiController.php routes/web.php tests/Feature/Admin/ProductReviewAuthorizationTest.php tests/Feature/ProductReviewOperationalClosureTest.php`

## Task 6: Published-only storefront read model and legacy-rating retirement

**Files:**
- Modify: `app/Models/Product.php`
- Modify: `app/Http/Controllers/StorefrontController.php`
- Modify: `resources/views/storefront/show.blade.php`
- Test: `tests/Feature/StorefrontPdpRichContentTest.php`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Consumes published review scope and public derivatives from Tasks 1 and 5.
- Produces PDP variables `publishedReviews`, `publishedReviewAverage`, and `publishedReviewCount`.

- [ ] **Step 1: Write failing PDP aggregate tests**

```php
public function test_pdp_exposes_only_published_order_linked_reviews_in_its_rating_summary(): void
{
    ProductReview::factory()->for($product)->published()->create(['rating' => 5]);
    ProductReview::factory()->for($product)->pending()->create(['rating' => 1]);

    $this->get(route('storefront.products.show', $product))
        ->assertSee('5.0')
        ->assertDontSee('pending review body');
}
```

- [ ] **Step 2: Run the focused test and confirm it fails while PDP reviews remain dormant**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=pdp_exposes_only_published`

Expected: assertion failure because PDP does not yet consume published review data.

- [ ] **Step 3: Bind the source-approved PDP rating/review markup to the published read model**

```php
$publishedReviews = $product->reviews()->published()->with('publicMedia')->oldest('published_at')->get();
$publishedReviewCount = $publishedReviews->count();
$publishedReviewAverage = round((float) $publishedReviews->avg('rating'), 1);
```

Keep `$showRating` false when `publishedReviewCount === 0`; remove all public use of `ProductPdpContent.rating_score`, `review_count`, and hand-authored `pdpReviews`.

- [ ] **Step 4: Run PDP tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontPdpRichContentTest.php tests/Feature/ProductReviewOperationalClosureTest.php --filter=pdp`

Expected: published-only summary/media render, pending/rejected content absence, and zero-review hidden state pass.

- [ ] **Step 5: Record scoped changes without committing**

Run: `git diff --name-only -- app/Models/Product.php app/Http/Controllers/StorefrontController.php resources/views/storefront/show.blade.php tests/Feature/StorefrontPdpRichContentTest.php tests/Feature/ProductReviewOperationalClosureTest.php`

## Task 7: Build source-faithful isolated UI previews

**Files:**
- Create: `resources/views/storefront/account-review-preview.blade.php`
- Create: `resources/admin/src/components/catalog/ProductReviewsPreview.tsx`
- Modify: `routes/web.php`
- Modify: `resources/admin/src/components/catalog/ProductReviewsAdmin.tsx`
- Modify: `resources/admin/src/components/catalog/ProductExtraInfoEditor.tsx`
- Modify: `resources/admin/src/components/catalog/CatalogHub.tsx`
- Modify: `resources/admin/src/components/layout/AdminSidebar.tsx`
- Modify: `resources/admin/src/App.tsx`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Consumes Tasks 4–6 JSON read/write contracts.
- Produces isolated customer/Admin preview URLs and no official-route cutover.

- [ ] **Step 1: Write failing isolated-preview route tests**

```php
public function test_review_ui_preview_routes_are_available_without_replacing_official_routes(): void
{
    $this->actingAs($customer->user, 'customer')->get('/preview/account/reviews')->assertOk();
    $this->actingAs($admin)->get('/admin/preview/product-reviews')->assertOk();
    $this->get('/account')->assertOk();
}
```

- [ ] **Step 2: Run the focused test and confirm preview routes are missing**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=review_ui_preview_routes`

Expected: 404 for the preview routes.

- [ ] **Step 3: Port only the selected ZIP regions**

Preserve `PurchaseHistoryTab.tsx` modal/item-action structure and the `ProductReviewsAdmin.tsx` list/drawer/bulk/audit structure exactly. Replace React mock state with the Task 4/5 endpoints; retain all source classes and responsive behavior. Mount previews only at `/preview/account/reviews` and `/admin/preview/product-reviews`.

- [ ] **Step 4: Run UI contract tests and production Admin build**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=review_ui_preview_routes; npm run build`

Expected: preview routes pass and Admin build exits 0.

- [ ] **Step 5: Capture desktop/mobile preview screenshots and request official-route cutover approval**

Save source and preview screenshots under `output/product-review-closure/`; inspect visual differences before asking for cutover. Do not replace `/account` or `/admin` review navigation yet.

## Task 8: Official UI cutover and end-to-end acceptance

**Files:**
- Modify: `resources/views/storefront/account.blade.php`
- Modify: `resources/admin/src/App.tsx`
- Modify: `resources/admin/src/components/layout/AdminSidebar.tsx`
- Modify: `resources/admin/src/components/catalog/CatalogHub.tsx`
- Test: `tests/Feature/ProductReviewOperationalClosureTest.php`

**Interfaces:**
- Requires explicit user acceptance of Task 7 screenshots.
- Produces real Account and Admin review experiences using the same verified adapter contracts.

- [ ] **Step 1: Write failing cutover assertions after user approval**

```php
public function test_real_account_and_admin_surfaces_render_review_adapters(): void
{
    $this->actingAs($customer->user, 'customer')->get('/account')->assertSee('Review this product');
    $this->actingAs($admin)->get('/admin')->assertSee('Product Reviews');
}
```

- [ ] **Step 2: Run the test and confirm the official routes do not yet contain the adapter surfaces**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php --filter=real_account_and_admin_surfaces`

Expected: failing assertion before cutover.

- [ ] **Step 3: Replace only the approved account/admin regions with the verified preview adapters**

Do not alter unrelated Account/Admin DOM or classes. Preserve existing route guards and inject the same server data contracts proven in preview.

- [ ] **Step 4: Run the targeted regression suite**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php tests/Feature/CheckoutCommerceRulesTest.php tests/Feature/StorefrontMockClosureTest.php; npm run build; git diff --check`

Expected: all focused tests/build/diff check pass.

- [ ] **Step 5: Perform real-browser operational acceptance and save final evidence**

Use separate customer and staff sessions: delivered order → image submission → pending state → single publish → PDP public result → second pending review → bulk moderation → audit drawer. Capture desktop/mobile Account, Admin queue, Admin audit, and PDP screenshots; check console errors are zero.

## Plan self-review

- Spec coverage: Tasks 1–6 cover the persistent model, server authorization, private media, audit ledger, moderation, and public aggregate; Tasks 7–8 cover source-faithful preview, user cutover gate, and browser proof.
- Placeholder scan: no deferred implementation placeholders; Task 8 is intentionally gated by explicit cutover approval required by the fidelity workflow.
- Interface consistency: Task 1 models feed Task 2 policies; Task 3 media feeds Task 4 submission; Task 5 moderation feeds Task 6 aggregate; Task 4–6 APIs feed Task 7 preview and Task 8 cutover.
