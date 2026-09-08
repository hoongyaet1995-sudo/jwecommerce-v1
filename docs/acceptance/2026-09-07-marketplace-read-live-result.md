# Sandbox read acceptance after refresh-aware release

- User approved release and subsequently granted ongoing in-plan implementation/test/release/Sandbox acceptance authority; do not repeat synchronization approval requests. Escalate only missing access, interactive login/verification, or material out-of-scope decisions.
- Local, GitHub main and Hostinger match 0676f1b854a9218c07bea335c5480519cba5c75d. Hostinger fast-forward and cache clear succeeded.
- Authenticated official OMS: selected store 1, clicked Fetch Orders (7 days), manually ran bounded marketplace worker. Order run 3 succeeded, one page, no error. Actual orders now 260906B667WJ5E and 260907CYVAACAD.
- Authenticated official Mapping: selected store 1, clicked Fetch Store Products, ran bounded worker. Catalog run 3 succeeded, one page, no error. Two external variants now observed, including JWSTORE1PS5-500GB and TES-BLU-3CM.
- JW product count remains 10; stock fingerprint unchanged: e9648ce1062bbbb306ddafaf397f9625515c20275450b7044e5e0edef3e55e11. Queue empty. Sandbox reads enabled, general operations disabled. No publishing, stock overwrite or shipment.
- Connection GET returns connected; observed access expiry before these pulls was already 2026-09-07T06:25:02Z. These pulls therefore prove real read capability, not that this run itself rotated an expired token. No forced expiry or claim of observed automatic rotation.
- Screenshots: output/playwright/marketplace-30/orders-real-fetch-20260907.png and catalog-real-fetch-20260907.png.
- Remaining: real import/mapping closure, publish and order fulfillment/inventory, worker scheduling, legacy badges, further platforms and AI. Overall goal remains incomplete.
