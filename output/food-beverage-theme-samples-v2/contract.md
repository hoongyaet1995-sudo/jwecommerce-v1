# Food & Beverage — source-backed sample contract

Status: design convergence only, 2026-10-12. No application integration, database changes or deployment in this phase. Images illustrate layout; this contract owns field names, allowed actions and conditional states.

## Existing commerce contracts

| Surface | Existing source | Binding and boundary |
| --- | --- | --- |
| Cart / drawer | CartController | Add variant + quantity; update quantity; remove item; apply/remove coupon. Selected variation is read-only inside cart. No variation-switch endpoint. |
| Checkout | CheckoutController | Existing contact/address fields; country ISO2; phone and state nullable. Shipping and payment options come from configuration/services. Proof optional at controller validation: JPG/JPEG/PNG/PDF, 10MB; service/provider rules still apply. Hosted payment remains provider-owned. |
| Success / pending | CheckoutController | Render actual order/payment state. Pending must never claim paid. Retain redirecting, cancelled, failed and retry states. |
| Guest tracking | OrderTrackingController | Lookup → verification code → verified result. No order details before verification. Include invalid/expired code and resend states. |
| Register / password | CustomerAuthController | Name, email, password + confirmation, accepted terms. Password minimum 8. Existing login/remember/forgot/reset flows. |
| Profile | CustomerAccountController | Name and phone editable. Email read-only. No preferences, email-change or payment-method management. |
| Addresses | CustomerAccountController | Label, first_name, last_name, phone, line_1, line_2, city, state, postal_code, country, is_default. Preserve required/optional validation. Add/edit/default only; no delete action. |
| Account / orders | OriginalAccountPayloadFactory | Existing owned customer/order payload and eligibility. Canonical navigation: Overview, Orders, Addresses, Saved items, Profile, Vouchers, Sign out. |
| Vouchers | CustomerCouponViewModelFactory / CustomerCouponEligibilityService | Available, Used, Unavailable map to eligible/used/expired arrays. Code, title, description, discount and min-spend fields. Apply validates eligibility. No invented expiry property or separate terms control. |
| Review | CustomerProductReviewController / ProductReviewMediaService | One eligible orderItemId; rating 1–5; required title max255 and comment max5000; up to5 JPG/PNG/WebP photos, 5MiB each. Preserve moderation and errors. |
| Return | CustomerReturnRequestController | One fixed order_item_id, eligible quantity; resolution refund/replacement; supported reason enum; details optional max2000; up to5 JPG/PNG/WebP photos, 5MB each. No multi-item batch submission. |

## Approved shared content controls still to implement

These are not existing operational functions and must not be described as already connected.

- Product Story: one shared product content model, optional label/heading/description/image/focus. All empty hides the block; partial content gets a supported fallback. No duplicate controls per theme.
- Account Banner: dedicated Website Theme → Page Media capability for this theme. Full-width image, bounded live account text; compact heading when image is missing.
- PLP hero CTA: optional label/link needs an explicit shared content control; omit until implemented. Category/collection title and description remain live text, never baked into an image.

## Visual and integration boundaries

- Full-width hero; bounded readable content/grid. Header overlay only when configured and an appropriate hero image exists; otherwise normal header. Shared header/footer stay single owners, with actual menus and brand settings.
- Product photos and galleries belong to product media. Category/collection hero media belongs to category/collection settings. Account/auth media belongs to declared theme Page Media capabilities. No extra Checkout decorative-media slot.
- Sample products, prices, counts, shipping fees, banks and vouchers are illustrative. Integration uses real payloads and currency formatting, never copies sample values.
- Background navigation, marketing copy and decorative artwork in generated images are not authorization for new routes or backend fields. This contract supersedes incidental image text.
- Empty/loading/error, stock constraints, server validation, permission/ownership and payment failures remain part of every interaction. Static images do not prove runtime acceptance.
- Keep each page/overlay as a separate image. Local implementation and browser acceptance follow explicit sample approval; GitHub/Hostinger synchronization requires separate release approval.

## Revised images

Shopping Cart, Profile, Addresses, Return, Register, Checkout, Vouchers and Review were corrected against source. Other images retain the previous visual reference subject to this contract. No runtime tests were run for this image/document-only phase.

## Auth decorative copy clarification � 2026-10-12

User approved Login/Register/Forgot Password decorative slogans as part of the uploaded image. No separate decorative-copy controls or live text overlays for these image panels. Form headings, labels, actions, errors and accessibility descriptions remain real HTML. Account welcome/name and PLP category/collection text retain their existing live-data contracts.
