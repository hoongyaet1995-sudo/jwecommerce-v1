# WhatsApp audio messaging

Scope approved: WhatsApp human audio reception, playback, upload and recording with preview before send. No AI transcription, interpretation or automatic audio reply. Other platforms and video/documents excluded.

## Existing flow and selected approach
Reuse private support attachments, conversation-scoped ownership, UUID binding, pending download jobs, message idempotency and delivery status. Add attachment kind and duration metadata through an additive migration. Keep the approved composer styling and add an audio upload and microphone control only for WhatsApp.

## Audio processing
WhatsApp-compatible audio is accepted up to 16 MB. Inspect actual audio container and codec, reject video or malformed content, strip metadata and convert browser WebM recordings to mono OGG/Opus. Recommended server tooling: configured FFmpeg and FFprobe executables, invoked with argument arrays without shell interpolation, random private working paths, process timeout and bounded output. Maximum recording duration: 5 minutes. An unavailable processing runtime disables recording and gives an actionable upload error; it must not pretend to deliver unsupported WebM.

Local and Hostinger executable discovery on 2026-10-06 found no FFmpeg/FFprobe in PATH. Provisioning and confirming a compatible runtime is a prerequisite for the recording path. Production changes remain subject to the approved local -> tests -> sync contract.

## Receive and send
Incoming WhatsApp audio IDs are recorded transactionally and downloaded asynchronously with existing HTTPS/DNS/private-address guards and 16 MB audio-specific size cap. Pending, ready and terminal failure are visible. No AI job is dispatched for audio. Outgoing audio uploads to Meta and sends an audio media ID without caption; accompanying text must be a separate message. Keep the 24-hour reply window and unknown-delivery duplicate protection.

## UI
Audio bubbles use accessible native playback controls, duration and authorized download. Composer offers upload or record, recording duration, stop/cancel, preview/remove and explicit send. Stop microphone tracks on cancel, conversation change and component unmount. Never continue a recording into a different customer conversation. Failed sends retain the audio draft.

## Verification
Backend: valid/invalid audio, oversize, non-WhatsApp rejection, owner isolation, caption rejection, provider payload, replay, ambiguous delivery, incoming receipt deduplication, private downloads and no AI calls. Frontend: recording lifecycle, permission denial, preview/removal, conversation switching and image/text regressions. Local browser: uploaded audio receive/playback and staff reply; recorded audio preview/send after real codec runtime is available. Real Meta acceptance follows deployment and does not rely on fake HTTP tests.
