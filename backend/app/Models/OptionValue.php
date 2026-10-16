<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionValue extends Model
{
    protected $table = 'option_values';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['additional_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function group()
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }
}
