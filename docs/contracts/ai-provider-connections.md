# AI Connect contract

AI Connect is a store-wide integration managed only by `is_super_admin` owners. Keys are separate per provider, encrypted using Laravel's application key in `ai_connections.api_key`, hidden from model serialization and never returned to Admin. Preserve the application encryption key and encrypted database backups together using the existing private backup process; changing APP_KEY without a key-rotation process makes existing credentials unreadable.

## Connect from localhost

Open `/admin/integration/ai-connect` as the store owner:

1. Choose OpenAI, Google Gemini, Anthropic or OpenAI-compatible. Paste the key and save with Enable AI Connect off. Leave the key field blank on later saves to retain the existing key.
2. Click Verify & Load Models. This reads the provider's model catalog and does not send a generation prompt. A successful model list confirms key access, not generation quota or model compatibility.
3. Select a returned text model and optionally an image model.
4. Select the intended features, enable the connection and save. Only one provider is enabled at a time. Other providers retain their encrypted credentials and preferences.

Changing the key or compatible endpoint clears verification and the model catalog. Failed verification disables the connection. A stale version receives HTTP 409; reload before saving again.

## Protocols

| Provider | Model discovery | Text generation |
| --- | --- | --- |
| OpenAI | `GET https://api.openai.com/v1/models`, Bearer authentication | `POST /v1/responses`, `store: false` |
| Gemini | `GET https://generativelanguage.googleapis.com/v1beta/models`, x-goog-api-key header, pageToken pagination | `POST /v1beta/models/{id}:generateContent` |
| Anthropic | `GET https://api.anthropic.com/v1/models`, x-api-key and anthropic-version headers, after_id pagination | `POST /v1/messages` |
| OpenAI-compatible | Allowlisted base URL + `/models`, Bearer authentication | Base URL + `/chat/completions` |

Compatible endpoints must be configured explicitly via `AI_COMPATIBLE_BASE_URLS` (comma-separated HTTPS base URLs, including the API version path). No arbitrary URL is accepted from the browser. Userinfo, query strings, fragments and custom ports are rejected. DNS addresses must be public IPv4; private/special ranges are rejected and cURL pins the resolved address. Redirects are disabled. A proxy whose authentication or response format differs from these protocols needs a separate adapter.

## Producer, persistence and future consumers

Owner Admin -> authenticated/CSRF-protected `/admin/api/integration/ai` -> `AiConnectionService` -> MySQL -> explicit safe DTO and refreshed Admin. Save/check use separate rate buckets per owner/provider. A singleton settings-row lock serializes writes and active-provider selection; monotonic per-provider versions reject stale edits and verification results after credential replacement.

`AiConnectionService::generateText('support'|'builder', $prompt, $maxTokens)` is the internal consumer boundary. It requires an enabled provider, that feature's permission flag, verified credentials and a selected text model. The method limits input size and output tokens and never trusts a browser-selected arbitrary provider/model for generation. There is no public generation endpoint.

Support FAQ automation, escalation/human takeover policy, knowledge base, CMS generation actions, image generation, streaming, usage accounting and retries are separate future work. Merely enabling this connection does not activate those consumers.

## Release boundary

Local implementation and mock-provider tests do not prove a real provider account. The user enters the real key in the local form; real model discovery is the provider verification evidence; actual generation is verified when a consumer is connected. No keys belong in Git, browser storage, chat or acceptance screenshots. Deploy only after explicit synchronization approval, with the exact accepted source SHA and this migration applied by its scoped path.

UI shortcut: after entering a new key or editing preferences, Save & Verify Models saves the pending draft with Enable off and immediately verifies using the new server version. Separate Save Changes then Verify remains available. No test-reply button or test requirement is included.

