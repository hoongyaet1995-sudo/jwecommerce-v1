# Shared cart and quotation Checkout UI

Approved scope: both order sources use the same Website Theme Checkout renderer, navigation, footer, width, CSS and customization. Quote-specific content must not reprice, touch cart, require shipping, change ownership or payment semantics. Existing payment/order page remains separate in this phase.

Plan: (1) test every registered theme selecting the same checkout view for quotations, without cart/coupon/shipping endpoints; (2) shared view resolver, quote-mode body slot inside existing templates, shared quote fields styled using native theme classes; (3) focused isolated SQLite tests, view compilation and desktop/mobile browser evidence; (4) report local gates, no release.

Baseline: output/shared-checkout-ui/baseline.json. Unrelated dirty work preserved.

## Issue ledger

- Initial TDD red planned: quote currently renders independent document rather than theme checkout. Unexpected failures: 0.

- Intentional red confirmed: 1 failed / 4 assertions, expected theme renderer vs independent quotation document. Not an unexpected failure. Implementation now shares registered checkout view, shell and native theme form classes; quote-only body excludes cart endpoints.

- Focused suites after renderer and protected demo changes: 23 passed / 541 assertions (10.91s); unexpected test failures 0. Browser requested customer URL returned owner-protected 404, so paused that route and requested customer login. Protected Admin dummy demo renders successfully; no customer data bypass.

- Ordinary checkout/payment/address/customization regression: 21 passed / 168 assertions (6.02s). Browser found Food editorial heading/emblem overflow at 390px (438px scroll width), shared inherited heading flex did not wrap; fixed only mobile wrapping, preserving content. UI issue unexpected count 1, status repair pending measurement. Native payment method/bank class names now follow theme prefix. Reviewer found no important logic issues.

- Post-review control audit found checkout Page Media lived inside the cart-only body. Extracted one shared checkout-page-media partial consumed by both modes (native theme image placement/defaults/hide retained). Checkout customization roots now include non-main Food/Home/Beauty wrappers; custom Main also targets shared submit. This closes shared-control parity rather than copying independent quote media settings.

- 2026-10-15T15:12:07.992688+00:00: final control parity test passed (10 assertions), previous focused suites 44 passed / 709 assertions. Blade compilation passed. Desktop/mobile protected renderer demos captured for eight themes; 390px overflow none, corrected Food overflow verified 390/390. Standard/Wide/Full measured in width-metrics.json. Ordinary Universal checkout desktop/mobile screenshot retained. Browser console errors none at sampled normal page. Reviewer found no important issue after media/customization extraction. Real customer quote remains browser-auth pending; no order/payment submitted. Screenshots are dummy-data official renderers, not live customer acceptance. No release or database migration.

## Future industry contract
New Wedding/Logistics checkout must use WebsiteDesignService::view(checkout), preserve quote-mode shared form slot and native theme classes, and include the protected quotation-checkout acceptance scenario. Do not create an independent quote checkout design. Post-order quotation payment/receipt page remains the existing implementation; this phase changes pre-order checkout UI only.

- 2026-10-15T15:12:27.223673+00:00: final combined verification planned after media extraction, customization-root fix and quote media/hide test added. Command: php artisan test --compact [QuotationCheckoutTest, EnquiryQuotationTest, WebsiteThemeDemoTest, CheckoutSavedAddressTest, Payment/OfflineCheckoutTest, Payment/HostedPaymentCheckoutTest, WebsiteThemeCustomizationTest]. Unexpected unresolved failures 0.

- Final combined verification result: 45 passed / 719 assertions, 16.43 seconds. Unexpected unresolved failures 0. Source fingerprints: output/shared-checkout-ui/source-hashes.json. No frontend JS or compiled Tailwind source changed; existing classes reused, public CSS served directly. Blade compilation successful. Local implementation complete; real customer visual acceptance and synchronization remain pending.

- Customer login supplied by user: real quotation checkout verified on desktop 1440 and mobile 390 viewport. Logo loaded from configured branding asset; fixed total RM81,600.00; readonly quote email; no horizontal overflow; sampled console errors 0. Screenshots customer-desktop.png/customer-mobile.png. No form submitted, order created or payment initiated during browser acceptance. Earlier customer-auth visual gate now resolved.
