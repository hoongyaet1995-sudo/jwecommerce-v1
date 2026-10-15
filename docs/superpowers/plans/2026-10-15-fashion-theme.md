# Fashion storefront implementation plan

Goal: implement the approved Fashion storefront and simple diagram-based Page Content settings locally.
Architecture: dedicated Fashion Blade/CSS compositions share existing commerce DTOs/actions; one account shell; WebsiteThemeCustomization owns capability-scoped plain-text content and style/media validation. No public fixture records.
Spec: docs/acceptance/2026-10-14-fashion-design-manifest.md and individual output/fashion-design-theme-samples images.

- [x] Register Fashion theme, licensed condensed heading font/default colors, media and content contracts; tests for supported/rejected payloads and escaped content.
- [x] Create Fashion renderers and responsive CSS for catalog, auth/account, commerce and overlays; preserve stock/ownership/snapshot currency and shared JS hooks.
- [x] Add safe unsaved demo DTOs and all acceptance scenes/reference mappings. No production/customer/catalog writes.
- [x] Add diagram-based Page Content Admin controls with numbered clickable regions, text/visibility/restore, no CSS coordinates.
- [x] Run isolated relevant tests/build, inspect hydrated desktop/mobile scenes and widths/media states, record failures/evidence.
- [x] Report verified versus pending locally; no commit/push/deploy without release approval. User visual acceptance and physical keyboard/production checks remain separate gates.

Review focus: account alpha fade/translucent content-width menu; bottom drawer short-screen/keyboard layout; zero overflow six-column desktop grid; unknown stock stays unavailable; inactive theme/settings profiles and unrelated dirty work preserved.
