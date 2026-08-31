<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EasyParcelLabelService
{
    private const MAX_PDF_BYTES = 10 * 1024 * 1024;

    public function response(Shipment $shipment, User $actor, bool $download): Response
    {
        if ($shipment->source !== 'easyparcel') {
            $this->logFailure($shipment, $actor, $download, 'unsupported_shipment');
            abort(404);
        }

        if (blank($shipment->label_path)) {
            $this->logFailure($shipment, $actor, $download, 'label_missing');
            abort(404);
        }

        $url = (string) $shipment->label_path;
        if (! $this->isAllowedUrl($url)) {
            $this->logFailure($shipment, $actor, $download, 'invalid_reference');

            throw ValidationException::withMessages([
                'label' => 'The EasyParcel AWB reference is invalid.',
            ]);
        }

        try {
            $providerResponse = Http::accept('application/pdf')
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->withOptions([
                    'on_headers' => function (ResponseInterface $response): void {
                        $length = $response->getHeaderLine('Content-Length');

                        if (is_numeric($length) && (int) $length > self::MAX_PDF_BYTES) {
                            throw new \RuntimeException('EasyParcel AWB exceeds the download limit.');
                        }
                    },
                    'progress' => function (float $downloadTotal, float $downloadedBytes): void {
                        if ($downloadedBytes > self::MAX_PDF_BYTES) {
                            throw new \RuntimeException('EasyParcel AWB exceeds the download limit.');
                        }
                    },
                ])
                ->get($url);
        } catch (Throwable) {
            $this->logFailure($shipment, $actor, $download, 'provider_unavailable');
            abort(502, 'The EasyParcel AWB is temporarily unavailable.');
        }

        $contentLength = $providerResponse->header('Content-Length');
        $body = $providerResponse->body();
        $mime = strtolower(trim(explode(';', (string) $providerResponse->header('Content-Type'))[0]));
        if (! $providerResponse->successful()
            || $providerResponse->status() !== 200
            || $mime !== 'application/pdf'
            || (is_numeric($contentLength) && (int) $contentLength > self::MAX_PDF_BYTES)
            || strlen($body) > self::MAX_PDF_BYTES
            || ! str_starts_with($body, '%PDF-')) {
            $this->logFailure($shipment, $actor, $download, 'invalid_provider_response');
            abort(502, 'The EasyParcel AWB could not be verified.');
        }

        $action = $download ? 'download' : 'preview';
        Log::info('EasyParcel AWB accessed', [
            'shipment_id' => $shipment->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'result' => 'success',
        ]);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$this->filename($shipment).'"',
            'Content-Length' => (string) strlen($body),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = is_array($parts) && is_string($parts['host'] ?? null)
            ? strtolower($parts['host'])
            : null;

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && $host !== null
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ($host === 'easyparcel.com' || str_ends_with($host, '.easyparcel.com'));
    }

    private function filename(Shipment $shipment): string
    {
        $reference = filled($shipment->tracking_number)
            ? (string) $shipment->tracking_number
            : 'shipment-'.$shipment->id;
        $reference = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $reference), '-.');

        return 'AWB-'.($reference !== '' ? $reference : 'shipment-'.$shipment->id).'.pdf';
    }

    private function logFailure(Shipment $shipment, User $actor, bool $download, string $result): void
    {
        Log::warning('EasyParcel AWB access failed', [
            'shipment_id' => $shipment->id,
            'actor_id' => $actor->id,
            'action' => $download ? 'download' : 'preview',
            'result' => $result,
        ]);
    }
}
