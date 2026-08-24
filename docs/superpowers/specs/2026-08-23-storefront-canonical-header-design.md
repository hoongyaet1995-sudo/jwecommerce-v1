# Storefront Canonical Header Convergence — Design Specification

**Status:** proposed for review  
**Date:** 2026-08-23  
**Decision source:** the accepted Google AI Studio Account workspace header, including the authenticated profile dropdown and second-level mega menu.

## 1. Outcome

Every customer-visible storefront route will render one canonical header.  The Account workspace's original Google AI Studio header is the visual and interaction baseline; it is not a new JW-designed alternative.

This includes the storefront home/landing pages, product listings and collections, PDPs, cart, checkout, order-success, authentication, CMS/page surfaces, and Account. Admin pages remain out of scope.

The canonical header must retain the real runtime behaviour that is already available in the current shared storefront header: SiteMenu-driven navigation, customer authentication state, cart count, theme switching, and storefront currency context.

## 2. Evidence and current boundary

- The normal storefront surfaces already invoke `resources/views/components/storefront/nav.blade.php` through `<x-storefront.nav>`.
- The official Account surface (`/account`) currently uses the source-faithful Account workspace with an independent inline header in `resources/views/storefront/account-original-preview.blade.php`.
- The existing shared navigation already owns dynamic menu data, cart/runtime state, theme control, and currency control, but does not faithfully carry the Account source's authenticated profile dropdown or its second-level mega-menu treatment.

Therefore the defect is shell divergence, not a PDP, PLP, Cart, Checkout, or Account-page-specific defect.

## 3. Chosen architecture

### One header host

`<x-storefront.nav>` becomes the only customer storefront header host. Its rendered desktop and mobile markup will be replaced by (or extracted from) the Account source header without altering the source hierarchy, Tailwind classes, visual copy, responsive structure, or interaction affordances.

The Account page will remove its duplicate inline header and invoke the same host. No page will have a local header variant after cutover.

### Preserve source presentation; inject runtime data only

The source-faithful header DOM receives only server/Alpine bindings needed to connect existing runtime truth:

| Header capability | Existing runtime owner | Canonical behavior |
| --- | --- | --- |
| Primary and second-level navigation | `SiteMenu` tree | Render real configured navigation in the Account-source mega-menu structure. |
| Authenticated profile dropdown | customer guard / current customer | Render initials/avatar, name, email, tier/points where presently available, account links, and the existing logout action. |
| Guest state | customer guard | Render the existing sign-in/register path instead of a fake customer panel. |
| Cart | storefront cart service/session | Preserve live count and cart link on every route. |
| Theme | existing storefront theme state | Use the same light/dark state and Account-source control across every route. |
| Currency | storefront currency context/session | Preserve the selected real currency, selector behavior, and checkout-safe session state. |

No customer profile data, points, menu items, currency, or cart values may be duplicated into static header copy.

### Retained Account-source interactions

1. **Authenticated profile dropdown.** The logged-in user icon opens the Account source's compact profile panel. It contains the existing actionable destinations for “My Orders & Dispatch” and “Account & Addresses”, plus the existing logout action. This is a navigation surface; it does not introduce a new profile-edit API.
2. **Second-level mega menu.** Desktop root-menu hover/focus opens the Account source's translucent light/dark overlay and its hover treatment. Its columns and featured content remain driven by the configured menu tree/current shared-nav data rather than fixture content.
3. **Responsive parity.** The source mobile header/accordion behavior remains intact, with cart, guest/member, currency, and theme controls mapped to the same runtime owners.

## 4. Page coverage

The cutover applies to all rendered customer storefront entrypoints, not only the initially named commerce pages:

- home and landing surfaces;
- PLP, product search/listing, collections, and categories;
- PDP;
- cart;
- checkout;
- order success / confirmation;
- sign-in, registration, and password-reset surfaces;
- Account (official and its source-fidelity preview route);
- CMS-driven public pages and any future public Blade route that uses the storefront shell.

The Admin shell, admin navigation, and internal moderation/dashboard UI are explicitly excluded.

## 5. Source-fidelity constraints

- The accepted Account source header is the sole visual reference for this migration.
- The work may extract shared partials only to avoid duplicate markup; extraction must preserve the source DOM order, classes, text, assets, breakpoints, and visible behaviors.
- Dynamic Blade/Alpine directives, non-visible data attributes, URL bindings, and accessibility bindings are permitted only where required to connect real runtime state.
- No fresh navigation design, new visible header copy, third-party header library, or page-specific CSS override is allowed.
- Header unification must not alter product, cart, checkout, payment, order, or footer layouts outside the header region.

## 6. Interaction and compatibility rules

- Desktop hover and keyboard focus must both operate the mega menu; leaving the menu closes it without trapping focus.
- The member menu must work on every authenticated storefront route, including Success and Account, with no duplicate overlays.
- Cart, theme, and currency interactions must retain their current semantics on Checkout and Success; header work cannot reset checkout/session state.
- Guest routes must never render a fake member identity or fake points.
- A page may opt out only if it is not customer-visible storefront UI; otherwise the canonical header is mandatory.
- Header behavior must be resilient when a configured menu has fewer children or no featured item: use the source layout's existing empty/available state, not invented cards.

## 7. Acceptance evidence

Implementation is acceptable only when all of the following hold:

1. Each page group in section 4 renders one header, with no Account-specific duplicate header.
2. Desktop and mobile screenshots show the Account-source visual system on Account, PDP, PLP, Cart, Checkout, Success, Auth, and a CMS/public page in both relevant theme states.
3. A signed-in browser session verifies profile dropdown links and logout visibility without creating fake profile data.
4. Desktop browser checks verify the second-level mega menu's overlay, hover state, and real menu navigation.
5. Browser checks verify live cart count, selected currency, and theme state persist across representative route transitions, including Checkout and Success.
6. Laravel feature coverage proves all customer storefront templates route through the one canonical header host and preserves existing nav/cart/currency/auth contracts.
7. No unrelated global failure is attributed to this work unless it is reproducibly caused by the shared-header change.

## 8. Delivery sequence

1. Capture pre-change screenshots for representative pages and the Account-source interactions.
2. Extract the Account source header into the canonical shared host while retaining existing runtime bindings.
3. Remove the Account duplicate and migrate any exceptional public route that bypasses the host.
4. Add focused feature coverage, then execute real authenticated/guest browser checks.
5. Capture post-change screenshots on all page groups and produce a route-by-route acceptance report.

## 9. Explicit non-goals

- Redesigning the storefront, PDP, PLP, Cart, Checkout, Success, or Auth bodies.
- Changing order lifecycle, review policy, payment, shipping, product, or currency business logic.
- Adding backend-editable header content beyond the navigation configuration already owned by `SiteMenu`.
- Changing admin UI.

