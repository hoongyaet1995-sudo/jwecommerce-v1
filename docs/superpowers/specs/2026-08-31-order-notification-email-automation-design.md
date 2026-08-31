# Order Notification Email Automation Design

Date: 2026-08-31
Status: Approved design; implementation not started
Convergence mode: `OPERATIONAL_CLOSURE`

## 1. Objective

Replace the Google AI Studio notification-template mock with a source-faithful Admin UI backed by Laravel, MySQL, the Laravel queue, and real email delivery. Customer notifications are emitted for customer-relevant business milestones rather than for every database write or EasyParcel webhook.

This phase closes Email only. WhatsApp templates remain editable and previewable, but WhatsApp sending is dormant and must never report simulated success.

## 2. Approved Product Decisions

- Automatic email uses business-milestone deduplication.
- A normal milestone is sent at most once per order and channel.
- Each independent Bank In / DuitNow payment-proof rejection review may send its own rejection email.
- EasyParcel scan/status updates update the JW Ecommerce tracking timeline but do not each generate email.
- Every customer email includes the JW-owned tracking destination `https://ecom.jwaistudio.online/order-tracking`.
- Customer tracking continues to require order number, matching email, and OTP verification.
- Order/payment/shipping state commits even when email delivery fails. Email is queued and retryable.
- Authorized staff may retry a failed email and resend an already sent email.
- A resend of an already sent email uses the original frozen content. Sending the current template is a separate explicit action.
- WhatsApp editing and preview remain visible; sending and test sending show `Not connected` and create no success audit.
- Payment-proof re-upload is excluded. Rejection email instructs the customer to contact Support and links to order tracking; it must not claim that re-upload is available.

## 3. Explicit Scope

### Included

- Source-faithful Notification Template Management UI in Store Settings.
- Source-faithful notification controls and preview in Admin Order Detail.
- MySQL template ownership and template defaults.
- Email rendering, queue dispatch, deduplication, delivery audit, retry, resend, and test email.
- Integration with every approved order/payment/fulfillment milestone producer.
- Customer-facing branded HTML email containing the JW order-tracking link.
- Authorization, validation, rate limiting, failure handling, tests, and local browser acceptance.

### Excluded

- WhatsApp API and WhatsApp delivery.
- Customer Payment Proof re-upload after rejection.
- Email for every EasyParcel scan, location, webhook, or polling response.
- A new configurable Order Status engine.
- Marketplace notifications, campaigns, newsletters, and abandoned-cart automation.
- GitHub/Hostinger synchronization until a separate post-localhost approval.

## 4. Google AI Studio Source Extraction Manifest

### Request boundary

- ZIP: `C:\Users\User\Downloads\jw-ecommerce-dashboard (18).zip`
- ZIP SHA-256: `52F1146A1E1DBD9C6D43F8C6BFC40DA7901F40A1CB93FA66772D47E9EF4428CE`
- Laravel project: `C:\xampp\htdocs\jwstudio-ecommerce`
- Requested regions:
  - Store Settings `Auto Email & WhatsApp Template` panel.
  - Admin Order Detail notification selection and template preview.
- Excluded ZIP regions: all unrelated settings, storefront, marketplace, catalog, reporting, AI, payment, and shipping surfaces.
- Raw ZIP/extracted source remains private and outside the repository/deployment.

### Selected source

| Source path | SHA-256 | Role | Laravel target |
|---|---|---|---|
| `src/components/settings/NotificationTemplateSettingAdmin.tsx` | `1FD34A0824198ADE18E9DFE5012F464EA4093D1CC108AFF01876164E5254203B` | Template editor and preview | Admin React settings component |
| `src/components/settings/ShopSettingsHub.tsx` | `F729E96DE1943E9AD21E26CD854DBCD4CAEA2E77A3FE50B2033545BDABEE3642` | Settings navigation/mount | Existing Admin settings hub |
| `src/components/orders/OrderAdmin.tsx` | `957876FA7FD7A7640D40196FBBBC74D606A1B975BB895029ED4A253D42976F97` | Order notification controls/preview | Existing Admin Order Detail |
| `src/data/notificationTemplatesData.ts` | `B96518A0097AFCE7091F07146A656932E14A51FE0353BE5AD36EE91C13E5659F` | Defaults and variable catalog | Seed/default contract |
| `src/types/notifications.ts` | `BBB1FBF2A535BD150E5B7F4EF2EA16F652316AF6CC062DD4071749654EA397EE` | UI type contract | Admin integration types |
| `src/context/AdminContext.tsx` | `23DE83AC3FC562B49FEF188919E0F8EDF06149AFB5F3B602DBB47958EB35AC5C` | Mock state/interaction reference only | Narrow Laravel adapters; do not import whole context |

### Source baseline

- `npm run lint`: passed.
- `npm run build`: passed; Vite reported only a Node engine warning and large-chunk warning.
- Desktop settings editor, mobile settings editor, Order Detail, and template-preview interactions rendered successfully in a real browser.
- The only observed source console error was a missing `favicon.ico`.
- Source mock ownership:
  - templates persist in `localStorage`;
  - test sending produces a simulated toast;
  - Order Detail appends simulated Email/WhatsApp timeline entries.

### Fidelity rules

- Preserve selected DOM hierarchy, wrappers, Tailwind classes, responsive composition, copy ownership, variable-chip interaction, desktop/mobile preview, and existing visual language.
- Add data values, disabled state, real status state, and events at the narrowest boundaries.
- Do not import unrelated ZIP files or replace unrelated Admin surfaces.
- WhatsApp `Not connected`, Email-only checkbox copy, delivery status, Retry, and Resend are approved operational micro-states using exact existing component patterns.

## 5. Current-State Root Cause

The ZIP is not an operational notification system. Templates and custom statuses are browser-owned, send actions are simulated, and timeline success records are fabricated. The current Laravel application only queues tracking OTP email. Order milestones are produced independently by Admin status mutation, payment-proof review, hosted-payment webhooks, manual dispatch, EasyParcel booking/reconciliation, shipment tracking, and cancellation. No shared notification orchestration, deduplication record, rendered snapshot, or delivery audit exists.

The source also contains mismatches that the adapter must resolve without redesign:

- `payment_rejected` has a default template but is omitted from the rendered seven-status matrix because the component derives rows only from mock custom statuses.
- `Packed & AWB Generated` is a shipment milestone, not a Commerce Order status.
- provider `on_hold` is a shipment exception, not an active Commerce Order status.
- `{payment_proof_upload_url}` has no customer upload contract after checkout.

## 6. Architecture

```text
Approved business mutation
  -> OrderNotificationMilestoneService::record(...)
  -> notification_dispatches row in the same DB transaction
  -> transaction commits
  -> SendOrderNotificationJob after commit
  -> frozen template snapshot rendered as Email
  -> queued / sending / sent / failed audit state
```

### Components

- `NotificationTemplate`: persisted operator-managed template configuration.
- `NotificationDispatch`: transactional outbox, deduplication owner, frozen message snapshot, delivery audit, retry/resend lineage.
- `NotificationEventCatalog`: fixed system-owned event keys, labels, available variables, and channel eligibility.
- `OrderNotificationMilestoneService`: single API used by every producer; creates a dispatch intent or returns the existing deduplicated intent.
- `NotificationTemplateRenderer`: allowlisted variable replacement and safe CTA resolution.
- `SendOrderNotificationJob`: after-commit asynchronous delivery and bounded retry.
- `OrderStatusNotificationMail`: branded HTML email shell.
- Protected Admin APIs: template collection/update/reset, test send, dispatch audit, failed retry, sent resend, and current-template manual send.

No Controller or provider service sends Mail directly.

## 7. Canonical Notification Event Catalog

| Event key | Producer | Automatic idempotency scope | Template/source status |
|---|---|---|---|
| `order_created` | successful Checkout creation | order + event + email | Pending Payment |
| `payment_verified` | proof verification or verified hosted-payment webhook | order + event + email | Processing & Verified |
| `payment_proof_rejected` | proof rejection audit event | proof review event + email | Payment Proof Rejected |
| `ready_to_ship` | EasyParcel AWB first becomes available | order + event + email | Packed & AWB Generated |
| `shipped` | manual dispatch or first confirmed carrier acceptance | order + event + email | Dispatched (In Transit) |
| `delivered` | manual/provider first confirmed delivery | order + event + email | Delivered & Completed |
| `cancelled` | Commerce Order cancellation | order + event + email | Cancelled & Restocked |

Rules:

- `payment_verified` is not re-sent when the same paid order later enters internal warehouse processing.
- If the first observed provider state has progressed beyond carrier acceptance, the system may record the missing `shipped` milestone once; later scan updates remain timeline-only.
- EasyParcel AWB cancellation/rebooking does not imply Commerce Order cancellation and must not send `cancelled`.
- provider `on_hold`, `returned`, `in_transit`, `out_for_delivery`, location scans, no-op polls, and webhook retries update tracking/audit only.
- The On Hold template remains editable/previewable with Email disabled and is `DORMANT` in this phase.

## 8. Template Variables and Link Rules

Approved variables are allowlisted by event. Unknown, unavailable, or unresolved variables block enabling or sending a template.

- `{tracking_url}` and `{order_url}` resolve to the JW-owned absolute `/order-tracking` URL.
- No customer email exposes EasyParcel private URLs, tokens, label URLs, or storage paths.
- `{payment_proof_upload_url}` is unavailable in this phase. It cannot be used by an enabled template.
- Rejection defaults include `{payment_proof_reason}`, Support instructions, and the JW order-tracking link.
- All variable content is HTML-escaped. Newlines are converted safely by the email view.
- CTA accepts approved placeholders, application-owned relative paths, `https`, and allowed `mailto` values only. Script/data protocols, URL credentials, and malformed URLs are rejected.

## 9. Persistence Model

### `notification_templates`

- `id`
- unique `event_key`
- display metadata: `status_name`, `color`
- Email: `email_enabled`, `email_subject`, `email_preheader`, `email_heading`, `email_body`, `email_cta_text`, `email_cta_url`
- WhatsApp: `whatsapp_enabled`, `whatsapp_message`, `whatsapp_cta_buttons`
- `version`
- `updated_by`
- timestamps

### `notification_dispatches`

- public identifier and `order_id`
- optional `notification_template_id`
- `channel`
- `event_key`
- polymorphic/logical source type and source identifier
- unique automatic `idempotency_key`
- recipient snapshot
- rendered subject/preheader/headline/body/CTA snapshot
- template version and variable snapshot
- state: `queued`, `sending`, `sent`, `failed`
- attempt count, queued/sent/failed timestamps, safe last error code, optional provider message identifier
- source: `automatic`, `admin_current_template`, `manual_resend`, `test`
- requested actor
- optional `resend_of_id`
- timestamps

The frozen snapshot is the historical truth. Later template edits do not rewrite prior communications.

## 10. Delivery, Failure, Retry, and Resend Semantics

- Business mutation and dispatch intent are committed atomically.
- Queue work starts only after commit.
- Email failure never rolls back payment, order, shipment, or proof-review state.
- Automatic events use a unique idempotency key and cannot create a second automatic dispatch.
- A failed job uses bounded exponential retry. Exhaustion marks the dispatch `failed`.
- `Retry` reuses a failed dispatch and frozen snapshot.
- `Resend` is authorized manual intent, creates a new dispatch linked to the original, and uses the original frozen content.
- `Send Current Template` creates a distinct manual dispatch from the current template and preview.
- Manual actions never alter the automatic deduplication record.
- The system guarantees one automatic send intent per deduplication key. SMTP acceptance followed by an ambiguous network failure can still produce a rare provider-level duplicate; delivery state and bounded retry minimize this risk without falsely claiming mathematical exactly-once delivery.

## 11. Admin UI Contract

### Store Settings

- Load/save/reset templates through protected Laravel APIs.
- Preserve source status matrix, editor, variables, previews, and responsive behavior.
- Render the payment-proof rejection template in the matrix.
- Email test sends are real queued tests and clearly labeled.
- WhatsApp editing/preview works; send/test controls show `Not connected` and do not create success records.
- Reset requires confirmation and records the actor.
- Unavailable variables cannot be saved in an enabled template.

### Order Detail

- Status selection loads the persisted matching template.
- Checkbox copy is Email-only.
- Preview uses the actual order view model and current note.
- Mutation success shows the real dispatch state, initially `Queued`, not fabricated `Sent`.
- Notification timeline reads `notification_dispatches` and displays subject, channel, state, and relevant timestamps.
- Failed items expose `Retry`; sent items expose `Resend`.
- A separate `Send Current Template` action uses the live preview/current template.
- UI does not display private provider URLs, internal exception traces, secrets, or customer data beyond the authorized order context.

## 12. Authorization and Operational Safety

- Template view/manage, test send, delivery view, retry, resend, and current-template send use explicit Admin permissions.
- Test recipient is validated, rate-limited, audited, and excluded from order business timeline.
- Logs contain structured actor/dispatch/order/action/result fields but not SMTP secrets, full rendered bodies, or private provider URLs.
- Public APIs never expose template internals or dispatch recipient data.
- Queue Worker configuration is required in localhost acceptance and Hostinger release runbooks.
- Secrets remain in environment configuration and never enter Git.

## 13. Commerce Closure Mapping

| Feature ID | Owner/truth | Producer -> consumer | Status before work | Closure evidence |
|---|---|---|---|---|
| `notifications.template.manage` | operator-managed / MySQL | Settings -> protected API -> template | `MOCK_ONLY` | auth, validation, persistence, cross-session browser proof |
| `notifications.email.render` | system-managed / dispatch snapshot | event context -> renderer -> email HTML | `BACKEND_CONTRACT_GAP` | variable/escaping/CTA tests and rendered mail proof |
| `notifications.email.automatic` | system-managed / MySQL outbox | milestone producer -> queue -> recipient | `BACKEND_CONTRACT_GAP` | each producer, dedup, failure, and Mail assertions |
| `notifications.email.audit` | internal-only / MySQL | dispatch lifecycle -> Admin timeline | `MOCK_ONLY` | queued/sent/failed persistence and non-exposure tests |
| `notifications.email.retry` | operator-managed / MySQL | failed dispatch -> retry -> same snapshot | `BACKEND_CONTRACT_GAP` | permission and retry tests |
| `notifications.email.resend` | operator-managed / MySQL | sent dispatch -> linked manual dispatch | `BACKEND_CONTRACT_GAP` | frozen snapshot and audit tests |
| `notifications.email.test` | operator-managed / MySQL audit | Settings modal -> queued test | `MOCK_ONLY` | rate limit, validation, permission, Mail fake/local sink |
| `notifications.whatsapp.templates` | operator-managed / MySQL | Settings editor -> persisted template | `MOCK_ONLY` | persistence and disabled-send browser proof |
| `notifications.whatsapp.delivery` | external provider absent | none | `DORMANT` | must show Not connected and no success records |
| `notifications.proof_reupload` | no approved producer/contract | rejected email -> no upload consumer | `DORMANT` | explicitly excluded; no fake CTA or active upload claim |

No capability may be called `CLOSED` until its persistence, authorization, behavior, and real-browser downstream evidence pass.

## 14. Test Plan

### Backend

- Template collection/update/reset authorization and validation.
- Unknown/unavailable variables, unresolved replacements, unsafe CTA, and invalid content fail safely.
- One automatic dispatch for each regular milestone.
- A new dispatch for every distinct proof rejection event.
- Duplicate webhook, polling, and no-op transitions create no duplicate dispatch.
- Order/payment/shipment transaction success with queued mail; rollback creates neither business mutation nor dispatch.
- Email failure does not roll back business state.
- Bounded retry, failed Retry, sent Resend, frozen snapshot, current-template send, and actor audit.
- Customer emails contain JW `/order-tracking` and no EasyParcel private URL.
- WhatsApp actions create no simulated success.
- Public/non-admin users cannot read or mutate template/dispatch data.

### Producer coverage

- Checkout order creation.
- Admin Update & Notify, including unchecked notification.
- Bank In/DuitNow proof verified and repeated independent rejection reviews.
- Stripe, Billplz, and PayPal verified webhooks.
- Manual dispatch.
- EasyParcel booking/AWB reconciliation.
- EasyParcel first carrier acceptance, delivery, repeat webhook, status polling, and AWB cancellation/rebook behavior.
- Commerce Order cancellation.

### Frontend and browser

- Admin TypeScript lint, unit tests, production build.
- Settings template load/save/reset/test/WhatsApp-disabled states.
- Variable insertion and desktop/mobile preview fidelity.
- Order preview, queued/sent/failed timeline, Retry, Resend, and current-template send.
- Refresh, re-login, and separate-session MySQL convergence.
- Desktop/mobile comparisons against the ZIP baselines.

## 15. Acceptance and Release Gates

### Localhost

- Implement only in `C:\xampp\htdocs\jwstudio-ecommerce`.
- Use `Mail::fake()`, database queue, or a local mail sink; do not send real customer email.
- Complete source fidelity, automated tests, build, queue execution, and authenticated browser evidence.
- Report exact completed scope and remaining gaps.

### GitHub and Hostinger

- Require a fresh explicit synchronization approval after localhost acceptance.
- Commit/push one accepted SHA to GitHub `main` and deploy that exact SHA to `/apps/jwecommerce`.
- Configure production SMTP and a durable Queue Worker outside Git.
- Use a dedicated test order and test mailbox only after explicit production-test approval.
- Verify queued-to-sent state, tracking link, audit persistence, Retry/Resend, and same-SHA evidence.
- WhatsApp remains `Not connected`.

## 16. Completion Language

- Before backend binding: `source-faithful preview complete` only.
- After real template binding but before producer/delivery proof: `real-data binding complete; closure gaps remain`.
- Only after every non-excluded blocking mapping row is proven: `Email notification automation operationally closed`.
