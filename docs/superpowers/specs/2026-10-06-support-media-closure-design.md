# Support media closure design
Date: 2026-10-06. Baseline ecbda77. Status: written design for review; not an implementation-completion claim.

## Purpose and exclusions
Complete human multichannel attachments, verify CRM contact linkage, and prepare approval-dependent acceptance. Blast Campaign excluded by user. AI attachment reading/transcription/replies remain disabled until a separately approved AI boundary design.

## Current evidence
Images have private storage, validated re-encoding, signed Meta delivery URLs, native zoom/download. WhatsApp audio uses private Opus storage, bounded conversion, microphone preview/removal and explicit send. User reports live audio acceptance passed. CRM resolver creates scoped platform identities and platform history, matches trusted normalized contact values, and retains contradictory records rather than merging orders or users. Existing checkout inventory testing gate remains paused and excluded.

## Chosen architecture
Extend existing attachment service/controller/job/transport with document descriptors; preserve existing UI wrappers and Tailwind patterns. Central channel capability map feeds composer and server validation. No second upload/storage subsystem. Preserve image/audio behavior and existing ownership, merchant, duplicate-message and unknown-provider-send guards.

Alternative: separate document subsystem duplicates authorization, routing and replay protections. Defer arbitrary file formats until validated handling and provider contracts exist. First document scope PDF only, product cap 10 MiB independent of provider maximum. Facebook and WhatsApp transport plus Storefront upload/read path; Instagram document controls remain unavailable pending official capability evidence. TikTok remains blocked on actual App/API access.

## Attachment behavior
One staged attachment per message. Explicit image, PDF and WhatsApp audio controls. Show filename, formatted size, actual upload progress, cancel, failed upload retry and preview/removal. Upload progress 100 percent means request bytes transferred, not provider delivery; processing/ready are separate. Keep failed file in conversation-scoped local draft for explicit upload retry. Switching conversations cannot move a draft. Preserve explicit send and provider delivery states.

PDF is an authenticated download card, not HTML or embedded active-content rendering. Check extension, MIME and structural parsing, malformed/encrypted-file behavior, byte caps and unsafe source URLs; use a vetted parser and bounded processing. Private UUID path and safe filename; download disposition, nosniff, no-store. Documents are untrusted customer content and never instructions. Provider-download failure retry must re-check account and tenant, retain valid provider reference, rate-limit and avoid duplicate dispatch. Message sends with uncertain delivery cannot blindly retry.

WhatsApp document upload returns media ID and sends document id/filename/optional validated caption; Facebook uses file attachment and short-lived signed provider access. Server enforces channel capability even if client is modified. Incoming Facebook file/WhatsApp document receipts are deduplicated and queued; unsupported types get explicit descriptor/status without invoking AI. Unsupported contents do not disappear.

## CRM closure
Verify contact creation for repeated/new platform sender, trusted WhatsApp phone matching, Storefront login identity, cross-platform platform icons/filter, case-normalized email and international phone matching, conflicting email/phone and merchants/accounts scope. Names, usernames and avatar URLs never establish identity. Customer-written phone/email do not become trusted merely because AI extracted them. Existing order/customer/user ownership must remain unchanged. Verify provider-produced message -> conversation -> scoped identity -> CRM -> directory with real receipts when access allows.

## External approval gates
Instagram advanced-access and TikTok Business Messaging scope/OAuth/webhook access must be inspected live before testing non-role accounts. Developer approval alone is not messaging approval. Prepare checklist and request only missing real account actions; do not fabricate a ready state or bypass platform permissions.

## Acceptance
Backend validates caps/content/ownership, PDF transport payloads, receipt dedup, no AI jobs for documents, retry authorization and replay guards. Frontend verifies draft scope/progress/cancel/retry/document cards, existing image and audio regressions; Admin and Storefront builds. Local MySQL and authenticated browser light/dark/mobile evidence. Real Facebook/WhatsApp PDF receive/send acceptance is separate from fake-provider tests. Release only with new explicit sync approval and exact SHA.

## Sources
Meta-maintained document object: https://whatsapp.github.io/WhatsApp-Nodejs-SDK/api-reference/types/DocumentMediaObject/
Meta Messenger collection: https://www.postman.com/meta/messenger-platform-api/documentation/iyp204x/messenger-platform-api
Provider format/size contracts require current confirmation at implementation; our PDF-only 10 MiB limit is a product decision.
