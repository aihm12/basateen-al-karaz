<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionGroup extends Model
{
    protected $table = 'option_groups';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function values()
    {
        return $this->hasMany(OptionValue::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_option_groups');
    }
}
