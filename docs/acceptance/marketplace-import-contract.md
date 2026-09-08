# Marketplace product import contract

Mode: OPERATIONAL_CLOSURE target, not yet closed. Scope: Shopee catalog to JW drafts only.
Runtime owner: MySQL. UI authority: ZIP 30 MarketplaceMappingPage import action; this batch is L0 backend only.
Release: local only; official UI cutover and deployment remain unapproved.

| Capability | Owner / producer | MySQL truth / consumer | Eligibility and behavior | Status / evidence gap |
|---|---|---|---|---|
| MP-IMPORT-01 explicit product import | Employee import action -> MarketplaceImportService | products, product_variants, marketplace import identity, mappings; Admin draft catalog | Existing fetched product, complete SKU/title/price, matching default currency; creates draft and zero stock, never publishes | BACKEND_CONTRACT_GAP; service/API/browser and real source acceptance pending |
| MP-IMPORT-02 retry identity | System import identity | Unique store + external product -> retained JW product | Repeat returns same product, including after mapping unlink; no duplicate master | BACKEND_CONTRACT_GAP; concurrency and retry proof pending |
| MP-IMPORT-03 conflicts | Employee resolves existing SKU through mapping | Existing products preserved | Existing SKU or partial existing mapping blocks creation; no automatic merge | BACKEND_CONTRACT_GAP; rollback evidence pending |

No remote writes, inventory initialization, deletion, currency conversion or storefront publication.
Price conversion follows JW integer minor-unit storage, without copying channel stock.
Source snapshots are editable catalog observations, not immutable order facts.
Required final evidence: actual import click, refresh/relogin persistence, draft excluded from public storefront,
same-store mapping invariant, MySQL concurrency, and real authorized catalog source.
