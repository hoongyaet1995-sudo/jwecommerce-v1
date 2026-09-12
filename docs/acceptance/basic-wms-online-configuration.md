# Basic WMS online development configuration

Not a deployment receipt. Apply only after the coherent release is approved and deployed.

- MARKETPLACE_BASIC_WMS_ENABLED=true enables initial configuration and manual count/delta API entry points.
- MARKETPLACE_ADVANCED_WMS_ENABLED=false keeps advanced reconciliation and mode conversion closed, including service calls.
- MARKETPLACE_WAREHOUSE_TRANSFERS_ENABLED=false keeps transfer creation and transitions closed.
- MARKETPLACE_STOCK_SYNC_OPERATIONS_ENABLED=false keeps WMS stock broadcast dispatch closed.
- Inventory database configuration accounting_enabled must remain false. Basic setup does not change legacy stock or allocations.

Lazada official test credential access outside local/testing additionally requires MARKETPLACE_LAZADA_ONLINE_TEST_ACCESS_ENABLED=true and MARKETPLACE_LAZADA_TEST_STORE_ID set to the actual deployed store ID. Never assume local IDs match the deployed database. The selected store must be classified test, use seller identity 300163725140 and application 139209, and have official_test provenance with an unexpired locally imposed lease. Both switches default closed/unset in .env.example. No secret belongs in this file.

This access gate permits obtaining the existing server-side credential; it does not authorize stock writes. The separate bounded sandbox inventory write workflow is not yet complete. Existing test-connection provisioning remains local-only, so online credential installation/renewal must be completed before online provider acceptance can succeed. Do not represent a configuration edit or mocked credential test as live provider evidence.

Required release evidence still includes: authenticated browser manual stock operations, unchanged legacy stock/order allocations, test classification visibility, sandbox read and bounded write/readback, and matching local/GitHub/Hostinger commit SHAs.
