# Buyer review operational closure

## Status and scope

This design covers one operational capability: a delivered-order buyer may submit one product review with up to five photos; authorized staff may review it singly or in bulk; every material action is append-only audited; only published reviews contribute to public PDP social proof.

The visual source of truth remains the user-supplied Google AI Studio ZIPs:

- Storefront ZIP SHA-256: `0395F83B6D5B1BFBE46D8D379E3E0F222EC7C1F3A660408E23816077AF226549`
- Admin ZIP SHA-256: `F37BFA1490A75C89E2A02F245AF807F920D3D6B1753B1081F36F477F63907977`
- Source extraction manifest: `docs/superpowers/reports/2026-08-23-product-reviews-source-extraction-manifest.md`

No ZIP mock data, manually set rating/count, or manually assigned `Verified Buyer` value is an operational source of truth.

## Explicit policy

1. Eligibility is evaluated on the server. The authenticated customer must own an `orders.status = delivered` order item. It may have one active `pending` or `published` review at a time.
2. A review starts `pending`. It becomes public only when an authorized staff member changes it to `published`.
3. `Verified Buyer` is a derived public presentation fact: a published review linked to the fulfilled order item. It is not editable.
4. A rejected review is excluded from all public output and aggregates. The customer account maps it back to the approved source's existing `Review this product` action and modal, allowing a new attempt without adding a customer-visible rejection state.
5. An order item may have many rejected attempts, but the server permits at most one `pending` or `published` attempt at a time.
6. Published reviews may be withdrawn to `rejected`; withdrawal is audited and immediately removes it from public aggregates. The owner may then submit a replacement attempt.
7. Customer-uploaded originals are private. At publication, each attached image may be exposed through an authorized public derivative. No public response exposes a private original path.

## Data model

### `product_reviews`

One immutable attempt. `order_item_id` is not globally unique; a composite unique key on `order_item_id` and `attempt_number` preserves attempt order. The submission service locks the order item and enforces the single-active-attempt rule.

- `id`
- `product_id`, `product_variant_id` nullable for historical variant deletion
- `order_id`, `order_item_id`, `customer_id`
- `attempt_number`
- immutable purchase snapshots: `order_number`, `product_title`, `variant_title`
- customer snapshot: `display_name`
- `rating` (1–5), `title`, `comment`
- `status` (`pending`, `published`, `rejected`)
- `submitted_at`, `published_at`, `rejected_at`, `withdrawn_at`
- `moderated_by_user_id` nullable and `moderation_reason` nullable
- timestamps

The review writes only against a database transaction that locks the matching order item and verifies its order/customer/status relationship. It never accepts a product, variant, buyer display name, or verified flag as a client authority. The lock rejects a new submission when another attempt is `pending` or `published`; rejected attempts remain immutable historical records.

### `product_review_media`

- `id` UUID
- `product_review_id`
- private original: `disk`, `original_path`, `original_name`, `mime_type`, `size`, `width`, `height`, `checksum`
- publication derivative: `public_path` nullable, `public_url` nullable
- `position`, timestamps, `deleted_at`

Allowed types are JPEG, PNG, and WebP. Initial limits: maximum five files per review, five MiB per file, 20 megapixels per image. File content, extension, raster dimensions, checksum, and trailing-data boundary are server validated. Originals use a non-public disk. Derivatives are created only after publication and removed/unpublished when the review is withdrawn.

### `product_review_events`

Append-only ledger with no update/delete application endpoint.

- `id`
- `product_review_id`
- `event_type` (`submitted`, `media_attached`, `media_removed`, `published`, `rejected`, `withdrawn`, `bulk_published`, `bulk_rejected`)
- actor polymorphism represented by `actor_type` (`customer`, `staff`, `system`) and nullable `actor_id`
- `batch_id` UUID nullable
- `from_status`, `to_status`, `reason` nullable
- safe structured `metadata` JSON (review/media IDs and counts only; no private paths or request secrets)
- `created_at`

Events are inserted in the same transaction as the state mutation. Audit reads are chronological and read-only.

### RBAC persistence

The current `is_admin` gate remains the outer Admin boundary. Add durable staff permission ownership through a canonical `user_permissions` table keyed by `user_id` and permission key, and expose only server-computed grants to Admin bootstrap.

Permissions:

| Permission | Capability |
| --- | --- |
| `reviews.view` | list, filter, inspect review/order context and approved/private media |
| `reviews.moderate` | publish, reject, withdraw one review |
| `reviews.bulk_moderate` | perform bulk publish/reject, in addition to single moderation |
| `reviews.audit.view` | read immutable review audit history |

Super Admin has all grants. No client-side permission value authorizes an API operation.

## Services and authorization

`ProductReviewEligibilityService` computes a customer’s eligible items from order snapshots and existing reviews.

`ProductReviewSubmissionService` owns customer submission, validation, private media storage, order-item locking, unique eligibility enforcement, and event insertion.

`ProductReviewModerationService` owns publish/reject/withdraw and bulk moderation. It locks each target review, rechecks permission and state, produces explicit per-review success/failure results, recomputes publication effects transactionally, and appends events.

Policies enforce ownership of customer reads/submissions and permission grants for Admin reads/moderation/audit/media. Controllers remain thin API adapters.

## API and UI adapters

Customer APIs operate behind `auth:customer` and return only the logged-in customer’s review eligibility/status:

- read account order review states
- submit one review as multipart form data
- retrieve owned private review media

Admin APIs operate behind `auth` + `admin` + review permission middleware/policies:

- list/filter review records with approved read model
- retrieve one review including audit history and private media URLs only when authorized
- single and bulk moderation
- read product-level computed review summary

The existing Google source regions are ported source-faithfully:

- `PurchaseHistoryTab.tsx`: delivered-item action, review modal, required field states, pending/published status, and customer image controls.
- `ProductReviewsAdmin.tsx`: list, filters, review drawer, media gallery, single/bulk moderation, audit-history panel.
- `CatalogHub.tsx`, `AdminSidebar.tsx`, `App.tsx`: review navigation and pending count.
- `ProductExtraInfoEditor.tsx`: read-only summary and moderation deep link; manual ratings/review creation are removed.

Only Blade/Alpine or React adapter bindings may be added around source structure/classes. The official account/Admin routes are not visually replaced until isolated-preview acceptance.

## Public output and aggregates

The PDP query loads only `published` reviews with public media derivatives. The average rating and count are computed from those rows; stored manual `ProductPdpContent.rating_score`, `review_count`, and hand-authored reviews are dormant and excluded. The PLP remains free of rating claims unless a separately approved source component is bound to these same aggregates.

## Failure and concurrency behavior

- Customer submission is rejected with a stable conflict/validation response while another attempt is pending or published. A rejected attempt instead makes the existing source `Review this product` action available again.
- Non-delivered, cancelled, and non-owned items are denied server side regardless of rendered UI.
- A disallowed/oversized/corrupt upload is rejected before review creation, leaving no persisted media or partial review.
- Each bulk target is independently locked and evaluated. The response reports accepted and rejected item IDs/reasons; no partial operation is silently represented as a full success.
- A reviewer whose grant changes during a request is denied by the server authorization check.
- Failed derivative generation keeps the source private and the review non-public until completion; it never publishes a broken public asset.

## Verification requirements

1. Feature tests: eligibility, ownership, duplicate prevention, invalid media, private-media denial, all permission combinations, audit event append-only behavior, single/bulk race-safe moderation, published-only aggregation.
2. Source fidelity: desktop/mobile source and isolated Laravel preview screenshots; source modal/list/drawer interaction checks; no DOM/class redesign.
3. Persistence: refresh and separate-session checks for customer state, staff moderation, public PDP aggregate, and audit history.
4. Browser acceptance: real customer submission with test media, staff single and bulk moderation, audit inspection, public PDP result, console errors zero.

## Explicit exclusions

- Real third-party content moderation, malware scanning service, image transformation CDN, notifications, helpful-vote counters, review editing, and external syndication.
- Any manually seeded, imported, or staff-created buyer review.
- Any publicly accessible private original image.
