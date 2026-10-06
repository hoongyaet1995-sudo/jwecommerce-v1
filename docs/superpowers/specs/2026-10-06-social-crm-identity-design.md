# Unified social CRM contacts

Status: design ready for review; no application changes or deployment.

## Approved intent

Incoming Storefront, Facebook, Instagram, TikTok and WhatsApp conversations create or associate a CRM Customer. The customer directory shows accumulated platform icons and supports platform/tag segmentation. Preserve the existing directory layout except the requested Platform column and matching filter controls.

## Baseline and existing constraints

Development root: C:/xampp/htdocs/jwstudio-ecommerce. HEAD: 91aaa16699c98cf61e9fcd9bacb3f3a74478248b. Preserve existing unrelated dirty files. CustomerDirectoryDataController requires a unique email; the frontend DTO requires a string email. SupportConversation currently associates customer_user_id, a login User, rather than a CRM Customer. WhatsAppInboundService deduplicates message receipts and creates conversations inside an account-locked transaction. Its profile contacts are not currently consumed.

## Storage and identity

Allow nullable customer email throughout database, validation, serialization and UI; never manufacture placeholder addresses. Add nullable support_conversations.customer_id referencing customers, separately from customer_user_id. Associating a CRM contact never grants login privileges or access to orders through the storefront.

Add customer_channel_identities with customer_id, platform, merchant_key, app_id, account_id, external_sender_id, identity_hash, display_name, username, avatar_url, first_seen_at and last_seen_at. A unique hash covers the platform, merchant, app, business account and sender identity. Different Pages/accounts must not collide. Add customer_platform_presences with unique customer/platform and first/last interaction times. Use foreign keys and indexed customer relationships.

Contact email and phone comparisons require normalization and provenance. Trim and case-normalize email without provider-specific plus/dot rewriting. Normalize phones with explicit country context; ambiguous local numbers cannot match automatically. Retain provider sender identifiers separately from display numbers. A future WhatsApp opaque sender identifier must never be interpreted as a telephone.

## Ingestion and matching

After validating the provider event and before recording a new customer message, resolve the existing platform identity. If absent, match a trusted normalized email or phone to exactly one Customer. If email and telephone point to different customers, or there are duplicate candidates, create an independent provisional contact and record a conflict for review. Never match by name, username, avatar or inferred message text.

When no trustworthy match exists, create a Customer with available display name and nullable contact fields. Record identity, platform presence and conversation association transactionally. Unique identity constraints and account locking prevent concurrent duplicates; repeated webhook messages must not create another contact or interaction. Do not transfer orders, login users or addresses or delete existing customers as part of automatic linking. Later reliable identity enrichment may link a provisional identity to an existing contact; merging two established customers requires explicit conflict resolution.

An authenticated Storefront conversation uses its existing User/Customer association. Anonymous Storefront conversations use the existing guest conversation identity until reliable identification is supplied, then associate the contact without changing authentication. Guests with no email/phone remain visible as provisional contacts.

## Profile enrichment

WhatsApp: match contacts[].wa_id to the message sender before reading profile.name. Capture telephone only when the received identifier is a supported telephone representation. Never use the first unrelated contact in a webhook batch.

Facebook/Instagram: queue permitted user-profile lookups after message persistence, cache results and retry transient errors. Store only returned name/username/avatar; email and telephone remain unavailable unless explicitly and reliably provided. Enrichment errors must not reject messages, disable replies or overwrite existing verified values.

TikTok: provide the shared identity adapter contract, but do not claim real ingestion or customer fields until Business Messaging permissions and actual payloads are available. Keep current approval status.

## Directory and support UI

Expose platforms as an array and add Platform between Lifetime Spend and Tags, using existing channel icons and accessible platform labels/tooltips. A contact can show several icons. Add platform filtering server-side before pagination and retain tags. Do not label every existing CRM record Storefront solely because it exists; use actual storefront user/order/conversation evidence to backfill.

Support displays linked contact name, available email/telephone and CRM association. CRM visibility does not broaden storefront authorization. Store platform identity provenance and first/last interaction for future campaign segmentation. Marketing permission remains separate from platform presence; this phase sends no campaigns.

## Backfill and failure behavior

An idempotent Artisan backfill command processes existing conversations in chunks through the same identity resolver; default dry-run reports create/link/conflict counts. Apply locally only after tests and inspection. Do not call provider profile APIs inside a long database transaction. Operational failures must be surfaced and recorded, not reported as successful CRM association.

## Acceptance

Verify: nullable-email contacts; same sender repeated/concurrent events; scoped sender collisions; reliable phone/email association; ambiguous/conflicting values; unrelated webhook contact names; profile permission failure preserving ingestion; existing authenticated/anonymous Storefront linking; existing conversation backfill idempotency; no user/order reassignment; directory platform arrays and filtering before pagination; icons and empty contact fields in dark/light local browser views. Run focused PHP/frontend tests and production build. Record commands/results in the phase ledger and follow repository failure escalation rules.

Synchronization remains a separate user-approved release after local acceptance. No new provider messaging permission, TikTok live acceptance or marketing campaign is implied by this phase.
