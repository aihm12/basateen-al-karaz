<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stock_reserved' => 'boolean', 'unit_price' => 'decimal:2', 'total_price' => 'decimal:2', 'tax' => 'decimal:2', 'tax_rate' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function options()
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
