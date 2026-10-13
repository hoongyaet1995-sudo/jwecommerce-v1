# Storefront Theme Data Contract v1

Date: 2026-10-12. Scope approved by user: complete Home & Decor before extending Beauty & Skincare. Local development only. Existing original design remains Default Theme.

## Boundary and implementation status

This document distinguishes the **existing operational contract** from the **target shared presentation contract**. Existing routes/services are operational; normalized view models below remain a target, not a new API; StorefrontVariantData is implemented for shared variant projection/selection. Do not claim that PDP/auth/account are themed because the theme selector exists.

| Surface | Existing owner | Home & Decor status |
| --- | --- | --- |
| PLP / Category / Collection | StorefrontController + CatalogFilterService | Integrated, real catalog, filters/sort/pagination |
| Quick Buy | quick-add-variant-modal + resources/js/app.js | Integrated skin; original stock/cart hooks retained |
| Cart drawer | CartController + CartViewData + CartPricingService | Independent Home & Decor skin integrated; same CartViewData/routes/hooks |
| PDP | StorefrontController::show + variant-selector + storefront.show | Default retained; independent Home & Decor renderer integrated |
| Login / Register / Forgot password | CustomerAuthController + customer guard | Default retained; independent Home & Decor renderer integrated |
| My Account | CustomerAccountController + OriginalAccountPayloadFactory | Default retained; independent Home & Decor renderer integrated |
| Header / Footer | StorefrontShellSettingsService + shared nav/footer | Existing separately saved shell settings, not overwritten by listing theme |
| Homepage | published CMS document / HomepageSelection | Existing CMS ownership, not replaced by Website Theme |

Website Theme preview selects page **types**, not every catalog URL: PLP (including filters, Quick Buy and cart), PDP, Login, Register, My Account. PLP/PDP/Login/Register/Forgot Password/My Account previews are enabled for Default and Home & Decor. Category/Collection exceptions remain a separate optional settings disclosure; the selected catalog supplies real PLP data, without becoming a different page-type template.

## Ownership and rules shared by every theme

Theme code owns DOM, CSS, typography, spacing, image ratios and animation. Laravel owns product eligibility, stock, money, filters, reviews, authentication, authorization and order operations. A theme must never create a parallel cart, inventory table, login endpoint or account payload.

MySQL product/catalog/customer/order records are authoritative. Cart variant quantities and coupon selection currently live in the Laravel session. Theme settings live in `store_settings.website_design.value.data` (internal key and `/admin/website-design` URL intentionally retained for compatibility; visible name is Website Theme).

Public models/payloads must use explicit allowed fields. Never serialize whole ProductVariant/Customer/User models into public JSON: cost_price, password hashes, internal inventory/provider records are excluded. Account data belongs only to the authenticated customer guard; Admin authentication does not authorize reading another customer's account.

Money uses integer minor units. Prices originate from variant price, falling back to product base_price only when no variant exists. Display conversion/formatting uses StorefrontCurrencyContext and `@storefrontMoney`; purchase endpoints recalculate, never trust browser display totals. Compare-at is shown only when greater than current price. Order history uses stored order currency/exchange-rate/line snapshots, not today's catalog price.

Missing, null, zero, false and [] are distinct. Stock null means unverified; zero means unavailable; positive stock still requires enabled=true. Missing metadata hides the block or uses the existing image-not-found asset. No theme invents ratings, guarantees, delivery dates, certifications or membership points.

## Existing PLP inputs

Producer: Catalog Admin Product/Category/Collection editors -> protected catalog APIs -> products/product_variants/product_images/categories/collections and relationship tables. Consumer: `StorefrontController::{index,category,collection}` -> `CatalogFilterService::for` -> `CatalogFilterResult::toViewData` -> Default or Home & Decor Blade.

| Field | Current shape / meaning |
| --- | --- |
| products | LengthAwarePaginator of active Products with variants/images; total/current page/links retained |
| categories | Active category models for navigation/filter selection |
| collection | Nullable active Collection; title/subtitle/description/cover_image |
| listingCategory | Nullable Category; name/description/image_url |
| catalogListingSettings | Store-owned all-products title/description, not theme factory copy |
| filters | search, category slug, sort, layout, minPrice/maxPrice, boundsMinPrice/boundsMaxPrice, priceRequested, inStock, colors[], materials[], minRating, variantFiltered |
| facets | Available colors/materials with counts, price bounds and published-rating availability |
| listingRouteName / listingRouteParameters | Canonical GET target preserving category/collection constraints; Admin draft preview retains theme/scope |

GET queries are `search`, `category`, `sort`, `layout`, `price_filter`, `min_price`, `max_price`, `color[]`, `material[]`, `in_stock`, `min_rating`, `page`. Sort values: empty featured, price_asc, price_desc, rating, newest. `CatalogFilterService` validates/normalizes values and computes facets; themes do not reimplement queries or counts. Category paths lock their category. Filter submission resets pagination. Sort preserves active filters. Reset preserves path scope. Empty results have an explicit reset action.

## Existing PDP and variant inputs

Producer: Product Admin and PDP content/review editors -> product records, ProductPdpContent, published ProductReview/media and explicit related-product relations. Consumer: StorefrontController::show -> storefront.show + variant-selector.

| Field | Current source |
| --- | --- |
| product | id/handle/title/subtitle/brand/description/features/options/category/SEO; active products only |
| images | ProductImage sorted by position; path/alt/product_variant_id |
| selectedVariant | Query `variant` within loaded product variants, then existing fallback |
| selectedImage | Query `image` index, then selected-variant linked media, then first image |
| pdpBrand | Product brand, otherwise store name |
| pdpPaymentMethods | PaymentConfigurationService::configuredStorefrontMethods |
| companions | Explicit related products filtered active; images/variants loaded |
| pdpContent | highlights, specifications, shipping_policy/use_store_default_shipping, custom_accordions, configurator_label |
| reviews | Published reviews with only published review media; actual count/average computed from these |

The existing variant-selector emits an explicit payload: string id, sku, title, integer price, nullable compareAt, nullable stock from WebsiteInventoryAvailability, enabled, options map, image, visualType, colorCode, optionImage. Product option groups use id/name/displayType/values/valueVisuals; supported presentation types are color_palette, option_image, pills, dropdown. A legacy Finish group is presentation fallback, not a new SKU. Variation changes must update price/SKU/media/stock/selected hidden variant ID consistently in main and sticky purchase controls.

Runtime hooks to retain or adapt centrally: `data-pdp-*`, selector payload and form fields, `data-quick-add-trigger`, `data-quick-add-product-id`, `data-quick-add-variant-id`, `data-quick-add-modal`, existing modal controls. Inspect resources/js/app.js consumers before changing hook names. Home & Decor markup may differ, but actions must share these semantics.

## Purchase and cart contract

| Action | Method and route | Input / result |
| --- | --- | --- |
| Add selected variant | POST /cart/items | variant_id, quantity>=1, optional checkout boolean; CSRF required |
| Buy selected variant now | Same POST | checkout=1 routes to checkout; not a second cart engine |
| Update line | PATCH /cart/items/{variant} | quantity; same stock guard |
| Remove line | DELETE /cart/items/{variant} | Session line removal |
| Drawer refresh | GET /cart/drawer | Shared HTML from CartViewData |
| CSRF refresh | GET /csrf-token | Same-session token used by shared quick-buy flow |

Successful add redirects to cart.show or checkout.create; shared JS follows the existing flow, refreshes drawer/count and opens the drawer. Validation errors expose variant_id or quantity (JSON422 for AJAX / session errors for forms). Unknown/disabled/out-of-stock variants cannot be purchased. Existing cart quantity + requested quantity must not exceed authoritative available stock. Theme preview is read-only for **theme settings**, but its commerce controls still operate the user's normal session; do not silently create a second preview cart.

CartViewData exposes items {variant,quantity,available}, coupon, summary {subtotal,discount,shipping,tax,total}, shippingEstimateAvailable, shippingOption, shippingProgressThreshold and active addons. Shipping estimation may be unavailable; never present unknown shipping as free. Pricing/eligibility comes from CartPricingService/ShippingConfigurationService. Cart drawer uses #cart-drawer and shared data-cart-* hooks.

## Authentication and My Account contract

Login: GET/POST /account/login, fields email/password/remember. Only non-admin users with a Customer record may log in; session regenerates and redirects to customer.account. Register: GET/POST /account/register, name/email/password/password_confirmation/terms; unique users.email, password minimum8, accepted terms, transactional User+Customer association. Forgot: GET/POST /account/forgot-password, email, generic non-enumerating result. Logout: POST /account/logout, customer guard only; Admin session preserved.

Account: GET /account guarded by auth:customer + customer middleware. `OriginalAccountPayloadFactory` emits user, orders, addresses, wishlist, dashboard, vouchers, returnPolicy and gaps. Orders include their own monetary/currency snapshots, lines, tracking, payments, payment-proof and return view models. Profile PATCH /account/profile uses name/phone; address POST /account/addresses uses label, first_name,last_name,phone,line_1,line_2,city,state,postal_code,country,is_default. Wishlist POST/DELETE /account/wishlist/{product}. Existing coupon/review/return/payment-proof endpoints remain shared; redesign does not imply a new business capability.

Current explicit account gaps: password, two_factor, sessions, membership_points. A new theme must not display active buttons or fabricate values for these. Admin-only preview of My Account must use an explicitly non-sensitive fixture or an explicitly authorized customer context, with preview writes disabled; never pick an arbitrary real customer to make the sample look populated.

## Target normalized view models (next implementation gate)

Introduce one PHP presentation adapter layer, preserving existing controller/routes/services and Default output. Avoid a second REST product/account API solely for themes. All future theme renderers consume the same allowlisted projections:

| Object | Stable proposed fields |
| --- | --- |
| ThemeContext | contractVersion=1, themeKey, pageType, scope, preview, capabilities |
| Money | amountMinor, currency, formatted; order snapshot context where applicable |
| ProductCard | id,handle,url,title,brand,media,price,compareAt,variantId,availability,quickBuyAvailable |
| Variant | id,sku,title,options,price,compareAt,media,visual,enabled,availableQuantity,availabilityStatus |
| ProductDetail | product identity/content,media[],options[],variants[],selection,highlights[],specifications[],accordions[],publishedReviews,paymentMethods[],companions[] |
| Listing | heading,description,media,products,pagination,filters,facets,sortOptions,routeContext |
| Purchase | selectedVariantId,quantity,canAdd,reason,addUrl,checkoutUrl,csrf; server remains authority |
| Cart | Shared CartViewData projection plus canonical action URLs |
| Account | Existing allowlisted accountPayload, shared URLs and capability gaps; no invented parallel shape |

Do not implement these names independently per theme. Add adapter contract tests first, then bind Default and Home & Decor through it. Stock status is a centralized enum: unverified / unavailable / available / disabled; distinguish disabled from sold out. Theme capability status is per page type; a theme may not silently claim a themed PDP while routing to Default.

## Findings to resolve before declaring a full theme

1. Variant data is assembled separately in variant-selector and Quick Buy; consolidate into one projection to prevent field/stock drift.
2. PDP initial fallback chooses a variant using legacy variant.stock; purchase availability already uses WebsiteInventoryAvailability. Replace selection policy centrally with authoritative availability before new PDP binding.
3. PDP initial cartEnabled checks quantity but not enabled; the server rejects disabled variants. Normalize canAdd in the shared projection and test both renderers.
4. Listing price sort currently orders product base_price, while displayed card price may be first/filtered variant price. Specify and test a common listing-price policy before changing semantics.
5. Account wishlist markup must consistently use the customer guard; audit Default PDP guard access before moving it into a shared renderer.
6. Theme settings currently switch PLP only. Add per-surface capability resolution and explicit fallback before PDP/auth/account rollout.
7. Home & Decor cart/filter styling must be accepted as part of the full pack; a working shared drawer alone is not themed-design completion.

These are audited gaps, not claims that they were fixed in this documentation phase.

## Rollout and acceptance order

1. Website Theme name + page-type preview + this audited contract.
2. Shared product/variant/purchase projection, Default parity and contract tests.
3. Home & Decor PDP sample with real product data -> user design approval -> integration.
4. Home & Decor cart/filter skin -> interaction acceptance.
5. Home & Decor Login/Register/Forgot and My Account samples -> approval -> guarded integration.
6. Whole-pack desktop/mobile screenshots and workflows; save/reload/default reset and no cross-customer leakage.
7. Only after full Home & Decor acceptance, extend Beauty & Skincare using the same projections.

Every phase follows Admin producer -> Laravel service -> MySQL/session -> operational consumer -> permissions/failure -> tests -> browser. Keep one issue ledger at docs/acceptance/2026-10-11-home-deco-plp-sample.md. Current historical PLP evidence does not close future PDP/account gates. Push/deploy requires separate user synchronization approval.

## Audited source and existing regression coverage

Read for this contract: app/Http/Controllers/StorefrontController.php, Admin/CatalogApiController.php, CustomerAuthController.php, CustomerAccountController.php, CartController.php; app/Services/CatalogFilterService.php, CatalogFilterResult.php, CartViewData.php, Inventory/WebsiteInventoryAvailability.php; app/Domain/Accounts/OriginalAccountPayloadFactory.php; Product/ProductVariant/ProductPdpContent/ProductReview models; resources/views/storefront/show.blade.php and storefront variant-selector/quick-add-variant-modal components; routes/web.php and resources/js/app.js.

Existing tests to carry into adapter/pack implementation: WebsiteDesignTest, StorefrontCatalogFilterTest, StorefrontQuickAddTest, StorefrontVariantVisualTest, StorefrontProductPresentationFallbackTest, StorefrontPdpRichContentTest, CustomerAccountOperationsTest. The prior PLP run was23 tests116 assertions; PDP/account tests are identified for the next phase and were not rerun as part of this read-only audit. Do not substitute this inventory of tests for passing evidence.

## 2026-10-12 adapter implementation checkpoint
StorefrontVariantData now owns explicit variant projection, inventory-aware fallback selection and canAdd checks. PDP selector and Quick Buy share projection; existing option normalization/hooks retained. ProductDetail/Account normalized projections and PLP sort-price alignment remain pending. Home & Decor PDP exists only in a local sample route awaiting design approval. All future theme PLP/PDP/Login/Register/My Account designs require user sample approval before cutover.

## Approved pack checkpoint 2026-10-12

User approved the image-generated PDP/Login/Register/Forgot Password/My Account/Cart Drawer concepts and local integration. Theme surfaces now use independent Blade templates, not Default markup recolored. Product media remains catalog-owned; account-room.png is a decorative generated theme asset only. Existing menu/footer configuration still owns shell content/style.

WebsiteDesignService::surface reads only protected Admin draft attributes or live settings. Product PDP uses category override when present; auth/account/cart use store theme. Admin identity previews use WebsiteThemeAccountPreview::empty and disable writes; do not read arbitrary customer records. Category/Collection override remains a PLP exception; category override also supplies product PDP fallback. A collection cannot ambiguously claim a product's theme.

Customer reset now has GET token form and throttled POST handler. Laravel broker validates token/expiry; eligible non-admin customer only, password min8/confirmation, remember token rotation and PasswordReset event. Reset email URL resolves to customer.password.reset. SMTP is configured locally, but actual inbox delivery was not sent or verified. Address PATCH uses authenticated customer's relationship and shared validation.

Automated gate: 35 tests / 213 assertions; browser: real PDP/auth pages, empty drawer and non-sensitive account preview checked on desktop and390px mobile. Populated cart, owned order/payment proof/profile/address/wishlist, reset security covered by test database fixtures. Actual customer browser login/order operations and SMTP delivery remain acceptance items; do not mark entire operational pack fully closed based only on preview screenshots.

Final review: protected Admin draft drawer refresh retains selected unsaved theme. Printable order summary uses actual shop name and order number; no fabricated tax invoice identity. Final regression36 tests222assertions; frontend build25.51s after refresh change.

## Beauty & Skincare integration — 2026-10-12

`WebsiteDesignService::THEMES` now includes `beauty`. `view(surface, scope, theme)` centralizes the renderer map for listing/PDP/auth/reset/account, preserving Default and Home & Decor fallback. Cart shares its existing partial endpoint with a scoped Beauty renderer. Global theme owns auth/account/cart; category override supplies PLP/PDP, collection override supplies PLP only. Theme selection never changes catalog records, CMS homepage or Header/Footer settings.

Beauty uses a compact split catalog banner, square studio cards, PDP thumbnail rail/detail images, centered auth forms with a short decorative banner, horizontal account navigation and asymmetric overview. Product photos/content/specifications/options/facets/availability/reviews remain producer-owned; unavailable fields are hidden. Auth decorative imagery is theme-owned and is not a product record. Order operations continue through the shared account orders partial and existing customer endpoints.

GET preview forms must send `theme` and `scope` as hidden fields: query parameters in a GET form action alone are discarded by the browser. This applies to Filter and Sort in both supported industry themes. Protected cart refresh retains draft theme without saving it.

Beauty local evidence: 30 tests/224 assertions plus successful storefront/Admin builds. Real catalog PLP/PDP, category filtering, Quick Buy unavailable state, auth pages, non-sensitive account preview and mobile Filter/Cart/PDP verified. Populated cart, quantity limits, order snapshots and wishlist overview covered by test database fixtures. Real customer order mutations and actual reset-email inbox delivery remain operational acceptance items; screenshots do not close those gates. Local setting applied as Beauty through Website Theme; no GitHub/Hostinger synchronization authorized for this phase.

### Beauty fidelity repair and explicit local sample authorization

User explicitly authorized creating cosmetic sample products for visual judgement. `BeautyThemeLocalSamplesSeeder` is a manually invoked, local-only, idempotent fixture; never include it in DatabaseSeeder or deploy sample records. Three sample products have three persisted media entries each and cross-linked related products. All use existing Product/Variant/Image/PDP/category ports, zero legacy stock and no inventory balance/provider mutations. Records and category identify themselves as fictional local design samples, not saleable formulations.

Approved image design is the layout authority: Beauty PLP uses a continuous category/collection cover image with HTML title overlay, transparent outer background, unframed square media, serif titles and stacked title/price/text-arrow purchase action. The still-life backdrop may contain blush; this does not permit adding a separate blush frame or filling Buy now. Missing hero media falls back to a plain heading rather than invented photography.

Protected PDP preview respects selected category/collection catalog context; category theme overrides may supply PDP appearance, collection appearance overrides remain PLP-only. Mobile PDP retains all photos through thumbnail navigation while omitting the duplicate desktop detail-photo strip so purchase information appears earlier.

## Website Theme customization contract (2026-10-12)
- `website_design.data.customization[theme]`: `style`, `pages[pageType]`, `media[slot]`. Missing settings preserve approved template defaults; missing page keys inherit theme defaults. Existing saves without customization preserve profiles.
- Style: allowlisted font pairs default/editorial/modern/classic, small/medium/big text scaling, colorMode theme/brand/custom. Brand references existing CmsPaletteLibrary paletteId; Main/Sub custom hex only, readable button foreground computed. Theme retains base/background/content contrast. No arbitrary CSS or font injection.
- Media slots: shared account brand image plus login/register/forgot/reset exceptions; desktop/mobile fallback, alt, nine-point crop (`focus`: left/center/right; `focusY`: top/middle/bottom, legacy default middle), visual desktop/mobile crop previews, hide/restore. My Account may use explicitly configured shared image; Default remains unchanged when no settings exist.
- Protected preview accepts bounded validated draft customization JSON. No preview persistence. GET filters/sort and cart refresh preserve draft profiles. Real renderer resolves same saved contract. Public customer forms/actions, catalog fields and stock remain unchanged.
- PageMedia reuses managed upload and latest 60 image assets. Website Theme references use existing CatalogMediaReferenceSynchronizer owner_type website_theme / StoreSetting id inside transaction. Removing a setting releases reference, never deletes asset; normal orphan retention policy remains responsible for unused files.
- Page type overrides differ from Category/Collection theme selection; Header/Footer and homepage/CMS, full Cart/Checkout do not acquire these controls.

### Media capabilities
WebsiteThemeCustomization::mediaContracts is the server-owned per-theme/surface registry. Admin inventory publishes it; UI hides empty Page Media panels and restricts slot choices; validation allows only declared slots; unknown theme/surface resolves defaults. Current Default/HomeDeco/Beauty: account shared slot; login/register/forgot/reset shared plus own exception. PLP/PDP have no Page Media, retaining product/category media owners. Future themes declare only actual approved consumers.

## Header and Footer ownership - 2026-10-12 consolidation
The sole visible editor is Storefront CMS > Header & Footer (compatible route /admin/storefront/header-announcement). Header/Footer menu screens manage menu content only; Page Settings has no shell editor; Website Theme links to the sole editor. Global shell design and header presentation take precedence over retained legacy page copies; legacy copies remain fallback only when no global value exists. Global contentWidth supports standard (1280px), wide (1440px), full; shared gutter aligns navigation content, Footer and Food PLP hero/breadcrumb. Background and media remain full bleed. Draft preview injects proposed width/design/presentation without saving. Announcement content shares the same page, preserves current shell settings when saving.


### Food approved consumers
Food auth uses shared account media plus login/register/forgot/reset exceptions; decorative slogans belong to the image. Food My Account uses independent accountHero, defaults to theme hero asset, accepts uploaded replacement and explicit hidden fallback. Customer welcome remains real text. Product Story is the shared optional product content contract; no per-theme product control forks. Food Checkout has four visual steps using existing contact/address, shipping quote and payment configuration contracts. Step/title props on shared delivery renderer default to legacy values for other themes. Unknown stock is distinct from zero: options remain viewable while purchase stays guarded.
