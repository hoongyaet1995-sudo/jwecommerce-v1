# Approved conversion report integration
Mode: REAL_DATA_BINDING. Canonical development first; no commit/push/deploy authorized.
Approved source: work/video-quality-review/ab-comparison/conversion-reports-sample-20261029. Three tabs, icon above metric labels, one Conversion Events section, clickable enquiry/order details. Reuse existing campaign/event/payment evidence; no new charging, publication or tracking policy.

1. Test owner/merchant-scoped paginated conversion drill-down, enquiry/order source records, event anonymization, date and currency consistency, denied staff and cross-owner access. Initial TDD red is intentional.
2. Add MarketingConversionDetails service and protected read routes. Reuse MarketingAnalytics filter validation; no customer contacts or tokens in report responses. Existing Admin enquiry detail route is an optional super-admin action, ordinary analytics access remains read-only.
3. Bind approved sample in a dedicated analytics frontend module with existing shell, Bootstrap/assets. Real totals, per-currency net, enquiry/paid order drill-down, paginated lists, source post/account grouping. Preserve Campaign editor and existing routes.
4. Focused tests, PHP/JS syntax, normal local page and isolated fake-response rendering. Update existing readiness/implementation/ledger. No automatic release.
