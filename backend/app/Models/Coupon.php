<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean', 'first_order_only' => 'boolean', 'discount_value' => 'decimal:2', 'minimum_order' => 'decimal:2', 'maximum_discount' => 'decimal:2'];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
