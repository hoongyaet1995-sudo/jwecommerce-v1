# Fashion & Design approved language and design sources

Status: visual language, PDP v1, six-column Desktop PLP v1, Filter v1, Quick Buy v1, bottom Cart Drawer v1 (including mobile adaptation), Login/auth family and Overview v1 approved. User authorized same-series auth designs without further per-page review. Orders v1 pending approval. No Fashion application integration or release in this phase.

## Approved shared language

Bold urban streetwear editorial; black/offwhite foundation, electric lime Main and cool gray Sub defaults. Heavy sans headings, rectangular actions, square size buttons, restrained corners and shadows, consistent separators. No marquee, graffiti stickers or glitch animation. All accents must resolve Website Theme Main/Sub with readable foregrounds. Register typography in existing Theme Font and Small/Medium/Big controls during approved integration.

All native content follows shared Header/Footer Page Width Standard/Wide/Full and canonical gutter. Full-bleed photographs do not remove text gutters. Design images exclude Header/Footer and use landscape Desktop composition.

## PDP v1 control and layout contract

- Gallery: Products Media Gallery persisted order; first two photos are equal portrait columns, remaining photos continue below without duplication. One image collapses to one column; odd final image has no empty placeholder. Hide missing images. Product media does not belong to Page Media.
- Title, description, SKU and price: existing product/selected variant and currency context; sample copy and prices are illustrative, not public catalog records.
- Colour and Size: product options and existing variant selector (`product.options`, values, displayType, valueVisuals); selection, unavailable combinations and canonical stock eligibility remain authoritative. No new variant logic.
- Quantity/Add to cart/Buy now/Save item: shared commerce and wishlist actions, including validation and authentication states. Sample In stock is illustrative only; unknown real stock stays unavailable.
- Specifications: ProductPdpContent.specifications. Size & fit and Care instructions: existing custom_accordions. No unsupported dedicated size guide dialog or recommendation engine.
- Story: existing optional ProductPdpContent.story heading/description/image/alt/focus fields. Sample headline is editable story content, not template-baked merchant copy. Empty story hides.
- Related products, when designed, use existing related data with image-contained hearts and full-frame images.
- No fabricated reviews, urgency, delivery dates, badges or unsupported product bundles.

## Next surfaces

## PLP v1 control and layout contract

- Order: Hero > Breadcrumb > Title > Description > Filter/sort/count > Product Listing > Paging Control.
- Hero: category/collection catalog banner; All Products decorative hero must use a declared Page Media consumer during integration. Missing/hidden hero collapses without affecting native text gutters.
- Breadcrumb/title/description: existing route scope and category/collection fields, never baked into uploaded banner.
- Count/grid/pagination: existing scoped product query and actual paginator. Sample pagination is illustrative; omit extra pages when query fits one page.
- Filters and sort: existing GET acquisition/units/scope/reset logic remains unchanged, as explicitly deferred by user.
- Product image/title/short description/price: actual catalog and currency context. Wishlist heart sits inside each image, Quick Buy uses existing shared variant/stock modal flow.
- Desktop sample uses six slim portrait cards in a row; integration responsive column count must keep text/actions legible across Page Width Standard/Wide/Full and mobile. No extra category menu or unsupported badges.
- User explicitly reminded registration of Fashion Theme Font/default Main/Sub on 2026-10-14; required implementation gate, not yet completed.

Continue individually after each sample approval: PLP, Filter, Quick Buy, Cart Drawer, auth, account, Cart, Checkout/results, tracking and existing Review/Return overlays. Preserve existing filter acquisition logic. Cart Drawer reuses the released shared promo/footer layout. Decorative Page Media slots must have explicit consumers, Desktop/Mobile/alt/focus/hide/restore; no unused controls.

## Artifact

## Remaining batch, 2026-10-15

Generated-image deviations are explicitly excluded: scattered uncontrolled native marketing slogans are not template content; Review/Return dimmed sidebar backgrounds do not override canonical account shell. Verification extra side photographs are not supported extra Page Media slots and will not be introduced. Pending currency must use order snapshot context (sample dollar signs not authoritative), hero must retain approved lower alpha fade despite sample hard cut. Masked card/account numbers, dates, upload limits, transport estimates and provider labels are illustrative only; actual configured/validated fields govern. Return gallery extra photos are not new controls. A sample image is never authorization for invented backend capabilities.

Vouchers approved. User explicitly selected existing output/jewelry-gifts-theme-samples/voucher-css-proof.html as notch/perforation implementation reference; radial masks create actual transparency. Adapt only approved Fashion font/color/corners, preserve disclosure/apply behavior.

User requested all remaining designs together, one image per scene: cart, checkout, checkout-success, checkout-pending, tracking-lookup, tracking-verify, tracking-result, review and return. These remain pending visual approval. Cart/pricing/coupon/quantity and checkout shipping/payment producers stay shared. Checkout four independent steps 1..4, Delivery Method title, visible radio dots, sticky summary, no summary-bottom media. Success/Pending use same existing checkoutSuccess Page Media ownership with top overlapping alpha-faded media, actual payment status and snapshot totals. Pending proof remains actual existing owner-checked action. Tracking retains lookup -> email verification -> protected result, actual status/events and no invented ETA. Review/Return match existing eligibility, required fields, file limits, ownership, CSRF and errors. Return reasons/resolution remain existing enum, not size-exchange feature.

All Products inherits approved Fashion PLP composition with existing title/query and capability-owned optional media, no separate structural scene required. Existing unsupported features and CMS content/help/policy/search remain inherited/separately owned, no redesigned Header/Footer. Real renderers and data acceptance remain future integration gates, not proven by these images.

## Vouchers v1

Profile v1 approved as recovered canonical shell. Vouchers v1 edits the same Overview reference, changing active tab/body only. Real CSS transparent concave notches at stub join plus vertical dashed perforation; no background-colored fake circles. Natural-height inline Terms disclosure from actual terms, no hardcoded invented conditions on public routes. Available/Used/Unavailable follow existing voucher eligibility data and existing tabs; coupon amounts/codes/expiry/minimum spend from actual DTO.

Use voucher and manual Apply use existing cart coupon function and authenticated account behavior already shared by Baby/Jewelry. Do not claim vouchers are permanently stored on customer when only cart coupon applies. Preserve validation/expired/ineligible/no-cart states and actual feedback. Empty voucher collection renders empty state. Main/Sub,font,canonical Page Width govern presentation. Source output/fashion-design-theme-samples/account-vouchers-v1.png, pending approval.

## Immutable account shell and Profile v1

Saved items body approved, but user reiterated Hero/menu drift concern. Overview v1 is sole canonical account shell: preserve exact shared hero crop/height/alpha fade and menu position/content width/background across every tab. Generated Saved hero hard-cut is superseded, not approved shell variation. Subsequent samples edit Overview reference rather than regenerate shell independently. Integration uses one shared shell partial, tab body only changes.

Profile v1 uses existing full name/phone editable, email read-only and existing profile validation/ownership/CSRF. No avatar/password-change/gender/birthday controls invented. Helper static native copy and Manage addresses existing tab link. Main/Sub and Theme Font apply; canonical shared Page Width/gutter. Source output/fashion-design-theme-samples/account-profile-v1.png. Pending visual approval.

## Saved items v1

Addresses v1 approved. Saved items v1 pending approval. Shared canonical account hero/menu unchanged. Actual wishlist products/count and prices through existing wishlist/currency DTO, six-column desktop styling consistent with approved PLP and responsive legibility requirements. Filled heart inside upper-right image on Main square, removal uses existing authenticated ownership flow. Quick Buy uses shared variant/stock modal; Continue shopping centered below grid and links actual shop. Empty list retains empty state; no sample catalog added publicly. Source output/fashion-design-theme-samples/account-saved-v1.png.

## Canonical account navigation correction

User flagged Orders v1 full-width menu as inconsistent with approved Overview. All account tabs MUST reuse Overview content-sized left-aligned menu starting at shared shell gutter; do not stretch tabs into equal-width cells or add cell divider lines. Semi-transparent white background and alpha-faded hero remain mandatory, active tab black/white rectangle. Orders image menu is superseded by this correction, its order-body remains reference pending acceptance.

## Addresses v1 contract

Existing saved addresses and inline editing form, desktop list/edit columns and mobile stacked sections. Existing first/last name, phone, line1/line2, city/state/postcode/country/default fields only. Save/edit/delete/default uniqueness, ownership, CSRF/validation and destructive-action confirmation follow existing address flow. No Home/Work label enum, maps or autocomplete invented. Canonical account navigation above applies. Source output/fashion-design-theme-samples/account-addresses-v1.png. Controls align and all outer content uses shell Page Width/gutter.

## Overview fidelity requirements and Orders v1

User explicitly approved Overview with mandatory real lower image alpha fade, translucent white horizontal menu background (text/buttons remain opaque), thick sharp table frames and aligned buttons. Do not substitute a solid white hero overlay that changes merchant image visibility. Verify actual rendered desktop/mobile against approved reference before completion.

Orders v1: shared account hero/menu; independent zero-radius bordered orders with visible gaps, not nested master card. Existing order search/status tabs/expansion/payment status/address/totals/track action only. Review/Return/payment-proof recovery remain conditional on real eligibility even when omitted from this example. Money uses snapshot currency and authoritative totals, no sample arithmetic overrides. Empty/error states inherited. Desktop columns collapse legibly on mobile, shared gutter maintained. Artifact output/fashion-design-theme-samples/account-orders-v1.png.

## Forgot / Reset and Overview contracts

Forgot uses existing email/reset-link request validation, throttle and generic outcomes; Reset uses existing token/email state and password/confirmation validation, not a new password-change route. Same auth typography, open form and Page Media per-page exception system as Login. Uploaded images may differ by page. Generated photograph wall slogans are incidental image content, not added native editable text controls. Sources forgot-v1.png/reset-v1.png.

Overview v1 uses live user name/helper and shared account Page Media shallow alpha-faded hero. Horizontal navigation preserves existing account tabs/authenticated actions. Orders use existing order snapshots/currency/item images/status, View order opens existing order tab/detail state; empty orders show canonical empty state. Account details and default address from existing customer/address payload; missing address displays empty state. Profile/address buttons use existing tab navigation and align. No added stats, loyalty, notification or provider claims. Source account-overview-v1.png. All sections align shared shell Page Width/gutter; mobile tab navigation remains accessible without document overflow.

## Register v1 contract

Same approved open auth composition and alpha-faded top Page Media. Two-column desktop, one-column mobile. Existing registration fields only: full name/email/password/confirmation/optional phone, existing terms/privacy consent and links. Preserve server validation, password visibility behavior, CSRF and auth outcomes. No newsletter/gender/birthday/social-login fields. Register Page Media exception inherits shared account asset when absent; hidden/missing artwork removes hero-only spacing. Source output/fashion-design-theme-samples/register-v1.png. Main/Sub/font/size and canonical outer width required.

## Login v1 contract

Open centered form, no floating master card and no left-image/right-form split. Top decorative panorama overlaps behind heading with bottom alpha fade; form begins high enough to be visible without scrolling at common desktop sizes. Background belongs to declared Website Theme Page Media account shared/login exception during integration, with Desktop/Mobile/alt/focus/focusY/hide/restore. No background means no reserved hero height and no unsupported text placement. Mobile stacks existing email/password and uses uploaded mobile image or desktop focus fallback.

Existing customer auth route/controller owns email/password, remember, validation/redirect, forgot/register/shop links. Password eye is shared accessible show/hide behavior. No social sign-in invented. Headings, forms and buttons follow Theme Font/size and Main/Sub; shared canonical outer width/gutter with bounded readable internal form. Artifact output/fashion-design-theme-samples/login-v1.png.

## Bottom Cart Drawer v1 contract

User explicitly requested a bold bottom placement rather than right drawer. Desktop full-width sheet slides up from viewport bottom; large left product region and compact right footer/summary region. No heading icon. Promo stays owned by shared cart-drawer-promo partial inside summary immediately before subtotal, never attached to product listing. Existing CSRF/coupon error expansion/apply endpoint retained. Subtotal/checkout/cart links and quantity/remove use existing authoritative cart DTO/actions. Continue shopping only closes overlay. Existing empty/error/unknown-stock states retained.

Preserve shared #cart-drawer and data-cart-* hooks, Escape/backdrop/close/focus return/scroll lock behavior. Integration must extend narrowly scoped Fashion bottom presentation rather than alter other industry layouts. Desktop product region scrolls independently when many lines. Short/mobile view: bottom anchored sheet constrained to available viewport, product region flexes/scrolls, compact summary remains accessible with height fallback. Do not hardcode competing content gutters; interior horizontal frame follows shell Page Width. Source output/fashion-design-theme-samples/cart-drawer-v1.png, contextual background does not supersede approved PLP.

## Quick Buy v1 contract

Centered wide desktop rectangle with no heading ornament, ordered hero plus horizontal thumbnails, open purchase panel. Product identity/media/price/SKU and Colour/Size resolve the same product and variant DTO as PDP. Quantity, stock and purchase constraints remain shared; illustrative In stock is not a public fallback. Add to cart/Buy now use existing shared hooks, View full details links actual PDP. Close button, overlay close, Escape, focus return and mobile viewport scrolling follow shared accessible modal behavior. Gallery hides absent thumbnails without placeholders. No new specs, size-guide, bundles or delivery controls. Source: output/fashion-design-theme-samples/quick-buy-v1.png. Background PLP is contextual only and cannot supersede approved PLP.

## Filter v1 control contract

Artifact: output/fashion-design-theme-samples/filter-v1.png. This sample covers the right drawer only; generated background photo/copy differences do not supersede approved plp-v1.png. No new banner slogans or Women breadcrumb are introduced.

Right drawer inherits shared GET form and automatic-submit behavior. Price min/max/range use existing minor-unit query bounds and display currency. Colour and Material use existing conditional facets; absent facets hide. Availability uses in_stock. Do not introduce Size/Fit facets or alter acquisition logic. Existing ratings/category controls remain conditionally available according to existing route rules even when absent from this sample. Clear filters uses canonical reset URL retaining route scope/layout/sort. Done/X only close overlay; no invented deferred Apply behavior. Selected states use Main and contrasting tick/ring. Sample values are illustrative.

PDP v1: `output/fashion-design-theme-samples/pdp-v1.png`. Prompt saved beside sample. Built-in imagegen used. No application code, live settings, catalog or customer data changed.
