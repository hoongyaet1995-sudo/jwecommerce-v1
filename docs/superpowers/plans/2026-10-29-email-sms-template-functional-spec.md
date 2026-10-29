# Email and SMS template integration functional specification

Approved scope, 2026-10-29. Templates only: no recipient selection, transport setup, test delivery, campaign dispatch, paid API, commit or deployment.

## Source of truth
Reuse supplied crm.zip template catalog, email-editor shell, compiled EmailBuilder.js assets and SMS form. Preserve source structure, styles and interactions; replace Xenibox branding with JW Studio. Retain upstream MIT LICENSE and provenance. Existing WhatsApp and Post workflows remain unchanged.

## Merchant journeys
Email: Templates > Create Template > Email > Ready Email Templates > category/preview/use design or blank > full-screen Email Builder > save > template list > reopen same design.
SMS: Templates > Create Template > SMS > details/body/variables/preview > save > reopen.
Shared actions: Edit, Duplicate, Archive; archived templates remain recoverable and unavailable for new selection. Saving Active never sends.

## Email editor
Keep three-column source layout: template metadata and personalization left; editable canvas center; Email Style and Block Settings right. Preserve desktop/mobile preview, supported source blocks, block ordering/duplication/removal and source style controls. Name, Draft/Active, subject and optional preheader are stored alongside structured editor JSON, rendered HTML and text alternative. Source ready designs retain category, stable ID, title and preview. Changing a starter with unsaved edits requires confirmation. Initial empty canvas is valid while editing; saving Active requires usable subject/content and valid required placeholders.

Images use merchant-authorized stored assets and upload validation. No arbitrary remote fetch from supplied URLs. Email-compatible rendering is separate from the Admin shell. Never interpolate untrusted HTML into the Admin DOM; previews are sandboxed. Sanitize output and validate supported document schema and URLs server-side. An editor desktop/mobile preview is not proof of rendering parity in all email clients.

Personalization: first_name, last_name, email, store_name, unsubscribe_url. Example values are preview-only. Unknown variables are flagged; required unsubscribe destination is resolved by the sending system in a future phase. No fake unsubscribe URL is stored as a working recipient link.

## SMS editor
Reuse source visual form, variable insertion and phone-style preview. Count GSM-7 basic/extension units and UCS-2 units accurately; segmented display uses encoding-specific single/concatenated limits. Variable values can change the final length; show estimate rather than final provider price. No supplier credentials imported from CRM. No delivery enabled.

## Persistence and access
Reuse MarketingContentTemplate ownership, merchant/user boundaries, version checks, duplication and archival. Template channel is immutable. Save only current revision; stale changes return a useful message and keep local edits. Duplicate creates independent Draft without delivery/provider records. Preserve originals and old versions. Existing simple Email/SMS drafts must still open without destructive conversion.

## Acceptance
1. Source-faithful library, editor and SMS form with JW Studio branding, English text and no unrelated logos.
2. Choose starter, edit text/image/button/styles, reorder supported blocks, save, reload and compare structured design and rendered output.
3. Desktop/mobile preview; long subject/body and narrow viewport do not break controls.
4. SMS GSM-7, extension characters, Unicode and concatenation boundary fixtures; variables preview correctly.
5. Draft/Active, duplicate, archive/restore and stale save preserve records and ownership.
6. Foreign template/media denied; unsafe markup/URLs rejected; previews cannot execute scripts.
7. Fake-response tests and normal localhost checks only; assert no provider delivery or paid API.
8. Record explicit remaining email-client compatibility limits before release.
