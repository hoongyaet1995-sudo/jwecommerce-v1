<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = ['user_id', 'email', 'first_name', 'last_name', 'phone', 'avatar', 'status', 'tags', 'notes'];
    protected function casts(): array { return ['tags' => 'array']; }
    protected static function booted(): void {
        static::saving(function (Customer $customer): void {
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
