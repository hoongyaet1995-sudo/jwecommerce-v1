# Currency, manual refund and mobile checkout release gate

User approved GitHub + Hostinger synchronization including side-chat mobile Checkout changes.

Scope: default-currency Admin formatting and historical order FX; actual external refund amount/receipt stock correction; payment labels; Checkout grid sizing and dedicated submit-label updates. No payment provider API, historical ledger rewrite or database migration.

Local gates: 352 Admin tests; 49 PHP tests / 325 assertions; TypeScript; Admin and storefront builds. Browser Checkout widths320/375/390/768/1280: scrollWidth equals viewport, Apply preserved, submit total matches summary, lock icon retained. New manual refund full-path evidence is in 2026-09-05-manual-refund-retest.md.

Generated public/build assets and manifest are explicitly versioned for this release, alongside public/build-admin, so Git deployment includes the verified storefront bundle. PDF manuals, temporary screenshot/script outputs and local test data are excluded from the application release.

Deployment uses jw-hostinger and the approved Git pull / optimize:clear sequence. Final deployed commit is verified against local and GitHub; live checks are read-only and do not create orders or issue refunds.
