# Posts & Calendar V2 — Functional Spec and Acceptance Scope

Status: scope approved for design; implementation and live publishing not started.
Canonical development: C:/xampp/htdocs/jwstudio-ecommerce.

## Objective
Merchants across industries prepare one content package, adapt it to connected destinations, confirm once, and track each publishing result. No product-specific logic. Preserve approved Bootstrap UI, English merchant-facing copy, existing posts/assets/accounts and shared Hub callbacks.

## Included
1. Choose connected accounts and see supported content formats before composing. Account identity is separate from publishing readiness.
2. Single image, ordered 2–10 image posts, or one existing video. Enforce actual per-platform limits; YouTube remains video-only. No mixed image/video carousel in V2. Select/upload images, reorder, remove, choose supported cover, preserve originals. Do not silently crop or discard incompatible media.
3. Shared copy/media with explicit per-destination overrides. Show inheritance state; restoring shared content must protect unsaved overrides. One account modification cannot silently change another.
4. AI copy assistant inside composer: optional product/service context, confirmed facts, purpose, audience, language, tone and selected accounts. Custom-topic posts also supported without a catalog item. No requirement for four product angles to write copy or publish ordinary posts. Missing facts are not invented. Prices, discounts, efficacy, guarantees and specs require supplied evidence.
5. AI generates shared copy plus platform adaptations subject to character limits. Preview proposed changes, edit or apply selected proposals, preserve originals and unselected fields. Generating does not save over original, publish, schedule, or create imagery. Provide shorten, adjust tone and CTA actions. Expose cost/allowance before paid work; no automatic paid retry; paid generation needs explicit authorization.
6. Platform-aware preview, image navigation/count, selected cover and full video playback. Preview is illustrative, not a claim of exact native rendering.
7. Unified validation before confirmation: account authorization, media readiness/ownership, format/count/ratio, copy limits, required visibility/disclosure/audience fields. Errors link to relevant field; drafts can remain incomplete.
8. Confirm destination-specific content, local time/timezone and immediate or scheduled publishing. Background work continues after navigation. Scheduled edit/cancel and duplicate clicks do not cause duplicate publishing. Token refresh uses existing grants; reauthorization only if needed.
9. Destination-level progress, partial success, failure and unknown result. Preserve successful results; query known tasks before any resend. Explicit approval for a new submission. View on platform only when a valid real post link is available.
10. Posts/Calendar share records and status; filter by account/status, inspect/edit/cancel pending schedules. Technical details stay expandable, not primary user actions.

## Deferred
AI image/video generation; mixed-media carousel; Stories; trending music/stickers; advanced media editing; recurring/bulk scheduling; best-time recommendations; team approval workflow; Campaign planning; Analytics. No Canva/ComfyUI/Blender installation or integration in this phase.

## Acceptance gates
A. Complete interactive prototype before business-code implementation: account/format selection, ordered multi-image media, AI propose/apply, shared/override copy, platform preview, validation, schedule confirmation, processing, partial success, expired authorization and unknown-result flows.
B. Fake-provider and fake-LLM checks: factuality and length validation, missing facts, selected-only adoption, original preservation, inheritance, order/cover persistence, unsupported combinations, revoked access, stale revision, cancel/edit invalidation, expired-token refresh, duplicate clicks/jobs, partial successes, unknown submissions. No external calls.
C. Normal-page local verification: draft save/reload, originals retained, desktop/mobile layouts, keyboard and labeled controls, progress updates, clear next CTA. Do not label fake results as real provider success.
D. After approval and scoped deployment, a fixed live test matrix defines account, platform, source assets, final copy, visibility, scheduling time, retention and permitted attempts before publishing. Verify native content/order/link and delayed execution with browser closed; actual token-expiry scheduling coverage required. TikTok domain/app/account prerequisites must pass. No automatic resend, paid API or unapproved additional posts.

## Completion criteria
V2 is accepted only when included scope has evidence for applicable gates and platform exceptions are explicitly listed. New discoveries map to existing requirements; new capabilities go to the next phase rather than silently expanding scope. Current V1 evidence does not imply all-platform scheduling or multi-image acceptance.
