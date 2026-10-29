# Post Campaign closure implementation plan

Scope: reuse the existing Posts review/delivery engine from Campaign. No new channel sending, provider requests, commits or deployment.

- [x] Add Save draft to Review, independent of schedule readiness. Preserve confirmation when removing an existing schedule.
- [x] Add one state-aware Campaign action for its single selected Post: review draft, manage schedule, follow progress/results. Carry a validated campaign return context through the shared Posts UI.
- [x] Preserve existing server-side version checks, delivery locks, published outcomes and unknown-result protection; verify focused fake-provider tests.
- [x] Correct account platform label and clarify draft versus delivery copy.
- [x] Verify localhost through normal pages; record remaining gaps without claiming real publishing acceptance.

Files: public/marketing-studio/posts.js, campaigns.js; existing MarketingCampaignTest and MarketingPostsTest; focused frontend regression test; acceptance receipt.

Verification: JavaScript syntax and frontend regression; Laravel campaign/posts/queue tests; localhost review draft and Campaign navigation. Preserve unrelated dirty files and all original content.
