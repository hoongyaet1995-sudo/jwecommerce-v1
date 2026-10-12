# Home & Decor Theme Pack Implementation Plan

> For agentic workers: use superpowers:executing-plans inline, task by task.

**Goal:** Integrate approved PDP, Login, Register, Forgot Password, Account and Cart Drawer images into operational theme selection.
**Architecture:** Independent theme Blade renderers reuse shared controllers, explicit view data, inventory and cart services. Default Theme remains intact. Admin draft preview is read-only for identity/settings and account fixtures are non-sensitive.
**Tech Stack:** Laravel, Blade, Alpine, existing JS, MySQL.
**Spec:** docs/contracts/storefront-theme-data-contract.md plus approved output/industry-template-concepts/home-deco-*-concept.png images.

## Global constraints
- Localhost only; no commits/push/deployment without phase synchronization approval.
- Preserve dirty workspace and Default renderers.
- No sample business facts in live database. Scene imagery is theme decoration, never invented product media.
- Tests run only after clearing local config cache.
- Existing phase test ledger persists; 3/6/9 escalation applies.

## Review focus
- Unknown/disabled inventory, multi-option choices and authoritative price.
- Customer guard boundaries, ownership and password broker token validation.
- Zero/one/many gallery images, missing specs/policies and empty account/cart.
- Preserved payment proof/review/return/order history flows and snapshot currency.
- Mobile overflow, drawer refresh theme consistency, read-only Admin account previews.

- [x] Theme routing and surface preview contract; meaningful red test then implementation.
- [x] Independent PDP gallery/purchase/details layout using real data and existing hooks.
- [x] Auth editorial templates and complete customer reset broker flow; no live mail claims without delivery evidence.
- [x] Account overview/forms/wishlist and shared advanced order operations.
- [x] Cart drawer independent theme template with canonical hooks and summary.
- [x] Admin preview entry points and theme capabilities; build.
- [x] Regression tests, localhost desktop/mobile browser acceptance, screenshots and honest gaps.

Implementation/testing tasks completed. Operational acceptance remains PARTIAL for authenticated customer browser and real SMTP delivery; see existing phase ledger. No release.
