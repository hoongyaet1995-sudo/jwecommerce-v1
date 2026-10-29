# CRM Campaign workspace: local phase

## Approved scope
- Reuse user-supplied crm.zip presentation and CSS for Email, SMS and WhatsApp creation forms.
- Collapse campaign filters by default; explicit Apply/Reset and pagination.
- Unified Create Campaign chooser: Email, SMS, WhatsApp, Post. Post keeps #new.
- Persist private, versioned drafts using existing campaign ownership, with channel default post for historical rows.
- Use actual saved templates; WhatsApp selector accepts approved, unarchived templates only.

## Boundaries
- No CRM credentials, recipients, historical campaigns or transport code imported.
- No delivery, scheduling or consent inferred by saving a draft.
- Audience segments and Email/SMS transport are not implemented in this phase. Their controls are unavailable, and this is stated before editing.
- WhatsApp campaign delivery/review remains a later integration; this new editor saves a channel draft only. Existing WhatsApp preparation path is retained.
- Header/button recipient variable mapping, merge-tag resolution, immutable execution snapshot, audience freeze and pricing must be verified before any delivery is enabled.

## Evidence
- CRM original form wrapper/section/classes extracted from supplied source with PHP values removed; generated frontend form asset contains no CRM data or backend includes.
- 25 focused tests, 129 assertions passed. PHP lint and three JS syntax checks passed.
- Local browser verified filter default/toggle, chooser four routes, Email draft save, SMS/WhatsApp layouts and original Post form.
- Email draft saved through normal browser: 01a18749-63d8-72b2-b80c-b57afc752c1f.
- No real provider requests, message sends, commits, pushes or deployments.

## Channel invariant
Each campaign has exactly one immutable channel: email, sms, whatsapp or post. Post Content offers only social posts. WhatsApp preparation selects only WhatsApp campaigns. Server-side association checks enforce this, including legacy endpoints. Existing records are preserved.
