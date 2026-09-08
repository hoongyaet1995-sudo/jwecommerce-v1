# ZIP 29 source reconciliation — integration paused

Archive SHA256: 8730B48C0CD6CA3BF74A3FD04E222C2749502B98306822B93C48B1DCE4E81BDD.

Scope: Marketplace components, common navigation/toasts and their required types/state/fixtures only. Raw extraction is private in the local Temp directory. No official route, backend, public build, credentials or production data changed.

## Confirmed in source

- MarketplaceLogo.tsx is present in components/marketplace and used by Marketplace surfaces. Brand authenticity was not independently verified.
- ProductMappingModal.tsx contains same-store duplicate selection checks and a conflict banner (around lines 54–70 and 573–587).
- AI Suggestions UI and fixture recommendations remain, as recorded in the AI Engine direction note.
- AdminContext contains disconnectConnection and reconnectConnection methods.

## Material mismatch with supplied completion summary

- No ConnectStoreModal component exists in src.
- MarketplaceAuthPage.tsx still renders developer configuration, including "Configure App Key, App Secret, and Open API Permissions" (line 576), "Open API App Key / Client ID" (703), "App Secret Key (AES-256)" (716), scopes (731), and "Save & Authorize API Connection" (778).
- Existing lifecycle context methods alone do not prove that the new merchant connection UI was exported or wired.
- Mapping uses local array slicing for pagination. The summary's server-pagination claim does not establish a backend contract.
- Source MarketplaceHub consumes triggerChannelSyncAll/isSyncingMarketplace, which are not supplied by the source AdminContext. This is an upstream source defect, not a real synchronization capability.

## Extraction checkpoint

A narrow preview extraction was attempted. Vite bundled it, but TypeScript found an omitted Toast dependency in the extractor plus the upstream missing context properties. A successful bundle is not accepted browser evidence. No desktop/mobile fidelity pass is claimed.

To keep unfinished extraction out of application typechecking, its files were moved recoverably to tmp/marketplace29-preview-wip. Preview HTML/config and extraction helper are local work-in-progress only. No running preview is offered as accepted output.

## Required next input

Export a corrected ZIP from the Admin Google AI Studio project containing the actual merchant Connect Store experience, remove developer fields from that surface, and repair Hub context bindings. Preserve the supplied Logo, AI Suggestions and same-store conflict UI. Source previews may simulate OAuth only when explicitly labelled; production OAuth remains a separate backend integration gate.

Status: paused by source-fidelity gate; no official cutover or deployment.
