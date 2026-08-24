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

## Review Fixes (2026-08-24)

- Added regression coverage for enabled-zone required fields:
  - `zones.left.text` when `zones.left.enabled=true`
  - `zones.center.text` when `zones.center.enabled=true`
  - `zones.right.trackingUrl` when `zones.right.trackingEnabled=true`
  - `zones.right.whatsappUrl` when `zones.right.whatsappEnabled=true`
- Tightened the singleton write path so API saves delete rogue non-global rows and always persist a single canonical `global` record.
- Removed `StorefrontShellSetting` mass-assignable `key` / `payload` declarations; the service now performs explicit assignment on the canonical record instead of mass-assignment creation.

### Review Fix Evidence

- Red command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php`
  - Result before review fix:
    - `enabled zones require live copy and utility urls` failed with `200` instead of `422`
    - `api save rebuilds the canonical global singleton when rogue rows exist` failed because the table still contained `2` rows.
- Green command:
  - `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php`
  - Result after review fix: `PASS` with `7 passed (41 assertions)`.
