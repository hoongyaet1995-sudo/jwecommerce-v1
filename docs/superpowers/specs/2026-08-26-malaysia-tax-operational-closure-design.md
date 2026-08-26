# Malaysia Tax Operational Closure Design

**Status:** proposed and approved for planning; implementation is gated by this document review.

## Purpose

Replace the current global percentage tax calculation with a Malaysia-first tax policy engine. The approved Google AI Studio Tax Zones UI remains the visual source of truth; Laravel and MySQL become the operational source of truth.

JW Studio is an ecommerce platform, not a tax authority. Administrators may enable any configured tax rule without providing an SST registration number. The engine must not infer a rate from a product title, destination, or a generic 8% value; it only applies rules explicitly published by the merchant.

## Current-state findings

1. `store_settings.tax_rate_percent` is currently `8.875` and `CartPricingService` applies it globally to merchandise, shipping, and payment surcharge.
2. The Admin Tax Zones UI keeps zones, classes, rates, and global engine settings in browser localStorage under `aurora_admin_*`; it has no Laravel API or MySQL ownership.
3. Products and variants have no tax-class relationship. Orders only retain `tax_amount`, with no rule, jurisdiction, or taxable-base snapshot.
4. Checkout JavaScript recalculates tax in the browser from `data-tax-rate`, so server and displayed values can diverge.

## Scope and tax policy

### Included

- Malaysia domestic tax configuration, effective-dated rules, product tax classifications, Checkout quoting, and immutable order tax evidence.
- Existing Tax Zones UI bound to Admin APIs without structural or Tailwind changes.
- Existing Cart, drawer, and Checkout tax rows receiving server-calculated values.

### Excluded

- Tax filing, MySST submission, tariff-code lookup automation, a tax-adviser decision engine, foreign tax collection, or changing payment and shipping UI.
- Any automatic claim that a designated area, export destination, or product is zero rated.

## Data model

1. `tax_configurations` stores the current policy: enabled flag, merchant-facing compliance metadata, price display basis, address basis, and publication state. Registration number is optional metadata, never an activation prerequisite.
2. `tax_zones` stores country, state, postcode, or designated-area matching conditions; a zone alone has no rate.
3. `tax_classes` is operator-assigned to products. Its labels are legal-policy labels, not marketing labels.
4. `tax_rules` links one class to one zone with tax type, percentage or specific rate, whether shipping is taxable, priority, enabled state, and `effective_from` / `effective_to`.
5. `products.tax_class_id` links every sellable product to an explicit tax class. No classification means no guessed tax.
6. `order_tax_lines` records rule ID, rule name, class, zone, rate, taxable base, calculated amount, and configuration version at authorization.

## Calculation contract

`TaxQuoteService` is the only authoritative calculator. It receives the cart items, eligible discount, selected shipping quote, payment surcharge, destination address, and current time.

1. Resolve the published configuration and confirm tax collection is enabled.
2. Resolve each product tax class and the matching active, effective rule.
3. Compute only the taxable bases permitted by that rule. Shipping is included only when its matched rule says so.
4. Return a quote containing total tax, immutable candidate tax lines, status, and display label.
5. If tax collection is disabled, a classification is absent, or no matching rule exists, return a non-taxable `not_configured` or `no_matching_rule` result; never fall back to a global percentage. A published merchant rule is sufficient to calculate tax.
6. Checkout authorization recalculates on the server and persists its returned tax lines atomically with the order and payment attempt.

The browser receives a server quote and only renders it. It must not multiply a data attribute by a tax rate.

## Admin and storefront binding

The current Tax Zones screen is retained visually but its rows and counters are loaded from protected `/admin/api/tax/*` endpoints and saved through Laravel validation and transactions. The live inspector becomes a server quote preview, rather than a local calculation.

The existing Checkout, Cart, and cart-drawer summary rows remain structurally unchanged. When no policy is active, their existing tax row shows an unconfigured/calculated-at-checkout state instead of a fabricated amount. When a policy is active, they show the server quote. This is L1 binding work and reuses the approved existing text-row pattern.

The existing Tax Engine Configuration tab has no approved fields for effective dates or merchant compliance metadata. Those are an L2 Admin UI gap to batch into a later Google AI Studio export. They improve traceability, but they do not block the current Admin from publishing a tax rule or enabling tax collection.

## Malaysia-first seed policy

- Preserve Malaysia and address-region definitions as a geographic foundation.
- Remove the generic `Taxable Goods (Standard 8%)` and the `8.875%` global seed.
- Keep tax classes and geo-zone/rule concepts, but seed no chargeable rule. The merchant creates and publishes rates intentionally.
- Do not seed automatic 0% treatment for Labuan, other designated areas, international exports, food, digital services, or medical goods. These need an operator-approved rule backed by the merchant's tax advice and applicable documentation.
- Keep prices tax-exclusive initially, because the current catalogue and Checkout display tax as an added line; that setting remains explicit and versioned.

## Failure and authorization rules

- Only authorized admins may alter tax configuration.
- Invalid date ranges, conflicting equal-priority rules, rates outside permitted numeric bounds, and missing product classes receive stable validation errors. Missing registration metadata never blocks publishing or calculation.
- Checkout must re-quote on submission; a stale browser value cannot be trusted.
- Historical orders retain their stored tax lines even when an Admin changes or disables later rules.

## Acceptance evidence

1. Admin changes persist in MySQL, survive refresh/re-login, and appear in a separate browser session.
2. A domestic address with a valid, active rule receives the expected server quote in Cart, Checkout, and the saved order snapshot.
3. A product without an assigned class, an expired rule, or a disabled configuration never receives a guessed tax amount.
4. Changing an Admin rate changes a new checkout quote but not an already-created order.
5. Unauthorized Admin API calls are denied; malformed rules are rejected.
6. Browser proof covers unconfigured, configured, address-change, and final-order paths in both supported themes.

## Closure mapping

| Capability | Current status | Closure target |
| --- | --- | --- |
| Tax Zones Admin editor | MOCK_ONLY | CLOSED after API + MySQL + browser proof |
| Product tax classification | BACKEND_CONTRACT_GAP | CLOSED after product binding and validation |
| Cart/Checkout tax quote | BACKEND_CONTRACT_GAP | CLOSED after TaxQuoteService replaces global setting |
| Browser delivery-change calculation | MOCK_ONLY | CLOSED after server quote is rendered |
| Order tax audit trail | BACKEND_CONTRACT_GAP | CLOSED after immutable order tax lines |
| Effective-date and compliance-metadata UI | ADMIN_UI_GAP | non-blocking; batch into a later approved Google AI Studio source |
