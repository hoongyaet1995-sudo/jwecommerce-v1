# Support image messaging — phase 1

## Approved purpose
Extend the existing text support flow with images and pasted screenshots. Customers and staff can send, inspect and download media in the unified Inbox. AI must not inspect attachments, perform OCR, transcribe media, or invent replies to attachment-only messages. Preserve the approved UI except for additive attachment controls and image presentation.

## Scope
- Storefront: customer and staff upload JPEG/PNG/WebP, paste screenshots and preview/remove before explicit Send.
- Facebook, Instagram (existing Facebook Login), WhatsApp: receive and send images through the existing Social Hub routing, subject to existing account readiness and reply-window checks.
- TikTok stays unavailable pending provider approval; no mock-success messaging.
- Documents, audio recording/playback, video and AI media processing belong to subsequent phases.
- Local development and acceptance first. Commit/push/deploy require a separate synchronization instruction.

## Current code findings
Baseline HEAD: c224f2a20d543c172ab6d8a60d4bc396d4b58fb7. Preserve unrelated dirty work.
`SupportChatService::append` stores text; its serializer returns `type: text`. `MessengerReplyService` builds `message.text` payloads, retaining ambiguous-send protection. `supportChat.ts` posts JSON text and preserves draft/retry identity. Storefront and Admin have separate controllers. Existing ingress services must be extended rather than replaced.

## Architecture and alternatives
Recommended: dedicated SupportAttachment records, private storage and channel adapters. Inline public URLs would be simpler but cannot provide durable access control or expiry handling. Storing file bytes in MySQL would inflate message queries and backups; retain only metadata in MySQL.

Each attachment records UUID, conversation/message ownership, merchant scope, uploader identity, private disk/path, original filename, actual MIME, byte count, dimensions, SHA-256, provider media reference and lifecycle status. Pending uploads expire and are cleaned up. Message attachment IDs are immutable once reserved for delivery.

Uploads are CSRF-protected, rate-limited and authorized using existing customer session ownership or staff authorization. Start with one image per message, maximum 5 MiB, JPEG/PNG/WebP only, verified by decoded image data. Reject SVG and executable/polyglot payloads. Re-encode accepted images to remove extraneous data; bound pixel dimensions to prevent decompression abuse. An empty caption is allowed when an attachment exists.

Serving customer/staff images requires conversation authorization, including logged-out guest ownership. Provider retrieval uses short-lived, narrowly scoped signed URLs where required; no permanent public paths. WhatsApp should use its authenticated upload/media ID flow. Provider URLs are untrusted: download asynchronously using bounded requests, HTTPS and validated destinations, no redirects into private networks, no credentials in logs. Raw token-bearing provider URLs must not reach the browser.

Incoming media metadata must be committed durably before acknowledging webhook work; downloading can then retry independently. Repeated events/media IDs are deduplicated. A failed download remains a visible unavailable attachment, never silently drops the message. Verify that Hub preserves attachment metadata; make any Hub change in its canonical local repository only after identifying that repository.

Sending reuses existing reply-window/readiness and durable reservation rules. Include attachment identity in replay checks; identical empty captions on different images must not collide. Timeout/ambiguous acceptance stays unknown and is not automatically resent. Provider rejection preserves the preview and offers a safe retry with the original identity. Avoid treating a caption and attachment as atomic when a provider requires separate calls: phase 1 must either send one supported image payload with caption or explicitly represent distinct sends.

## AI boundary
Media-only messages do not enqueue AI generation. Caption text may follow existing AI policy, but attachment bytes, URLs and metadata do not enter AI prompts. History formatting must omit attachment contents and must not treat placeholder labels as customer questions. Existing human handling, master/channel switches and escalation take precedence unchanged.

## UI contract
Add a paperclip/image entry beside the existing composer, with hidden file input, clipboard image handler, preview, remove control and upload progress. Show file validation errors near the composer. Preserve drafts when sending fails or selection changes; revoke local object URLs when removed/unmounted. Disable image sending when the platform is unavailable, with the actual reason. Render image bubbles with accessible alt text and a modal/lightbox, download action and loading/error state. Both light/dark and mobile must remain usable. This phase contains no microphone or generic file menu implying unsupported functionality.

## Code boundaries
- New attachment model/migration, attachment lifecycle service, inbound download job and authorized media controllers/routes.
- Modify Storefront/Admin SupportChatController, SupportChatService and SupportMessage relations/serialization.
- Extend MessengerInboundService, WhatsAppInboundService, MessengerReplyService and WhatsAppReplyService.
- Update Admin support types, supportChat integration and ChatWorkspacePanel.
- Update resources/views/components/storefront/support-chat.blade.php and its actual existing script owner after tracing it.
- Add focused backend authorization/media/idempotency tests and frontend paste/preview/draft tests.

## Acceptance gates
1. Storefront image and pasted screenshot persist through refresh and appear in Admin; staff replies appear in the same customer session.
2. Cross-session/cross-conversation attachment access and attaching another conversation's upload are denied; MIME/size/pixel invalid input rejected; abandoned uploads cleaned up.
3. Duplicate upload/send/webhook does not duplicate messages; timeout cannot cause automatic resend; different images with identical captions remain distinct.
4. Media-only input produces no AI reply and sends no media to AI.
5. Local automated tests/build plus desktop/mobile light/dark browser evidence.
6. Facebook, Instagram and WhatsApp each require real image receive/reply/open evidence after an approved release. Fake HTTP tests prove adapter behavior only. Instagram broader access remains subject to review; TikTok is blocked externally.

## Status
Design prepared for review. Product implementation has not started. No new test runs or failure counts yet; elapsed time and token accounting unavailable. Existing historical unrelated issue counts are not reset.
