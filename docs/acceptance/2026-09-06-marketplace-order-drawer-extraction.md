# Order inspection drawer extraction

Scope: ZIP30 MarketplaceOMSPage.tsx, Order Inspection Drawer region only. Existing MarketplaceLogo and lucide icons retained. Source DOM and class strings were copied from the existing local ZIP30 extraction; original full OMS page was not altered.

Adapter: OrderInspectionDrawer.tsx takes MarketplaceOrderDetail, including opt-in recipient fields. No mock context dependency. No official route changed.

Truthfulness changes limited to values/events: unknown warehouse and pickup data no longer use demo defaults; unknown price uses an em dash; unlinked JW SKU says Unmapped; close receives an accessible label; AWB and dispatch buttons are disabled because platform fulfillment is not yet implemented. Timeline explicitly labels the latest platform snapshot, not a performed local action or webhook.

Verification: component render test passed (identity, product, missing price and mapping, no fake warehouse/AWB, two disabled actions); TypeScript noEmit passed. Desktop/mobile browser comparison and protected loader are still pending. Do not claim full UI integration or deployment from this component-only checkpoint.
