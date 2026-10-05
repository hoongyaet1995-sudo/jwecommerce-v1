# Automatic Social Hub website pairing

Boundary: same website installation for Facebook and Instagram; Hub controls OAuth only. Existing installations preserved. Manual installation page and PUT endpoint return 404; customer overview link removed. Developer-only diagnostics remain.

Deployment hook: ops/prepare-social-pairing.sh, run from private Laravel root. New installs create a 24-hour random code at storage/app/private/social-hub-pairing.txt, never print it or copy it into Git. Developer enters that code in Hub Add Client once. Hook is provided but not yet integrated into an external customer deployment orchestrator.

Hub pins verified public IPv4 for HTTPS target, disallows redirects, issues independent encrypted credentials, delivers only to registered domain with the one-time code, verifies signed receipt and runs reverse challenge. Client validates APP_URL domain, code hash, expiry, request signature; locks token row, creates encrypted settings atomically, rejects replay or replacing existing installation, and sends signed heartbeat. Registration / paired status does not imply both directions verified; returned heartbeat and reverse results are independent. Failed or ambiguous transport retains credential and awaiting state, never claims successful pairing. Automatic retry/repair UI for a failed registration is not yet implemented; do not rotate/delete blindly after unknown transport outcome.

Tests: Ecommerce 8 passed / 70 assertions; Hub 8 passed / 94 assertions. Hub test initial unexpected failure: called challenge instead of existing check method; corrected after inspection. Count 1 resolved. Both frontend builds passed. Real HTTPS new-domain end-to-end pairing and production deployment pending. Existing Ecommerce pairing and provider tokens untouched. Instagram webhook Meta configuration and real DM acceptance remain pending.

Five-minute request: core code is implemented, but full live provisioning and Instagram verification cannot be honestly claimed within that limit. Measured elapsed/token usage unavailable.
