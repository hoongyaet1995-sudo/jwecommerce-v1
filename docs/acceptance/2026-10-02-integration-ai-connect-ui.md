# Integration and AI Connect UI

Baseline: main at 6c569270fd9fe7ac3f4da56d4278c4899ab3840c. Existing inventory and support documentation changes preserved.

Scope: authenticated Admin Integration entry, one AI Connect card, provider/key/model/feature form and connection summary. Existing light/dark shell retained. Reference: user-supplied CRM screenshots. No provider requests, persistence, AI activation, GitHub push or deployment.

Preview secrets remain in component memory only; changing provider or saving preview clears them. Leaving the page resets all preferences. Connection remains Not connected.

## Verification ledger

- Initial inspection: incorrect types.ts path; actual types/index.ts identified. Distinct navigation lookup issue, one occurrence, resolved. No test failures so far.
- Planned: focused route tests, production frontend build, PHP syntax/routes, authenticated localhost browser desktop light/dark and mobile, form preview and navigation.
- Time/token accounting: unavailable.

- 2026-10-02T22:31:12+08:00: route tests passed 2/2. Build started. Pre-run ledger append used wrong relative directory (one occurrence, resolved using workspace-root path); accounting recorded here retrospectively.

- Build passed (2991 modules; existing large-chunk warning). PHP routes/web.php syntax passed; both protected GET routes registered.
- Authenticated localhost browser: Integration card -> AI Connect, Back to Integrations, refreshed deep link all passed. Sidebar Integration highlighted on both pages.
- Desktop dark and light screenshots reviewed. Mobile 390x844: stacked form and summary, document scrollWidth/clientWidth both 390, no horizontal overflow. Viewport override reset; dark theme restored.
- Preview save clears sample key and remasks field; provider switch clears sample key/models; enable preference updates while configuration remains Not connected. Leaving/refresh resets defaults. No AI connection or persistence implemented.
- Evidence: output/playwright/integration-overview-dark.png, ai-connect-dark.png, ai-connect-light.png, ai-connect-mobile.png.
- Current verification completed; no unexpected test/build failures. GitHub/Hostinger not synchronized for this UI phase.
