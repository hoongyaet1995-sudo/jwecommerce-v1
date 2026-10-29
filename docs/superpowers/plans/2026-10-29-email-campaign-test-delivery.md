# Email Campaign single-recipient delivery and conversion acceptance

User authorized one real test email to their own supplied Gmail. Implement generic single-recipient test action on saved Email Campaign; do not add bulk audience or sender configuration.

- Dedicated database queue with immutable template/subject/CTA snapshot and one attempt. SMTP acceptance is not inbox delivery. Unknown errors do not retry.
- Recipient encrypted at rest; unique campaign revision + recipient hash prevents duplicate submissions. No hardcoded recipient in application code.
- Same-store landing URL and existing consent-aware attribution flow. Tracking links allow null Post source and declare email channel; no fake social Post.
- New action has explicit confirmation in normal Campaign page. Existing SMTP sender only, no spoofed from address. Test marker in subject/body, test-only unsubscribe wording (no marketing subscription is created).
- Local fake-mail tests before approved sync; migrate additive table/nullable source then normal-page one-recipient request online.
- Verify email send status, then visitor click/request quote. Payment is not authorized; purchase event requires real verified payment or fake-provider local proof and must not be fabricated.
