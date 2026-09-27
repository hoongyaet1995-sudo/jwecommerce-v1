# Publishing queue recovery — 2026-09-27

Production Task 33 (Shopee Sandbox store 1, JW product 56) remained `queued` after staging at 15:16:13 UTC. Its saved task had no external item ID and no provider request had been claimed. The frontend's progress animation overrode the actual queued status with `processing`, hiding the existing "Start saved publishing task" button and leaving a permanent spinner. The modal's initial best-effort dispatch had no durable server worker fallback.

The user authorized continuing the saved push. The existing production dispatch service executed Task 33 exactly once. It returned `succeeded`, Shopee item ID `846060134`, at 15:21:43 UTC. No task status was edited directly. An already succeeded mapped listing remains an existing listing; creating a duplicate listing is not a retry of an unsent task.

Local fix: the saved task status now takes precedence over animation. A queued task shows "Start saved publishing task" and "Cancel unsent task". Cancellation uses the same store-first lock order as dispatch, requires the task owner, and transitions only a still-queued task with no confirmed identity to `rejected` with `cancelled_before_dispatch`. A processing or completed task cannot be cancelled. After cancellation, normal staging may create a new attempt; the previous task remains auditable.

Validation: MarketplacePublishingRecoveryTest passes, including queued cancellation followed by a new task; PublishProductModal tests pass with queued state, visible actions and cancellation event; production frontend build completed. Current implementation is local and awaits GitHub/Hostinger synchronization approval.

An uncertain task without a confirmed external item ID is now advisory: the store status no longer blocks a fresh user-triggered submission. The previous task and relationship remain auditable, and the UI warns that a duplicate listing is possible. Queued, processing and confirmed-item tasks retain their actual saved status and actions. This user-requested policy allows a new attempt without an internal recovery authorization for the uncertain/no-ID case.
