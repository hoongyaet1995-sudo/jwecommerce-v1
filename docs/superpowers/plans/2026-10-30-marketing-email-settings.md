# Marketing Email Settings local implementation

Approved scope: reuse CRM sending identity, SMTP form, password preservation, connection test and limits; single route only. Existing store identity and staff auth are reused. No account/store duplication, pool, DNS-verification claims, actual send or deployment.

- Add merchant-scoped StoreSetting document, encrypted password, revision checks. Only store owner can read private configuration, save or check SMTP. Staff see readiness only.
- Reuse CRM node form wrappers/classes and existing xenibox.css. Provide Settings > Email entry; configured store sender as starting point. SMTP destinations restricted to deployment-owned trusted host list; TLS required.
- Authentication-only connector, disabled until explicitly enabled for actual test. Save never connects. Result clears on connection changes, unknown domain/receipt status remains visible.
- Marketing-only mailer binding. Snapshot settings revision and refuse changed configuration. Bulk requires verified route and known rate, limits count actual reserved attempts; throttling delays only unsent work, never retries unknown submissions.
- Fake adapter tests for secrets, owner rights, revision, verification reset, quota and sending isolation. Normal localhost UI with fake/synthetic saved settings only, no SMTP connect or mail.

Ledger: intentional initial TDD red is missing settings endpoint. Counts recorded below. Token use unavailable.

Initial red: endpoint404 intentional. First focused green: 28 tests/175 assertions passed, 4.89s. Fake SMTP only. No unexpected failures in this settings issue so far. Config saves reset verification; isolated marketing mailer preserves default transactional SMTP.

## Local completion evidence
- Full focused Email/Audience/Campaign/Template suite: 90 tests / 631 assertions passed, 19.34 seconds. JS syntax passed. MySQL reservations migration completed.
- Normal Chrome: Settings navigation and CRM-derived form inspected. Existing port465 default correctly uses SMTPS; port587 uses STARTTLS. Synthetic settings saved via normal UI; success shown, password field cleared. Database verified encrypted password, verified=false, bulk=false, connection checks=false. Synthetic row was removed under exact username/version guard, leaving prior environment fallback unchanged.
- Screenshot output/email-settings-local.png. Contains only synthetic settings and ordinary business sender info, not password.
- No real SMTP check, email, provider API, push, commit or deployment. Existing order mailer/default configuration untouched. Local scripts are output-only acceptance artifacts.

## Remaining real gates
Owner-provided sender credentials + explicit authentication-only connection check, actual confirmed delivery price, sender-domain authentication evidence, provider-specific authenticated bounce/complaint intake. The page does not claim these are configured. Pool and additional store/user copies excluded.

## User decision: existing Hostinger route
Use current mailbox with pilot cap 1,000 per rolling24h; no SES/server expansion. This is an application ceiling, not proof of actual provider remaining quota. Intentional TDD red: reject >1,000 and midnight must not reset rolling window. Quota identity must follow mailbox, not merely store, when several merchants share one database. Other websites external to this database remain provider-controlled.

Hostinger route validation: initial quota TDD red intentionally accepted >1000 (missing guard). After change focused29tests/180assertions passed. Rolling minute/hour/day and mailbox bucket implemented; MySQL quota-key migration succeeded. Existing local Hostinger SMTP authenticated successfully using current configuration; no MAIL FROM, RCPT TO, DATA, messages, or paid calls. No new credentials/settings persisted by this auth-only check. hPanel quota not inspected; app ceiling does not assert live remaining provider allowance.
