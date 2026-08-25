# Checkout Bank Transfer Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\universal-e-commerce-system-&-schema-suite (9).zip`
- ZIP SHA-256: `451A308D051177722EFF269B3819E88B2ED973388CE1CC6BADAD74D244712FBB`
- Target Laravel project and official route: `C:\xampp\htdocs\jwstudio-ecommerce`, `/checkout`
- Requested page/region/function: Payment Gateway Selection's Direct Bank Transfer / DuitNow option and conditional detail panel.
- Explicitly excluded ZIP regions: mock address autofill, mock card collection, fake gateway processing, mock totals/taxes/orders/tracking, and all other Checkout regions.
- Acceptance intent: Operational binding to real admin configuration and MySQL-backed checkout records, followed by official-route acceptance.

## Source baseline

- Source entry: `src/main.tsx` -> `src/App.tsx` -> `src/components/pages/CheckoutPage.tsx`.
- Source startup/build command: `npm run dev` / `npm run build` from the private extracted archive.
- Desktop baseline: supplied screenshot `codex-clipboard-25bc9f17-4f5b-417d-8837-4aeec20568d4.png`.
- Mobile baseline: must be captured from the ZIP before official-route cutover.
- Known source mock behavior: `DEFAULT_BANK_TRANSFER_CONFIG`, bank accounts, financial values, uploads, orders, provider states, and tracking are local React state/mocks.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/pages/CheckoutPage.tsx` | `8D94433BCF9E3DE1BDBFC9AE8031FEB82403D4566722E52E19A1BFE04E59579A` | Bank Transfer selector and conditional detail panel | `resources/views/storefront/checkout.blade.php` plus narrow client behavior | selected |
| `src/types/ecommerce.ts` | `D92B0295326C0402C6692763625E83D32B1C52BB0E200B10E311BD1510E31C06` | Bank Transfer view-model shape | Laravel checkout view-model adapter | selected |
| `src/data/mockData.ts` | `47F17FC40F0CD976282228C6666614EB6EFAA8E5B403E05F727CA170722FD8F5` | Mock-only bank data and copy source | no direct runtime use | reference only |
| `src/index.css` | `C53AE89A02CAC22EB1FA5EA0FB2E823CBC087C999426925E67EB53A7256635F2` | Tailwind base/dark variant | existing Laravel Tailwind build | existing-compatible |

## Dependency map

| Dependency | Why it is needed | Type | Decision |
| --- | --- | --- | --- |
| `BankTransferConfig` | active accounts, allowed receipt upload, payment window, instructions, disclosure | direct | adapter-needed |
| browser file state | display selected receipt and remove action before submit | direct | adapter-needed |
| Lucide icons | bank, copy, QR, upload, file and remove states | direct | existing-compatible |
| fake processing status block | presents mock provider progress and success | transitive | excluded; backend contract replaces it |

## Preserved interactions

| Flow/state | Source trigger | Expected visible behavior | Data classification |
| --- | --- | --- | --- |
| availability | active accounts exist | Bank / DuitNow option renders; absent otherwise | available adapter |
| account selection | selected account card | selection border/pill changes; copied values use selected account | available adapter |
| copy | account number or DuitNow copy button | temporary copied feedback | available adapter |
| receipt attach/remove | file selection or drop / remove action | optional receipt state appears and can be cleared | backend contract gap |
| disclosure | Bank / DuitNow selected | selected source panel expands beneath payment grid | available adapter |

## Approval record

- Initial source boundary approved by: user, 2026-08-25.
- Dependency expansion approved by: none. Online payment return failure/cancel UI remains unapproved.
- Official route cutover approved by: user requested `/checkout` operational closure; implementation and browser acceptance remain required.
