# Bank Transfer Payment Proof Closure Acceptance

## Outcome

Bank In / DuitNow payment proof is closed locally across checkout, order success, authenticated Purchase History, and the existing Admin review flow. Google AI Studio remains the visual source of truth; Laravel and MySQL own authorization, private storage, state, audit history, and payment transition.

## Accepted behavior

- Checkout no longer labels the receipt as optional and explains that proof may be uploaded immediately or after the order number is created.
- `/order/success` renders the source-faithful hero panel only for the exact session-created Bank In order.
- `/account#purchase_history` renders the compact panel only on an owned Bank In order.
- Missing, selected, uploading, uploaded, rejected, verified/locked, and inline validation states are supported.
- JPG, JPEG, PNG, and PDF files up to 10 MB are accepted; invalid type and size errors stay inline.
- Proof files use the private local disk. Customer payloads never expose disk, path, or SHA-256 values.
- Replacement deletes the superseded private file only after the new pointer is persisted; failed database mutations clean up the newly stored file.
- Verified proofs are immutable from the customer route.
- Admin rejection preserves the pending order and returns the latest rejection reason to the customer.
- Admin verification marks the payment paid, records the audit event, and locks the customer panel.

## Automated verification

- Focused payment/account/Admin suite: 37 tests passed, 231 assertions.
- Complete Laravel suite: 750 passed, 1 skipped, 5,387 assertions.
- Pint source-style check: passed.
- Vite production build: passed (1,832 modules transformed).
- `git diff --check`: passed.

Non-blocking build notice: local Node is 20.17.0 while the current Vite plugin recommends 20.19+ or 22.12+.

## Real-browser acceptance

Tested through headed Chrome against `http://127.0.0.1:8500` using isolated local order `JW-20260903073833-2092`:

1. Created a Bank In / DuitNow order without checkout proof.
2. Confirmed the success-page hero missing state on desktop and 390 px mobile.
3. Selected and submitted a valid PNG; confirmed persisted uploaded/awaiting-verification state.
4. Logged in as the owning customer; confirmed the same proof in expanded Purchase History.
5. Confirmed invalid file selection renders inline and does not mutate the stored proof.
6. Logged in as Super Admin; rejected the proof with an operational reason.
7. Confirmed the customer saw that exact latest reason and could select a replacement.
8. Resubmitted from Purchase History; confirmed Admin received the new uploaded proof.
9. Verified the payment in Admin; confirmed payment became paid and the customer view became verified/locked.
10. Confirmed zero browser console errors on the Laravel proof flows and no payment-provider or courier booking was triggered.

## Visual evidence

Laravel captures are under `output/playwright/payment-proof/`; Google AI Studio baselines are under `output/playwright/payment-proof/source/`.

- Success hero: `success-missing-desktop.png`, `success-missing-mobile-panel.png`, `success-selected-desktop.png`, `success-uploaded-desktop.png`
- Purchase History compact: `account-uploaded-compact-desktop.png`, `account-uploaded-compact-mobile.png`, `account-rejected-compact.png`, `account-verified-compact.png`, `account-inline-validation.png`
- Source baselines: hero state series plus `compact-missing-desktop.png` and `compact-missing-mobile.png`

Deliberate differences are data and runtime adapters only: real MYR/order/customer values, Laravel CSRF/form wiring, and omission of the source-only QA state switcher. No material visual divergence was found.

## Release status

Local acceptance is complete. GitHub and Hostinger remain unchanged pending explicit synchronization approval.
