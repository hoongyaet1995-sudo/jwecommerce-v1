# Task 3 report — Persist immutable checkout tax snapshots

- Status: complete
- Implemented server-side checkout snapshot persistence inside the existing database transaction.
- `orders.tax_amount` remains the authoritative `CartPricingService` quote aggregate; each matched tax quote line (including 0%) now creates an immutable `order_tax_lines` record with current nullable FKs and a complete configuration/rule/class/zone snapshot.
- Unmatched or disabled configurations create no tax snapshot rows.
- Added a migration that keeps order-tax lines immutable while permitting `ON DELETE SET NULL` changes to the historical rule/class foreign keys; the serialized snapshot remains unchanged.
- Tests: `C:\xampp\php\php.exe artisan test tests/Feature/TaxCheckoutSnapshotTest.php tests/Feature/TaxQuoteServiceTest.php tests/Feature/TaxQuoteCartPricingTest.php tests/Feature/CheckoutCommerceRulesTest.php` — 36 passed, 128 assertions.
- Risk: no UI/route changes; browser rendering remains Task 5/6 scope.
