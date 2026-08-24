# Operational PLP Filters Design

## Goal

Replace the storefront PLP's partial filtering with an operational Laravel/MySQL contract, using only the approved Filter Matrix region from `universal-e-commerce-system-&-schema-suite (8).zip`.

## Closure boundary

This is `OPERATIONAL_CLOSURE`: an Admin Variant Matrix edit must persist to MySQL and appear after a fresh PLP request as the correct filter value, count, and product result. The source ZIP is `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (8).zip`, SHA-256 `F7D1B8C69F138254DE8E9FA93A2777418BB7E9105794DA9FA806AB023E01D894`. The selected source is `src/components/pages/PLPPage.tsx`: Filter Matrix, active chips, empty state, and mobile filter drawer only.

## Ownership

`products` and `product_variants` are the only catalog fact source. Existing Admin Variant Matrix fields remain the only editable source for `price`, `stock`, `enabled`, `color_name`, `color_hex`, and `material`.

ZIP-only `AVAILABLE_MATERIALS`, keyword material matching, fixed `$0-$800` bounds, `PRODUCTS`, and mock product ratings are excluded from production behavior.

## Filter semantics

| Parameter | Real source | Rule |
| --- | --- | --- |
| `category` | Active category + product category | One selection, locked on category routes. |
| `min_price`, `max_price` | Enabled variant price | Inclusive range in minor currency units. |
| `color[]` | Enabled variant `color_name` | OR inside Color; AND with other groups. |
| `material[]` | Enabled variant `material` | OR inside Material; AND with other groups. |
| `in_stock=1` | Enabled variant with stock greater than zero | Product needs a matching sellable variant. |
| `min_rating` | Published product-review average | No published review does not match a positive threshold. |
| `search`, `sort`, `layout`, `page` | Existing valid route state | Preserved by all control links and pagination. |

All input is normalized server-side. Unknown values are discarded, numeric bounds are clamped, a changed filter resets to page one, and no browser storage owns state. A returned product card receives an enabled matching variant so displayed price/SKU/options cannot contradict the filters.

## Facets and UI

`CatalogFilterService` creates the filtered paginator and facet metadata. Every facet count applies all selected groups except its own group. Empty facets hide; actual values that become unavailable because of another selection use the approved disabled-empty state. Price bounds come from the active enabled-variant scope. Currency formatting comes from the storefront currency context.

Blade/Alpine may adapt data and events only. The approved DOM hierarchy, Tailwind classes, desktop sidebar, mobile drawer, accordions, chips, clear actions, and no-results state remain source-faithful.

## Evidence

Feature tests cover inclusive price filtering, enabled/stock eligibility, OR/AND combinations, published-review ratings, validation, facet counts, category lock, and pagination. Browser acceptance modifies one real test variant with Admin, reloads PLP, checks desktop and mobile output, then saves before/after screenshots plus a shared filtered URL.
