# Wedding editorial and quotation commerce release

User approved synchronization after local Wedding integration and Website Theme encoding correction.

## Scope

- Wedding editable CMS template: nine sections, photo marquee, editorial gradient/quote layouts, Cormorant/Inter font registration and website-width inheritance.
- Wedding catalog, auth/account, cart, normal/quotation checkout, receipt, tracking and overlays. Eleven corrupted Admin static strings repaired.
- Required previously approved mixed buy/quote/display catalog, enquiry/support follow-up, versioned quotations, manual-fulfilment acceptance/payment and common Theme Checkout UI.
- Existing inventory opening-stock changes, unrelated social/Admin changes, local test records and draft29 are excluded. No homepage or active Theme switch.

## Verified candidate

Base: 4241c216382decf28c6c8f84edeebbaa81df233e. Canonical implementation first accepted in C:/xampp/htdocs/jwstudio-ecommerce. Scoped release prepared in managed wedding-release worktree, with fresh builds rather than dirty primary bundles.

- Isolated SQLite: 65 PHP tests / 1069 assertions passed, 89.81s.
- Focused Vitest: 47 tests passed, 4.14s.
- Actual shared enquiry JS module: 4 tests passed, 105.50ms.
- Frontend build passed 5.20s; Admin build passed 14.28s. Existing chunk-size and font URL warnings remain; no full-suite/global typecheck claim.
- Source reflection confirms services loaded from release worktree; inventory directory diff empty.
- Local browser evidence: saved unpublished Landing, desktop/mobile layouts, three width modes, lazy batches, modal opening, corrected Main/Sub/selected states and Admin encoding. See phase ledger and local output/wedding-integration evidence.

## Deployment requirements

Run only the five included additive 2026_10_15 migrations (purchase settings, enquiries, support association, quotation documents, acceptance/order associations). No seeders or data import. PRODUCT_ENQUIRIES_ENABLED=true is the non-secret production capability setting matching the approved local functionality; preserve all other environment configuration. The existing production theme remains tech.

Before deployment counts: products10, orders25, pages6. New quote/enquiry tables absent. Verify these existing counts remain unchanged after schema deployment; verify new tables empty and migration status complete. No real financial, order, email or support-message submission during acceptance.

Release completion requires identical local, GitHub main and Hostinger commit SHA, then read-only live HTTPS checks. Token usage unavailable. Helper preflight API mismatch was corrected before deployment; no application failure occurred. Composer quiet dump stalled during copied-dependency setup and was stopped; relative copied Composer/PSR4 runtime was verified by reflection and successful tests.
