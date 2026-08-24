# Storefront Header & Announcement Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the approved Header & Announcement Admin CMS surface persist structured global configuration and drive the shared storefront announcement bar.

**Architecture:** Laravel owns the globally saved shell configuration, validation and publication rule. The source-faithful Admin React editor keeps unsaved state only for its live simulator, writes through a protected adapter, and hydrates from MySQL. The shared Blade navigation reads the saved enabled configuration and suppresses absent zones.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Admin React/Vite, Blade, Vitest, Laravel Feature tests, Playwright browser acceptance.

**Spec:** `docs/superpowers/specs/2026-08-24-storefront-header-announcement-closure-design.md`

## Global Constraints

- Preserve selected `jw-ecommerce-dashboard (11).zip` source DOM/classes/copy/responsive behavior exactly.
- MySQL owns saved behavior; `localStorage` may only hold unsaved preview state.
- Only `atelier` may be saved as published storefront layout; other source layouts are Admin-preview-only.
- Storefront hides disabled or absent announcement zones rather than rendering synthetic copy.
- Do not alter existing shared header/menu/footer behavior outside the selected announcement region.

---

### Task 1: Persist and protect the storefront shell configuration

**Files:**
- Create: `database/migrations/2026_08_24_000000_create_storefront_shell_settings_table.php`
- Create: `app/Models/StorefrontShellSetting.php`
- Create: `app/Services/StorefrontShellSettingsService.php`
- Create: `app/Http/Controllers/Admin/StorefrontShellSettingsApiController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/StorefrontShellSettingsApiTest.php`

**Interfaces:**
- Produces `GET /admin/api/storefront-shell-settings` and `PUT /admin/api/storefront-shell-settings`.
- Produces `StorefrontShellSettingsService::live(): array` for Blade consumers.

- [ ] **Step 1: Write failing persistence and publish-rule tests**

```php
public function test_admin_can_save_and_reload_structured_announcement_settings(): void
{
    $this->actingAs($this->admin())->putJson('/admin/api/storefront-shell-settings', [
        'enabled' => true, 'layout' => 'atelier', 'theme' => 'midnight-obsidian',
        'zones' => ['left' => ['enabled' => true, 'text' => 'Official'], 'center' => ['enabled' => true, 'text' => 'Free shipping'], 'right' => ['enabled' => false]],
    ])->assertOk();

    $this->actingAs($this->admin())->getJson('/admin/api/storefront-shell-settings')
        ->assertOk()->assertJsonPath('layout', 'atelier');
}

public function test_non_atelier_layout_cannot_be_published(): void
{
    $this->actingAs($this->admin())->putJson('/admin/api/storefront-shell-settings', ['layout' => 'floating-pill'])
        ->assertUnprocessable()->assertJsonValidationErrors('layout');
}
```

- [ ] **Step 2: Run tests and verify failure**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php`

Expected: FAIL because the route and model do not exist.

- [ ] **Step 3: Implement the normalized service and API**

```php
public function save(array $input): StorefrontShellSetting
{
    validator($input, ['layout' => ['required', Rule::in(['atelier'])]])->validate();
    return StorefrontShellSetting::query()->updateOrCreate(['key' => 'global'], ['payload' => $this->normalize($input)]);
}
```

Use a JSON payload with explicit `zones.left`, `zones.center`, `zones.right`; validate every external utility/link protocol.

- [ ] **Step 4: Run tests and migration**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations app/Models/StorefrontShellSetting.php app/Services/StorefrontShellSettingsService.php app/Http/Controllers/Admin/StorefrontShellSettingsApiController.php routes/web.php tests/Feature/Admin/StorefrontShellSettingsApiTest.php
git commit -m "feat: persist storefront shell settings"
```

### Task 2: Bind the shared storefront announcement consumer

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` or existing storefront view composer owner
- Modify: `resources/views/components/storefront/nav.blade.php`
- Test: `tests/Feature/StorefrontAnnouncementBarTest.php`

**Interfaces:**
- Consumes `StorefrontShellSettingsService::live()`.
- Produces source-faithful visible three-zone announcement rendering on all shared-header storefront routes.

- [ ] **Step 1: Write failing storefront tests**

```php
public function test_saved_enabled_announcement_is_visible_on_storefront(): void
{
    $this->saveLiveShell(['enabled' => true, 'zones' => ['left' => ['enabled' => true, 'text' => 'Official']]]);
    $this->get('/shop')->assertOk()->assertSee('Official');
}

public function test_disabled_or_missing_zone_is_not_rendered(): void
{
    $this->saveLiveShell(['enabled' => false]);
    $this->get('/shop')->assertOk()->assertDontSee('Official');
}
```

- [ ] **Step 2: Run test and verify failure**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontAnnouncementBarTest.php`

Expected: FAIL because the shared nav still owns static announcement copy.

- [ ] **Step 3: Bind only data branches to the approved nav region**

```blade
@if($storefrontShell['enabled'] && $storefrontShell['zones']['center']['enabled'])
  {{-- retain the approved wrapper/classes; substitute only structured values --}}
@endif
```

Do not add a second header or per-page copy; render safe optional links only when provided.

- [ ] **Step 4: Run feature tests**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/StorefrontAnnouncementBarTest.php tests/Feature/StorefrontCanonicalHeaderTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add app/Providers/AppServiceProvider.php resources/views/components/storefront/nav.blade.php tests/Feature/StorefrontAnnouncementBarTest.php
git commit -m "feat: bind storefront announcement settings"
```

### Task 3: Port the source-faithful Admin CMS editor and live simulator

**Files:**
- Create: `resources/admin/src/integration/storefrontShellSettingsData.ts`
- Create: `resources/admin/src/integration/storefrontShellSettingsData.test.ts`
- Create: `resources/admin/src/components/cms/StorefrontHeaderAnnouncementEditor.tsx`
- Create: `resources/admin/src/components/menus/StorefrontHeaderPreview.tsx`
- Modify: `resources/admin/src/components/cms/StorefrontHub.tsx`
- Modify: `resources/admin/src/components/layout/AdminSidebar.tsx`
- Modify: `resources/admin/src/App.tsx`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/types/index.ts`

**Interfaces:**
- Consumes Task 1 protected API and MySQL payload.
- Produces `storefront-header-announcement` Admin route and source-faithful live preview.

- [ ] **Step 1: Add failing adapter and route assertions**

```ts
expect(await loadStorefrontShellSettings()).toMatchObject({ layout: 'atelier' });
expect(appSource).toContain("case 'storefront-header-announcement':");
expect(sidebarSource).toContain('Header & Announcement');
```

- [ ] **Step 2: Run focused Vitest suite and verify failure**

Run: `npm --prefix resources/admin test -- storefrontShellSettingsData.test.ts`

Expected: FAIL because adapter and route are absent.

- [ ] **Step 3: Port only selected ZIP components and adapt their state boundary**

Copy source hierarchy/classes/copy from the selected `StorefrontHeaderAnnouncementEditor.tsx` and `StorefrontHeaderPreview.tsx`. Replace ZIP persistent `localStorage` writes with adapter calls; retain component-local unsaved preview state. Expose non-Atelier choices in preview but disable Save Live Changes with the source-compatible validation state.

- [ ] **Step 4: Wire Storefront Hub and Sidebar source entries**

Use the ZIP’s Header & Announcement card and status counts, derived from the loaded settings rather than mock constants.

- [ ] **Step 5: Run Admin checks**

Run: `npm --prefix resources/admin run lint; npm --prefix resources/admin test; npm --prefix resources/admin run build`

Expected: focused new tests PASS; record any pre-existing suite failures separately; build PASS.

- [ ] **Step 6: Commit**

```powershell
git add resources/admin/src
git commit -m "feat: add storefront header announcement CMS"
```

### Task 4: Browser closure evidence

**Files:**
- Create: `resources/admin/tests/storefront-header-announcement.spec.ts`
- Create: `docs/superpowers/reports/2026-08-24-storefront-header-announcement-acceptance.md`

**Interfaces:**
- Consumes saved Task 1 payload and Task 3 UI.
- Produces evidence of Admin -> MySQL -> shared storefront effect.

- [ ] **Step 1: Write failing browser flow**

```ts
await page.getByText('Header & Announcement').click();
await page.getByLabel('Center Promotion Text').fill('Closure shipping proof');
await page.getByRole('button', { name: 'Save Live Changes' }).click();
await storefront.goto('/shop');
await expect(storefront.getByText('Closure shipping proof')).toBeVisible();
```

- [ ] **Step 2: Run it and verify failure before UI completion**

Run: `npx playwright test resources/admin/tests/storefront-header-announcement.spec.ts`

Expected: FAIL until Tasks 1–3 are complete.

- [ ] **Step 3: Capture desktop and mobile evidence**

Capture Admin editor, Admin mobile preview, and storefront announcement screenshots before and after save. Reload both sessions to prove MySQL persistence.

- [ ] **Step 4: Run final focused checks**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php tests/Feature/StorefrontAnnouncementBarTest.php; npm --prefix resources/admin run lint; npx playwright test resources/admin/tests/storefront-header-announcement.spec.ts`

Expected: PASS.

- [ ] **Step 5: Commit evidence**

```powershell
git add resources/admin/tests/storefront-header-announcement.spec.ts docs/superpowers/reports/2026-08-24-storefront-header-announcement-acceptance.md
git commit -m "test: verify storefront announcement closure"
```
