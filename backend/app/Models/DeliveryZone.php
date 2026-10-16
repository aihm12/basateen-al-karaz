<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $table = 'delivery_zones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['districts' => 'array', 'is_active' => 'boolean', 'delivery_fee' => 'decimal:2', 'minimum_order' => 'decimal:2', 'free_delivery_above' => 'decimal:2'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}
