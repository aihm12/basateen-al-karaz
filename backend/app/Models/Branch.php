<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'branches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['opening_hours' => 'array', 'is_active' => 'boolean', 'delivery_enabled' => 'boolean', 'pickup_enabled' => 'boolean'];
    }

    public function variants()
    {
        return $this->belongsToMany(ProductVariant::class, 'branch_product_variants')->withPivot('is_available', 'stock_quantity');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryZones()
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function deliverySlots()
    {
        return $this->hasMany(DeliverySlot::class);
    }
}
