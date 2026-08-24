# Storefront Canonical Account Header Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Make the accepted Google AI Studio Account header the single source-faithful, runtime-backed header on every customer-visible storefront route.

**Architecture:** Retain resources/views/components/storefront/nav.blade.php as the one Blade component entrypoint, but transplant the accepted Account Navbar DOM into it and inject only existing SiteMenu, customer, cart, currency, and theme bindings. Replace Account's duplicate inline header with that component; the Account root keeps page-tab state and the shared header owns only its local menu state.

**Tech Stack:** Laravel Blade, Tailwind CSS, Alpine.js, existing resources/js/app.js storefront controls, Laravel feature tests, Chrome browser acceptance.

**Spec:** docs/superpowers/specs/2026-08-23-storefront-canonical-header-design.md

## Global Constraints

- The accepted Google AI Studio Account header is the sole visual reference.
- Preserve DOM order, Tailwind classes, copy, assets, breakpoints, and visible interactions; Blade/Alpine/data bindings may only connect real runtime state.
- Do not introduce a controller, service, model, migration, API, mock customer state, third-party header library, or page-specific header override.
- Use real Menu/SiteMenu data, customer-guard state, cart session state, StorefrontCurrencyContext, and existing theme behavior.
- Cover home, listing/PLP, collection, PDP, cart, checkout, success, auth, account, CMS/public page surfaces, and future public Blade callers. Exclude Admin.
- Capture pre-change and post-change desktop/mobile screenshots, then verify guest and authenticated browser behavior before completion.
- Work in the shared XAMPP checkout without resetting, rebasing, staging, or editing unrelated dirty worktree changes.

---

## File structure and ownership

| File | Responsibility |
| --- | --- |
| resources/views/components/storefront/nav.blade.php | Sole source-faithful shared storefront header with desktop/mobile Account Navbar structure and runtime data adapters. |
| resources/views/storefront/account-original-preview.blade.php | Account workspace body and Account-specific Alpine tab state only; consumes the nav component. |
| resources/js/app.js | Retains cart/theme/currency contracts; changes only if source-local Alpine markup cannot safely express an existing behavior. |
| tests/Feature/StorefrontCanonicalHeaderTest.php | Header rendering, guest/member branching, and public-route regression coverage. |
| tests/Feature/OriginalAccountWorkspacePreviewTest.php | Guards official Account from regaining a duplicate header. |
| docs/superpowers/reports/2026-08-24-storefront-canonical-header-acceptance.md | Source, route, screenshot, browser, and test acceptance evidence. |

### Task 1: Establish failing shared-header regression coverage

**Files:**
- Create: tests/Feature/StorefrontCanonicalHeaderTest.php
- Modify: tests/Feature/OriginalAccountWorkspacePreviewTest.php

**Interfaces:**
- Consumes: storefront.home, storefront.index, storefront.products.show, cart.show, checkout.create, checkout.success, customer.login, customer.account; current factories and customer guard.
- Produces: assertCanonicalHeader(TestResponse response): void used by all public route assertions.

- [ ] **Step 1: Write the failing public-route header contract**

Create StorefrontCanonicalHeaderTest with RefreshDatabase. Create the smallest active product/variant and active header menu tree required by existing route tests. Request home, shop, PDP, cart, checkout, order success, customer login, and one rendered CMS/public page. For each successful response call:

~~~php
private function assertCanonicalHeader(TestResponse $response): void
{
    $response->assertSee('COMPLIMENTARY SHIPPING', false)
        ->assertSee('Handcrafted Studio Essentials for Intentional Workspaces', false)
        ->assertSee('data-atelier-theme-toggle', false)
        ->assertSee('data-atelier-currency', false)
        ->assertSee('data-cart-drawer-open', false)
        ->assertSee('Explore Collections & Categories', false);
}
~~~

Do not skip a route: provide its required fixture.

- [ ] **Step 2: Verify public-route RED**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCanonicalHeaderTest.php
~~~

Expected: FAIL because current shared nav.blade.php lacks Account source announcement/header structure.

- [ ] **Step 3: Write failing member/guest contract**

Use a real customer-guard user and assert runtime identity and source destinations:

~~~php
$this->actingAs($customer->user, 'customer')
    ->get(route('storefront.index'))
    ->assertOk()
    ->assertSee($customer->user->name, false)
    ->assertSee($customer->user->email, false)
    ->assertSee('My Orders & Dispatch (我的订单与物流)', false)
    ->assertSee('Account & Addresses (个人资料与地址)', false)
    ->assertSee(route('customer.logout'), false);
~~~

Add a guest assertion for existing login/register/password targets and assert no fixture member name or 1250 PTS is used as runtime state.

- [ ] **Step 4: Verify member/guest RED**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCanonicalHeaderTest.php --filter=member
~~~

Expected: FAIL because the old shared panel exposes only its older Account CTA.

- [ ] **Step 5: Add Account single-header regression before production code**

Add a test to OriginalAccountWorkspacePreviewTest for both customer.account and customer.account.original-preview. Assert the Account workspace marker once and COMPLIMENTARY SHIPPING once. Do not add a test-only visible marker.

- [ ] **Step 6: Verify Account RED**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php --filter=canonical
~~~

Expected: FAIL because Account owns the duplicate inline header.

- [ ] **Step 7: Commit red tests only when shared-worktree state makes it safe**

~~~powershell
git add tests/Feature/StorefrontCanonicalHeaderTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
git commit -m "test: define canonical storefront header contract"
~~~

Never stage unrelated dirty files. If standalone commit is unsafe, record exact changed files in the acceptance report and continue unstaged.

### Task 2: Port Account Navbar DOM into the canonical shared host

**Files:**
- Modify: resources/views/components/storefront/nav.blade.php
- Modify: resources/js/app.js only if source-local Alpine markup cannot retain an existing interaction
- Test: tests/Feature/StorefrontCanonicalHeaderTest.php

**Interfaces:**
- Consumes: active header Menu tree, auth customer, cart session, enabled CurrencySettings, StorefrontCurrencyContext, customer routes, cart-drawer selectors.
- Produces: unchanged component interface x-storefront.nav active equals current caller's active value.

- [ ] **Step 1: Build narrow component-local source adapter**

Retain existing data reads. Derive only the data required by source markup:

~~~php
$initial = str($memberUser?->name ?: 'U')->substr(0, 1)->upper();
$menuItems = $items->values()->map(fn ($item) => [
    'id' => 'menu-'.$item->id,
    'label' => $item->label,
    'url' => $item->url,
    'badge' => $item->badge,
    'badgeType' => $item->badge_type,
    'badgeColor' => $item->badge_color,
    'direct' => $item->children->isEmpty(),
    'columns' => $item->children->values(),
]);
~~~

Map existing featured attributes only when present. Never add fallback product/menu/points fixtures.

- [ ] **Step 2: Replace component markup with Account Navbar source structure**

Transplant the header region from account-original-preview.blade.php, preserving source DOM order/classes/copy/assets for announcement strip, logo, desktop navigation, search, theme/currency, member/guest menu, cart, translucent desktop overlay, and mobile nested menu.

Wrap it in independent state:

~~~html
x-data="{ searchOpen: false, userDropdownOpen: false, mobileMenuOpen: false, activeMenuId: null, mobileExpandedLayer1: null, mobileExpandedLayer2: null }"
~~~

Map configured root, column, and link destinations to real MenuItem URLs. Map member actions to /account#purchase_history and /account#profile or #addresses; preserve CSRF logout. Guests use the current login/register/password named routes.

- [ ] **Step 3: Preserve real frontend bindings**

Keep source-faithful controls annotated with:
- data-cart-drawer-open and data-cart-count-badge;
- data-atelier-theme-toggle and data-atelier-theme-icon;
- data-atelier-currency on both source selects; and
- existing template data-atelier-currency-options endpoint/default/options payload after the header.

Do not hardcode user name/email, selected currency, cart count, or points. If installMegaNavigation conflicts with Alpine state, make it ignore this Alpine header rather than changing source markup.

- [ ] **Step 4: Verify GREEN**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCanonicalHeaderTest.php
~~~

Expected: PASS for all public routes and both auth states.

- [ ] **Step 5: Verify currency and CMS contracts**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/CurrencySettingsClosureTest.php tests/Feature/Cms/SharedPageRendererTest.php
~~~

Expected: enabled persisted currencies only and CMS public shell remains. If the known exact-class assertion is stale, document it; do not change source markup merely to satisfy it.

- [ ] **Step 6: Commit only scoped safe files**

~~~powershell
git add resources/views/components/storefront/nav.blade.php resources/js/app.js tests/Feature/StorefrontCanonicalHeaderTest.php
git commit -m "feat: unify storefront on account source header"
~~~

### Task 3: Remove Account duplicate header without changing Account body

**Files:**
- Modify: resources/views/storefront/account-original-preview.blade.php
- Test: tests/Feature/OriginalAccountWorkspacePreviewTest.php

**Interfaces:**
- Consumes: canonical x-storefront.nav active account.
- Produces: official/preview Account pages with one shared header and unchanged Account tabs.

- [ ] **Step 1: Delete header-only Account data after proving no other consumer**

Run rg for megaMenuItems, megaMenuDetails, activeMenuId, userDropdownOpen, searchOpen, mobileMenuOpen, and mobileExpandedLayer. Remove only header-only mock arrays and Alpine fields. Retain Account body mock data and body DOM unchanged.

- [ ] **Step 2: Insert shared header at old header location**

Replace the complete duplicate Account header region with:

~~~blade
<x-storefront.nav active="account" />
~~~

Shared profile dropdown must use URL fragments, never Account-root activeTab, so it works from every storefront page.

- [ ] **Step 3: Verify Account GREEN**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/OriginalAccountWorkspacePreviewTest.php
~~~

Expected: PASS, including payload and buyer-review assertions.

- [ ] **Step 4: Run combined regression**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCanonicalHeaderTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php
~~~

Expected: PASS. Treat buyer-review errors as prerequisite investigation, not a reason to weaken header coverage.

- [ ] **Step 5: Commit only scoped safe files**

~~~powershell
git add resources/views/storefront/account-original-preview.blade.php tests/Feature/OriginalAccountWorkspacePreviewTest.php
git commit -m "refactor: consume canonical storefront header on account"
~~~

### Task 4: Build and accept full storefront route matrix

**Files:**
- Create: docs/superpowers/reports/2026-08-24-storefront-canonical-header-acceptance.md

**Interfaces:**
- Consumes: canonical routes, real customer browser session, source manifest.
- Produces: screenshots and acceptance evidence, not unverified completion claims.

- [ ] **Step 1: Capture pre-change screenshots**

Before production changes, capture 1440px desktop and 390px mobile screenshots for authenticated Account profile dropdown, Account desktop mega menu in light/dark, guest shop/auth, representative PDP/cart/checkout/success. Record absolute artifact paths, viewport, route, auth state, and overlay state.

Do not type credentials, create orders, submit checkout, or mutate customer data.

- [ ] **Step 2: Build source classes**

~~~powershell
npm run build
~~~

Expected: exit 0 and emitted CSS includes Account Navbar responsive utilities.

- [ ] **Step 3: Browser accept every route group**

For home, PLP/collection, PDP, cart, checkout, success, auth, CMS/public page, and Account, prove:
1. exactly one header;
2. desktop mega hover/focus opens translucent source overlay and configured URLs work;
3. dark/light state persists across a route transition;
4. enabled currency list/selection persists without disrupting checkout state;
5. cart count/drawer remain real;
6. member dropdown shows real identity, account links, and logout; guest has no fake member state;
7. mobile nested menu works with no 390px horizontal overflow.

Capture the same post-change views as Step 1.

- [ ] **Step 4: Convert every browser defect into RED before a fix**

For duplicate headers, missing header, cart/currency/theme regression, or source divergence: add the smallest focused feature assertion, run RED, make minimal source-faithful fix, then rerun GREEN. No page-local CSS workaround.

- [ ] **Step 5: Final verification**

~~~powershell
C:\xampp\php\php.exe artisan test tests/Feature/StorefrontCanonicalHeaderTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php tests/Feature/ProductReviewOperationalClosureTest.php tests/Feature/Admin/ProductReviewAuthorizationTest.php
npm run build
~~~

Expected: all scoped tests and build pass. Run full suite afterward and report unrelated failures by exact test name.

- [ ] **Step 6: Publish evidence report**

Write source manifest hash, changed files, route matrix, auth matrix, before/after paths, browser interactions, test/build outputs, mock-retained behavior, and unrelated failures. Commit only when safe:

~~~powershell
git add docs/superpowers/reports/2026-08-24-storefront-canonical-header-acceptance.md
git commit -m "docs: record storefront canonical header acceptance"
~~~

## Plan self-review

### Spec coverage

- One shared host and Account duplicate removal: Tasks 2 and 3.
- All customer pages including Success/Auth/CMS: Task 1 contract and Task 4 browser matrix.
- Account-source fidelity: Task 2 constraints and Task 4 comparison.
- Real menus/auth/cart/theme/currency: Task 2 mappings and Task 4 checks.
- No fake data/backend/admin scope expansion: global constraints and Tasks 2 and 3.
- Required before/after screenshots: Task 4.

### Placeholder scan

No TODO/TBD or unspecified test step is present. A route cannot be skipped; its existing fixture must be identified before test code is written.

### Interface consistency

All tasks use existing x-storefront.nav active interface, named customer routes, existing selector contracts, and defined test file names. No new backend interface is introduced.

