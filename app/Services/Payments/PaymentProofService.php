<?php

namespace App\Services\Payments;

use App\Models\OrderPayment;
use App\Models\OrderPaymentProofEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofService
{
    private const PRIVATE_DISK = 'local';

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    public function storeCheckoutUpload(OrderPayment $payment, UploadedFile $file): OrderPayment
    {
        $mime = $this->validatedMime($file);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'pdf',
        };
        $path = Storage::disk(self::PRIVATE_DISK)->putFileAs(
            'payment-proofs/'.$payment->order_id,
            $file,
            Str::uuid().'.'.$extension,
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['payment_proof' => 'The payment proof could not be stored securely.']);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $payment->update([
            'proof_path' => $path,
            'proof_disk' => self::PRIVATE_DISK,
            'proof_original_name' => basename((string) $file->getClientOriginalName()),
            'proof_mime_type' => $mime,
            'proof_size' => $file->getSize(),
            'proof_sha256' => is_string($checksum) ? $checksum : null,
            'proof_status' => 'uploaded',
            'proof_uploaded_at' => now(),
            'proof_reviewed_at' => null,
        ]);
        $this->appendEvent($payment->fresh(), 'uploaded');

        return $payment->fresh();
    }

    public function stream(OrderPayment $payment, User $actor, bool $download = false): StreamedResponse
    {
        $payment = $this->moveLegacyProofToPrivateDisk($payment);
        $disk = Storage::disk((string) $payment->proof_disk);

        if (! $payment->proof_path || ! $disk->exists($payment->proof_path)) {
            abort(404);
        }

        $stream = $disk->readStream($payment->proof_path);
        if (! is_resource($stream)) {
            abort(404);
        }

        $this->appendEvent($payment, $download ? 'downloaded' : 'previewed', $actor);
        $filename = self::safeFilename($payment->proof_original_name, $payment->proof_mime_type, $payment->proof_path);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => self::safeMime($payment->proof_mime_type, $payment->proof_path),
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function moveLegacyProofToPrivateDisk(OrderPayment $payment): OrderPayment
    {
        if ($payment->proof_disk !== 'public') {
            return $payment;
        }

        $path = (string) $payment->proof_path;
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $contents = Storage::disk('public')->get($path);
        if (! Storage::disk(self::PRIVATE_DISK)->put($path, $contents)) {
            abort(503, 'The legacy payment proof could not be secured.');
        }
        Storage::disk('public')->delete($path);
        $payment->update(['proof_disk' => self::PRIVATE_DISK]);

        return $payment->fresh();
    }

    public function appendEvent(OrderPayment $payment, string $eventType, ?User $actor = null, ?string $reason = null): OrderPaymentProofEvent
    {
        return OrderPaymentProofEvent::query()->create([
            'order_id' => $payment->order_id,
            'order_payment_id' => $payment->id,
            'user_id' => $actor?->id,
            'event_type' => $eventType,
            'reason' => $reason,
            'proof_sha256' => $payment->proof_sha256,
        ]);
    }

    private function validatedMime(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['payment_proof' => 'Only JPG, PNG, and PDF payment proofs are supported.']);
        }

        return $mime;
    }

    public static function safeMime(?string $mime, ?string $path): string
    {
        if (in_array($mime, self::ALLOWED_MIMES, true)) {
            return $mime;
        }

        return match (strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/pdf',
        };
    }

    public static function safeFilename(?string $originalName, ?string $mime, ?string $path = null): string
    {
        $fallback = match (self::safeMime($mime, $path)) {
            'image/jpeg' => 'payment-proof.jpg',
            'image/png' => 'payment-proof.png',
            default => 'payment-proof.pdf',
        };
        $name = basename((string) $originalName);

        if ($name === '') {
            $name = basename((string) $path);
        }

        return $name !== '' ? str_replace(['"', "\r", "\n"], '', $name) : $fallback;
    }
}
