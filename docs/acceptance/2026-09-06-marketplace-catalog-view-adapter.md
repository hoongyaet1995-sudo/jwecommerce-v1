# Catalog view adapter (local)

- Added conversion from the grouped persisted catalog contract to ZIP 30 product/variant view models. Uses store-scoped identity and explicit supplied Shop ID, preserving JW product/variant IDs rather than matching by strings.
- Unknown numeric observations remain null. Original list and modal text bindings show an em dash for missing price/stock. No DOM/class redesign; no suggestion or stock initialization is generated.
- Unknown provider status remains unknown; only the observed Shopee NORMAL status maps to active.
- Converter plus mapping page/modal interaction tests exercise these adapters. Full operational mapping container and real browser/provider chain are still pending; do not claim the mapper alone completes the feature.
- No release in this increment; production remains bba88ae7.
