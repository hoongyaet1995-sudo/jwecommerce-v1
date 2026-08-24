# Storefront Currency Session Closure Specification

## Goal

Make the active storefront currency a server-side session preference that is resolved once per request, converts only canonical monetary values for display, and snapshots the selected currency and manually maintained exchange rate when an order is created.

## Decisions

- Currency definitions, enabled state, default currency, symbols, decimal precision, and rates are owned by the MySQL-backed `currencies` StoreSetting.
- Exchange rates are manually maintained by administrators for this P0 scope. No live FX provider is introduced.
- Product, variant, promotion, tax, delivery, and order arithmetic remain base-currency integer cents. Only presentation is converted.
- A visitor choice is stored in the Laravel `storefront_currency` session key, never in localStorage.
- An order snapshots the selected code and rate at checkout. Historical order views use that snapshot even if the setting later changes.
- Free-form CMS or navigation copy containing money-like text is not parsed or rewritten. Only structured monetary values are formatted.

## Contract

`App\Support\StorefrontCurrencyContext` resolves a normalized enabled currency and exposes:

- `current(): array`
- `set(string $code): array`
- `format(int|float $baseCents): string`
- `formatWithSnapshot(int|float $baseCents, string $code, float $rate): string`
- `clientConfig(): array`

The display conversion is `base cents * exchangeRate`, rounded once to the configured decimal precision. Aggregates are converted from their base aggregate, not from a sum of rounded display lines.

The Blade directive `@storefrontMoney($baseCents)` calls the context. Views retain their existing DOM/classes and only replace hard-coded monetary text.

`POST /currency` accepts an enabled ISO code, writes the session, and returns JSON. The header selector posts this endpoint then reloads the current document so all server-rendered surfaces use the same context.

Orders persist `currency_exchange_rate` in addition to the existing `currency`. New orders set both values within the existing checkout transaction. Existing orders without a rate retain a safe base/default fallback for legacy display.

## In Scope

- Header currency selection and session persistence.
- PLP, category, collection, PDP, published CMS product sections, Cart, drawer, Checkout, success, account order history, wishlist, home, quick add, variant selector, and JS dynamic money updates.
- Checkout order currency/rate snapshot and tests.

## Out of Scope

- Live FX feeds, tax jurisdiction recalculation, payment settlement in non-base currencies, CMS rich-text price parsing, and Admin price conversion.
