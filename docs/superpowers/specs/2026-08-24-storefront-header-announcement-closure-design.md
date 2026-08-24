# Storefront Header & Announcement CMS Closure Design

## Boundary

- Mode: `OPERATIONAL_CLOSURE`.
- UI authority: `C:\Users\User\Downloads\jw-ecommerce-dashboard (11).zip` SHA-256 `82FDF30E2BFF1C96F7E03FDC528703C3B230163CE9673B57B6C500F42DD26CFA`.
- Selected L3 sources: `src/components/cms/StorefrontHub.tsx`, `src/components/cms/StorefrontHeaderAnnouncementEditor.tsx`, `src/components/menus/StorefrontHeaderPreview.tsx`, and `src/types/storefrontHeader.ts`.
- Official Admin route: `/admin/storefront`; storefront consumer: shared `resources/views/components/storefront/nav.blade.php` announcement region.
- Excluded: new storefront header templates. Only the currently approved Atelier header can publish.

## Persisted owner and lifecycle

Create one MySQL-backed global storefront-shell configuration with a draft editor payload and one saved/live payload. Admin may hold unsaved preview state locally only; saving uses protected Admin API and persists the live record. Storefront reads the saved enabled configuration on every rendered request. Missing or disabled Zone content is hidden.

The persisted shape is structured: global enable/theme/custom palette/layout; Zone A copy, badge and optional link; Zone B promotion copy, coupon code and optional link; Zone C tracking and WhatsApp enable flags, respective links and helper copy. Input URLs are constrained to safe same-site paths, `https`, `tel`, and WhatsApp URLs.

## Layout rule

All five source layouts remain selectable and previewable in Admin. `atelier` is the only publishable layout. Choosing any other source layout can update the unsaved preview but saving it as live returns a stable validation error and leaves the current live Atelier layout unchanged. This prevents a configuration UI from claiming a storefront layout exists before Google AI Studio provides the matching public source.

## Capability mapping

| Capability | Owner | Consumer | Status target |
| --- | --- | --- | --- |
| Three-zone announcement copy, links and visibility | operator / MySQL | storefront shared nav | CLOSED |
| Coupon presentation | operator / MySQL | Zone B display only | CLOSED |
| Tracking and WhatsApp utility links | operator / MySQL | Zone C links | CLOSED |
| Theme/palette | operator / MySQL | announcement region | CLOSED |
| Interactive preview | unsaved Admin UI state | Admin only | SYSTEM_MANAGED |
| Five header layouts | operator config | Admin preview; Atelier storefront only | DORMANT for four non-Atelier layouts |

## Tests and evidence

Feature tests cover authenticated persistence, validation, unauthorized writes, enabled/disabled and empty-zone storefront rendering, safe-link normalization, and refusal to publish a non-Atelier layout. Admin tests cover API adapter and source route wiring. Browser acceptance proves Admin save -> storefront announcement change -> refresh persistence, plus the editor desktop/mobile preview states.

## Fidelity requirements

Port only selected ZIP DOM, Tailwind classes, copy, controls, preview behavior and responsive states. Replace ZIP `localStorage` ownership only at the adapter boundary; do not add visible UI not present in the ZIP.
