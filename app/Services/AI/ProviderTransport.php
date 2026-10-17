<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderTransport
{
    public function json(string $method, string $url, array $headers, array $data = [], bool $custom = false): array
    {
        $options = ['allow_redirects' => false];
        if ($custom) {
            $host = parse_url($url, PHP_URL_HOST);
            $addresses = $host ? gethostbynamel($host) : false;
            if (! $addresses) {
                throw new ProviderFailure('endpoint');
            }
            foreach ($addresses as $address) {
                $number = ip2long($address);
                $special = false;
                foreach (['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $range) {
                    [$network, $bits] = explode('/', $range);
                    $mask = -1 << (32 - (int) $bits);
                    if (($number & $mask) === (ip2long($network) & $mask)) {
                        $special = true;
                    }
                }
                if ($special || ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new ProviderFailure('endpoint');
                }
            }
            // Pin DNS resolution so a host cannot change to a private address between validation and use.
            $options['curl'] = [CURLOPT_RESOLVE => ["$host:443:".$addresses[0]]];
        }
        try {
            $response = Http::acceptJson()->withHeaders($headers)->connectTimeout(5)->timeout(30)
                ->withOptions($options)->send($method, $url, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
            if (! $response->successful()) {
                throw new ProviderFailure(match ($response->status()) {
                    401, 403 => 'authentication', 402, 429 => 'quota', 404 => 'endpoint', default => 'unavailable',
                });
            }
            $json = $response->json();
            if (! is_array($json)) {
                throw new ProviderFailure('response');
            }

            if (isset($json['usage']) && is_array($json['usage'])) {
                $usage = array_intersect_key($json['usage'], array_flip(['input_tokens', 'output_tokens', 'total_tokens', 'prompt_tokens', 'completion_tokens']));
                Log::info('ai_provider_usage', ['model' => $data['model'] ?? null, 'usage' => $usage]);
                // Request-local only: never persist credentials or provider response bodies.
                request()->attributes->set('ai_provider_usage', array_filter([
                    'input_tokens' => $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null,
                    'output_tokens' => $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null,
                    'total_tokens' => $usage['total_tokens'] ?? null,
                ], fn ($value) => is_int($value) && $value >= 0));
            }

            return $json;
        } catch (ProviderFailure $failure) {
            throw $failure;
        } catch (\Throwable) {
            // Do not chain exceptions containing request headers or raw response data.
            throw new ProviderFailure('unavailable');
        }
    }
}
