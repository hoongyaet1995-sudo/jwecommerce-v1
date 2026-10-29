# Email/SMS template implementation plan

Execute inline in canonical localhost. User approved source fidelity and template-only scope.

1. Import CRM editor assets, source templates and isolated renderer/validator services with MIT provenance. No credentials, database dumps or transports.
2. Add private Laravel catalog/editor/save/media routes. Reuse MarketingContentTemplate ownership and versioned writes; round-trip editor JSON, sanitized HTML, text, subject, preheader and Draft/Active. Preserve legacy draft payloads and WhatsApp behavior.
3. Bind original Email catalog/editor views and SMS source form to these routes. Reuse original CSS and client behavior, replace visible branding, route existing type choices to new pages.
4. Test roundtrip, stale writes, foreign access, duplicate/archive, HTML safety, GSM/Unicode segments; then normal-page save/reopen and visual comparison.
5. Record local evidence and limitations. No delivery, paid API, commit/push/deploy.
