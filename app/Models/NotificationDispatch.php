<?php

namespace App\Models;

use Database\Factories\NotificationDispatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationDispatch extends Model
{
    /** @use HasFactory<NotificationDispatchFactory> */
    use HasFactory;

    public const STATE_QUEUED = 'queued';

    public const STATE_SENDING = 'sending';

    public const STATE_SENT = 'sent';

    public const STATE_FAILED = 'failed';

    public const SOURCE_AUTOMATIC = 'automatic';

    public const SOURCE_ADMIN_CURRENT_TEMPLATE = 'admin_current_template';

    public const SOURCE_MANUAL_RESEND = 'manual_resend';

    public const SOURCE_TEST = 'test';

    private const DELIVERY_LEASE_SECONDS = 60;

    protected $fillable = [
        'public_id',
        'order_id',
        'notification_template_id',
        'channel',
        'event_key',
        'source_type',
        'source_id',
        'idempotency_key',
        'recipient_email',
        'recipient_name',
        'rendered_subject',
        'rendered_preheader',
        'rendered_heading',
        'rendered_body',
        'rendered_cta_text',
        'rendered_cta_url',
        'template_version',
        'variable_snapshot',
        'state',
        'source',
        'attempt_count',
        'delivery_lease_token',
        'delivery_lease_expires_at',
        'queued_at',
        'sent_at',
        'failed_at',
        'last_error_code',
        'provider_message_id',
        'requested_by',
        'resend_of_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (NotificationDispatch $dispatch): void {
            $dispatch->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'variable_snapshot' => 'array',
            'template_version' => 'integer',
            'attempt_count' => 'integer',
            'delivery_lease_expires_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public static function claimForDelivery(int $dispatchId): ?self
    {
        $now = now();
        $leaseToken = (string) Str::uuid();
        $claimed = self::query()
            ->whereKey($dispatchId)
            ->where(function ($query) use ($now): void {
                $query->whereIn('state', [self::STATE_QUEUED, self::STATE_FAILED])
                    ->orWhere(function ($query) use ($now): void {
                        $query->where('state', self::STATE_SENDING)
                            ->where(function ($query) use ($now): void {
                                $query->whereNull('delivery_lease_expires_at')
                                    ->orWhere('delivery_lease_expires_at', '<=', $now);
                            });
                    });
            })
            ->update([
                'state' => self::STATE_SENDING,
                'attempt_count' => DB::raw('attempt_count + 1'),
                'delivery_lease_token' => $leaseToken,
                'delivery_lease_expires_at' => $now->copy()->addSeconds(self::DELIVERY_LEASE_SECONDS),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return self::query()
            ->whereKey($dispatchId)
            ->where('delivery_lease_token', $leaseToken)
            ->first();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resendOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resend_of_id');
    }

    public function resends(): HasMany
    {
        return $this->hasMany(self::class, 'resend_of_id');
    }
}
