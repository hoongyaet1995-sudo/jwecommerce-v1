# Posts and Calendar Integration Plan
Goal: approved UI backed by owned MySQL drafts, per-destination outcomes, shared calendar and explicit schedule revisions; no live publishing.
Architecture: MarketingPost document with destination state snapshot, unique per-revision delivery attempt records; service transactions with optimistic version checks. Existing MarketingPermission and merchant connection scoping. Dedicated controller and approved sample UI adapter. No provider publisher registered.
Spec: docs/superpowers/specs/2026-10-28-posts-calendar-prototype-design.md

- [x] Intentional TDD red: owned draft reload, foreign account rejection, permissions, schedule checks/version conflicts, cancellation, fake delivery idempotency and unknown outcome; Http::preventStrayRequests.
- [x] Add only Posts/attempt migrations and service/model/controller; owner-scoped queries; media limited to owned completed assets/shared catalog images; sanitize payload and output.
- [x] Port approved UI, replace localStorage/seed snapshots with server boot and writes; retain version on conflicts, editable unsaved work, English disclosure no real publishing. No user-facing fake-result controls.
- [x] Focused fake tests, shared-host safe routes, Blade checks. Apply only new migration locally. Normal browser draft/account/media/review/calendar checks without provider calls.
- [x] Record evidence and pending production publishing gates. No commits, push, deployment. User authorization supersedes skill commit steps; all implementation in canonical primary root.

Evidence and limits: docs/acceptance/2026-10-28-posts-calendar-local-integration.md. Normal-page authenticated acceptance limited to local actual data (zero connected accounts); real-account schedule remains fake-tested.
