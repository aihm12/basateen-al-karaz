<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $guarded = ['id'];

    protected $hidden = ['request_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return ['address_snapshot' => 'array', 'delivery_slot_snapshot' => 'array', 'delivery_date' => 'date:Y-m-d', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'delivery_fee' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliverySlot()
    {
        return $this->belongsTo(DeliverySlot::class);
    }
}
