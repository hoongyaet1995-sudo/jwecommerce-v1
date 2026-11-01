# JW corporate privacy CMS publication — 2026-11-01

User approved Packages/Add-ons visual layer and requested a CMS Privacy Policy published on www for TikTok review. This release contains policy content and explicit migration/legacy-routing helpers only. SaaS runtime changes remain local and are excluded.

## Scope
- Existing rich_text + policy_content directory + cta_strip; editable in independent JW CMS.
- Operator identity from supplied company brief, public privacy email reused from current /privacy: sales@jwaistudio.online.
- Connected accounts, messages, configured AI processing, service providers, optional analytics, retention, revocation, deletion and merchant/controller distinction.
- TikTok Business Messaging explicitly pending approval.
- Exact GET/HEAD privacy paths only bridge to corporate instance. Existing homepage, Admin, Hub, OAuth and webhooks remain on their original handlers. Bridge reversible using recorded private .htaccess backup.

## Verification / issue ledger
- Initial local contract validation rejected mailto action destination (1 unexpected validation failure). Replaced action with supported same-site Return to JW Studio; contact email remains readable. Canonical create and publish passed. Local Page53 /pages/privacy-policy HTTP200, content/TikTok/contact checks passed. No blind retries.
- PHP lint passed bridge and installer. Normal authenticated CMS preview displayed 12 policy entries in existing renderer. No paid API.
- TikTok actual profile inspected: Rejected, reason domain security; asks for representative or developer ticket. Profile purpose currently marketing-data wording, not current support-messaging goal. New policy alone does not resolve or guarantee this review.
- Deployment, HTTPS and final screenshot evidence to follow. Time/token usage unavailable.

## Live verification — completed 1 November 2026
- Corporate CMS published Page2, slug privacy-policy. /privacy (preserves existing review URL), /privacy-policy and /pages/privacy-policy all return200 without login.
- Read-only HTTPS checks: policy, isolated CSS and JS, existing /terms, old homepage and Social Hub all200. Screenshot desktop/mobile and HTTPS JSON under output/jw-corporate-migration/acceptance/privacy-*.
- Browser: 12 policy headings; desktop1536 / scroll1526; mobile390 / scroll390. No sample placeholder. Header shows JW Studio. Existing directory visual retained.
- Source deployment verified local/GitHub/corporate Hostinger equal54ebfad8714bb002bcbf27fad57e524f1ac7e51f before this documentation checkpoint. Ecom runtime untouched.
- Asset issue ledger: first live view lacked CSS because old asset namespace was used; ASSET_URL isolated. PHP symlink unavailable (one failed installation plus one explicit diagnostic invocation); shell links then exposed a distinct private-directory traversal limitation. Resolved by copying only public build/css/js assets into separate public namespace, retaining private directory permissions. Installer now copies assets and validates preexisting links. No blind reruns; final assets200 with CSS/JS MIME types.
- Reversible legacy public rewrite backup: /home/u762348670/backups/jw-privacy-20261101-041400/public.htaccess; corporate env backup remains private in same directory. Legacy callbacks unchanged; only exact privacy GET/HEAD paths intercepted. No full homepage/Admin cutover claimed.

## TikTok follow-up draft (not submitted)
Profile remains Rejected. Actual reason: security of provided domain; TikTok requests a representative or developer ticket for further checks. Publishing a policy does not establish the domain is approved or diagnose TikTok's security classifier.

Suggested ticket summary: Please re-check https://www.jwaistudio.online/ for developer onboarding. Our public Privacy Policy is https://www.jwaistudio.online/privacy and Terms are https://www.jwaistudio.online/terms. JW Studio is operated by JW TECH SOLUTIONS in Malaysia. We intend to integrate authorised TikTok Business Account messages into a merchant customer-support inbox, with agent replies, optional configured AI assistance, human takeover and account disconnection. TikTok messaging is not active before approval. Please identify the domain-security issue or the verification needed. We can supply the prototype and business-registration evidence through the required secure process.

Before resubmission, replace the old marketing-data purpose with the actual support-messaging scope and verify any claimed clients/revenue. Do not invent clients or describe prototype capabilities as approved live access. No application resubmission or ticket sent in this phase.
