# Order drawer browser acceptance (isolated fixtures)

Command: `npx --yes --package @playwright/cli playwright-cli -s=mpOrderUI run-code --filename tmp/marketplace-order-ui-acceptance.js`

The browser used actual locally built JS/CSS, but its HTML shell and order API response were intercepted with explicitly labeled UI fixtures. No database record, token or real connection was created. This is not production authentication or ingestion evidence.

Passed at 1280x800 and 390x844:

- Order identity and item visible; no document horizontal overflow.
- Close button remains within viewport and returns to /admin/marketplace/oms.
- Both AWB and Dispatch buttons are disabled.
- Error response 503 renders an alert instead of a success drawer.
- Screenshots visually inspected. Original source button colors remain even while disabled; unavailable status explains why. No independent styling change made.

Artifacts:

- output/playwright/marketplace-30/order-ui-fixture-1280.png
- output/playwright/marketplace-30/order-ui-fixture-390.png
- output/playwright/marketplace-30/order-ui-fixture-items-1280.png
- output/playwright/marketplace-30/order-ui-fixture-items-390.png
- output/playwright/marketplace-30/order-ui-fixture-error.png

Build under test: order-Da8UAeSJ.js and order-C3U2LsF9.css. Backend route authorization is covered separately by MarketplaceOrderPreviewTest. Full source-app comparison, real ingestion and merchant operational navigation remain open.
