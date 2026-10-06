# WhatsApp audio local phase
Baseline: d2802ec. Canonical cwd jwstudio-ecommerce; unrelated dirty files preserved. Approved spec and implementation plan; execute inline. No sync approval for audio phase yet. Elapsed/time tokens unavailable.
Ruling: reuse image attachment job and transport names internally to minimize compatibility risk, but descriptors and errors become media-aware.
Initial TDD red declared: WhatsApp audio ingress must create an audio attachment and never dispatch AI. Command php vendor/phpunit/phpunit/phpunit --filter=test_audio_inbound. Missing behavior is intentional red.

Partial script issue count 1: default Python cp1252 interrupted edits before audio ingress implementation. Explicit UTF8 fixed; retained partial schema edits. Missing attachment rerun count 1 unexpected due interrupted patch.

Ingress red -> green PASS 2 tests / 12 assertions. Codec runtime verified SHA256 60f467265b1e312373dbcd92200c2618a74850f98d3d078e94296bb3fa2047ba; FFmpeg 9.0.2. Upload validation issue count 1: prohibited_with unsupported by Laravel; replaced with explicit one-attachment guard. Rerun focused WhatsApp suite with real synthetic WebM fixture.

Provider fixture issue count 1: fake audio response omitted required messaging_product/recipient, correctly classified unknown; fixed fixture shape. Binary test fixture is synthetic 1-second tone under tests/Fixtures.

Schema setup issue count 1: duplicate migration line from partially completed UTF8 script; removed duplicate. Review fix: restrict probe and decoder demuxers to standalone media formats (no HLS/concat), and block recorder restart until final stop callback; stale callback cannot clean up new recording. Next verification includes regression and malicious manifest rejection.

Local fixture issue count 1: MySQL requires tasks value unlike SQLite fixture default; added empty tasks. Synthetic account uses nonfunctional token and reserved fake IDs; no provider send attempted.

Fixture field issue count 1: customer_name not a conversation column; removed guessed field, use visible provider_page_name fixture label.

Review security/race fixes implemented and recorder regression PASS 9 tests. Deterministic Opus bitexact encoding PASS stable fingerprint test. Audio serving uses authorized BinaryFileResponse for native Range/seek support. Download timeout accommodates bounded lookup/download/probe/conversion. Browser synthetic audio decoded duration 0.9935, readyState 4, play changed paused=false. No real provider delivery asserted.

Final evidence: backend 129 tests / 837 assertions PASS; frontend 17 tests PASS; Admin build PASS (existing chunk-size warning only). Local MySQL audio migration applied. Browser native player loaded and played synthetic audio. Screenshot tmp/whatsapp-audio-light.png. Final reviewer confirmed both security and recorder-race fixes, no additional significant finding. Job timeout revised to 100 seconds, below existing support retry_after=120; combined network/probe/convert timeout budget <=90 seconds.
Microphone permission: user explicitly approved approximately 3 seconds at localhost; Chrome is waiting on native permission prompt. Raw CDP does not support granting microphone permission, so affected test paused pending user click. Added cancel-wait control. No WhatsApp customer send performed. File chooser permission blocker from image phase remains; no blind retries.
Release prerequisites: Linux FFmpeg/FFprobe binaries compatible with Hostinger, configure SUPPORT_FFMPEG_BINARY and SUPPORT_FFPROBE_BINARY, confirm libopus, PHP upload/post size >=16MB plus multipart overhead, private storage writable; no binaries or private env committed. Old unsupported audio placeholder messages do not contain recoverable media IDs.

Fixture UUID issue count 1: recording upload reached route but synthetic non-UUID conversation ID violated existing route constraint; changed fixture to canonical UUID. No recording stored from failed request. Next browser attempt targets UUID fixture; same product route unchanged.

Fixture unique-owner issue count 1: new UUID reused old owner hash, unique constraint correctly rejected; new synthetic fixture uses a distinct owner hash. Product validation unaffected.
Chrome MediaRecorder WebM missing input duration issue count 1: real browser recording rejected despite a valid audio track. Fix permits absent input duration, measures normalized OGG duration, and rejects over 300 seconds after bounded 301-second conversion. Verification follows.
Browser microphone regression PASS: actual Chrome WebM converted to OGG, preview duration 22.746 seconds, readyState=4, Remove audio removed draft, no customer send. Browser tool round trips exceeded requested approximate 3 seconds; local test recording deleted after preview. Synthetic fixture conversations resolved, fixture account disconnected, temporary routing env restored to false. Screenshot tmp/whatsapp-audio-recording-preview.png. Backend regression 129/837 PASS plus streaming regression 6/20 PASS; output-duration probe bounded to 5 seconds, aggregate job budget <=95 seconds under 100-second timeout. Real Meta delivery and production runtime remain release gates.
2026-10-06 user approved synchronization. Preflight local/GitHub/Hostinger SHA d2802ec aligned. Full backend final PASS 130 tests/842 assertions. Server Linux x86_64 runtime provisioned outside application Git; production PHP CLI upload/post 1536M. Runtime verification and real HTTPS follow deployment. Server lacked xz unpack utility; archive extracted locally and exact binaries transferred via SSH, no application code modified remotely.
