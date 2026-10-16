<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'weight' => 'decimal:3', 'is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_product_variants')->withPivot('is_available', 'stock_quantity');
    }
}
