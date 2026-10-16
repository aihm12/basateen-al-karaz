<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tax_rate' => 'decimal:2', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'is_new' => 'boolean', 'is_best_seller' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function optionGroups()
    {
        return $this->belongsToMany(OptionGroup::class, 'product_option_groups')->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
