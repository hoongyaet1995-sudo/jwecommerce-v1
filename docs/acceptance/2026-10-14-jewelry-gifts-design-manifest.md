# Jewelry & Gifts design contract

Status: All23 baseline surfaces approved, plus tracking verification. User authorized integration; local implementation and verification recorded in 2026-10-14-jewelry-gifts-integration.md. Final implementation acceptance pending. No Jewelry release performed.

Global customization rule reaffirmed by user: every Jewelry & Gifts scene, overlay, action and decoration responds to Website Theme > Brand & Style Main/Sub. Bronze and blush are defaults only. Main controls primary CTAs, selection and emphasis; Sub controls helper surfaces and ornamental accents. Verify custom-color draft and saved public renderers, including category overrides. Neutral readable ink/background remain purposeful theme foundations.

2026-10-14: User approved PDP and requested next scene. PDP gallery clarification remains authoritative. PLP v1 presented, awaiting review.

User accepted retaining PLP v1 and requested next design, while noting that industry PLP structures are too similar. Filter v1 presented for review; only its right-side overlay is the source. The generated dimmed background, invented brand/menu/ratings/product prices and alternate listing composition are excluded; retain PLP v1 underneath.

Approved predecessor: Baby & Kids accepted as a whole by the user on 2026-10-14. Its public category/collection/PDP followup is recorded in the Baby integration log.

## PDP v1

Reference: `output/jewelry-gifts-theme-samples/pdp-v1.png`.

Industry direction: warm ivory, champagne bronze actions, dusty blush accents, editorial serif titles and restrained sans-serif body. Main `#92744f`, Sub `#eee1d8`, page `#fbf8f3`, ink `#352c25`. Planned theme default font pair Lora / Inter, retaining existing font and size choices. Theme registration follows design approval.

Composition: open purchase panel; broad portrait main photograph with two vertically stacked detail photographs beside it; thumbnail rail below. Wide Product Story photo followed by centered live heading/description. The finer details split heading/accordions. Four related products. Header/Footer excluded. Shared Standard/Wide/Full content variables and gutter remain mandatory; image treatments never shift live text outside the shared frame.

Control mapping:

- Product title/subtitle/price/SKU and inventory: existing product, variant and availability producers. Sample labels/prices do not establish public inventory or merchant claims.
- Metal and chain length: existing product option dimensions, swatches and SKU combinations; no new filter or engraving logic.
- Gallery and detail images: Products > Media Gallery; render existing images, never synthesize missing public detail shots.
- Highlights: existing icon + optional Badge + Text. Badge supplies each short heading, Text supplies the supporting sentence. Icons gem/gift shown in this design require extending the shared icon picker and allowlist during approved integration; leaf already exists. No hardcoded product highlight claims.
- Story photograph, heading and paragraph: shared Product Story fields, rendered as one full-width editorial composition.
- Specifications: existing specification rows. Care instructions and What's in the box: existing custom accordions. No additional merchant content controls needed.
- Related products: existing related product mapping, actual gallery/title/price; no invented recommendations or ratings.
- Save item, quantity, Add to cart and Buy now: existing authenticated wishlist, stock and commerce contracts. Unknown/unverified inventory keeps the canonical disabled state.
- PDP photographs remain product-owned, not Website Theme Page Media. Auth/account/tracking decorative slots will be mapped when those scenes are designed.

Acceptance for implementation must include actual public category/collection overrides with a different store default theme, not only protected Dummy Sample rendering. Compare asset links, computed type/spacing and wish-list positioning; preserve the Baby public CSS resolution fix.

## Gallery ordering clarification

The user's annotated PDP specifies sequential, non-duplicated placement. This supersedes the incidental repeated thumbnails in the generated v1 image. Read Product Media Gallery's persisted `position` order: image 1 is the large main photo, image 2 the upper detail photo, image 3 the lower detail photo, and image 4 onward the lower supplemental row. With exactly three images, omit the whole lower row and its spacing. With one image omit the side column; with two images show the available detail without a blank third slot. Do not cap the gallery at seven images; additional images keep their order in a horizontally scrollable row when required. Every displayed image remains available for enlarged viewing. Product Story uses its independent shared content image field, not a numbered gallery slot.

## PLP v1

Reference: `output/jewelry-gifts-theme-samples/plp-v1.png`. Hero image first and full width; no overlay title. Then Breadcrumb, Title, Description, Quantity/Filter/Sort, four-column product grid, centered paging. Post-hero text and products share Header/Footer's Standard/Wide/Full frame and gutter; incidental image margins must not become competing max-widths. No divider beneath heading or toolbar. Eight illustrative products with real-field title/subtitle/price; heart controls inset inside photographs. 8px image/button/control corners and restrained bronze actions.

Category/Collection hero and text belong to catalog fields; All Products optional hero belongs to its Page Media slot. Wishlist and Quick buy use existing commerce contracts. Filters retain existing data logic. Pagination renders actual paginator state; sample page 2 is illustrative, never a fabricated public page. Title/body typography and Main/Sub colors remain controlled by Brand & Style. Public override CSS resolution must be verified with actual data and a different global store theme.

## Filter v1

Reference: `output/jewelry-gifts-theme-samples/filter-v1.png`, right-hand drawer only. Full-height ivory drawer, serif title, 32px content padding, expanded Price and Availability groups, visible checked checkbox mark, thin group rules, pinned bottom Reset filters / Show results buttons, 8px control corners. Min/max/currency/slider bounds come from existing filter and currency services; RM0/RM500 are sample labels. The illustrated two groups are universal controls; current conditional category/color/material/rating facets remain available when the existing service returns data and follow this same visual styling. Do not invent gemstone, metal, occasion or recipient filter logic. The user deferred filter data normalization earlier; this theme does not change it.

2026-10-14: User requested next scene after Filter. Quick buy v1 presented for review.

## Quick buy v1

Reference: `output/jewelry-gifts-theme-samples/quick-buy-v1.png`, foreground modal only. The generated dimmed alternate listing/brand is context, excluded from integration. Centered ivory panel, 12px corners, soft shadow, gift outline icon header and close control, fine header rule. Large active product photograph left, full ordered thumbnail rail underneath; compact purchase fields right. Both purchase buttons and full-details link fit normal desktop viewport; overflowing content remains accessible on smaller screens.

This overlay uses all product gallery thumbnails from image 1 in persisted order, because it contains one active image. The PDP clarification (first three photos in upper composition, remaining photos below) still applies to PDP only. Product data, variant options/SKU/prices/availability, quantity, Add to cart and Buy now use shared contracts. No new content controls or business features. Header gift is a template ornament, subject to Sub/accent styling; independent highlight icon picker extension remains separately recorded. Native checked swatches and 8px option/button corners must be verified on public routes.

2026-10-14: User requested next scene after Quick buy and reasserted Main/Sub control rule. Cart Drawer v1 presented.

## Cart Drawer v1

Reference: `output/jewelry-gifts-theme-samples/cart-drawer-v1.png`, right-side drawer only. Generated brand/header/background catalog changes are excluded; original PLP v1 remains underneath. Full-height ivory drawer with gift icon/header, ordered product rows, large square photos, title/variant/SKU/price, native quantity/remove hooks, collapsible existing coupon form. Quiet Sub-colored strip with template ornamental gift and decorative copy 'Little pieces. Meaningful moments.' Fixed footer shows real subtotal, shipping/tax helper, Checkout/View cart/Continue shopping. 8px controls/photos, fine rules and generous item spacing. Item section scrolls independently when necessary; footer remains reachable. Preserve actual coupon error/success, empty cart and verified/unverified stock states. No new gifting, shipping, recommendation or inventory logic.

User correction: omit the gift icon beside the 'Your cart' heading. The title begins at the normal content gutter. This supersedes the v1 raster's header icon; the lower helper-strip ornament remains unchanged. User requested next design.

## Login v1

Reference: `output/jewelry-gifts-theme-samples/login-v1.png`. Presented for review. Distinct open editorial layout: merchant Company Logo strip, panoramic optional photo banner, centered native heading and open form below. Desktop email/password fields sit side by side; mobile stacks fields. No enclosing floating card or shadow. Existing remember/password visibility, forgot/register/shop links and login validation remain canonical. 8px input/button corners; Main controls action/link emphasis and Sub supplies soft surface accents.

Company Logo is read from Shop Settings, never literal JW STUDIO. Login image belongs to Website Theme Page Media login slot, with Desktop/Mobile, hide and focal-position controls; hide removes the banner and its spacing. Photo is image-only; heading/labels/buttons remain HTML. A narrow form width is an inner functional layout within the shared Page Width/gutter, not a competing outer storefront frame. Shared Header/Footer remain excluded from redesign.

2026-10-14: User approved Login and requested Register.

## Register v1

Reference: `output/jewelry-gifts-theme-samples/register-v1.png`, presented for review. Same merchant-logo strip, optional panoramic media and open form family. Desktop full name/email row, password/confirmation row, optional phone half row; mobile stacks fields. Existing canonical customer registration persists name/email/phone and validates confirmed password/required Terms agreement. Policy links use existing shared destinations. No marketing consent or additional customer attributes. Independent Register Page Media Desktop/Mobile/hide/focus slot; Company Logo from Shop Settings. Main/Sub and existing font/size controls must affect draft and public renderers. Inputs/buttons 8px corners and no enclosing shadow/card.

2026-10-14: User approved Register and praised the distinct auth composition; requested next design.

## Forgot password v1

Reference: `output/jewelry-gifts-theme-samples/forgot-password-v1.png`, presented for review. Same Company Logo strip and optional panoramic image banner. Centered open form with email field, Send reset link, Back to sign in and Back to shop. Existing password-reset request endpoint and real result/error/rate-limit states remain authoritative; no pre-submission success message, OTP or countdown invented. Independent forgot Page Media slot with Desktop/Mobile/hide/focus. All Main/Sub, font/size and shared width/gutter rules continue.

2026-10-14: User requested next scene after Forgot password.

## Reset password v1

Reference: `output/jewelry-gifts-theme-samples/reset-password-v1.png`, presented for review. Company Logo strip, optional panoramic Reset Page Media, open native form. New password and confirmation side by side on desktop and stacked on mobile, password visibility controls, full-width Reset password CTA and sign-in/shop links. Hidden email/token and canonical reset validation/expired-link/errors remain operational state; never invent successful reset before submission. Main/Sub, font/size, 8px corners, media Desktop/Mobile/hide/focus and shared outer Page Width/gutter apply.

## Auth banner overlap correction — supersedes four v1 compositions

User requested that Login, Register/Create account, Forgot password and Reset password banners overlap approximately half their height behind the open form and fade to transparent, bringing the form upward. All four scenes adopt this correction; the earlier raster's separate hard-edged image band and below-banner spacing are no longer authoritative.

- Keep Company Logo strip at top. Photo remains an independently uploaded Page Media asset, not a baked form screenshot.
- Retain photo detail in the upper portion; apply CSS alpha mask on the image layer, fading its lower half into the current page background. Gradient is renderer-owned and applies to any replacement image; merchants do not need to upload a pre-faded file.
- Start the native form composition approximately halfway down the photo layer. Use relative normal-flow form layout with controlled overlap, not absolute positioning of individual fields. Image layer is behind, non-interactive; heading/labels/fields/actions have a higher layer and remain fully usable.
- Preserve the approved open form composition and desktop field columns; no new floating master card or shadow. Inputs keep legible opaque/light surfaces. Gradient must sufficiently fade under native copy to preserve contrast.
- Desktop aims to show heading, first fields and, where form length permits, primary CTA in the initial viewport. Register has more fields: do not shrink type/controls or promise the entire form fits every screen.
- Mobile uses the Mobile upload when provided and existing desktop fallback otherwise; use a shorter photo region and adjusted overlap, with fields stacked. Preserve focal-position controls. Do not merely retain desktop banner height on narrow screens.
- Hide or missing Page Media removes the photo layer and its reserved/overlap spacing; form starts naturally beneath the logo. No empty hero or negative-offset gap.
- Main/Sub and font/size controls remain effective on all four scenes. Shared outer Page Width/gutter continues; alpha fades into the actual theme background rather than fixed white.

These are approved-direction implementation requirements for the pending Jewelry theme phase. Revised visual samples may demonstrate the overlap, but the four original v1 sample files remain archived intact.

## Account overview v1

Reference: `output/jewelry-gifts-theme-samples/account-overview-v1.png`, presented for review after user requested next design. Shared Account Page Media shallow panorama with lower-edge fade; native MY ACCOUNT/Hello customer name/helper copy uses real customer DTO and shared Page Width gutter. Desktop uses left vertical icon navigation with Sub selection surface and Main thin indicator, right open content. Mobile navigation must remain accessible without forcing desktop sidebar width.

Recent orders are two independent 8px bordered rows with actual order number/date/status/total and view-order action, expanded items from existing DTO. No tracking/rating/reward data invented. Profile/default address summary beneath use existing account fields and edit/manage actions. Photo belongs to Account Page Media only; no additional lower media slot. Main/Sub apply to all CTA/selection/decorative states; font/size controls continue. Header/Footer excluded. Existing review/return eligibility, privacy and submission controls must remain canonical when order details are designed.

2026-10-14: User approved overview and explicitly confirmed the visible bottom transparency gradient. Preserve this alpha fade in actual CSS across shared account hero tabs.

## Account orders v1

Reference: `output/jewelry-gifts-theme-samples/account-orders-v1.png`, presented for review. Shared faded Account Page Media hero and vertical sidebar; selected Orders uses Sub background and Main left indicator. Right content has existing status filters/search and independent 8px order cards, not a master card. Expanded order contains native items, eligible Buy again action and payment/address/summary columns. Processing example excludes Review/Return; those remain conditionally available for eligible delivered items. Collapsed card preserves header/date/status/total and chevron. Actual shipping/tax/discount totals and payment states override sample labels; shipping RM0 is illustrative. Existing pending-payment proof and refund/return state must remain accessible rather than silently removed. All Main/Sub, font/size and Page Width/gutter contracts continue.

2026-10-14: User approved Orders and requested Addresses.

## Account addresses v1

Reference: `output/jewelry-gifts-theme-samples/account-addresses-v1.png`, address content composition presented for review. Shared account hero and sidebar remain the approved Overview/Orders versions; the generator's enlarged MY ACCOUNT and smaller Hello hierarchy does not replace them. Saved address list alongside native editor, independent 8px bordered panels with no heavy shadows, approximately35/65 content split. Existing first/last name, phone, label, line1/line2, city/state/postcode/country and default-address contracts; Edit/Delete/Add/Set default/Save/Cancel use canonical endpoints and validation. Label uses its existing input contract; incidental dropdown arrow does not introduce a new label enumeration. Default checkbox must show a visible check. Mobile stacks list/editor. Actual address rules, delete/default behavior and errors stay authoritative. Main/Sub, typography controls and outer shared Page Width/gutter remain required.

2026-10-14: User approved Addresses, explicitly requiring aligned buttons and Page Width Standard/Wide/Full gutters. Same-row button height, baseline and gap must match; column widths plus gap must stay within the shared content frame. User requested next design.

## Account saved items v1

Reference: `output/jewelry-gifts-theme-samples/account-saved-v1.png`, presented for review. Shared faded Account hero and sidebar; generated baked gift-box slogan is incidental and not a new content requirement. Three cards in right content region; actual wishlist count, persisted product images/title/subtitle/price and canonical Quick buy. Filled white heart within Main circular top-right image button, inset12px, uses existing wishlist removal action. Images edge-to-edge with8px card/photo corners, no heavy shadows. Product text areas use consistent sizing/flexible cards to align all44px Quick buy buttons despite wrapping. Continue shopping centered below whole right grid; its pill outline/radius in this reference is explicit. Main/Sub, font/size, responsive wrapping and shared outer Page Width/gutter apply; no separate saved-items media slot.

2026-10-14: User approved Saved items and requested Profile.

## Account profile v1

Reference: `output/jewelry-gifts-theme-samples/account-profile-v1.png`, presented for review. Shared approved faded Account hero/sidebar remain consistent; generated alternate necklace photo is sample context, not a per-tab media slot. One8px lightly bordered profile surface split roughly65/35: editable full name/phone and read-only email left, gentle Sub-tinted native helper/icon right. Main Save changes button aligns to form gutter. Canonical profile endpoint saves only supported name/phone and returns existing validation/status; email remains readonly. Helper text/icon are template content, not privacy promises or merchant-uploaded media. No additional password/notification/personal fields. Mobile stacks columns/fields. Main/Sub, font/size and Page Width/gutter contracts apply.

2026-10-14: User approved Profile and requested Vouchers.

## Account vouchers v1

Reference: `output/jewelry-gifts-theme-samples/account-vouchers-v1.png`, presented for review. Shared Account hero/sidebar continue; baked gift-box slogan remains incidental. Two-column ticket cards with Sub stub, Main amount/action, top/bottom semicircular cutouts at the divider and a dashed perforation. 8px card corners, aligned44px Use voucher CTAs, native status tabs. Terms expand inline within the content half and allow natural card height; no popup. Text/rules/expiry/amount/eligibility use existing coupon DTO, never assumed from sample dates.

Manual code form explicitly applies to current cart via the existing customer coupon endpoint/shared eligibility service, as previously authorized in Baby phase. No claim-to-wallet feature or new coupon storage. Input/Apply share height and baseline; viewport/error states remain visible. Main/Sub, font/size and shared Page Width/gutter apply. Mobile stacks cards/form without damaging ticket shape or cutouts. Coupon rejection, minimum spend, category restrictions and expired/used states remain canonical.

## Voucher CSS feasibility proof

2026-10-14: User explicitly approved the runnable voucher CSS proof as the source for eventual Jewelry integration, including transparent cutouts, perforation and inline Terms behavior, and requested the next design.

User specifically questioned whether the cutouts/perforation can be faithfully implemented. Bounded local HTML/CSS proof: `output/jewelry-gifts-theme-samples/voucher-css-proof.html`, served locally at `http://127.0.0.1:8534/voucher-css-proof.html` by a separate loopback-only preview process. This is a visual prototype, not official Jewelry integration or a cart-writing endpoint; prototype Use/Apply explicitly report no cart change.

Implemented real transparent top/bottom radial mask cutouts, 13px desktop/11px mobile radii, thin outline arcs, dashed divider, 8px outer corners and 44px buttons. Background pattern and color switching demonstrate transparency without background-colored filler circles. Terms expand inline; first card height grows from346 to457px independently while lower notch follows the new height. Main/Sub inputs changed computed action/stub colors successfully. At390px viewport cards stack with no horizontal overflow; Terms remains usable and button heights44px. Native HTML details and existing Lucide ticket/chevron assets used.

Browser evidence: `voucher-css-closed.png` and `voucher-css-expanded.png`. Reference and actual component screenshots inspected together. Initial static lora/inter font files were weight700; proof now uses already available variable Lora/Inter files with cache-versioned URLs. Jewelry integration must use variable fonts, not the static700 samples. One keyboard-action timeout and one raw CDP screenshot timeout occurred; documented screenshot fallback succeeded, with visible DOM state verified. No repeated issue or application changes. Font licenses copied beside preview assets.

## Shopping cart v1

2026-10-14: User approved Shopping cart v1 and requested Checkout next.

Reference: `output/jewelry-gifts-theme-samples/shopping-cart-v1.png`, presented for review. Open product rows left, bordered Order summary right; large native product images, title, variant, SKU, quantity and Remove use existing cart contracts. Coupon input/Apply uses the existing eligibility endpoint; totals remain server authoritative. Checkout and Continue shopping use canonical routes. Desktop summary is sticky below the header; mobile stacks in normal flow. Optional lower jewelry image belongs to Cart Page Media and disappears without reserved space when hidden or absent. Its outer frame must align to the shared Page Width gutter despite the generated image's incidental wider framing. Main/Sub, registered variable fonts, typography sizes, aligned buttons and Standard/Wide/Full rules apply. No new gift options or commerce logic.

## Checkout v1

2026-10-14: User approved Checkout v1 and requested next design.

Reference: `output/jewelry-gifts-theme-samples/checkout-v1.png`, presented for review. Four independent white 8px cards with visible gaps, no enclosing master card: 1 Contact information, 2 Shipping address, 3 Delivery Method, 4 Payment method. Shared canonical content frame overrides incidental generated outer margins. Right summary top aligns with first card, desktop sticky below header and mobile normal flow, large product photos, no lower image. Main/Sub control numbered badges, selected radios, surfaces and CTAs; checked radios must have an unmistakable center dot. Main/body typography remains registered Lora/Inter with existing font/size controls, rather than treating generated serif field labels as a separate font contract.

Existing checkout.store, shipping-quote, delivery-options and cart.coupon.apply/remove contracts remain authoritative. Saved address selection, validation, tax/discount rows, unsupported-payment guards, hosted return/retry and pending proof states remain conditional. Sample delivery fees/dates, bank name, online provider list and 5MB upload caption are illustrative only: actual shipping/payment configuration and existing validation determine rendered labels, limits and availability. No new gateways or delivery promises. Server totals and button amount must agree. Header/Footer remain shared; no Checkout Page Media slot is introduced.

## Order confirmation success v1

### Success v2 requested composition

2026-10-14: User explicitly approved Success v2 top hero overlap and lower transparency fade; this supersedes Success v1 bottom media placement.

Reference: `output/jewelry-gifts-theme-samples/success-v2.png`, presented for review after user requested the success Page Media image at the top overlapping behind Thank you, Alex. This revision replaces the bottom decorative image with a full-bleed top panorama beneath shared Header. Photo lower half fades with CSS alpha mask into the actual theme page background, with live confirmation icon/name/helper/order number layered above its lower region. Do not bake live confirmation text into the uploaded photo. Same existing success Page Media slot, Desktop/Mobile/hide/focus contract; no additional slot. Missing/hidden media removes photo height and overlap offsets, leaving native confirmation content with normal spacing. Mobile uses its mobile asset or focused fallback, shorter hero and readable text. Order details and actions retain canonical Page Width edges and original commerce contracts. This sample is a composition revision awaiting approval, not application integration proof.

## Pending payment v1

2026-10-14: User approved Pending payment v1 and requested next design.

Reference: `output/jewelry-gifts-theme-samples/pending-payment-v1.png`, presented for review. Same approved success top Page Media panorama/overlap/alpha fade, native clock and awaiting-payment heading, actual order number. Left item/address/totals panel and right bank/proof panel, independent 8px bordered surfaces; same-height centered tracking/shopping actions. No Paid claim. Uses existing success route state, latest payment configuration snapshot bankName/accountNumber/accountHolder, clipboard Copy details and customer.payment-proofs.store with existing ownership/CSRF/validation. Proof status, filename, rejection reason and canUpload remain authoritative; submitted proof does not mark payment Paid. JPG/PNG/PDF maximum10MB matches existing shared proof contract. Sample bank/amount/address are illustrative. Hosted online pending/retry states retain canonical conditional actions instead of displaying bank details. Main/Sub, fonts, Page Width and responsive stacking remain required; no new pending media slot or backend flow.

## Tracking lookup v1

2026-10-14: User requested next design and explicitly excluded Header/Footer from subsequent design images. Official shared shell remains inherited during integration.

Reference: `output/jewelry-gifts-theme-samples/tracking-lookup-v1.png`, presented for review. Full-bleed upper tracking Page Media panorama fades through lower half behind native title; open centered form within canonical Page Width frame, not a shadow card or image/form split. Existing order_number/email fields post order-tracking.request to send verification code; this does not directly disclose tracking data. Existing verification/throttle/error/status behavior remains canonical. Send verification code Main CTA and Back to shop; registered fonts/Main/Sub and Desktop/Mobile/hide/focus media apply. Hidden/absent media removes reserved photo space/offset, retaining readable form. Generated Header/Footer, menus, slogan and gift-box lettering are incidental and do not replace shared shell settings or introduce editable fields.

## Tracking verification v1

2026-10-14: User approved verification composition but flagged portrait/mobile-looking image. Subsequent sources must be landscape Desktop body-only, excluding Header/Footer.

Reference: `output/jewelry-gifts-theme-samples/tracking-verify-v1.png`, body-only sample presented for review. Same tracking Page Media with bottom alpha fade, open centered title/form, single native six-digit code input, Verify code, Resend code and Change order details. Existing order-tracking.verify/resend/show contracts, CSRF, code expiry, throttling and inline errors/status remain authoritative. No countdown or email disclosure added. Main/Sub, typography and canonical outer width/gutter continue. Generated portrait sample depicts composition; desktop must use the approved lookup's compact shallow hero and overlap rather than scaling this photo height to push the form below the fold. Mobile uses focused/mobile media and compact spacing; missing/hidden image removes reserved height. Shared Header/Footer excluded from design source per user request, inherited in actual integration.

## Tracking result v1

2026-10-14: User approved Tracking result v1 and requested next design.

Reference: `output/jewelry-gifts-theme-samples/tracking-result-v1.png`, landscape Desktop body-only presented for review. Shared tracking media top panorama/alpha fade behind native heading/order/status; horizontal five stages, left item panel/right Shipping updates, lower details/address/totals panels, centered Continue shopping. 8px thin borders, moderate gaps, aligned canonical Page Width edges, Main/Sub and registered fonts. Generated all-serif body is incidental: body remains Inter as registered. Existing verified trackingPayload provides items/address/timeline/status/tracking/totals and currency snapshot; sample dates/events are illustrative only. Payment confirmed stage requires actual payment evidence, not inference from order status; unknown stages must not imply completion. Courier/number shown only when populated, empty timeline uses existing fallback, no ETA invented. Mobile stacks cards and allows accessible stage wrapping. Same existing tracking Page Media slot, hidden/absent media removes reserved space; shared Header/Footer inherited.

## Review overlay v1

2026-10-14: User confirmed Review overlay v1 and requested next design.

Reference: `output/jewelry-gifts-theme-samples/review-v1.png`, landscape desktop overlay presented for review. Foreground only is authoritative; generated account background menus including Payment methods are incidental and do not replace approved account navigation. 8px modal with left product photo/title/variant/order number and right rating/title/comment/photo form, aligned Cancel/Submit for review. Canonical existing reviewTarget/orderItemId, rating/title/comment/images contracts and eligibility, pending/published guards, validation and submission states remain shared. Up to5 JPEG/PNG/WebP photos,5MB each; remove preview buttons; approval-pending copy after submission. Main/Sub color stars/CTA/helper surfaces; registered Lora/Inter fonts and canonical outer frame responsive bounds. Modal focus/escape/close and loading guards retained, mobile stacks context/form with accessible scroll. Product image from order item; no Page Media slot, reward or fabricated review. Sample4-star state illustrates selection only, initial rating remains0.

## Return overlay v1

2026-10-14: User approved Return overlay v1 and requested all remaining designs together. Coverage audit found all23 baseline surfaces already presented (plus separate tracking verification). No remaining new baseline page design. Empty/error/loading/expired/stock/proof/provider states are required conditional implementations within these approved surfaces, not omitted functionality. Auth overlap correction remains authoritative despite v1 source images. CMS homepage/help/policy content at /pages/{slug} remains separate CMS scope; support chat/FAQ inherited from existing shared support surfaces. Search stays shared listing scope. Shared Header/Footer inherited and excluded from subsequent design images. Design coverage does not establish application implementation or browser acceptance.

Reference: `output/jewelry-gifts-theme-samples/return-v1.png`, landscape body-only overlay presented for review. Foreground authoritative; dimmed generated account navigation is incidental. Left product context photo/title/variant/SKU/order/purchased quantity, right quantity stepper, resolution refund/replacement radios with visible selected dot and Sub background, reason select, optional details and evidence upload, aligned Cancel/Submit request. Existing customer.returns.store order_item_id/quantity/resolution/reason/details/evidence contracts, eligibility/window/policy summary, remaining returnable quantity, CSRF/ownership and errors remain canonical. Existing six reason options retained. Up to5 JPG/PNG/WebP5MB each, no new pickup/automatic refund/bank fields. Store policy copy and window are data-driven; sample generic copy not a replacement for configured rules. Same-SKU replacement and staff-recorded external refunds preserve existing operations. Main/Sub, registered fonts, modal responsive bounds/scroll/focus/close and shared Page Width apply. No new Page Media slot.

Reference: `output/jewelry-gifts-theme-samples/success-v1.png`, presented for review. Centered confirmation heading/check, actual customer/order number, bordered item rows with large photos; three details/address/totals columns and aligned Track order/Continue shopping buttons. All content and lower optional checkout-success Page Media must share canonical Page Width edges; generated wider image margins are not authoritative. Header stays shared, not the generated replacement. Main/Sub and registered typography controls apply. Mobile stacks summaries/buttons. Actual order snapshot, currency, discounts/tax, ownership and tracking authorization remain canonical. Paid shown only for confirmed paid state; pending payment has its own next sample and existing proof/retry actions. No email-delivery promise or invented delivery estimate. Lower image uses existing success media contract, hides without reserved space, supports Desktop/Mobile/hide/focus.
