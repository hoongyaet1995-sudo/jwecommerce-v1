# Product Review Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the real customer-submission -> permissioned Admin moderation -> published PDP review loop using the latest approved Google AI Studio Admin and Storefront source structures.

**Architecture:** Laravel services, policies and MySQL remain the only business-state owner. A new Admin review adapter maps protected Laravel responses into the approved React component’s view model; no review mock data or localStorage may own state. The PDP reads one published-review collection and computes its own social-proof aggregate, hiding the complete review source region when no eligible record exists.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade, Vite, React 19, TypeScript, Vitest, PHPUnit, authenticated browser automation.

**Spec:** `docs/superpowers/specs/2026-08-24-product-review-operational-closure-design.md`

## Global Constraints

- Preserve the exact selected Google AI Studio DOM hierarchy, Tailwind classes, copy, responsive structure and interaction states.
- Use MySQL and Laravel APIs/services as runtime truth; do not import `mockReviewsData.ts` or use localStorage for review state.
- The only public review consumer is the PDP and it must receive `published` order-linked records only.
- `Verified Studio Buyer` is system-managed from delivered-order eligibility and moderation state; it is never a manual product-editor field.
- Keep review media private until the policy’s approved-public delivery path is proven; never create a public fallback URL.
- Retire legacy manual rating/count/sample-review controls from the Admin product editor without deleting their historical database rows.
- Preserve unrelated dirty-worktree changes; stage only files belonging to the completed task.

---

## File structure and interfaces

| Path | Responsibility |
| --- | --- |
| `app/Http/Controllers/Admin/ProductReviewApiController.php` | Normalized protected review rows and audit payloads for Admin. |
| `app/Http/Controllers/Admin/AdminAppController.php` | Emits protected review API URLs and granted permissions to Admin bootstrap. |
| `app/Domain/Reviews/ProductReviewModerationService.php` | Authoritative transition and audit append behavior; only amend when API response normalization requires it. |
| `app/Http/Controllers/StorefrontController.php` | Eager-loads only public-eligible published review/media data for PDP. |
| `resources/admin/src/types/index.ts` | `AdminProductReview`, review status, permissions and bootstrap URL types. |
| `resources/admin/src/integration/reviewData.ts` | Authenticated fetch, normalized view-model conversion and mutation operations. |
| `resources/admin/src/context/AdminContext.tsx` | Loads real reviews, exposes refresh/moderation actions and product filter state. |
| `resources/admin/src/components/catalog/ProductReviewsAdmin.tsx` | Source-faithful ZIP moderation surface. |
| `resources/admin/src/components/catalog/ProductExtraInfoEditor.tsx` | Source-faithful read-only review summary; removes manual seed controls. |
| `resources/admin/src/components/catalog/CatalogHub.tsx` | Source-faithful review module card and real pending count. |
| `resources/admin/src/components/layout/AdminSidebar.tsx` | `catalog-reviews` entry and real pending badge. |
| `resources/admin/src/integration/adminRoutes.ts`, `resources/admin/src/App.tsx` | Official Admin route and active section dispatch. |
| `resources/views/storefront/show.blade.php` | Selected source-faithful PDP rating and accordion binding. |
| `tests/Feature/Admin/ProductReviewAuthorizationTest.php` | Admin contract, transition, authorization and audit coverage. |
| `tests/Feature/ProductReviewOperationalClosureTest.php` | Customer-to-PDP public eligibility and legacy-source exclusion coverage. |
| `resources/admin/src/integration/reviewData.test.ts` | Adapter transformation, requests and error coverage. |
| `resources/admin/src/integration/adminRoutes.test.ts` | New official `catalog-reviews` route state coverage. |

## Task 1: Normalize the protected Laravel review contract

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductReviewApiController.php`
- Modify: `app/Http/Controllers/Admin/AdminAppController.php`
- Modify: `tests/Feature/Admin/ProductReviewAuthorizationTest.php`

**Interfaces:**
- Produces `AdminProductReviewPayload` JSON with `productThumbnail`, `productHandle`, `customerId`, `customerEmail`, `moderatedBy`, `photos`, and audit events.
- Consumes existing `ProductReview`, `ProductReviewMedia`, `ReviewPermission`, `ProductReviewPolicy`, and moderation endpoints.

- [ ] **Step 1: Add failing API assertions for the source-required row fields and no unauthorised exposure**

```php
$this->actingAs($viewer)
    ->getJson(route('admin.api.reviews.index'))
    ->assertOk()
    ->assertJsonPath('data.0.productHandle', 'review-auth-product-first')
    ->assertJsonPath('data.0.customerEmail', $review->customer->email)
    ->assertJsonPath('data.0.moderatedBy', null);

$this->actingAs($unauthorised)->getJson(route('admin.api.reviews.index'))->assertForbidden();
```

- [ ] **Step 2: Run the targeted test to verify it fails**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\Admin\ProductReviewAuthorizationTest.php`

Expected: FAIL because the list payload lacks at least one new source-required field.

- [ ] **Step 3: Expand eager loads and map a stable admin-only payload**

```php
$reviews = ProductReview::query()
    ->with(['media', 'events.actor', 'product.images', 'customer.user', 'moderator'])
    ->latest('submitted_at')
    ->get();

'productThumbnail' => $review->product?->images->sortBy('position')->first()?->path,
'productHandle' => $review->product?->handle,
'customerEmail' => $review->customer?->email,
'moderatedBy' => $review->moderator?->name,
```

Keep private media URLs behind the existing Admin media route and do not add these fields to a public route.

- [ ] **Step 4: Add Review URLs and permissions to the Laravel Admin bootstrap**

```php
'reviewsUrl' => route('admin.api.reviews.index'),
'reviewBulkModerateUrl' => route('admin.api.reviews.bulk-moderate'),
'reviewPermissions' => $user->is_super_admin ? ReviewPermission::ALL : /* existing permission query */,
```

- [ ] **Step 5: Re-run the feature test and API smoke checks**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\Admin\ProductReviewAuthorizationTest.php tests\Feature\ProductReviewOperationalClosureTest.php`

Expected: PASS, including published transition event, batch UUID and private-media authorization.

- [ ] **Step 6: Commit Task 1 only**

```powershell
git add app/Http/Controllers/Admin/ProductReviewApiController.php app/Http/Controllers/Admin/AdminAppController.php tests/Feature/Admin/ProductReviewAuthorizationTest.php
git commit -m "feat: expose protected review moderation contract"
```

## Task 2: Add the real Admin review adapter, types and route state

**Files:**
- Create: `resources/admin/src/integration/reviewData.ts`
- Create: `resources/admin/src/integration/reviewData.test.ts`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/integration/adminRoutes.ts`
- Create: `resources/admin/src/integration/adminRoutes.test.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`

**Interfaces:**
- Produces `fetchReviews(bootstrap)`, `fetchReviewAudit(bootstrap, reviewId)`, `moderateReview(bootstrap, reviewId, action, reason?)`, and `bulkModerateReviews(bootstrap, reviewIds, action, reason?)`.
- Produces `AdminProductReview` with `status: 'pending' | 'published' | 'rejected'` and `photos: Array<{id:string; url:string; mimeType:string}>`.
- Consumes the bootstrap fields added in Task 1 and uses the same `credentials`, CSRF and API-error policy as `catalogData.ts`.

- [ ] **Step 1: Write failing adapter tests for list mapping and mutation headers**

```ts
expect(await fetchReviews(bootstrap)).toEqual([expect.objectContaining({
  id: '17', productId: '8', productThumbnail: '/storage/p.jpg',
  customerEmail: 'buyer@example.test', status: 'pending',
})]);
expect(fetch).toHaveBeenCalledWith(bootstrap.reviewsUrl, expect.objectContaining({
  credentials: 'same-origin', headers: expect.objectContaining({ Accept: 'application/json' }),
}));
```

- [ ] **Step 2: Run the adapter test to verify it fails**

Run: `npm --prefix resources/admin test -- reviewData.test.ts`

Expected: FAIL because the module and types do not exist.

- [ ] **Step 3: Add the type and bootstrap extensions**

```ts
export interface AdminProductReview {
  id: string; productId: string; productTitle: string; productThumbnail?: string | null;
  productHandle?: string | null; customerId: string; buyerName: string; customerEmail: string;
  orderNumber: string; variantTitle?: string | null; rating: number; title: string;
  comment: string; status: ReviewModerationStatus; moderationNote?: string | null;
  moderatedBy?: string | null; submittedAt: string; publishedAt?: string | null;
  photos: Array<{ id: string; url: string; mimeType: string }>;
}
```

Add `catalog-reviews: '/admin/catalog/reviews'` to the exhaustive route map and union.

- [ ] **Step 4: Implement the adapter without localStorage fallback**

```ts
export async function moderateReview(
  bootstrap: LaravelAdminBootstrap, reviewId: string, action: 'publish' | 'reject', reason?: string,
): Promise<ModerationResult> {
  return request<ModerationResult>(bootstrap, `${bootstrap.reviewsUrl}/${encodeURIComponent(reviewId)}/moderate`, 'POST', { action, reason });
}
```

Use a single internal `request` helper with `X-CSRF-TOKEN` on non-GET operations. Throw the Laravel validation/error message; do not turn failures into a mock success.

- [ ] **Step 5: Change AdminContext to load/refresh real reviews and expose permission-aware actions**

```ts
const [reviews, setReviews] = useState<AdminProductReview[]>([]);
const refreshReviews = async () => setReviews(await fetchReviews(laravelBootstrap));
const canModerateReviews = laravelBootstrap.user.reviewPermissions.includes('reviews.moderate');
const canBulkModerateReviews = laravelBootstrap.user.reviewPermissions.includes('reviews.bulk_moderate');
const canViewReviewAudit = laravelBootstrap.user.reviewPermissions.includes('reviews.audit.view');
```

Load once with cancellation protection as existing catalog/page loaders do. After a successful single or bulk action, call `refreshReviews`; retain `reviewFilterProductId` and selection as UI-only state.

- [ ] **Step 6: Run TypeScript, adapter and route-state checks**

Run: `npm --prefix resources/admin run lint; npm --prefix resources/admin test -- reviewData.test.ts adminRoutes.test.ts`

Expected: PASS with no `mockReviewsData` or review localStorage key added.

- [ ] **Step 7: Commit Task 2 only**

```powershell
git add resources/admin/src/integration/reviewData.ts resources/admin/src/integration/reviewData.test.ts resources/admin/src/types/index.ts resources/admin/src/integration/adminRoutes.ts resources/admin/src/integration/adminRoutes.test.ts resources/admin/src/context/AdminContext.tsx
git commit -m "feat: bind admin review state to Laravel"
```

## Task 3: Port the approved Admin review surfaces and retire manual social proof

**Files:**
- Create: `resources/admin/src/components/catalog/ProductReviewsAdmin.tsx`
- Modify: `resources/admin/src/App.tsx`
- Modify: `resources/admin/src/components/layout/AdminSidebar.tsx`
- Modify: `resources/admin/src/components/catalog/CatalogHub.tsx`
- Modify: `resources/admin/src/components/catalog/ProductExtraInfoEditor.tsx`

**Interfaces:**
- Consumes Task 2’s `reviews`, `refreshReviews`, single/bulk action functions, `reviewFilterProductId`, route state and permission booleans.
- Produces the official source-faithful `/admin/catalog/reviews` surface and read-only product-editor metrics/deep link.

- [ ] **Step 1: Add a failing route/dispatch assertion**

```ts
expect(appSource).toContain("case 'catalog-reviews':");
expect(sidebarSource).toContain("{ id: 'catalog-reviews' as NavSection, label: 'Product Reviews' }");
expect(catalogHubSource).toContain("pendingReviewsCount");
```

- [ ] **Step 2: Run the focused Admin test to verify it fails**

Run: `npm --prefix resources/admin test -- catalogHubMetrics.test.ts`

Expected: FAIL after adding the dedicated source assertions because the route/component is not wired.

- [ ] **Step 3: Extract and port `ProductReviewsAdmin.tsx` exactly from the approved ZIP**

Copy only `src/components/catalog/ProductReviewsAdmin.tsx` from `jw-ecommerce-dashboard (11).zip`. Preserve every selected wrapper, class, copy string, icon, drawer, modal, filter and responsive behavior. Replace only imports and context fields with Task 2 interfaces:

```ts
const { reviews, refreshReviews, moderateReview, bulkModerateReviews, canModerateReviews, canViewReviewAudit } = useAdmin();
```

Do not import `INITIAL_PRODUCT_REVIEWS`, do not add localStorage, and disable/hide actions when the bootstrap permission is absent.

- [ ] **Step 4: Wire source-faithful navigation and live counts**

Add `ProductReviewsAdmin` to `App.tsx`; mark `catalog-reviews` inside the Catalog active group; add the exact source sidebar menu row/badge and Catalog Hub review card. The pending count must derive from the loaded real list.

- [ ] **Step 5: Replace Product Editor’s mutable review controls with the ZIP read-only region**

Copy the selected review region from `ProductExtraInfoEditor.tsx` in the same approved ZIP. Remove handlers/UI for `handleLoadSampleReviews`, add/delete seed review, and inputs bound to `ratingScore`/`reviewCount`. Compute published/pending/rejected counts from `reviews` by `productId`; the deep-link sets `reviewFilterProductId` then `setCurrentSection('catalog-reviews')`.

- [ ] **Step 6: Run Admin build and tests**

Run: `npm --prefix resources/admin run lint; npm --prefix resources/admin test; npm --prefix resources/admin run build`

Expected: PASS. Confirm the build manifest emits the Admin entry and no compilation error changes nearby catalog UI.

- [ ] **Step 7: Commit Task 3 only**

```powershell
git add resources/admin/src/components/catalog/ProductReviewsAdmin.tsx resources/admin/src/App.tsx resources/admin/src/components/layout/AdminSidebar.tsx resources/admin/src/components/catalog/CatalogHub.tsx resources/admin/src/components/catalog/ProductExtraInfoEditor.tsx
git commit -m "feat: add source faithful review moderation UI"
```

## Task 4: Bind the PDP to published operational reviews and remove legacy social-proof consumption

**Files:**
- Modify: `app/Http/Controllers/StorefrontController.php`
- Modify: `resources/views/storefront/show.blade.php`
- Modify: `app/Http/Controllers/Admin/CatalogApiController.php`
- Modify: `tests/Feature/ProductReviewOperationalClosureTest.php`
- Modify: `tests/Feature/StorefrontPdpRichContentTest.php`

**Interfaces:**
- Consumes `ProductReview::published()` with authorized public media behavior and the selected source region in `PDPPage.tsx`.
- Produces a PDP view model whose rating/count/reviews derive only from `ProductReview` status `published`.

- [ ] **Step 1: Write failing public-boundary tests**

```php
$pending = $this->pendingReviewFor($customer);
$published = $this->publishedReviewFor($customer);

$html = $this->get('/products/review-product')->assertOk()->getContent();
$this->assertStringContainsString($published->title, $html);
$this->assertStringNotContainsString($pending->title, $html);
$this->assertStringNotContainsString('142 Reviews', $html);
```

- [ ] **Step 2: Run PDP tests to verify they fail**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\ProductReviewOperationalClosureTest.php tests\Feature\StorefrontPdpRichContentTest.php`

Expected: FAIL where legacy manual social proof remains accepted or the PDP source structure differs.

- [ ] **Step 3: Constrain the controller relation and make public media eligibility explicit**

```php
'reviews' => fn ($query) => $query->published()
    ->with(['media' => fn ($media) => $media->whereNotNull('published_at')])
    ->oldest('published_at'),
```

If the existing media publishing service does not set `published_at`, add the smallest service-level transition update and its test before enabling PDP image rendering. Never use the private Admin media URL in Blade.

- [ ] **Step 4: Port only the selected `PDPPage.tsx` rating/reviews structures**

Keep the approved rating row/accordion wrappers/classes/copy. Replace React product values only with `$pdpRating`, `$pdpReviewCount`, and the published collection. Render each review’s actual star count; show the system marker `Verified Studio Buyer`; hide both regions when count is zero.

- [ ] **Step 5: Retire legacy Admin contract writes**

Remove `ratingScore`, `reviewCount`, and seed-review validation/persistence from the Catalog API’s product PDP content contract. Leave historical columns/tables untouched. Update old rich-content tests so they verify non-consumption rather than manual social proof.

- [ ] **Step 6: Re-run PDP and review closure tests**

Run: `C:\xampp\php\php.exe artisan test tests\Feature\ProductReviewOperationalClosureTest.php tests\Feature\StorefrontPdpRichContentTest.php tests\Feature\Admin\CatalogPdpContentApiTest.php`

Expected: PASS; pending/rejected/legacy rows are absent from the public response and published rows determine rating/count.

- [ ] **Step 7: Commit Task 4 only**

```powershell
git add app/Http/Controllers/StorefrontController.php app/Http/Controllers/Admin/CatalogApiController.php resources/views/storefront/show.blade.php tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/StorefrontPdpRichContentTest.php tests/Feature/Admin/CatalogPdpContentApiTest.php
git commit -m "feat: publish verified reviews to PDP"
```

## Task 5: Verify the real end-to-end lifecycle and source fidelity

**Files:**
- Create: `output/product-review-closure/` evidence screenshots (untracked)
- Modify: `docs/superpowers/specs/2026-08-24-product-review-operational-closure-design.md` only if closure status/evidence must be recorded after proof

**Interfaces:**
- Consumes complete Tasks 1–4 build and the authorized customer/admin accounts.
- Produces browser screenshots and an evidence-backed closure report; no mock evidence is accepted.

- [ ] **Step 1: Run the complete relevant automated suite**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\ProductReviewOperationalClosureTest.php tests\Feature\Admin\ProductReviewAuthorizationTest.php tests\Feature\StorefrontPdpRichContentTest.php
npm --prefix resources/admin run lint
npm --prefix resources/admin test
npm --prefix resources/admin run build
npm run build
```

Expected: every command exits successfully; record the exact count/output in the closure report.

- [ ] **Step 2: Capture before screenshots before state mutation**

Use the real browser to capture Account Purchase History, Admin `/admin/catalog/reviews` pending tab, and the target PDP before approval. Save under `output/product-review-closure/before-*.png`.

- [ ] **Step 3: Exercise submit -> approve -> PDP with fresh sessions**

1. Sign in as the supplied customer and submit a valid delivered-item review with one JPEG.
2. Refresh/re-login customer account and verify `Review submitted — pending approval`.
3. Sign in as an authorized Admin, open `/admin/catalog/reviews`, inspect drawer/audit, then publish the record.
4. Refresh the PDP in a separate Storefront session; verify the exact review/rating/count and `Verified Studio Buyer` are visible.
5. Capture `after-account.png`, `after-admin-published.png`, and `after-pdp-published.png`.

- [ ] **Step 4: Exercise rejection, re-submission, batch and authorization branches**

1. Reject a pending review with a source modal reason; verify event and public absence.
2. Re-submit from the same delivered order item; assert a new attempt number and preserved rejected audit history.
3. Select two pending reviews and batch publish; verify per-item results share a batch UUID.
4. Sign in as a `reviews.view`-only account, verify actions/audit/media obey the assigned permissions and API denies ungranted operations.

- [ ] **Step 5: Compare source fidelity at desktop and mobile**

Capture the selected Admin and PDP regions at desktop and mobile widths, comparing the newest ZIP baselines with Laravel screenshots. Inspect spacing, typography, control order, filter/tabs, empty state, drawer/modal, and accordion behavior. Fix only material integration differences by returning to the owning task; do not redesign.

- [ ] **Step 6: Update the mapping status and commit evidence documentation**

Mark each mapping row `CLOSED` only when its required persistence, authorization and downstream evidence exists. Leave any unproven media-publication rule as `BACKEND_CONTRACT_GAP`; do not claim operational closure.

```powershell
git add docs/superpowers/specs/2026-08-24-product-review-operational-closure-design.md
git commit -m "docs: record product review closure evidence"
```
