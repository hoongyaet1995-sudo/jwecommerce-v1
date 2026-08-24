# Task 2 Report

## Status

- Implementation: complete in the working tree.
- Verification: complete.
- Commit: partial Task 2 commit created only from separable hunks; overlapping shared-header files remain unstaged by design.

## Review Resolution

- P1: not a defect.
  - The approved Google AI Studio `Navbar.tsx` itself uses `hidden sm:flex` for the left announcement zone and `hidden md:flex` for the right announcement zone.
  - Source fidelity governs here, so those exact responsive visibility states remain correct even though the earlier task brief wording was broader.
- P2: fixed in test coverage.
  - Added a shared-header public-route regression that seeds an enabled live shell configuration and verifies announcement content across the customer-visible shared-header route matrix.

## Root Cause

The shared storefront nav still hardcoded the announcement bar copy inside `resources/views/components/storefront/nav.blade.php`, so saved `StorefrontShellSettingsService::live()` data never reached storefront routes.

## Red / Green Evidence

- Red command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontAnnouncementBarTest.php`
  - Result before implementation: `FAIL`
  - Failure reason: `/shop` still rendered the static announcement strings `COMPLIMENTARY SHIPPING`, `WELCOME10`, and `Worldwide Express Dispatch` instead of the saved live shell payload.
- Green command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontAnnouncementBarTest.php tests/Feature/StorefrontCanonicalHeaderTest.php`
  - Result after implementation: `PASS` with `7 passed (213 assertions)`.
- Review-fix red command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontAnnouncementBarTest.php`
  - Result before the safe route provider/fixture setup: `FAIL`
  - Failure reason: the new cross-route regression hit `404` because product/order/CMS fixtures for the full shared-header route matrix were not seeded yet.
- Review-fix green command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontAnnouncementBarTest.php`
  - Result after adding the safe route provider/fixtures: `PASS` with `3 passed (123 assertions)`.

## Working-Tree Changes

- Modified: `app/Providers/AppServiceProvider.php`
- Modified: `resources/views/components/storefront/nav.blade.php`
- Created: `tests/Feature/StorefrontAnnouncementBarTest.php`
- Modified: `tests/Feature/StorefrontCanonicalHeaderTest.php`

## Implementation Notes

- Added a shared view composer for `components.storefront.nav` so the header consumes `StorefrontShellSettingsService::live()` once at the shell boundary.
- Replaced only the announcement bar data branches in the shared nav:
  - left zone shows saved text/badge and optional link
  - center zone shows saved text and optional coupon/link
  - right zone shows helper text and optional tracking / WhatsApp links
- Hidden zones and globally disabled shell state now suppress the synthetic announcement copy instead of rendering fallback text.
- Updated the canonical header regression to match the approved contract: absent announcement settings should not force synthetic copy.
- Added shared-header announcement route coverage in `tests/Feature/StorefrontAnnouncementBarTest.php` using seeded product, order, CMS page, and header-menu fixtures so the saved shell config is asserted across Home, Shop, PDP, Cart, Checkout, Success, Login, and public CMS routes.

## Commit Isolation Decision

I re-ran Task 2 staging as patch-based cached hunks and verified which files were separable from unrelated existing work:

- `app/Providers/AppServiceProvider.php`: separable
  - staged only the `StorefrontShellSettingsService` import and the `components.storefront.nav` view composer.
- `tests/Feature/StorefrontAnnouncementBarTest.php`: separable
  - staged the full new regression test file.
- `resources/views/components/storefront/nav.blade.php`: not separable
  - the announcement consumer edits sit inside a large pre-existing uncommitted canonical-header rewrite; staging only the Task 2 lines would leave an invalid hybrid file in the index, and staging the whole file would capture unrelated header work.
- `tests/Feature/StorefrontCanonicalHeaderTest.php`: not separable
  - the file is entirely untracked in this worktree, so the one expectation adjustment cannot be committed without claiming unrelated uncommitted canonical-header test ownership.

Ruling applied: move only the exact inseparable files (`nav.blade.php` and `StorefrontCanonicalHeaderTest.php`) out of the Task 2 commit, and commit the remaining separable Task 2 files plus this report.

This means the commit captures the Task 2 binding entrypoint and regression coverage, while the verified storefront runtime change in `nav.blade.php` remains intentionally uncommitted until the shared canonical-header work is owned and staged safely.
