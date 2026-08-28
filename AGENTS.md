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

## Drift prevention

- Do not implement application code only on Hostinger.
- Do not treat a GitHub-only change as deployed or accepted.
- Do not deploy uncommitted local changes.
- `.env` is never committed. It may differ only for environment-specific values such as `APP_URL`, database connection, mail delivery, and private provider credentials.
- If a production-only `.env` change is required, apply the equivalent non-secret configuration/documentation change locally so the next deployment does not silently overwrite the intended setup.

## UI source fidelity

For Google AI Studio ZIP work, preserve the supplied DOM structure, classes, assets, copy, responsiveness, and interaction behavior. Add Laravel/Alpine data bindings only; do not redesign or substitute components unless the user explicitly authorizes it.
