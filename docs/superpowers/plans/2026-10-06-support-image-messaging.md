# Support image messaging implementation plan

**Goal:** Deliver image/screenshot messaging in existing Storefront and unified Admin Inbox without AI media processing.
**Spec:** ../specs/2026-10-06-support-image-messaging-design.md
**Execution:** Inline in canonical local repository, authorized by user '开始吧'. Preserve unrelated dirty files; no commit/push/deploy until sync authorization. No extra worktree because canonical-local acceptance is required.

## Tasks
1. Attachment lifecycle: migration/model, private image validation/re-encoding, upload ownership and authorized serving. Test valid upload, spoofing, unauthorized attach/access and pending expiry.
2. Message boundary: optional caption, attachment binding, replay identity and serializers; suppress media-only AI. Test empty caption, replay and customer/Admin visibility.
3. Provider adapters: preserve inbound image events, async safe download, Meta media upload and image send; maintain readiness/ambiguous-send behavior. Test provider payloads, deduplication, download failures and SSRF protections.
4. UI: existing composers gain image selector/paste and explicit preview/remove; message images open accessible viewer. Test draft preservation, blocked channels and image input handling. Build and local browser desktop/mobile checks.
5. Review and acceptance: focused regression suite, inspect complete change, document actual provider/browser evidence and remaining live gates. Request synchronization only after local phase verification.

Each implementation task starts with declared intentional TDD red, then implementation and focused green. Record commands/results in docs/acceptance/2026-10-06-support-image-messaging.md before/after runs. Do not rerun unchanged failures. Local tests cannot claim live provider acceptance.
