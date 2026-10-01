# JW Studio Ecommerce Delivery Contract

## Canonical environments

| Role | Canonical location |
| --- | --- |
| Development and first acceptance environment | `C:\xampp\htdocs\jwstudio-ecommerce` |
| Canonical localhost Admin inventory entry | `http://127.0.0.1:8500/admin/marketplace/inventory` |
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

## Testing budget and escalation

- Maintain one durable issue ledger in the active phase's existing checkpoint/test log. Identify the underlying issue, not just a test name. Before each run record its timestamp, command, purpose and changed code/configuration or new diagnostic evidence; afterwards record result, cumulative unexpected failure count, status and next step. Unchanged blind reruns are prohibited.
- For the same unresolved issue, cumulative unexpected failures trigger **3: Attention**, **6: High attention**, **9: Test error**. Report 3/6 status in the next checkpoint. At 9, stop automatic retries and affected repair/review work, report to the parent/user, and require an explicit user decision before resuming that issue. Continue independent safe work only.
- Counts persist across agents, tasks, sessions and context recovery. Renaming tests, switching agents, changing tactics or relabeling the same root cause does not reset its count. A new issue requires evidence of a distinct root cause; retain the original history after resolution. User-authorized resumption must be recorded, not silently treated as a reset.
- A known token/authentication/environment blocker pauses the affected work immediately; do not spend nine failures discovering the same blocker. Classify code versus environment/auth evidence without guessing credentials are the cause.
- Declare an intentional initial TDD red before running it and record it separately from unexpected failures. It proves the missing behavior, not a passing check. Repeated unresolved failures cannot be relabeled as initial TDD reds to evade escalation; retries still need a concrete change or new evidence.
- Record elapsed time and token usage only when measured; otherwise write **unavailable**. Do not fabricate cost or reconstruct missing historical counts. Mark incomplete historical accounting explicitly and keep the issue history.
- Keep essential safety validation. Budget limits do not authorize unsafe execution or claiming unfinished/failed checks passed. Pause and report a blocked safety gate instead.
