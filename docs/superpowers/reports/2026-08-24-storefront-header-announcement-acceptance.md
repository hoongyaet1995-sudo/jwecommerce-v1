# Storefront Header & Announcement — acceptance record

Date: 2026-08-24

## Real browser evidence

Using the authenticated Admin session, the acceptance configuration was saved through **Storefront CMS → Header & Announcement Bar**:

- Left: `JW Studio browser acceptance` with badge `LIVE`
- Center: `Browser acceptance free shipping` with coupon `CODE: BROWSER`
- Right: `Track browser acceptance`

The post-save MySQL record was read through the Laravel model bootstrap and confirmed:

```json
{"enabled":true,"left":true,"center":true,"right":true}
```

Refreshing `http://127.0.0.1:8500/shop` rendered all three saved regions and the coupon. Returning to the Admin hub after a full page reload showed Announcement Bar **Enabled** and restored each saved editor input.

The Admin editor's source-owned **Interactive Live Preview** was also exercised in Mobile View. Selecting **Glassmorphic Floating Pill** left the live setting untouched and disabled the save control with: `Only Modern Atelier is currently available for live storefront publishing.`

## Regression correction

The Google AI Studio source exposes a master switch but no per-zone switches. Previously, populated fields inherited the default internal `false` zone flags and therefore hid the entire storefront bar. The persistence mapping now derives each zone's enabled state from its source-owned populated text field. This adds no visible UI and makes the editor's fields operational.

## Fresh automated verification

- `C:\xampp\php\php.exe artisan test tests/Feature/Admin/StorefrontShellSettingsApiTest.php tests/Feature/StorefrontAnnouncementBarTest.php`
  - 11 passed, 166 assertions.
- `npm exec vitest run src/components/cms/StorefrontHeaderAnnouncementEditor.test.tsx src/integration/storefrontShellSettingsData.test.ts`
  - 8 passed.
- `npm run lint`
  - passed.
- `npm run build`
  - passed (Vite's existing large-chunk warning remains informational).
