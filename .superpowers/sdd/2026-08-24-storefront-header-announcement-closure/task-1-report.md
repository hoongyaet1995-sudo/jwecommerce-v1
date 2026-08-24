# Task 1 Report

## Commits

- `fe0bfa2` `feat: persist storefront shell settings`

## Red / Green Evidence

- Red command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php`
  - Result before implementation: `FAIL` with five `404` responses because `/admin/api/storefront-shell-settings` GET/PUT did not exist yet.
- Green command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php`
  - Result after implementation: `PASS` with `5 passed (24 assertions)`.

## Files

- Created: `database/migrations/2026_08_24_000000_create_storefront_shell_settings_table.php`
- Created: `app/Models/StorefrontShellSetting.php`
- Created: `app/Services/StorefrontShellSettingsService.php`
- Created: `app/Http/Controllers/Admin/StorefrontShellSettingsApiController.php`
- Modified: `routes/web.php`
- Created: `tests/Feature/Admin/StorefrontShellSettingsApiTest.php`

## Scope Notes

- Persistence is MySQL-backed through a dedicated `storefront_shell_settings` table keyed by `global`.
- The live payload is normalized to one structured three-zone object with `left`, `center`, and `right` branches.
- `atelier` is the only publishable live layout.
- Link validation allows safe same-site paths, safe `https`, `tel:`, and WhatsApp-hosted URLs; unsafe URLs fail validation.
- GET/PUT are protected by the existing `auth` + `admin` middleware group.

## Concerns

- Task 1 only establishes the live data contract and protected API. The shared Blade announcement consumer is still untouched and remains Task 2 work.
- The WhatsApp allowlist is intentionally narrow (`wa.me`, `api.whatsapp.com`, `www.whatsapp.com`) to avoid claiming broader external utility support before later UI/acceptance work.
