# Home & Deco approved design and integration manifest

2026-10-13. Design approval is not official integration or release approval. All application changes remain local first. Acceptance is scope inventory only, never Food layout source.

## Source inventory
| Scenario | Source | Decision |
|---|---|---|
| PLP/category/collection | Existing approved Home listing; exact original page reference must be recovered before fidelity closure | Retain; no replacement inferred |
| Filter | output/home-deco-filter-desktop.png and home-deco-filter-mobile.png | Prior approved work; verify source role |
| PDP | output/industry-template-concepts/home-deco-pdp-editorial-concept.png | Retain |
| Cart Drawer | output/industry-template-concepts/home-deco-cart-drawer-concept.png | Retain |
| Login | output/industry-template-concepts/home-deco-login-concept.png | Retain |
| Register | output/industry-template-concepts/home-deco-register-concept.png | Retain |
| Forgot | output/industry-template-concepts/home-deco-forgot-password-concept.png | Retain |
| Quick Buy | output/home-deco-theme-samples-v2/quick-buy.png | Approved modal only |
| Reset | output/home-deco-theme-samples-v2/reset-password.png | Approved |
| Account Overview | output/home-deco-theme-samples-v2/my-account.png | Approved; replaces old account concept |
| Account Orders | output/home-deco-theme-samples-v2/account-orders.png | Approved |
| Account Addresses | output/home-deco-theme-samples-v2/account-addresses.png | Approved |
| Account Saved | output/home-deco-theme-samples-v2/account-saved-items.png | Approved |
| Account Profile | output/home-deco-theme-samples-v2/account-profile.png | Approved |
| Account Vouchers | output/home-deco-theme-samples-v2/account-vouchers.png | Approved |
| Shopping Cart | output/home-deco-theme-samples-v2/shopping-cart.png | Approved |
| Checkout | output/home-deco-theme-samples-v2/checkout.png | Approved |
| Success | output/home-deco-theme-samples-v2/checkout-success-editorial-v2.png | Approved; original checkout-success.png rejected |
| Pending | output/home-deco-theme-samples-v2/payment-pending.png | Approved |
| Tracking lookup | output/home-deco-theme-samples-v2/order-tracking.png | Approved |
| Tracking result | output/home-deco-theme-samples-v2/order-tracking-verified.png | Approved |
| Review | output/home-deco-theme-samples-v2/review-dialog.png | Approved modal only |
| Return | output/home-deco-theme-samples-v2/return-dialog.png | Approved modal only |
| Tracking OTP state | output/home-deco-theme-samples-v2/order-tracking-verify-code.png | Approved additional state, same existing route |

## Binding / visual invariants
- All surfaces inherit Website Theme Brand & Style font family, Small/Medium/Big, Main/Sub with legible contrast.
- Account has horizontal tabs Overview, Orders, Addresses, Saved pieces, Profile, Vouchers, Sign out. Header ONLY eyebrow MY ACCOUNT and title Your home, collected. No added introduction or slogan.
- No Page Media on any redesigned account tab, Shopping Cart, Quick Buy, Review or Return.
- Auth: shared existing account image plus login/register/forgot/reset exception slots. No HTML slogan over decorative image unless explicitly approved.
- New Home media slots: Checkout summary photograph; Checkout Success panoramic photograph shared with Pending; Tracking panoramic photograph shared with lookup/OTP/result. Standard library/upload/desktop/mobile/alt/hide/restore/nine focus. Slots only where real renderer exists.
- Product/gallery/wishlist/cart/ordered item photos belong to Products or immutable order snapshots, not Page Media. PLP banner belongs to Category/Collection. Product Story stays shared product setting.
- Header/Footer exact actual unified settings and width owner; generated incidental shell labels/social icons are not new backend controls.
- Selected/ordered items desktop 2 columns wrapping, one item half width; mobile 1 column. No horizontal scrolling.
- Existing cart recalculation/stock/shipping/payment/proof/coupon/account ownership/review/return contracts stay unchanged. Only eligible actions appear. Conditional errors/status/tax rows are required functional content, not marketing additions.
- Success Paid only when actually settled. Pending reserved heading only while stock reservation exists; cancelled/expired/failed state uses actual status.
- Tracking never exposes order before verification; no fabricated ETA/carrier events. All real timeline events rendered.
- Generated overlay backgrounds are incidental, never override approved Orders source or header copy.

## Integration gate
Recover exact prior PLP/filter source role; connect all approved renderers with shared services; create per-theme dummy acceptance manifest and screenshots; verify controls desktop/mobile and default/custom styles; user visual acceptance; only then request GitHub/Hostinger sync separately. Application integration not started by this manifest.
