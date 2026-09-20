# Marketplace Dynamic Attribute Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate source-faithful, provider-valid Shopee and Lazada category attribute controls, including required/optional fields, typed values, SKU scope, units, search, and image upload.

**Architecture:** Normalize each provider schema into a typed frontend descriptor, render it through shared Google AI Studio controls, and serialize values only at the provider adapter boundary. Laravel independently reloads and validates current provider metadata, resolves internal JW image IDs to provider-owned identities, and preserves the existing reviewed-task and uncertain-result safety model.

**Tech Stack:** Laravel 12, PHP 8.x, MySQL, React 19, TypeScript, Vite, Vitest/jsdom, Tailwind CSS, Laravel HTTP fakes, Google AI Studio ZIP source components.

**Spec:** `docs/superpowers/specs/2026-09-20-marketplace-dynamic-attribute-controls-design.md`

## Global Constraints

- Work only in `C:\xampp\htdocs\jwstudio-ecommerce`; preserve unrelated dirty worktree changes.
- The Google AI Studio ZIP is the visual source of truth; preserve selected DOM, copy, Tailwind classes, responsiveness, and interaction behavior.
- Do not include `UI States Spec`, state testers, demo data, or other developer-only ZIP controls.
- Do not hardcode merchant industries or category IDs.
- Do not fall back to a text field for unknown mandatory provider metadata.
- Browser requests carry internal JW image IDs, never provider credentials, Shopee Media Space IDs, Lazada image URLs, or arbitrary external URLs.
- Automated browser acceptance must stop before the final external Publish action.
- Keep existing Size Chart behavior and uncertain-result protection.
- Run local tests, build, and authenticated browser acceptance before asking for GitHub/Hostinger synchronization approval.

## Review Focus

- A late schema response from category A must never replace category B fields; Task 6 adds the A→B race test.
- A SKU value for `TEKAT 1G` must not overwrite the same attribute for `TEKAT 5G`; Tasks 1 and 6 pin scope and SKU identities.
- Multi-select custom values must honor cardinality, uniqueness, option IDs, and units; Tasks 2 and 5 cover client and server rejection.
- An uploaded image owned by another product or changed after review must be rejected before provider HTTP; Tasks 4 and 5 test both cases.
- Unsupported mandatory metadata must remain visible and block progress while unsupported optional metadata may remain empty; Tasks 1, 2, 3, and 6 exercise this behavior.

---

### Task 1: Canonical Attribute Values and Lazada Normalization

**Files:**
- Create: `resources/admin/src/marketplace/publishingAttributes/types.ts`
- Create: `resources/admin/src/marketplace/publishingAttributes/lazada.ts`
- Create: `resources/admin/src/marketplace/publishingAttributes/lazada.test.ts`
- Modify: `resources/admin/src/integration/lazadaPublishingFields.ts`
- Modify: `resources/admin/src/integration/lazadaPublishingFields.test.ts`

**Interfaces:**
- Consumes: `PublishingSource` from `resources/admin/src/integration/marketplacePublishing.ts`.
- Produces: `MarketplaceAttributeField`, `MarketplaceAttributeValue`, `normalizeLazadaAttributes(schema, source, values, brands)`, and `serializeLazadaAttributes(fields, values)`.

- [ ] **Step 1: Write failing normalization tests**

Add table-driven tests for all nine Lazada types, product/SKU identity, unknown mandatory metadata, finite numeric validation, date format, unique multi-values, and image values:

```ts
expect(normalizeLazadaAttributes(schema, source, new Map(), [])).toEqual(expect.arrayContaining([
  expect.objectContaining({key:'lazada:product:manufacture_date',control:'date',required:true}),
  expect.objectContaining({key:'lazada:variant:TEKAT 1G:color_thumbnail',control:'image',sku:'TEKAT 1G'}),
]));
expect(validateAttributeValue(numericField,{kind:'number',value:'Infinity'})).toContain('finite');
expect(validateAttributeValue(unknownRequired,{kind:'empty'})).toContain('cannot be completed');
```

- [ ] **Step 2: Run the focused tests and confirm RED**

Run: `npm test -- --run src/marketplace/publishingAttributes/lazada.test.ts src/integration/lazadaPublishingFields.test.ts`

Expected: FAIL because the canonical types and normalizer do not exist and current logic lacks `date`/`img`.

- [ ] **Step 3: Define the typed contract**

Implement these exact public shapes:

```ts
export type MarketplaceAttributeValue =
 | {kind:'empty'}
 | {kind:'text';value:string}
 | {kind:'number';value:string}
 | {kind:'date';value:string}
 | {kind:'single';value:{id:string|null;label:string;custom:boolean}}
 | {kind:'multi';values:{id:string|null;label:string;custom:boolean}[]}
 | {kind:'image';imageId:number;url:string};

export interface MarketplaceAttributeField {
 key:string; provider:'lazada'|'shopee'; scope:'product'|'variant'; providerName:string;
 providerId:string; sku?:string; label:string; control:'text'|'textarea'|'integer'|'decimal'|'date'|'single'|'multi'|'image';
 required:boolean; options:{id:string;label:string}[]; allowCustom:boolean; maxValues:number;
 units:string[]; help?:string; supported:boolean; unsupportedReason?:string;
}
```

Implement case-insensitive Lazada mapping for `text`, `richText`, `numeric`, `date`, `singleSelect`, `multiSelect`, `enumInput`, `multiEnumInput`, and `img`. Preserve `en_name`, distinguish product and SKU keys, and map Size Chart to the same image value contract while retaining its post-create purpose flag.

- [ ] **Step 4: Serialize only at the adapter boundary**

Implement `serializeLazadaAttributes` so multi-values join only in the returned provider payload, image fields return an internal `imageBindings` map, and empty optional values are omitted:

```ts
export interface LazadaSerializedAttributes {
 normal:Record<string,string>;
 skus:Record<string,Record<string,string>>;
 imageBindings:Record<string,number>;
}
```

Keep `lazadaPublishingFields` as a compatibility adapter until Task 6 removes its string-only state from the modal.

- [ ] **Step 5: Run tests and TypeScript checks**

Run: `npm test -- --run src/marketplace/publishingAttributes/lazada.test.ts src/integration/lazadaPublishingFields.test.ts`

Run: `npm run lint`

Expected: PASS with every Lazada input type and SKU identity covered.

- [ ] **Step 6: Commit the task**

```powershell
git add -- resources/admin/src/marketplace/publishingAttributes resources/admin/src/integration/lazadaPublishingFields.ts resources/admin/src/integration/lazadaPublishingFields.test.ts
git commit -m "feat: normalize Lazada publishing attributes"
```

### Task 2: Shopee Normalization and Typed Validation

**Files:**
- Create: `resources/admin/src/marketplace/publishingAttributes/shopee.ts`
- Create: `resources/admin/src/marketplace/publishingAttributes/shopee.test.ts`
- Modify: `resources/admin/src/marketplace/useShopeePublishingRequirements.ts`
- Modify: `resources/admin/src/marketplace/useShopeePublishingRequirements.test.tsx`

**Interfaces:**
- Consumes: canonical types from Task 1 and `ShopeeAttribute` provider tree.
- Produces: `normalizeShopeeAttributes(tree, selections)`, `validateShopeeAttributeValue(field, value)`, and `serializeShopeeAttributes(fields, values)` returning provider `attribute_id` and `attribute_value_list` records.

- [ ] **Step 1: Write failing tests for Shopee metadata combinations**

Cover input types 1–5, validation types integer/string/float/date, `date_format_type`, `format_type = 2`, units, search-backed values, nested child attributes, `max_value_count`, duplicate multi-values, and unsupported required metadata:

```ts
expect(field({input_type:3,input_validation_type:1})).toMatchObject({control:'integer'});
expect(field({input_type:3,input_validation_type:4,date_format_type:0})).toMatchObject({control:'date'});
expect(validateShopeeAttributeValue(unitField,{kind:'number',value:'800'},'')).toContain('unit');
expect(serializeShopeeAttributes([multiField],values)[0].attribute_value_list).toHaveLength(2);
```

- [ ] **Step 2: Run tests and confirm RED**

Run: `npm test -- --run src/marketplace/publishingAttributes/shopee.test.ts src/marketplace/useShopeePublishingRequirements.test.tsx`

Expected: FAIL because current controls are chosen mostly from `input_type` and do not apply every validation type/cardinality rule.

- [ ] **Step 3: Implement Shopee normalization**

Map input types 1/2/3/4/5 to single, custom single, validated free input, multi, and custom multi. Convert validation types 1/2/3/4 to integer/text/decimal/date controls. Carry `attribute_unit_list`, `max_value_count`, `support_search_value`, `format_type`, and `date_format_type` into the canonical descriptor.

Retain `activeShopeeAttributes` dependency traversal, but expose active descriptors rather than a second independent rendering model.

- [ ] **Step 4: Implement strict serialization**

Preserve provider option IDs for predefined choices. Emit `value_id: 0`, `original_value_name`, and `value_unit` only where the provider permits custom values. Format dates as `dd/mm/yyyy` or `mm/yyyy` according to `date_format_type`.

- [ ] **Step 5: Run focused tests and lint**

Run: `npm test -- --run src/marketplace/publishingAttributes/shopee.test.ts src/marketplace/useShopeePublishingRequirements.test.tsx`

Run: `npm run lint`

Expected: PASS, including nested dependency and maximum-selection tests.

- [ ] **Step 6: Commit the task**

```powershell
git add -- resources/admin/src/marketplace/publishingAttributes/shopee.ts resources/admin/src/marketplace/publishingAttributes/shopee.test.ts resources/admin/src/marketplace/useShopeePublishingRequirements.ts resources/admin/src/marketplace/useShopeePublishingRequirements.test.tsx
git commit -m "feat: normalize Shopee publishing attributes"
```

### Task 3: Source-Faithful Attribute Controls and Optional Panel

**Files:**
- Create: `resources/admin/src/marketplace/MarketplaceAttributeControl.tsx`
- Create: `resources/admin/src/marketplace/MarketplaceAttributeControl.test.tsx`
- Create: `resources/admin/src/marketplace/MarketplaceAttributeSections.tsx`
- Create: `resources/admin/src/marketplace/MarketplaceAttributeSections.test.tsx`
- Modify: `resources/admin/src/marketplace/publish37/OptionalAttributesSection.tsx`
- Modify: `resources/admin/src/marketplace/LazadaPublishingAttributes.tsx`
- Modify: `resources/admin/src/marketplace/ShopeePublishingRequirements.tsx`
- Modify: `resources/admin/src/marketplace/ShopeeOptionalAttributes.tsx`
- Modify: `docs/fidelity/2026-09-20-marketplace-size-chart-extraction-manifest.md`

**Interfaces:**
- Consumes: `MarketplaceAttributeField`, typed values, and field-level update callbacks.
- Produces: one renderer for both platforms plus `MarketplaceAttributeSections({fields, values, errors, onChange, supplemental})`.

- [ ] **Step 1: Extend the fidelity manifest before copying UI structure**

Record the SHA-256 hashes and selected regions from ZIP `OptionalAttributesSection.tsx`, `ProductMediaManager.tsx`, and radio/input patterns. Record `UI States Spec` and demo state testers as explicit exclusions.

- [ ] **Step 2: Write failing interaction tests**

Render and operate text, textarea, integer, decimal, date, searchable single, radio presentation for a documented binary single choice, multi-select chips, custom values, and unit selectors. Add an optional-panel test asserting collapsed state preserves values and `4 of 8 filled` updates after changes.

```tsx
expect(screen.getByRole('spinbutton',{name:'Battery Capacity'})).toHaveAttribute('step','any');
await user.click(screen.getByRole('button',{name:'Bluetooth 5.3'}));
expect(screen.getByText('Bluetooth 5.3')).toBeVisible();
expect(screen.getByText('1 of 2 filled')).toBeVisible();
```

- [ ] **Step 3: Run tests and confirm RED**

Run: `npm test -- --run src/marketplace/MarketplaceAttributeControl.test.tsx src/marketplace/MarketplaceAttributeSections.test.tsx`

Expected: FAIL because shared controls and the unified required/optional layout do not exist.

- [ ] **Step 4: Implement controls using ZIP DOM/classes**

Use the ZIP searchable select and chip markup, existing control classes, dependent badges, descriptions, filled counter, and Expand/Collapse behavior. Keep actual native number/date semantics. Render an unsupported required field as a visible disabled card with `unsupportedReason`; do not render an editable text fallback.

- [ ] **Step 5: Replace platform-specific duplicate renderers**

Make `LazadaPublishingAttributes`, `ShopeePublishingRequirements`, and `ShopeeOptionalAttributes` thin adapters around `MarketplaceAttributeSections`. Keep Shopee shipping channels outside the attributes panel and retain provider search as the field `supplemental` content.

- [ ] **Step 6: Run component suites and lint**

Run: `npm test -- --run src/marketplace/MarketplaceAttributeControl.test.tsx src/marketplace/MarketplaceAttributeSections.test.tsx src/marketplace/LazadaPublishingAttributes.test.tsx src/marketplace/ShopeePublishingRequirements.test.tsx`

Run: `npm run lint`

Expected: PASS with no React act warnings.

- [ ] **Step 7: Commit the task**

```powershell
git add -- resources/admin/src/marketplace/MarketplaceAttributeControl.tsx resources/admin/src/marketplace/MarketplaceAttributeControl.test.tsx resources/admin/src/marketplace/MarketplaceAttributeSections.tsx resources/admin/src/marketplace/MarketplaceAttributeSections.test.tsx resources/admin/src/marketplace/publish37/OptionalAttributesSection.tsx resources/admin/src/marketplace/LazadaPublishingAttributes.tsx resources/admin/src/marketplace/ShopeePublishingRequirements.tsx resources/admin/src/marketplace/ShopeeOptionalAttributes.tsx docs/fidelity/2026-09-20-marketplace-size-chart-extraction-manifest.md
git commit -m "feat: render marketplace attribute controls"
```

### Task 4: Managed Image Attribute Upload

**Files:**
- Create: `app/Services/Marketplace/MarketplaceAttributeImageUpload.php`
- Create: `app/Http/Controllers/Admin/MarketplaceAttributeImageController.php`
- Create: `tests/Feature/MarketplaceAttributeImageUploadTest.php`
- Modify: `routes/web.php`
- Create: `resources/admin/src/integration/marketplaceAttributeImages.ts`
- Create: `resources/admin/src/integration/marketplaceAttributeImages.test.ts`
- Create: `resources/admin/src/marketplace/MarketplaceImageAttribute.tsx`
- Create: `resources/admin/src/marketplace/MarketplaceImageAttribute.test.tsx`
- Modify: `resources/admin/src/marketplace/PublishingSizeChart.tsx`

**Interfaces:**
- Produces: `POST /admin/api/marketplace/products/{product}/attribute-images` accepting multipart `file` and returning `{imageId:number,url:string,alt:string}`.
- Produces: `uploadMarketplaceAttributeImage(productId, file, fetcher?)` and `MarketplaceImageAttribute`.
- Consumes: `ManagedMediaIngest`, `ProductImage`, and `CatalogMediaReferenceSynchronizer::syncProduct(Product $product)`.

- [ ] **Step 1: Write failing Laravel feature tests**

Test admin authentication, product existence, decoded JPEG/PNG/WebP MIME, 10 MiB maximum, corrupt image rejection, ProductImage creation, managed media reference creation, and JSON response. Test that a non-image and a foreign/nonexistent product return 422/404 without creating records.

```php
$response=$this->actingAs($admin)->postJson("/admin/api/marketplace/products/{$product->id}/attribute-images",[
    'file'=>UploadedFile::fake()->image('size-chart.png',1200,1200),
]);
$response->assertCreated()->assertJsonStructure(['imageId','url','alt']);
$this->assertDatabaseHas('product_images',['product_id'=>$product->id]);
```

- [ ] **Step 2: Run the feature test and confirm RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/MarketplaceAttributeImageUploadTest.php`

Expected: FAIL with route not found.

- [ ] **Step 3: Implement the authenticated upload service and endpoint**

Validate with `image`, `mimetypes:image/jpeg,image/png,image/webp`, and `max:10240`. In one transaction, ingest through `ManagedMediaIngest`, create a ProductImage at the next position using the managed URL, call `syncProduct`, and return the internal image ID. Do not accept `path`, external URL, provider identity, product ID in the body, or arbitrary purpose fields.

- [ ] **Step 4: Write failing frontend upload and UI tests**

Test FormData construction, progress/pending state, preview, replace/remove, upload error retaining the prior confirmed image, existing product image selection, and source-faithful modal preview.

- [ ] **Step 5: Implement the frontend adapter and image control**

Reuse ZIP `ProductMediaManager` upload/preview classes and the existing `PublishingSizeChart` card. After a successful upload, append the returned image to the current field candidate list and emit `{kind:'image',imageId,url}`. Refactor Size Chart to use this same control with `purpose="size-chart"` presentation copy only.

- [ ] **Step 6: Run upload suites and PHP syntax checks**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/MarketplaceAttributeImageUploadTest.php`

Run: `npm test -- --run src/integration/marketplaceAttributeImages.test.ts src/marketplace/MarketplaceImageAttribute.test.tsx src/marketplace/PublishingSizeChart.test.tsx`

Run: `C:\xampp\php\php.exe -l app/Services/Marketplace/MarketplaceAttributeImageUpload.php`

Expected: PASS; provider HTTP remains untouched.

- [ ] **Step 7: Commit the task**

```powershell
git add -- app/Services/Marketplace/MarketplaceAttributeImageUpload.php app/Http/Controllers/Admin/MarketplaceAttributeImageController.php tests/Feature/MarketplaceAttributeImageUploadTest.php routes/web.php resources/admin/src/integration/marketplaceAttributeImages.ts resources/admin/src/integration/marketplaceAttributeImages.test.ts resources/admin/src/marketplace/MarketplaceImageAttribute.tsx resources/admin/src/marketplace/MarketplaceImageAttribute.test.tsx resources/admin/src/marketplace/PublishingSizeChart.tsx resources/admin/src/marketplace/PublishingSizeChart.test.tsx
git commit -m "feat: upload marketplace attribute images"
```

### Task 5: Server Validation and Provider Image Binding

**Files:**
- Create: `app/Services/Marketplace/LazadaReviewedAttributes.php`
- Create: `tests/Feature/LazadaReviewedAttributesTest.php`
- Modify: `app/Services/Marketplace/LazadaAttributeValue.php`
- Modify: `app/Services/Marketplace/LazadaPublishingImages.php`
- Modify: `app/Services/Marketplace/LazadaListingPreflight.php`
- Modify: `tests/Feature/LazadaProductImageServiceTest.php`
- Modify: `tests/Feature/LazadaListingPreflightTest.php`
- Modify: `app/Services/Marketplace/ShopeeAttributeValue.php`
- Modify: `app/Services/Marketplace/ShopeeAttributeSelection.php`
- Modify: `app/Services/Marketplace/ShopeeListingAttributes.php`
- Modify: `tests/Feature/ShopeePublishingVariantsTest.php`

**Interfaces:**
- Consumes: staged `jw_attribute_image_ids` keyed by canonical field key.
- Produces: provider-native Lazada normal/SKU attributes after current schema validation and image receipt binding.
- Preserves: Shopee native `attribute_list` with strict value/unit/date/cardinality validation.

- [ ] **Step 1: Write failing backend validation tests**

Test all Lazada input types, product/SKU image keys, allowed options, custom enum permission, finite numeric, valid date, duplicate multi-values, stale/foreign image IDs, and malformed field names. Add Shopee tests for max count, duplicate option IDs, integer/float/date formats, missing/disallowed units, and inactive child attributes.

```php
$this->expectException(ValidationException::class);
LazadaReviewedAttributes::bind($schema,$source,[
    'normal'=>[], 'skus'=>[], 'imageBindings'=>['lazada:variant:OTHER:color_thumbnail'=>$foreignImage->id],
]);
```

- [ ] **Step 2: Run focused backend tests and confirm RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/LazadaReviewedAttributesTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/ShopeePublishingVariantsTest.php`

Expected: FAIL because generic typed image/date bindings are not supported end to end.

- [ ] **Step 3: Implement Lazada reviewed binding**

Reload/consume the current provider schema, allowlist field name/scope/SKU, validate values through `LazadaAttributeValue`, and resolve internal image IDs only through the selected product's current images. Extend `LazadaPublishingImages` to prepare/reuse each image receipt and replace internal bindings with `.slatic.net` URLs immediately before create-product serialization.

Keep Size Chart as a named post-create binding and remove all `jw_*` fields from the provider create payload.

- [ ] **Step 4: Complete Shopee validation**

Apply `input_validation_type`, `date_format_type`, `max_value_count`, custom-value permission, unique value IDs/names, units, and dependency presence in `ShopeeAttributeValue`, `ShopeeAttributeSelection`, and `ShopeeListingAttributes` before provider HTTP.

- [ ] **Step 5: Run backend suites**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/LazadaReviewedAttributesTest.php tests/Feature/LazadaProductImageServiceTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/ShopeePublishingVariantsTest.php tests/Feature/MarketplacePublishingTaskTest.php`

Expected: PASS, including zero provider create calls for every invalid/stale case.

- [ ] **Step 6: Commit the task**

```powershell
git add -- app/Services/Marketplace/LazadaReviewedAttributes.php app/Services/Marketplace/LazadaAttributeValue.php app/Services/Marketplace/LazadaPublishingImages.php app/Services/Marketplace/LazadaListingPreflight.php app/Services/Marketplace/ShopeeAttributeValue.php app/Services/Marketplace/ShopeeAttributeSelection.php app/Services/Marketplace/ShopeeListingAttributes.php tests/Feature/LazadaReviewedAttributesTest.php tests/Feature/LazadaProductImageServiceTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/ShopeePublishingVariantsTest.php
git commit -m "feat: validate marketplace attribute payloads"
```

### Task 6: Wizard Integration and State Lifecycle

**Files:**
- Modify: `resources/admin/src/marketplace/useLazadaPublishingAttributes.ts`
- Modify: `resources/admin/src/marketplace/useShopeePublishingRequirements.ts`
- Create: `resources/admin/src/marketplace/useMarketplaceAttributeValues.ts`
- Create: `resources/admin/src/marketplace/useMarketplaceAttributeValues.test.tsx`
- Modify: `resources/admin/src/previews/marketplace32/components/marketplace/PublishProductModal.tsx`
- Modify: `resources/admin/src/previews/marketplace32/PublishProductModal.test.tsx`
- Modify: `resources/admin/src/previews/marketplace32/types/index.ts`
- Modify: `resources/admin/src/marketplace/OperationalMapping.tsx`

**Interfaces:**
- Consumes: normalized fields, shared controls, typed values, provider serializers, image upload control.
- Produces: reviewed `PublishListingOptions` carrying provider-native scalar attributes plus `lazadaAttributeImageIds`/`sizeChartImageId` internal bindings.

- [ ] **Step 1: Write failing lifecycle and wizard tests**

Add tests for category A→B with A resolving late, product revision change, store change, variant list change, same-field compatibility retention, SKU isolation, optional collapse, required unsupported blocking, image upload selection, and correct serialized submit options.

```tsx
resolveCategoryB();
resolveCategoryA();
expect(screen.getByLabelText('Category B Voltage')).toBeVisible();
expect(screen.queryByLabelText('Category A Material')).toBeNull();
expect(nextButton).toBeDisabled();
```

- [ ] **Step 2: Run focused tests and confirm RED**

Run: `npm test -- --run src/marketplace/useMarketplaceAttributeValues.test.tsx src/previews/marketplace32/PublishProductModal.test.tsx`

Expected: FAIL because the modal still owns platform-specific string state.

- [ ] **Step 3: Implement identity-bound state**

Implement `useMarketplaceAttributeValues(schemaKey, fields)` with an incrementing request identity and compatibility check on provider, scope, provider identity, SKU, and control kind. Clear incompatible values on store/category/product/revision/variant changes and ignore late metadata results.

- [ ] **Step 4: Integrate shared sections into Step 2**

Replace direct platform field rendering with `MarketplaceAttributeSections`. Keep the ZIP wizard shell, backend/logistics notice, category taxonomy selector, shipping channels, validation banner, sticky footer, and source copy. Move Optional Attributes below mandatory fields and shipping in the same scroll region.

- [ ] **Step 5: Connect reviewed submission**

Serialize Lazada and Shopee typed values in `submitReviewed`; forward internal image bindings through `OperationalMapping` and include them in request recovery hashing. Disable Next/Publish while schema/image upload is pending or required fields are incomplete.

- [ ] **Step 6: Run wizard tests and lint**

Run: `npm test -- --run src/marketplace/useMarketplaceAttributeValues.test.tsx src/previews/marketplace32/PublishProductModal.test.tsx src/integration/submitShopeeReview.test.ts src/integration/submitLazadaReview.test.ts`

Run: `npm run lint`

Expected: PASS with no stale field/value after identity changes.

- [ ] **Step 7: Commit the task**

```powershell
git add -- resources/admin/src/marketplace/useLazadaPublishingAttributes.ts resources/admin/src/marketplace/useShopeePublishingRequirements.ts resources/admin/src/marketplace/useMarketplaceAttributeValues.ts resources/admin/src/marketplace/useMarketplaceAttributeValues.test.tsx resources/admin/src/previews/marketplace32/components/marketplace/PublishProductModal.tsx resources/admin/src/previews/marketplace32/PublishProductModal.test.tsx resources/admin/src/previews/marketplace32/types/index.ts resources/admin/src/marketplace/OperationalMapping.tsx
git commit -m "feat: integrate dynamic publishing attributes"
```

### Task 7: Stage Controller and Dispatch Safety

**Files:**
- Modify: `app/Http/Controllers/Admin/MarketplacePublishingController.php`
- Modify: `resources/admin/src/integration/submitLazadaReview.ts`
- Modify: `resources/admin/src/integration/submitLazadaReview.test.ts`
- Modify: `resources/admin/src/integration/submitShopeeReview.ts`
- Modify: `resources/admin/src/integration/submitShopeeReview.test.ts`
- Modify: `app/Services/Marketplace/MarketplacePublishingDispatchService.php`
- Modify: `tests/Feature/MarketplacePublishingTaskTest.php`

**Interfaces:**
- Consumes: reviewed typed/serialized values and internal image IDs from Task 6.
- Produces: immutable saved publishing payload containing only validated provider data and bounded internal fields, then provider requests free of all `jw_*` keys.

- [ ] **Step 1: Write failing request-boundary tests**

Test unknown top-level/body fields, forged attribute keys, foreign image IDs, stale source revision, category schema drift, malformed number/date/multi values, incomplete mandatory field, and a valid image-bound request. Assert invalid cases save no task and send no provider HTTP.

- [ ] **Step 2: Run tests and confirm RED**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/MarketplacePublishingTaskTest.php tests/Feature/LazadaReviewedAttributesTest.php`

Expected: FAIL until the controller invokes current-schema binding.

- [ ] **Step 3: Bind current-schema validation at the staging boundary**

After the initial destination/source-revision checks, reload the provider schema using the store token and selected category before opening the database transaction. Normalize and validate the reviewed values into an immutable schema snapshot plus provider payload. Inside the short transaction, lock and recheck the local store, product revision, destination, selected category, and owned image IDs; abort if any identity changed, otherwise save the validated snapshot and create the publishing task. Do not perform provider HTTP while a database transaction is open. Preserve existing Basic WMS master-data/image/warehouse binding and transaction boundaries.

- [ ] **Step 4: Strip internal fields before provider HTTP**

Assert in tests that Lazada `/product/create` and Shopee `/product/add_item` bodies contain provider-native attributes and no `jw_attribute_image_ids`, `jw_size_chart_image_id`, or other `jw_*` keys. Preserve Lazada post-create Size Chart update and parent checkpoint behavior.

- [ ] **Step 5: Run publishing regression suites**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/MarketplacePublishingTaskTest.php tests/Feature/LazadaClientTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/ShopeeProductImageServiceTest.php tests/Feature/LazadaProductImageServiceTest.php`

Run: `npm test -- --run src/integration/submitLazadaReview.test.ts src/integration/submitShopeeReview.test.ts`

Expected: PASS; uncertain-result and duplicate-prevention tests remain green.

- [ ] **Step 6: Commit the task**

```powershell
git add -- app/Http/Controllers/Admin/MarketplacePublishingController.php app/Services/Marketplace/MarketplacePublishingDispatchService.php resources/admin/src/integration/submitLazadaReview.ts resources/admin/src/integration/submitLazadaReview.test.ts resources/admin/src/integration/submitShopeeReview.ts resources/admin/src/integration/submitShopeeReview.test.ts tests/Feature/MarketplacePublishingTaskTest.php
git commit -m "feat: stage validated marketplace attributes"
```

### Task 8: Local Acceptance, Fidelity Evidence, and Release Readiness

**Files:**
- Create: `docs/fidelity/2026-09-20-marketplace-dynamic-attributes-evidence.md`
- Create: `output/playwright/marketplace-dynamic-attributes/` screenshots (local evidence; commit only if repository policy allows evidence files)
- Modify only if verification exposes a defect: files owned by Tasks 1–7, with a failing regression test first.

**Interfaces:**
- Consumes: complete local feature.
- Produces: test/build/browser evidence and a precise list of any provider categories blocked by expired test access or unsupported provider metadata.

- [ ] **Step 1: Run targeted frontend and backend suites**

Run:

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm test -- --run src/marketplace/publishingAttributes src/marketplace/MarketplaceAttributeControl.test.tsx src/marketplace/MarketplaceAttributeSections.test.tsx src/marketplace/MarketplaceImageAttribute.test.tsx src/previews/marketplace32/PublishProductModal.test.tsx src/integration/submitLazadaReview.test.ts src/integration/submitShopeeReview.test.ts
npm run lint
npm run build
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan test tests/Feature/MarketplaceAttributeImageUploadTest.php tests/Feature/LazadaReviewedAttributesTest.php tests/Feature/LazadaProductImageServiceTest.php tests/Feature/LazadaListingPreflightTest.php tests/Feature/ShopeePublishingVariantsTest.php tests/Feature/ShopeeProductImageServiceTest.php tests/Feature/MarketplacePublishingTaskTest.php
```

Expected: all targeted checks pass; Vite may report its existing chunk-size warning but exits 0.

- [ ] **Step 2: Run the full Admin frontend suite**

Run: `npm test -- --run`

Expected: no new failures. Record pre-existing unrelated failures separately instead of changing unrelated modules.

- [ ] **Step 3: Perform authenticated localhost browser acceptance**

At `http://127.0.0.1:8500/admin/marketplace/mapping`, renew the test-store access first if the destination is `reauthorization_required`. Open Push JW Master to Store and inspect, without publishing:

1. Lazada jewellery: numeric SKU fields, required color thumbnail upload/preview, optional panel.
2. Lazada fashion: Size Chart, sale properties, required gating.
3. Lazada category exposing date/select/enum/rich text where available.
4. Shopee category: dropdown/custom/multi/search/unit fields and shipping.
5. Switch categories quickly and confirm old fields/values disappear.
6. Test desktop and a 390px viewport; confirm scroll and sticky footer.

- [ ] **Step 4: Save fidelity evidence**

Document the ZIP archive hash, source component hashes, selected/excluded regions, route, viewport, screenshots, live categories tested, network-safe stopping point, test commands, and any test-access limitation in `docs/fidelity/2026-09-20-marketplace-dynamic-attributes-evidence.md`.

- [ ] **Step 5: Inspect the final diff and run syntax checks**

Run: `git diff --check`

Run PHP `-l` for every modified PHP file listed by `git diff --name-only -- '*.php'` using a PowerShell loop that passes each literal path directly to `C:\xampp\php\php.exe -l`.

Expected: no whitespace or PHP syntax errors.

- [ ] **Step 6: Commit acceptance evidence**

```powershell
git add -- docs/fidelity/2026-09-20-marketplace-dynamic-attributes-evidence.md
git commit -m "test: document marketplace attribute acceptance"
```

- [ ] **Step 7: Request release approval**

Report completed scope, targeted/full test results, build result, authenticated browser evidence, known provider/test-token limitations, and the exact local commit SHA. Ask for explicit GitHub `main` synchronization and Hostinger deployment approval as required by `AGENTS.md`.
