# WhatsApp Audio Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Human WhatsApp audio receive/playback and upload/record-preview-send without AI processing.
**Architecture:** Extend existing private support attachments and transport, with a separate audio processor and recording component. Preserve existing image/text flows and conversation-scoped ownership.
**Tech Stack:** Laravel, MySQL, React, browser MediaRecorder, configured FFmpeg/FFprobe.
**Spec:** docs/superpowers/specs/2026-10-06-whatsapp-audio-design.md

## Global constraints
- WhatsApp only. No AI transcription or interpretation.
- Audio up to 16 MB; recording up to 5 minutes; no video streams.
- Local implementation and browser acceptance before explicit synchronization approval.
- Preserve unrelated dirty worktree and existing visual patterns.

## Review focus
- Recording cancellation, permission denial and conversation change release all microphone tracks.
- Uncertain provider send cannot be duplicated with a new message UUID.
- Cross-conversation audio IDs never bind or reveal private media.
- Malformed containers, video tracks and unsupported codecs never reach Meta.
- Missing codec runtime is reported honestly, with no fake successful recording.

## Task 1: Audio processing and private attachment contract
- [x] Add kind/duration migration, model casts and audio processor configuration.
- [x] Provision local FFmpeg/FFprobe outside source control from a verified distribution; record source/version. Confirm production compatibility separately before release.
- [x] Add intentional red tests for codec/container validation, 16 MB cap, duration cap and private normalization.
- [x] Implement bounded subprocess inspection/transcoding with argument arrays, random private files, timeout and cleanup.
- [x] Extend existing upload controller for WhatsApp staff audio and reject other channels; use existing session/ownership binding.
- [x] Run focused processor/attachment tests and record issue ledger.

## Task 2: WhatsApp receipt/download/send
- [x] Red tests for incoming audio deduplication, no AI dispatch, authenticated media lookup, audio upload/send payload and caption rejection.
- [x] Persist audio receipt and encrypted media reference, queue download with audio-specific cap and processor.
- [x] Return attachment kind/duration, private media URL and pending/failure state.
- [x] Send audio ID without caption, preserving reply window and existing replay/unknown-send guards.
- [x] Run WhatsApp plus existing image/text regression tests.

## Task 3: Inbox audio controls
- [x] Add accessible audio playback/download and WhatsApp-only upload/record controls using existing composer classes.
- [x] Recording component: explicit microphone request, elapsed time, stop/cancel, preview, remove and send. No auto-send.
- [x] Clean up tracks and discard recording on conversation change/unmount; capture upload conversation ID.
- [x] Preserve failed draft and prevent caption/audio combination.
- [x] Test recorder lifecycle, permission denial, wrong-conversation prevention and preview/removal; build Admin assets.

## Task 4: Local acceptance and release handoff
- [ ] Local browser uploaded and recorded audio preview/send/playback, light/dark/mobile and image/text regression.
- [x] Record actual codec runtime, tests, screenshots and unresolved provider evidence in docs/acceptance/2026-10-06-whatsapp-audio.md.
- [ ] Report concrete local result and request synchronization approval under project delivery contract.
- [ ] After approval deploy exact SHA and runtime prerequisites, then real WhatsApp audio acceptance; simulated provider tests are not real delivery evidence.

Execution recommendation: inline in this chat, sequential tasks, preserving canonical workspace. No extra threads required. Elapsed time and token usage unavailable until measured.

Local recording/preview/removal and native playback verified. Real provider send, responsive acceptance and exact-SHA production release remain pending; see durable acceptance ledger.
