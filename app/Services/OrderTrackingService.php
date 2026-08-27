<?php

namespace App\Services;

use App\Mail\OrderTrackingCodeMail;
use App\Models\Order;
use App\Models\OrderTrackingVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderTrackingService
{
    public function request(string $orderNumber, string $email): ?OrderTrackingVerification
    {
        $normalizedNumber = Str::upper(trim($orderNumber));
        $normalizedEmail = Str::lower(trim($email));
        $order = Order::query()
            ->whereRaw('upper(number) = ?', [$normalizedNumber])
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->first();

        if (! $order) {
            return null;
        }

        $code = (string) random_int(100000, 999999);
        $attempt = DB::transaction(function () use ($order, $normalizedEmail, $code): OrderTrackingVerification {
            OrderTrackingVerification::query()
                ->where('order_id', $order->id)
                ->where('email_hash', hash('sha256', $normalizedEmail))
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return OrderTrackingVerification::query()->create([
                'public_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'email_hash' => hash('sha256', $normalizedEmail),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        Mail::to($normalizedEmail)->queue(new OrderTrackingCodeMail($code));

        return $attempt;
    }

    public function verify(?string $attemptId, string $code): ?OrderTrackingVerification
    {
        if (! $attemptId) {
            return null;
        }

        return DB::transaction(function () use ($attemptId, $code): ?OrderTrackingVerification {
            $attempt = OrderTrackingVerification::query()
                ->where('public_id', $attemptId)
                ->lockForUpdate()
                ->first();

            if (! $attempt || $attempt->consumed_at || $attempt->expires_at->isPast() || ! Hash::check($code, $attempt->code_hash)) {
                return null;
            }

            $attempt->update([
                'verified_at' => now(),
                'consumed_at' => now(),
            ]);

            return $attempt;
        });
    }
}
