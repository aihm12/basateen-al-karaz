<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchProductVariant extends Model
{
    protected $table = 'branch_product_variants';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
