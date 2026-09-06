# Sandbox V2 OAuth release and real acceptance

- Approved patch deployed: `c8cc6262fbf37ad89a81243ed63ed290670a16f0`.
- Local HEAD, GitHub main and Hostinger HEAD all matched. Hostinger optimize:clear succeeded.
- Local regression: 58 Marketplace/Shopee tests, 345 assertions passed (HTTP fixtures).
- Real Chrome acceptance on 2026-09-06 around 08:56 UTC: user-approved Shopee Sandbox consent completed on the official provider, then returned over HTTPS to the JW OAuth acceptance route.
- Verified result: Store Connected Successfully; provider shop ID `227482706`, name `OpenSANDBOX107385c9c45336b3868`, MY. No initial tasks queued; automatic synchronization remains off.
- Clicked Complete & Return to Stores. Store remained visible after navigating afresh to `/admin/marketplace/auth`; the page reports Authorized for the real Shop ID.
- Screenshot of actual success screen emitted in the conversation.

## Remaining issues / boundaries

The old official Connect Store page still contains contradictory static copy: Open API not connected, No credentials stored, Not authorized token validity, and a placeholder webhook secret. These are not evidence that the verified callback failed. Replace this outdated presentation through the approved ZIP30 integration/cutover process; do not expose placeholder claims as real configuration.

This closes only this Sandbox OAuth acceptance. Catalog pull, SKU mapping, product publishing, order pull, stock writes and other providers remain separate acceptance gates. No inventory, publishing, shipping or order jobs were enabled by this release.
