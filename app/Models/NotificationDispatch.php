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

    public const SNAPSHOT_VERSION_LEGACY_ESCAPED = 1;

    public const SNAPSHOT_VERSION_PLAIN_TEXT = 2;

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
        'snapshot_version',
        'variable_snapshot',
        'state',
        'source',
        'attempt_count',
        'queue_attempt',
        'queue_generation',
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
            'snapshot_version' => 'integer',
            'attempt_count' => 'integer',
            'queue_attempt' => 'integer',
            'delivery_lease_expires_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public static function claimForDelivery(int $dispatchId, int $queueAttempt, ?string $queueGeneration): ?self
    {
        if ($queueAttempt < 1) {
            return null;
        }

        $now = now();
        $leaseToken = (string) Str::uuid();
        $claimed = self::query()
            ->whereKey($dispatchId)
            ->when(
                $queueGeneration === null,
                static fn ($query) => $query->whereNull('queue_generation'),
                static fn ($query) => $query->where('queue_generation', $queueGeneration),
            )
            ->whereIn('state', [self::STATE_QUEUED, self::STATE_SENDING])
            ->where(function ($query) use ($queueAttempt): void {
                $query->whereNull('queue_attempt')
                    ->orWhere('queue_attempt', '<', $queueAttempt);
            })
            ->update([
                'state' => self::STATE_SENDING,
                'attempt_count' => DB::raw('attempt_count + 1'),
                'queue_attempt' => $queueAttempt,
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

    public function displaySubject(): string
    {
        return $this->displayText((string) $this->rendered_subject, header: true);
    }

    /** @return array{preheader: string, heading: string, body: string, ctaText: string, ctaUrl: string, variables: array<string, mixed>} */
    public function displaySnapshot(): array
    {
        return [
            'preheader' => $this->displayText((string) $this->rendered_preheader),
            'heading' => $this->displayText((string) $this->rendered_heading),
            'body' => $this->displayText((string) $this->rendered_body),
            'ctaText' => $this->displayText((string) $this->rendered_cta_text),
            'ctaUrl' => $this->displayText((string) $this->rendered_cta_url),
            'variables' => collect($this->variable_snapshot ?? [])
                ->map(fn (mixed $value): mixed => is_string($value) ? $this->displayText($value) : $value)
                ->all(),
        ];
    }

    private function displayText(string $value, bool $header = false): string
    {
        $snapshotVersion = (int) ($this->snapshot_version ?? self::SNAPSHOT_VERSION_LEGACY_ESCAPED);
        $display = $snapshotVersion === self::SNAPSHOT_VERSION_LEGACY_ESCAPED
            ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $value;

        return $header ? str_replace(["\r", "\n"], ' ', $display) : $display;
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
