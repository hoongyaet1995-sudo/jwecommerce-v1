# JW Studio Ecommerce Delivery Contract

## Canonical environments

| Role | Canonical location |
| --- | --- |
| Development and first acceptance environment | `C:\xampp\htdocs\jwstudio-ecommerce` |
| Source-control mirror | `https://github.com/hoongyaet1995-sudo/jwecommerce-v1` (`main`) |
| Production application root | `/apps/jwecommerce` |
| Production public URL | `https://ecom.jwaistudio.online` |
| Production web root | `/apps/jwecommerce/public` |

## Mandatory development and release order

1. Make every application-code change in `C:\xampp\htdocs\jwstudio-ecommerce` first.
2. Run the relevant tests, build steps, and browser acceptance against localhost.
3. Commit the accepted source and push it to GitHub `main`.
4. Deploy that exact Git commit to `/apps/jwecommerce` on Hostinger.
5. Verify that localhost, GitHub `main`, and Hostinger report the same commit SHA, then verify live HTTPS behavior before declaring a release complete.

## Sync and deployment cadence

- Do not push to GitHub or deploy to Hostinger after every individual change.
- Accumulate a coherent, locally verified phase of work in `C:\xampp\htdocs\jwstudio-ecommerce` first.
- At the end of that phase, report the completed scope and verification evidence, then explicitly ask the user whether to synchronize the phase to GitHub and/or Hostinger.
- Commit, push, and deploy only after the user gives that synchronization approval. Until then, localhost is the active development truth.

## Drift prevention

- Do not implement application code only on Hostinger.
- Do not treat a GitHub-only change as deployed or accepted.
- Do not deploy uncommitted local changes.
- `.env` is never committed. It may differ only for environment-specific values such as `APP_URL`, database connection, mail delivery, and private provider credentials.
- If a production-only `.env` change is required, apply the equivalent non-secret configuration/documentation change locally so the next deployment does not silently overwrite the intended setup.

## Inventory supervision and handoff

- Before starting or completing an inventory task, read `docs/contracts/shared-stock-pool-inventory.md` and the relevant phase acceptance record; the current Intake/Audit supervision entry is `docs/acceptance/2026-09-30-shared-pool-intake-audit-supervision.md`.
- On session recovery, record the branch/HEAD, existing dirty changes, relevant file hashes, approved phase and evidence before proceeding. Re-read files changed concurrently; preserve unrelated work. Scope or invariant changes require an explicit user decision recorded in the contract.
- At completion, compare against that baseline and record tests, localhost browser evidence, unresolved assumptions and gaps. Before synchronization/deployment, verify that the accepted source and evidence match the version being released, then follow the approval and exact-SHA delivery rules above. Documentation reduces drift; it does not replace verification.

## UI source fidelity

For Google AI Studio ZIP work, preserve the supplied DOM structure, classes, assets, copy, responsiveness, and interaction behavior. Add Laravel/Alpine data bindings only; do not redesign or substitute components unless the user explicitly authorizes it.
