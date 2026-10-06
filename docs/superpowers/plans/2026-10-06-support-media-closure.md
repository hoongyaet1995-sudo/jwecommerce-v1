# Human support media closure implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete PDF and attachment handling in the existing Inbox/Storefront, verify CRM linkage, and document provider approval gates.
**Architecture:** Reuse SupportAttachment storage, existing upload controller, inbound receipt deduplication, download job and outbound replay guards. Central backend channel capabilities are serialized to the UI; clients cannot expand provider capabilities.
**Tech Stack:** Laravel/PHP/MySQL, React/Blade, XHR progress, bounded PDF inspection process.
**Spec:** docs/superpowers/specs/2026-10-06-support-media-closure-design.md
**Execution:** Native in this chat recommended. The tasks share attachment descriptors and existing transport interfaces. No automatic commit, push or production deployment; synchronize only after phase acceptance and user approval.

## Global constraints
- Preserve current UI structure/classes except explicitly requested attachment controls/cards.
- PDF-only document support; maximum 10 MiB (10485760 bytes), one attachment per staged message.
- Storefront/Facebook/WhatsApp documents; Instagram documents unavailable pending verified support; TikTok approval blocked.
- WhatsApp audio remains 16 MiB/300 seconds. Existing image cap remains 5 MiB.
- No AI interpretation, transcription, document indexing, automatic learning or Blast Campaign.
- Preserve dirty CheckoutService/inventory changes; their unresolved testing gate is excluded.
- Keep docs/acceptance/2026-10-06-support-media-closure.md as the issue ledger; report 3/6 and stop affected issue at 9 unexpected failures. Log command/purpose/change/results; time/tokens unavailable unless measured.

## Review focus
- Same original filename across customers must never collide or expose another conversation's media.
- Cancel/switch during upload must not attach a late response to another conversation or permit a concurrent send.
- Expired/disconnected account cannot retry provider download or provider send; uncertain message delivery must never be blindly resent.
- Renamed HTML/executable, corrupt/encrypted PDF, malicious provider URL and oversized streams must be rejected without AI invocation.
- Contact contradictions must preserve buyer/order ownership; same platform sender ID in another account/merchant is a distinct identity.

## Task 1: Server PDF validation and channel capability contract
**Files:** Create app/Services/Support/SupportMediaCapabilities.php, app/Services/Support/SupportPdfProcessor.php, tests/Feature/SupportDocumentMessagingTest.php; modify SupportAttachmentService.php, SupportAttachmentController.php, SupportChatService.php, config/support_media.php, .env.example, routes/web.php.
**Interfaces:** SupportMediaCapabilities::forChannel(string $channel): array returns images/documents/audio flags and byte caps. SupportPdfProcessor::validate(string $bytes): string returns unchanged structurally valid PDF bytes or ValidationException. SupportAttachmentService::uploadDocument(SupportConversation $c, UploadedFile $file, ?int $staffId): SupportAttachment stores kind=document, application/pdf, safe original name and SHA256. Existing attachment descriptor adds no public storage path or provider reference.
- [ ] Declare intentional initial RED, add tests valid PDF upload201 and kind/document/name/size; 10MiB boundary; renamed HTML rejection; corrupt/encrypted rejection; Instagram/TikTok server denial; unauthenticated/cross-owner download denial.
- [ ] Run php vendor/phpunit/phpunit/phpunit --filter=SupportDocumentMessagingTest; missing document behavior must fail.
- [ ] Use maintained qpdf structural/encryption checks through argument-array Process, UUID private work directory, file-size cap, 10-second timeout per operation, bounded stdout/stderr and finally cleanup. Resolve local/production runtime through SUPPORT_QPDF_BINARY; no provider URL passed to parser. Reject encrypted documents. Fail closed when binary unavailable. PDF must download as attachment with nosniff/no-store, never inline/embed. Do not claim sanitization or malware scanning.
- [ ] Extend single-file validation for image/audio/document; existing server session/owner/staff guards apply to documents. Enforce capability map at controller and binding. Provider route allows Facebook document only; retain short signed lifetime and ensure PDF disposition.
- [ ] Run PDF tests and existing SupportChatApiTest. Record runtime/version and ledger result. Static inspection/check missing runtime is environment blocker, not blind retry.

## Task 2: Provider document receive/send and safe download retry
**Files:** Modify MessengerInboundService.php, WhatsAppInboundService.php, DownloadSupportImage.php, SupportAttachmentService.php, SupportImageTransport.php, routes/web.php; create attachment retry controller/service and focused tests in SupportDocumentMessagingTest.php.
**Interfaces:** Existing incoming(..., string $kind='image') accepts document with safe optional filename metadata. Transport emits WhatsApp {type:document,document:{id,filename,caption?}} or Facebook attachment.type=file with signed URL. Retry service retry(SupportAttachment $attachment): void resets only failed inbound media after rechecking scoped connected account, rate-limit and transaction lock; ready/pending retries produce no duplicate job.
- [ ] RED tests: WhatsApp document receipt creates one attachment on duplicate webhook; Facebook file receipt creates document; PDF caption allowed only according to channel rules; no document-only AI job; provider fake asserts exact media/document and file payload; spoofed host/redirect and cap rejected; unauthorized/disconnected/pending duplicate retry denied.
- [ ] Run scoped test before implementation; confirm failing assertions rather than schema/environment errors.
- [ ] Implement receipts and bounded private downloads using existing URL protections and 10MiB cap, preserve original reference on failed download for explicit retry. Validate PDF before status=ready. Unsupported file types yield visible unavailable status; no silent discard and no automatic AI parsing.
- [ ] Add POST admin/api/support/attachments/{attachment}/retry and owner-protected Storefront equivalent only where inbound retry is relevant. Throttle; no outbound retry endpoint that resends uncertain delivery. Update stream snippets to Document/Voice message/Image.
- [ ] Run document/image/WhatsApp/Messenger suites; inspect failure accounting and source diff. Runtime max duration remains below support retry_after120.

## Task 3: Shared upload progress and Admin attachment controls
**Files:** Create resources/admin/src/components/support/supportMediaUpload.ts and test; modify ChatWorkspacePanel.tsx, SupportMessageImages.tsx, resources/admin/src/types/support.ts, integration/supportChat.ts where type serialization requires it.
**Interfaces:** uploadSupportMedia(url:string,file:File,kind:'image'|'audio'|'document',onProgress:(percent:number)=>void,signal:AbortSignal): Promise<MessageAttachment>. Media descriptor existing type=document, sizeBytes/name/status/url. Progress reports transport bytes; 100 percent displays Processing until response201.
- [ ] RED tests: XHR computable upload progress; abort; errors preserve file; retry explicit; conversation switch scopes completion; no send while uploading; PDF card name/size/download; unsupported channel controls absent; failed inbound media Retry invokes only scoped endpoint.
- [ ] Run focused Vitest tests and declare initial missing behavior before implementing.
- [ ] Add PDF button using existing composer classes, input accept=application/pdf,.pdf; explicit download card and staged filename/size/remove. Keep failed File in conversation-keyed memory, Cancel and Retry upload; guard request generation and abort on unmount. Do not replace draft until new upload succeeds. Distinguish provider send failure from upload failure.
- [ ] Run existing recorder/workflow/image tests and npm run build --prefix resources/admin. Verify keyboard names/focus, pending/processing copy and existing confirmation flows.

## Task 4: Storefront PDF and progress closure
**Files:** Modify resources/views/components/storefront/support-chat.blade.php; test resources/admin/src/integration/storefrontSupport.test.ts and tests/Feature/SupportDocumentMessagingTest.php.
**Interfaces:** Same upload endpoint /support/chat/attachments with document field; same protected owner download. Reuse Task3 state/progress semantics in existing Blade script; do not substitute UI framework.
- [ ] RED tests guest owner PDF upload/download/send, another guest denied, filename escaped and URLs from server only, retry/cancel/processing state and no PDF parsing by AI.
- [ ] Extend existing composer/file-card markup using current classes; preserve screenshot paste/image upload and message send identifiers. Use explicit PDF input and single staged attachment with size/removal.
- [ ] Run Storefront and SupportChatApi focused tests; required asset build only if changed sources participate. Confirm local guest and Admin see same PDF receipt in MySQL/message descriptors.

## Task 5: CRM and approval-dependent readiness audit
**Files:** docs/acceptance/2026-10-06-social-crm-identity.md, docs/acceptance/2026-10-06-tiktok-preparation.md, new phase ledger; existing CustomerContactResolver.php only if an evidenced issue requires a correction.
**Interfaces:** Existing associate(conversation, scopedIdentity, profile) and CustomerDirectory platform icons/filter remain canonical.
- [ ] Run existing SocialCustomerIdentityInboundTest and CustomerDirectoryPlatformTest baseline once. Already verified 29 tests/131 assertions on baseline; rerun only after relevant changes or new diagnostic evidence.
- [ ] Read real authorized incoming receipt -> conversation.customer_id -> identity scope -> platform presence -> directory without printing customer secrets. Verify repeated message does not duplicate contact; trusted phone matches unique existing contact; conflicting values do not merge users/orders. Add tests only for demonstrated missing behavior.
- [ ] Inspect logged-in Meta and TikTok app permission/review status using browser; no submissions, credential changes or account messages unless needed and authorized. Record exact observed app/scope/status. If pending, prepare non-Tester Instagram and TikTok OAuth/webhook/text/media acceptance checklists; keep waiting label truthful.
- [ ] Existing inventory/checkout issue remains untouched; report provider blockers independently from CRM code results.

## Task 6: Local acceptance, release handoff and AI design readiness
**Files:** docs/acceptance/2026-10-06-support-media-closure.md and docs/superpowers/specs/2026-10-06-support-ai-capability-boundaries.md.
- [ ] Authenticated browser local Admin and guest: PDF attach/progress/cancel/failed retry/name-size/download; image zoom, audio preview/removal; 390px mobile and desktop light/dark. Use synthetic fixture PDF, no personal files. Save screenshots and record MySQL producer/service/consumer evidence.
- [ ] Whole-phase focused PHP/Vitest/build evidence, source diff review and independent reviewer gate under executing-plans. Counts persist; no blanket/full inventory suite.
- [ ] Summarize completed code versus remaining provider acceptance. Ask synchronization approval only when concrete local phase is accepted. After approval exact-SHA deploy/runtime/config and live HTTPS checks; real PDF bidirectional customer acceptance remains explicit.
- [ ] Present evidence-based AI capability gap and labelled evaluation set covering clarification/follow-ups/catalogue/order privacy/frustration/failure/attachment injection. Refine approved draft, but do not implement AI attachment reading or remove existing safety controls in this phase.

## Plan self-review
Spec coverage: attachment UX1-4; CRM5; provider gates5; AI boundary6; release6. Parser/runtime dependency belongs to PDF task; no new subsystem/schema needed because kind=document already represented. Every review-focus condition has a corresponding test or real gate. Provider endpoints/contracts must be checked against primary docs before coding transport; fake tests never establish real delivery. Elapsed total time/token usage unavailable.
