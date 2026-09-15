# ZIP 37 publishing UI extraction

Archive SHA256: 39b278d609811744b1ad43033edf00268b2c0633ad7e03ed31a1aeabac760e4d
Target: /admin/marketplace/mapping, existing three-step publish modal.
Scope: three section components only; no backend or API changes.
User approved stripping mock logic and integrating these sections.
Data: existing publishing source, attributes/logistics and image-choice adapters.
Excluded: entire ZIP shell, demo catalog, fake images/SKUs, static couriers/attributes, simulated refresh and publishing.

| Source component | SHA256 |
|---|---|
| ShopeeVariationImagesSection.tsx | 125dc8ddd834e71658f4fe9e7d12998db61b498a5c817684b528e51be52ef1fd |
| OptionalAttributesSection.tsx | 29bd75e91de5f8abc19e4f69b12e1ba834ad5720a21f72158548a3ecf3da49d6 |
| StoreShippingChannelsSection.tsx | 45231bca20a8782230b17848057a114784fd9ade2538ee83fb046e0d46e58e0e |

## Verification 2026-09-16
- 14 frontend tests passed: image resolution, preset ID mapping, shipping size IDs, required channel locks, existing wizard and submission.
- 37 PHP publishing/image tests passed (185 assertions).
- Production Vite build passed; existing large-chunk warning remains.
- Local browser fixture confirmed dropdown default options, filtering, selected chips, shipping size expansion and image readiness updates.
- Fixtures were isolated and removed from public output after verification; no provider publishing invoked.
- Source DOM/classes retained for three extracted sections; fake photos/SKUs, fixed attributes/logistics, false policy text and timer refresh removed as explicitly approved.
- Existing remote attribute search and custom-unit controls retained through adapter supplemental slots.
- Full-project TypeScript check has existing unrelated errors; it is not reported as passing.
- Final authenticated Shopee submission and product/mapping readback are reserved for user acceptance.
