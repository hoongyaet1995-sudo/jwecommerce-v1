# Automatic publishing design

Approved behavior: scheduled time starts generation; completed artwork and copy enter the existing publishing queue immediately. Automatic Publish Status defaults off. Manual Generate Post remains draft-only.

Reuse MarketingPostQueue rather than introduce a second publisher. Require explicit connected destination accounts and existing platform publishing options/consent. A generation must finish successfully before any publishing handoff. Failure preserves drafts and exposes an actionable status. Repeated cron ticks must not enqueue a second submission for the same occurrence/post.

Only local implementation and mock publishing validation are authorized for this phase. No actual social submission, deployment, or enablement of existing automations.
