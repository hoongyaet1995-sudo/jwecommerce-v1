# Food & Beverage local integration and dummy acceptance

Date: 2026-10-13. Branch: codex/social-crm-identity. Baseline HEAD: 062d75aab0b59ce186c54cbca1be67a7d258fc48. Existing unrelated dirty changes preserved. No commit, push or deployment.

## User scope and entry

User approved integration and explicitly replaced Admin live catalogue preview with fixed dummy data. Website Theme now labels Theme demo / Dummy data and links to `/admin/website-design/acceptance`. This protected workspace has 23 individual scenarios, Desktop/Mobile, approved-reference toggle and full-page Open demo. Approved source files remain unchanged in output/food-beverage-theme-samples-v2.

## Data and write boundary

Unsaved in-memory Product/Variant/Category, cart, account and Order snapshots use the same official renderers. No sample store records are created. Public storefront keeps real controllers/services and shared inventory authority. Inventory projection requires server-registered object identity AND unsaved models; cloned or persisted objects cannot obtain demo availability. Demo iframes disallow forms; emitted guard blocks native submit/requestSubmit, submit events, non-GET fetch/XHR and beacons. Demo links cannot navigate to operational routes. Customer chat excluded from demos. Negative IDs are fixtures, never persisted. Admin customization Apply remains the actual, authorized settings action outside the demo.

## Integration changes

PDP photographic fixture / horizontal thumbnails, confirmation/pending portrait and snapshot status summary, label-row profile, saved cards with canonical Quick Buy, full tracking result, owned review/return dialogs, cart quantity rows. Existing stock, payment state, tracking verification and review/return eligibility contracts retained. Decorative auth copy stays in artwork. Public Header/Footer remain managed by the single shared settings owner; demo shell is deliberately fixed.

## Verification

- Isolation inspector: sqlite / :memory:, configCached false. No actual DB migration/reset.
- Final targeted regression: 27 tests / 314 assertions passed (FoodThemePack, Customization, Demo, TrackingPreview, TrackingReadModel, TrackingVerification).
- Admin and storefront npm run build passed; existing Admin chunk-size warning.
- Authenticated Chrome Joshua profile: Website Theme shows real16-product count while iframe uses fixed6 samples; correct acceptance link visible and opens workspace.
- Desktop and390px mobile screenshots for23 scenarios; mobile document widths382px (scrollbar) or390px, no measured horizontal overflow. PLP stale capture explicitly recaptured after expected heading loaded. Checkout initial stale capture corrected after fixture bug fixed and expected renderer visible.
- Filter auto-opens; Quick Buy auto-opens after window load; variant controls visible; header bag opens fixed drawer; reference toggle shows approved image.
- Demo checkout bank is clearly Sample bank / DEMO-0000, instructions warn no payment. No real checkout, email, return, review or account mutation submitted in browser.
- Visual composition implemented and available for user acceptance; no fabricated pixel-fidelity percentage. Generated replacement decorative assets follow approved scenes but are not byte-identical photos. Brand/menu/product data differences on actual storefront follow configuration.

## Scenario evidence

| Scenario | Desktop | Mobile |
| --- | --- | --- |
| plp | [screenshot](../../output/food-theme-acceptance/plp-desktop.png) | [screenshot](../../output/food-theme-acceptance/plp-mobile.png) |
| filter | [screenshot](../../output/food-theme-acceptance/filter-desktop.png) | [screenshot](../../output/food-theme-acceptance/filter-mobile.png) |
| quick-buy | [screenshot](../../output/food-theme-acceptance/quick-buy-desktop.png) | [screenshot](../../output/food-theme-acceptance/quick-buy-mobile.png) |
| pdp | [screenshot](../../output/food-theme-acceptance/pdp-desktop.png) | [screenshot](../../output/food-theme-acceptance/pdp-mobile.png) |
| cart-drawer | [screenshot](../../output/food-theme-acceptance/cart-drawer-desktop.png) | [screenshot](../../output/food-theme-acceptance/cart-drawer-mobile.png) |
| login | [screenshot](../../output/food-theme-acceptance/login-desktop.png) | [screenshot](../../output/food-theme-acceptance/login-mobile.png) |
| register | [screenshot](../../output/food-theme-acceptance/register-desktop.png) | [screenshot](../../output/food-theme-acceptance/register-mobile.png) |
| forgot-password | [screenshot](../../output/food-theme-acceptance/forgot-password-desktop.png) | [screenshot](../../output/food-theme-acceptance/forgot-password-mobile.png) |
| reset-password | [screenshot](../../output/food-theme-acceptance/reset-password-desktop.png) | [screenshot](../../output/food-theme-acceptance/reset-password-mobile.png) |
| my-account | [screenshot](../../output/food-theme-acceptance/my-account-desktop.png) | [screenshot](../../output/food-theme-acceptance/my-account-mobile.png) |
| account-orders | [screenshot](../../output/food-theme-acceptance/account-orders-desktop.png) | [screenshot](../../output/food-theme-acceptance/account-orders-mobile.png) |
| account-vouchers | [screenshot](../../output/food-theme-acceptance/account-vouchers-desktop.png) | [screenshot](../../output/food-theme-acceptance/account-vouchers-mobile.png) |
| account-addresses | [screenshot](../../output/food-theme-acceptance/account-addresses-desktop.png) | [screenshot](../../output/food-theme-acceptance/account-addresses-mobile.png) |
| account-profile | [screenshot](../../output/food-theme-acceptance/account-profile-desktop.png) | [screenshot](../../output/food-theme-acceptance/account-profile-mobile.png) |
| account-saved-items | [screenshot](../../output/food-theme-acceptance/account-saved-items-desktop.png) | [screenshot](../../output/food-theme-acceptance/account-saved-items-mobile.png) |
| shopping-cart | [screenshot](../../output/food-theme-acceptance/shopping-cart-desktop.png) | [screenshot](../../output/food-theme-acceptance/shopping-cart-mobile.png) |
| checkout | [screenshot](../../output/food-theme-acceptance/checkout-desktop.png) | [screenshot](../../output/food-theme-acceptance/checkout-mobile.png) |
| checkout-success | [screenshot](../../output/food-theme-acceptance/checkout-success-desktop.png) | [screenshot](../../output/food-theme-acceptance/checkout-success-mobile.png) |
| payment-pending | [screenshot](../../output/food-theme-acceptance/payment-pending-desktop.png) | [screenshot](../../output/food-theme-acceptance/payment-pending-mobile.png) |
| order-tracking | [screenshot](../../output/food-theme-acceptance/order-tracking-desktop.png) | [screenshot](../../output/food-theme-acceptance/order-tracking-mobile.png) |
| order-tracking-verified | [screenshot](../../output/food-theme-acceptance/order-tracking-verified-desktop.png) | [screenshot](../../output/food-theme-acceptance/order-tracking-verified-mobile.png) |
| review-dialog | [screenshot](../../output/food-theme-acceptance/review-dialog-desktop.png) | [screenshot](../../output/food-theme-acceptance/review-dialog-mobile.png) |
| return-dialog | [screenshot](../../output/food-theme-acceptance/return-dialog-desktop.png) | [screenshot](../../output/food-theme-acceptance/return-dialog-mobile.png) |

UI entry proof: [Website Theme](../../output/food-theme-acceptance/website-theme-entry.png). Final workspace screenshot: [workspace](../../output/food-theme-acceptance/workspace.png).

Issue history stays in 2026-10-12-food-theme-integration.md. Elapsed time and token usage: unavailable. Final user visual acceptance and any GitHub/Hostinger synchronization remain separate decisions.
