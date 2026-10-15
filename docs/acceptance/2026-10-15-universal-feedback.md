# Universal final design feedback

Status: user design feedback recorded on 2026-10-15; official source integration not authorized by this record alone. Latest message requests consolidation, not deployment. Source is the original approved per-scene images plus explicit feedback, not incidental generator differences.

## Shared buttons
- Primary: Main Color background/border, white text for approved cobalt palette; hover white background, Main border/text.
- Secondary/Continue shopping: white background, Main border/text; hover Main background/border, white text. Saved items Continue shopping centered under entire grid.
- Transition background/text/border around180-220ms, no scaling, shift or geometry change. Keyboard focus visible, touch active state, disabled/loading distinct and no hover inversion when disabled. Preserve contrast for merchant custom palettes through existing foreground rules.

## Operational controls
- Voucher Copy copies exact code; visible short success/failure feedback and accessible status. Use voucher/Apply use existing coupon/cart pricing flow, no fake-success local-only cosmetic action; show validation and failure states.
- Quantity stepper fixed equal outer button cells, centered numeric cell, same heights/line height, border-box, no native spinner, no implicit flex shrink or inherited padding. Verify1/10/100, stock min/max, disabled, desktop/mobile. Prefer shared semantic stepper composition rather than per-theme utility collisions.
- Min/Max remains explicit required gate: typed values synchronize dual slider/current labels/currency units and real GET results. Main accent throughout.

## Cards and media
- Checkout main background is a subtle translucent MAIN-blue tint, not unrelated hardcoded blue. Separate white Step1-4 cards, light gray borders, slight radius/shadow and visible gaps, no master white card. Right Summary sticky, no decorative image below it.
- Success hero at top with lower-half transparency, overlapping behind Order items/Order summary cards; cards above image with white fill, light gray borders and modest corner radius. Pending must follow coherent result shell and must never claim Paid for pending state. Current Pending sample composition differs and requires correction before fidelity acceptance.
- Tracking result image at bottom with upper-half transparency; photo extends behind Shipping updates, never obscures its text/actions. Separate CSS mask/layering, not pre-baked white image required; merchant Page Media remains replaceable.
- All global eyebrows follow Main Color. Canonical Overview account hero/nav/icon set/gutter remains shared. Missing navigation icons in some generated account samples are not authorized removals. Hero bottom transparent fade persists.

## Mobile tracking progress proposal
- Keep all five ordered stages on a compact horizontal rail inside content gutter. Equal-width five cells, small dots18-20px, short labels10-11px wrapping within own cells, no shrinking desktop labels with transform or hidden stages.
- Use short visible labels Created / Paid / Processing / Shipped / Delivered, full accessible status names. Dates/details in vertical Shipping updates timeline below; no extra crowded dates in top rail. Processing/delivery state derives real data, not fixed five-step completion.
- Verify320/360/390/430px, long translations, text size200percent, no horizontal overflow; if localization cannot fit, change entire component to vertical layout at narrow breakpoint instead of clipping stages. Current stage uses aria-current=step and semantics beyond color alone. This responsive implementation is a proposal for approval, not tested runtime behavior.

## Scope and evidence
- Latest remaining batch13 images: account addresses/saved/profile/vouchers, cart, checkout, success/pending, tracking lookup/code/result, review/return. Saved under output/universal-theme-samples, one image per scene. Address editor remains optional additional composition not generated when user steered to feedback consolidation.
- Generated review/return backgrounds are incidental and must use canonical account Orders shell; no invented Payment methods/Preferences tabs. Review title/comment required, actual upload limits and moderation note retained. Return resolution refund/replacement retains existing fields. Unverified generated refund-method promises and policy prose are excluded; use real configured policy only.
- Monetary formatting uses actual currency snapshot/service including decimals; sample missing decimal zeros do not redefine format. Product/stock/order/sample bank values illustrative, no real records/actions created.
- No application source changed, no integration/build/runtime proof claimed, no commit/push/deploy. Dirty Fashion follow-ups remain separate.
