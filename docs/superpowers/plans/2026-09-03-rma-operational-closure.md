# RMA Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect the approved customer and employee RMA regions through Laravel and MySQL without importing mock behavior.

**Architecture:** A focused `ReturnRequestService` owns eligibility and transactional state changes. Three tables hold the request, private evidence metadata, and append-only events; the existing account payload and Admin order DTO adapt that truth to the approved UI shapes.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade/Alpine, React/TypeScript.

**Spec:** `docs/google-ai-studio/manifests/2026-09-03-rma-source-extraction.md`

## Global Constraints

- Work only in `C:\xampp\htdocs\jwstudio-ecommerce`.
- Preserve selected ZIP DOM/classes; exclude all identified mock/developer behavior.
- Customer requests only; delivered item within 30 days; maximum five JPG/PNG/WebP files of 5 MB each.
- No GitHub push or Hostinger deployment in this phase.

### Task 1: Persistence and customer submission

- [ ] Write failing feature tests for ownership, eligibility, validation, evidence and refresh persistence.
- [ ] Add migrations/models/relationships and transactional `ReturnRequestService`.
- [ ] Add owner-scoped routes/controller and account payload mapping.
- [ ] Run the focused PHP tests until green.

### Task 2: Employee lifecycle API

- [ ] Write failing API tests for list DTO, approve/reject/receive/refund/replacement transitions and authorization.
- [ ] Add protected API controller actions and state transition validation.
- [ ] Reuse the existing refund ledger; persist a same-SKU replacement order without dispatch claims.
- [ ] Run focused and related order tests until green.

### Task 3: Source-faithful Storefront adapter

- [ ] Add the single item-level entry, selected modal states and customer lifecycle card to Purchase History.
- [ ] Bind multipart submission and stable Laravel validation errors; retain five-file UX.
- [ ] Run Blade/account tests and browser-check desktop plus 390px.

### Task 4: Source-faithful Admin adapter

- [ ] Extend the typed Order contract and integration mutations.
- [ ] Add Returns filter, row chip and drawer workbench; retain only the header Reload Stream.
- [ ] Add component/integration tests and run lint/build.
- [ ] Browser-check filtering and each available state action.

### Task 5: Closure verification

- [ ] Run targeted PHP/React tests, full builds and relevant regression suites.
- [ ] Prove customer submit -> Admin visibility -> employee transition -> customer refresh persistence.
- [ ] Update the mapping rows with evidence and report local-only completion.
