# Automation creative intelligence implementation plan

> Execute inline using executing-plans. User approved implementation on 7 November after the interactive UI sample.

**Goal:** Connect the approved all-resource scope and four creative intelligence capabilities to manual draft generation.
**Architecture:** Build a bounded, timestamped intelligence snapshot before source selection, retain it with the generation, pass its constraints through to text/image planning, and disclose it on review. Keep existing durable provider states and draft-only outputs.
**Stack:** Laravel/PHP, Blade, existing vanilla JS and SQLite isolated tests.
**Spec:** Current approved sample `public/marketing-automation-intelligence-preview/` and this conversation.

## Constraints and review focus
- Canonical local checkout; preserve unrelated dirty changes. No commit/push/deploy until separate approval. No scheduling or publication.
- 90-day company-scoped memory including manual and Automation results; deduplicate platform variants and retain discarded status.
- Calendar context is sourced and date-bounded to the coming 14 days, never guessed movable dates. Unknown coverage falls back to evergreen.
- Trends are optional, public Google Trends RSS only, dated and cached; a trending query proves search interest, never factual news claims. No private merchant data sent, no new paid provider.
- All scope is dynamic on each run; bounded rotating candidates, no catalog preload. Selected scope backward compatible.
- Company isolation, stale/expired sources, simultaneous generations, prompt injection, empty/failed feeds and uncertain provider results require tests.

## Tasks
- [x] Tests first: source scope, calendar windows, memory isolation/status, angle rotation, trend off/stale/failure, immutable generation handoff and escaped review evidence.
- [x] Implement `MarketingCreativeIntelligence::context(merchant, settings)` and `MarketingTrendContext::read(market, enabled)`. Add source scope and ranked rotating candidates without arbitrary configuration caps.
- [x] Integrate settings validation/editor UI using approved markup. Store intelligence in parent/child snapshots and Post ai_context; feed selector/planner with factual constraints and disclose actual selection reasoning separately from system inputs.
- [x] Run focused PHP/JS, syntax, aggregate relevant checks; browser desktop/mobile and persistence; one bounded real single-image draft if environment allows, never retry unknown outcomes.
- [x] Review final changes and evidence; report passed/failed/not-run and calendar/feed coverage. Ask sync only after local phase completion.

## Baseline / rulings
- Branch codex/social-crm-identity at 7890952aa49a29f87d9d5cd61cf7ceaba32d1384. Existing large dirty workspace retained. Existing Automation files are untracked; do not mistake them for new work this phase.
- User explicitly authorized direct implementation; skill plan handoff confirmation is satisfied by that instruction. Canonical checkout requirement overrides isolated worktree recommendation. Existing durable issue ledger remains authoritative.
- Use free public Google Trends RSS rather than connecting a paid search API; availability is optional and failures are disclosed. Source: https://support.google.com/trends/answer/3076011?hl=en .
