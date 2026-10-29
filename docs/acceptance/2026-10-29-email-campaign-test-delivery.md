# Email Campaign test delivery acceptance ledger

Approved: one user-owned recipient supplied in chat, no customer list, no automatic retry. SMTP acceptance is not proof of inbox delivery. No real payment authorized.

- Initial test run intended red was blocked by migration namespace typo instead of missing routes. Recorded as unexpected schema-import issue, not a valid TDD red. A second already-launched run reported same typo; cumulative count 2. Corrected Schema facade import before next run.
- Initial raw recipient never committed to code. Application uses generic encrypted recipient and HMAC dedupe. No provider credentials printed.
- 2026-10-29T22:30:28.8635906+08:00: run focused suite after Schema import fix; test queue/mail fakes only.
- 2026-10-29T22:33:34.3365604+08:00: focused 3 tests passed / 14 assertions, 5.57s; Schema issue resolved. Added unknown SMTP no-resend and visitor consent/arrival regression; run after additions and panel unsaved-content guard.
- 2026-10-29T22:34:18.3390906+08:00: 10 tests passed, new visitor integration failed. Add explicit redirect-token assertion to distinguish redirect/config failure from consent handling before focused diagnostic run. No real SMTP.
- 2026-10-29T22:35:58.7824649+08:00: visitor test mismatch count 2: redirect token exists; existing consent API returns accepted, not tracked. Corrected assertion to existing contract and retain actual arrival DB assertion plus page tracked assertion. No application tracking logic changed.
- 2026-10-29T22:38:22.9959238+08:00: shared-host review found new template/email POST routes needed named safe-list entries when video execution is off. Added narrowly scoped route names and retained independent email flag/permissions. Tests now explicitly disable video execution to verify shared-host behavior; production video execution remains off.
- 2026-10-29T22:46:56.5148387+08:00: live draft save blocked by prior un-named channel-drafts route under shared-host rendering lock. No SMTP task created. Added route name and narrow preparation safe-list entry; regression via actual controller with video execution off.
