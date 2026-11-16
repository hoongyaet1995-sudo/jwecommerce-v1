# Privacy session prompt — local acceptance

Local only. No commit, GitHub push, deployment, production changes or `.env` edits.

## Behavior

- Retained the existing marketing script, consent endpoints, attribution gates, stored consent boolean and original consent expiry.
- Server bootstrap now claims `marketing.privacy_prompt_shown` once per Laravel visitor session when analytics is enabled and consent is undecided. The claim never grants consent.
- The client no longer creates a permanent Footer privacy entry. It shows the automatic banner only when the server sends `showPrompt=true`.
- Allow analytics saves explicit consent before sending optional arrival/page requests. Decline saves false and retains the existing context-clearing behavior.
- Not now and closing the settings dialog dismiss the prompt without saving true or false. Subsequent pages and refreshes in that session do not show it again. Navigation removes the old prompt before browser back-cache restoration.
- Existing true/false choices remain honored. Expired consent clears the old prompt marker and permits a fresh choice. Disabled analytics does not display or consume the automatic prompt.
- Failed consent saves remain retryable; they do not start optional tracking.

## Evidence

- 24 PHP tests / 209 assertions passed: new session tests, privacy withdrawal/expiry, attribution boundaries, visitor journeys and purchase/quotation regressions.
- 9 JavaScript interaction tests passed, including dismissal, acceptance ordering, refusal, prior choices, failed save, settings close and browser pagehide handling. JS syntax and scoped diff checks passed.
- Actual browser sessions used temporary ports8516/8517/8518 with unique session-cookie names and process-only analytics configuration. Existing login/consent cookies were not cleared.
- First visit: accepted=null, showPrompt=true, banner shown, no permanent footer entry.
- Temporary close: banner removed; shop navigation and refresh returned accepted=null/showPrompt=false.
- Decline: banner removed; home navigation and refresh returned accepted=false/showPrompt=false. Test campaign had zero arrival/page events.
- Accept: banner removed; home navigation and refresh returned accepted=true/showPrompt=false. Before acceptance, test campaign had zero arrival/page events; after acceptance it recorded one arrival and two page views across the two pages.
- Existing coarse marketing-link request counters retain their previous behavior. This phase changes consent-gated visitor tracking and prompt presentation, not those request counters.
- Temporary QA campaign, two links and five test events were removed with exact fixture-ID/name guards after confirming no order/enquiry attribution existed. Three temporary servers and their verified PHP workers were stopped. Existing session files and business records were not deleted.

Evidence folder: `output/jw-cms-integration-20261114/privacy-session-20261116/`, including source snapshots, phase delta, event receipts and fixture cleanup receipt.

## Release boundary

`public/marketing-studio/tracking.js` already contained unrelated unfinished privacy-UI work before this phase. The phase's `before/` snapshot and delta must be used to isolate the approved session changes for any later release; do not copy the entire dirty worktree. Existing privacy styles were reused locally without redesign. No Vite application runtime changed in this phase; the tracking asset is served directly with its existing filemtime cache-busting URL.
