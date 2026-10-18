<?php

namespace App\Services\AI;

use App\Models\AiConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiConnectionService
{
    public function __construct(private ProviderRegistry $providers) {}

    public function view(?AiConnection $record, string $provider): array
    {
        return [
            'provider' => $provider, 'label' => ProviderRegistry::LABELS[$provider],
            'has_key' => $record !== null && $record->getRawOriginal('api_key') !== null,
            'base_url' => $record?->base_url, 'enabled' => $record?->enabled ?? false,
            'support_enabled' => $record?->support_enabled ?? false, 'builder_enabled' => $record?->builder_enabled ?? false, 'search_enabled' => $record?->search_enabled ?? false,
            'text_model' => $record?->text_model, 'image_model' => $record?->image_model,
            'models' => $record?->models ?? [], 'verified_at' => $record?->verified_at?->toIso8601String(),
            
            'last_error' => $record?->last_error, 'version' => $record?->version ?? 0,
        ];
    }

    public function all(): array
    {
        $records = AiConnection::all()->keyBy('provider');

        return array_map(fn ($provider) => $this->view($records->get($provider), $provider), array_keys(ProviderRegistry::LABELS));
    }

    private function locked(string $provider, int $version): AiConnection
    {
        DB::table('ai_connection_settings')->where('id', 1)->lockForUpdate()->first();
        $record = AiConnection::firstOrNew(['provider' => $provider]);
        abort_if(($record->version ?? 0) !== $version, 409, 'Settings changed in another session. Reload before continuing.');

        return $record;
    }

    public function save(string $provider, array $data): AiConnection
    {
        return DB::transaction(function () use ($provider, $data) {
            $record = $this->locked($provider, $data['version']);
            $newKey = $data['api_key'] ?? null;
            $base = $provider === 'compatible' ? ($data['base_url'] ?? null) : null;
            if ($provider === 'compatible') {
                $parts = parse_url($base ?? '');
                if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($base, config('ai_connect.compatible_base_urls', []), true)) {
                    throw ValidationException::withMessages(['base_url' => 'Select an HTTPS endpoint configured in the server allowlist.']);
                }
            }
            $keyChanged = false;
            if ($newKey !== null) {
                try {
                    $keyChanged = $newKey !== $record->api_key;
                } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                    $keyChanged = true;
                }
            }
            $changed = $keyChanged || $base !== $record->base_url;
            if ($changed) {
                $record->verified_at = null;
                $record->models = [];
                $record->text_tested_at = null;
                $record->last_error = null;
            }
            if ($newKey !== null) {
                $record->api_key = $newKey;
            }
            $record->base_url = $base;
            $textModel = $data['text_model'] ?? null;
            $imageModel = $data['image_model'] ?? null;
            foreach (['text' => $textModel, 'image' => $imageModel] as $kind => $model) {
                if ($model && ! collect($record->models ?? [])->contains(fn ($item) => $item['id'] === $model && $item[$kind])) {
                    throw ValidationException::withMessages([$kind.'_model' => 'Verify the saved key, then select a model from its model list.']);
                }
            }
            if ($textModel !== $record->text_model) {
                $record->text_tested_at = null;
            }
            if ($data['enabled'] && (! $record->verified_at || ! $textModel)) {
                throw ValidationException::withMessages(['enabled' => 'Verify the key and select a text model before enabling this connection.']);
            }
            $record->fill([
                'text_model' => $textModel, 'image_model' => $imageModel, 'enabled' => $data['enabled'],
                'support_enabled' => $data['support_enabled'], 'builder_enabled' => $data['builder_enabled'], 'search_enabled' => $data['search_enabled'] ?? $record->search_enabled ?? false,
                'version' => ($record->version ?? 0) + 1,
            ])->save();
            if ($record->enabled) {
                AiConnection::where('provider', '!=', $provider)->where('enabled', true)->update(['enabled' => false, 'version' => DB::raw('version + 1')]);
            }

            return $record;
        });
    }

    public function verify(string $provider, int $version): AiConnection
    {
        $record = AiConnection::findOrFail($provider);
        abort_if($record->version !== $version, 409, 'Settings changed. Reload before continuing.');
        if (! $record->api_key) throw ValidationException::withMessages(['api_key' => 'Save an API key first.']);
        $failure = null; $models = [];
        try {
            $models = $this->providers->client($provider)->models($record->api_key, $record->base_url);
            if (! $models) throw new ProviderFailure('models');
        } catch (ProviderFailure $error) { $failure = $error; }
        $record = DB::transaction(function () use ($provider, $version, $models, $failure) {
            $record = $this->locked($provider, $version);
            if ($failure) {
                $record->last_error = $failure->reason; $record->enabled = false;
                $record->verified_at = null; $record->models = [];
            } else {
                $record->last_error = null; $record->models = $models; $record->verified_at = now();
                if ($record->text_model && ! collect($models)->contains(fn ($m) => $m['id'] === $record->text_model && $m['text'])) {
                    $record->text_model = null; $record->enabled = false;
                }
                if ($record->image_model && ! collect($models)->contains(fn ($m) => $m['id'] === $record->image_model && $m['image'])) $record->image_model = null;
            }
            $record->version++; $record->save();
            return $record;
        });
        if ($failure) throw $failure;
        return $record;
    }

    /** Internal consumer boundary. Public chat/CMS endpoints do not invoke it yet. */
    public function generateText(string $feature, string $prompt, int $maxTokens = 1024, ?float $temperature = null): string
    {
        if (! in_array($feature, ['support', 'builder', 'search'], true) || trim($prompt) === '' || strlen($prompt) > 32000 || $maxTokens < 1 || $maxTokens > 4096 || ($temperature !== null && (!is_finite($temperature) || $temperature < 0 || $temperature > 1))) {
            throw ValidationException::withMessages(['feature' => 'Invalid AI generation request.']);
        }
        $record = AiConnection::where('enabled', true)->where($feature.'_enabled', true)->first();
        if (! $record || ! $record->verified_at || ! $record->text_model) {
            throw ValidationException::withMessages(['connection' => 'AI is not configured for this feature.']);
        }

        return $this->providers->client($record->provider)->text($record->api_key, $record->base_url, $record->text_model, $prompt, $maxTokens, $temperature);
    }
}
