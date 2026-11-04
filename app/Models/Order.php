<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (self $order): void {
            if ($order->wasChanged('status')) app(\App\Services\Rewards\RewardsWallet::class)->synchronize($order);
        });
        static::updating(function (self $order): void {
            if ($order->isDirty('promotion_snapshot')) {
                throw new \LogicException('The order promotion snapshot is immutable.');
            }
        });
    }

    protected $fillable = ['promotion_snapshot', 'number', 'customer_id', 'coupon_id', 'coupon_code', 'email', 'currency', 'currency_exchange_rate', 'status', 'shipping_method', 'shipping_label', 'shipping_snapshot', 'tracking_carrier', 'tracking_number', 'customer_note', 'internal_note', 'shipped_at', 'subtotal', 'discount_amount', 'shipping_amount', 'payment_surcharge_amount', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['promotion_snapshot'=>'array', 'shipped_at' => 'datetime', 'currency_exchange_rate' => 'decimal:8', 'shipping_snapshot' => 'array'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function taxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shipmentQuotes(): HasMany
    {
        return $this->hasMany(ShipmentQuote::class);
    }

    public function notificationDispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class);
    }

    public function easyParcelShipmentRecoveries(): HasMany
    {
        return $this->hasMany(EasyParcelShipmentRecovery::class)->latest('id');
    }

    public function shipmentCorrections(): HasMany
    {
        return $this->hasMany(ShipmentCorrection::class)->latest('id');
    }

    public function addressAmendments(): HasMany
    {
        return $this->hasMany(OrderAddressAmendment::class)->latest('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
