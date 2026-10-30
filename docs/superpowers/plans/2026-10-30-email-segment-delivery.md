# Email Segment Delivery Implementation Plan

Goal: source-reuse Email review, explicit confirmation, frozen recipients, background progress and history. Existing CRM UI and JW tracking/opt-out retained.
Architecture: bounded 1,000-recipient review snapshot encrypted at rest; per-campaign version dedupe. Queue jobs process at most 25 recipients and never retry unknown submissions. Separate bulk enable switch and known-price ceiling are required; both disabled/unknown by default. No real send, release or paid API in this phase.

- [x] Review freezes eligible subscribed recipients, template, sender and landing page. Exclude conflict, invalid, opt-out/global suppression. No queue before confirmation; no silent truncation.
- [x] Versioned confirmation rechecks campaign/template/segment, permission and explicit known cost ceiling. Double click reuses batch. Queue only after commit.
- [x] Worker uses frozen HTML/subject and original media, tracking and unsubscribe; recheck consent before each send. Unknown pauses; no retries. Background 25-item chunks, durable progress, state/history endpoint.
- [x] Internal verified-feedback boundary handles dedupe and terminal bounce/complaint; no unauthenticated public webhook invented. Real provider integration remains unavailable until configured.
- [x] Existing Email editor adds review CTA and progress panel using current cards/dialog/table styles. Full normal localhost fake acceptance; no real mail.

Ledger: initial TDD red declared before new feature test. Elapsed and token use unavailable unless measured.

TDD red: missing endpoint 404 (expected). First green attempt: review passed, confirm exposed Query Builder findOrFail unsupported; unexpected issue count 1, replaced with scoped find + explicit404.

Later targeted run: 24 pass, 1 fixture failure (422 validation). Campaign-save test passed read-model-only version fields to write API; corrected fixture to allowed template_id/segment_id. Separate fixture issue count 1.

MySQL gate: migration rejected non-null expires_at timestamp default under local MariaDB settings (issue count1). Changed required business dates to DATETIME, preserving explicit values and nullability. No existing tables/data changed by rejected CREATE.

## Local acceptance result
- Final focused suite: 84 tests / 585 assertions passed, 18.49 seconds. JS syntax and scoped diff check passed. Token usage unavailable.
- Actual local MySQL migration passed after DATETIME compatibility repair.
- Normal Chrome page, synthetic campaign 01a18bee-dd45-7360-8a3c-1e054df5b33a: 3 matched / 1 subscribed / 2 excluded. Review dialog displays configured sender, unknown cost, disabled confirm. Saved batch state review, cursor0, confirmed_at null; campaign deliveries0, marketing-email jobs0. No real email sent.
- Browser screenshot capture failed twice for full viewport; clip capture returned wrong region, discarded. Browser AX visibly confirmed review dialog and disabled Confirm. No screenshot-based visual parity claim.
- No release, push, production config or real-provider operation.

## Explicit remaining gates
- Real bulk remains false. Tariff defaults unknown; no permission to assume SMTP free.
- Internal feedback reducer is tested with fake verified receipts only. No public provider webhook exposed, actual provider authentication/correlation not yet integrated. Delivery/bounce feedback remains labelled not connected.
- First release bounded to 1,000 eligible contacts, worker chunks25; larger campaigns require separate performance gate.
- Email Settings next: sender identity, connection test, provider limits/pricing and authenticated bounce/complaint adapter. Reuse existing store/admin identity, no duplicate store or user database. Delivery pool is not part of this phase.
