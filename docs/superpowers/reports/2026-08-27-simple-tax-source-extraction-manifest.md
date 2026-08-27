# Simple Store-Wide Tax Classes — Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (14).zip` (private local source; not copied into this repository).
- ZIP SHA-256: `BB91B130B021C5D9DEE6D5A6EB952A9316FBFF799CAF290A444366E045410088`.
- Target Laravel project and official route: `C:\xampp\htdocs\jwstudio-ecommerce`, `/admin/settings/tax-classes`.
- Requested region/function: Store-wide Tax Classes screen; its Settings navigation and canonical route; Product Editor removal of the visible product Tax Class control.
- Explicitly excluded ZIP regions: all other Admin modules, dashboard, marketplace, shipping, CMS, support, reports, authentication and all ZIP mock data not required by the selected Tax Classes boundary.
- Acceptance intent: replace generated mock state with authenticated Laravel/MySQL Tax Class CRUD and activation while retaining the selected source DOM, classes, copy, responsive layout, modal structure and confirmation states.

## Source baseline

- Source startup/build command: `npm install --no-audit --no-fund; npm run build` in the private extraction directory.
- Build result: passed on 2026-08-27. Vite `6.4.3` transformed 2,363 modules. Non-blocking source warnings: Node `20.17.0` is below the `@vitejs/plugin-react` preferred `20.19.0`, and the generated bundle exceeds Vite's 500 kB advisory threshold.
- Desktop baseline: `output/playwright/simple-tax-source-desktop.png` at the default desktop viewport.
- Mobile baseline: `output/playwright/simple-tax-source-mobile.png` at `390x844`.
- Known source errors or inert controls: source browser has only a `favicon.ico` 404 plus React DevTools information. It is a source-server asset issue, not a Tax Classes interaction error.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/settings/TaxClassesSettingAdmin.tsx` | `31E45EEFADA4AD236814FBA03D9589670E14F95829A805CB9AEEEA1AD5911108` | selected page/table/modals/confirmation states | `resources/admin/src/components/settings/TaxClassesSettingAdmin.tsx` | selected; callbacks adapt to canonical API |
| `src/components/catalog/ProductEditorModal.tsx` | `5269F4799CF9CA6E214D6C1AC80DEEDDBFBFAFE0B7DC799882825AB9ABE854EE` | product editor visible-field boundary | `resources/admin/src/components/catalog/ProductEditorModal.tsx` | selected; no visible Tax Class control remains |
| `src/components/layout/AdminSidebar.tsx` | `2CA7F9F7EB60A157FC59BA0337E716CD944C25EA4031BB027963F2E9EBBF1A97` | settings navigation dependency | `resources/admin/src/components/layout/AdminSidebar.tsx` | selected boundary |
| `src/components/settings/ShopSettingsHub.tsx` | `1366DFF6F4B3181B0ACB123EDE5BD863C23FC0E4F2D390D9E492352798C7C86B` | settings subnavigation and selected page mount | `resources/admin/src/components/settings/ShopSettingsHub.tsx` | selected boundary |
| `src/App.tsx` | `49569B35CD04F00327CA6546C7DE1A2294F3782F5FE41F5C5B2645DC38EE9A76` | route selection dependency | `resources/admin/src/App.tsx` | selected boundary |
| `src/context/AdminContext.tsx` | `3EF6C96AAAE1962AAD72983E1116DA100623C706EF0C296317EF5848C99D5BFF` | generated page's mock callbacks and toast dependency | `resources/admin/src/context/AdminContext.tsx` | adapter needed; mock ownership excluded |
| `src/types/oscommerceSettings.ts` | `9A08FEFD3B9481EF7F088D1A4DEE9B56E65163A7F0E43F9590F976568D26ACC0` | generated Tax Class type dependency | `resources/admin/src/types/oscommerceSettings.ts` | selected type boundary |
| `src/types/index.ts` | `755A29035133349BB3F7DE04628C4A35278B5C93AFA0B1FD0D66F0FE2E5472C2` | Product contract dependency | `resources/admin/src/types/index.ts` | selected type boundary |
| `src/data/oscommerceSettingsData.ts` | `BEF0ECEB1AE7537C53FE11B7CD8E1F36B50A06CE25CF6496B6323E0B28E5B24C` | source-only mock Tax Class seed data | no production copy | excluded mock retained for source baseline only |
| `src/index.css` | `4E9840B9AA92BF9FCFEE5B715D2943580BAA4334FF6B29C7C0D93F942AF2C75D` | global utility/source-style dependency | existing Admin stylesheet | existing-compatible; no wholesale copy |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| `AdminContext` Tax Class callbacks and `addToast` | page actions, loading and source modal behavior | direct | adapter-needed; bind only at callback/value boundary |
| `TaxClass` type | table, form and confirmation props | direct | existing-compatible after focused type update |
| `ShopSettingsHub` | preserves generated Settings subnavigation and mount hierarchy | direct | included |
| `AdminSidebar` and `App` | canonical `/admin/settings/tax-classes` navigation/route | transitive | included at route/value boundary |
| Laravel `GET/POST/PATCH/activate/deactivate/delete /admin/api/tax-classes` | replaces source mock mutation/persistence | backend contract | approved implementation-plan contract |
| ZIP mock data | displays SST/GST/Service Charge in source baseline | direct | excluded from production; MySQL is authoritative |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| add/edit | `Add Tax Class` / edit icon | source modal, inline validation, saving state and copy | available adapter after canonical API |
| activation | inactive `Activate` | source confirmation when replacing active class; previous class becomes inactive | available adapter after canonical API |
| deactivation | active `Deactivate` | source warning confirmation that future carts/orders have no tax | available adapter after canonical API |
| delete | inactive trash icon | source delete confirmation; active trash remains disabled | available adapter after canonical API |
| unconfigured | no active class | source amber warning row | available adapter |

## Source variance record

- The supplied Product Editor has no visible `Tax Class` label, select, or form control. It does retain a non-rendered `taxClassId: ''` key in its new-product mock initializer. The approved contract removes product tax assignment entirely, so that invisible stale key is excluded from the Laravel data adapter; no generated visible DOM, class, copy or interaction is changed.

## Approval record

- Initial source boundary approved by: user, through the approved simple store-wide Tax Class design and the supplied ZIP (14).
- Dependency expansion approved by: not required; all dependencies are within the approved page/navigation/Product Editor boundary.
- Official route cutover approved by: user approved canonical `/admin/settings/tax-classes` and compatibility redirect from `/admin/settings/tax-zones`; final operational cutover remains subject to browser acceptance.
