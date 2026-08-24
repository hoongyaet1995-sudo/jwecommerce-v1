# Storefront Announcement Theme Mapping

## Source extraction manifest

- ZIP archive paths: `C:\Users\User\Downloads\jw-ecommerce-dashboard (11).zip` and `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (7).zip`
- ZIP SHA-256: `82FDF30E2BFF1C96F7E03FDC528703C3B230163CE9673B57B6C500F42DD26CFA` and `0D41E70204113FB9409D86FF6A26F6F9959575C96E0F01FF7ABDDB8BD0D914D3`
- Target Laravel project and release surface: `C:\xampp\htdocs\jwstudio-ecommerce`, shared `components.storefront.nav` on all storefront routes.
- Requested function: persist and consume the Google AI Studio Announcement Bar preset theme and Custom Hex background/text palette.
- Explicit exclusions: marquee and dismiss controls remain unpersisted source-form state; Header Layout & Utilities stays preview-only except the already-approved Atelier layout.
- Acceptance intent: an Admin preset save changes the same shared storefront announcement bar after refresh.

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/cms/StorefrontHeaderAnnouncementEditor.tsx` | `883DB84FB663773C81054037C5D6B07441E68B20B90B92EFBB05D5E924C787A0` | preset and Custom Hex source state | existing Admin editor | preserved |
| `src/components/menus/StorefrontHeaderPreview.tsx` | `4710B1412D7475E6CA4E2AEB77967906834D2BB2929051C1D6499E4A2051DCD4` | exact preview colors | shared Blade token adapter | preserved |
| `src/components/layout/Navbar.tsx` | `5A7D4823886A988ECDF86432C6E6F9493B16C6AE6B1957113D027C2EBB9A3D1F` | storefront announcement region | `resources/views/components/storefront/nav.blade.php` | adapter only |

## Mapping contract

| Field | Value |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Phase/scope | P0 Header & Announcement Bar theme consumption only |
| Runtime truth | `storefront_shell_settings.payload` JSON, `theme` and `customPalette` |
| Admin ownership | Operator-managed |
| API contract | authenticated `PUT /admin/api/storefront-shell-settings` via `StorefrontShellSettingsService` |
| Storefront consumer | `components.storefront.nav`, all shared storefront routes |
| UI authority | approved Google AI Studio L2 source listed above |
| Lifecycle | realtime editable persisted configuration |
| Empty/default behavior | Midnight Obsidian (`#0f172a`, `#f8fafc`); invalid or missing Custom Hex falls back to it |

| Feature ID | Current source | MySQL truth | Storefront consumer | Closure status | Acceptance evidence |
| --- | --- | --- | --- | --- | --- |
| `storefront.announcement.theme` | Admin preset form plus API | `storefront_shell_settings.payload.theme` | shared announcement bar CSS variables | `CLOSED` | Laravel feature test plus Admin save/storefront browser refresh |
| `storefront.announcement.custom_palette` | Admin Custom Hex background/text form plus API | `storefront_shell_settings.payload.customPalette.background/text` | shared announcement bar CSS variables | `CLOSED` | Laravel feature test plus browser refresh |
| `storefront.announcement.marquee` | local Admin form state | none | none | `BACKEND_CONTRACT_GAP` | excluded from this batch |
| `storefront.announcement.dismiss` | local Admin form state | none | none | `BACKEND_CONTRACT_GAP` | excluded from this batch |
