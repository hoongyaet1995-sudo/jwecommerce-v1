<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = ['user_id', 'email', 'first_name', 'last_name', 'phone', 'avatar', 'status', 'tags', 'notes', 'date_of_birth'];
    protected function casts(): array { return ['tags' => 'array', 'date_of_birth' => 'date:Y-m-d']; }
    protected static function booted(): void {
        static::saving(function (Customer $customer): void {
            if ($customer->isDirty('date_of_birth') && $customer->date_of_birth?->isFuture()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['date_of_birth' => 'Date of birth cannot be in the future.']);
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn("customers", "normalized_phone")) return;
            $customer->normalized_phone = \App\Services\Customers\ContactValueNormalizer::phone($customer->phone);
        });
    }
    public function platformPresences(): HasMany { return $this->hasMany(CustomerPlatformPresence::class); }
    public function channelIdentities(): HasMany { return $this->hasMany(CustomerChannelIdentity::class); }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
