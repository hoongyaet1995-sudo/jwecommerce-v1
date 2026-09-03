# Bank Payment Proof Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (14).zip`
- ZIP SHA-256: `4F4709A96D9DFF9F04EA9B10B285E8B88E23C1C0AEC1678A4206E795ED809EB1`
- Target Laravel project and routes: `C:\xampp\htdocs\jwstudio-ecommerce`; `/checkout`, `/order/success`, `/account#purchase_history`
- Requested page/region/function: checkout proof copy, success-page hero proof panel, expanded Purchase History compact proof panel
- Explicitly excluded ZIP regions: catalog, PDP, PLP, navigation, footer, authentication, vouchers, shipping, mock catalog, and every Admin surface
- Acceptance intent: operational closure backed by Laravel/MySQL and source-faithful customer UI

## Source baseline

- Source startup/build command: `npm install`, `npm run lint`, `npm run build`
- Build result: passed on 2026-09-03; Vite emitted a non-blocking chunk-size warning
- Runtime note: ZIP resolved `@vitejs/plugin-react` 5.2.0, which warns that Node 20.17.0 is below its preferred 20.19.0 floor
- Desktop viewport and baseline path: pending screenshot capture before Blade conversion
- Mobile viewport and baseline path: pending screenshot capture before Blade conversion
- Known source-only controls: `showDemoStateBar={true}` is QA-only and must be disabled on official Laravel routes

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/types/paymentProof.ts` | `E7EB49B14EF28A76370010086FA432EB32FC24DCE5E44E403FD03E9EA8DA8B7E` | state/view-model contract | `app/Domain/Payments/CustomerPaymentProofViewModelFactory.php` | selected |
| `src/components/payment/BankPaymentProofPanel.tsx` | `9A59E02AA528487FFA3B092238C855F020C8757F697711EA57C6468062E46B84` | shared hero/compact presentation and interactions | `resources/views/components/storefront/bank-payment-proof-panel.blade.php` | selected |
| `src/components/pages/CheckoutPage.tsx` | `3FA90A36416D6CADAADB1BB98BA2F0858375743FA64E63D897208E535D2C18DC` | Region A placement/copy | `resources/views/storefront/checkout.blade.php` | selected region only |
| `src/components/pages/SuccessPage.tsx` | `74447078059086D924436B948BA2A293F9EB41B112318A6C74E9F1DC336AC294` | Region B placement | `resources/views/storefront/success.blade.php` | selected region only |
| `src/components/account/PurchaseHistoryTab.tsx` | `14F79FEFE86571807056EBE80CCE1770EAC0E1F76F7555B9A4BA788ADDDA3BCB` | Region C placement | `resources/views/storefront/account.blade.php` | selected region only |
| `src/types/ecommerce.ts` | `D97B6C13AEA2685E3B4945DE55910D2F435FF7E9395B2EB13508A7D3B7D2E393` | order proof field contract | account/success Laravel payload adapters | selected fields only |
| `src/data/mockData.ts` | `4CBD9B93D80185D9C04C3C031280D9C504D2E798CF86010F893CEBD022A21E81` | visual state fixtures | tests only; no runtime mock data | selected fixture semantics only |
| `src/index.css` | `C53AE89A02CAC22EB1FA5EA0FB2E823CBC087C999426925E67EB53A7256635F2` | Tailwind entry | existing Laravel Tailwind pipeline | existing-compatible |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| React local state/effects | source interaction semantics | direct | adapt to data attributes and existing `resources/js/app.js` |
| `lucide-react` icons | source icon identity | direct | use existing `lucide` package and icon renderer |
| Tailwind 4 utilities | exact appearance/responsiveness | direct | existing-compatible |
| mock order/proof records | six-state source preview | direct | test fixtures only; forbidden in runtime |
| checkout, success, account page shells | placement context | transitive | existing Laravel shells remain authoritative |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| missing | eligible Bank In payment without proof | action-required drop zone and transfer reference | available adapter |
| selected | valid file selected or dropped | filename/size row and submit action | browser-local transient state |
| uploading | submit proof | disabled spinner/progress and double-submit prevention | available adapter |
| uploaded | successful response | pending-verification state and permitted replacement | available adapter |
| rejected | Admin rejection persisted | safe reason and resubmit action | available adapter |
| verified | Admin verification persisted | locked verified state with no mutation control | available adapter |
| validation failure | invalid MIME/size or 422 | inline source error without browser alert | available adapter |

## Approval record

- Initial source boundary approved by: user, 2026-09-03
- Dependency expansion approved by: not required; no expansion beyond selected source
- Official route cutover approved by: not yet; requires local source-fidelity acceptance

