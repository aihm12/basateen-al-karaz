<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemOption extends Model
{
    protected $table = 'cart_item_options';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function value()
    {
        return $this->belongsTo(OptionValue::class, 'option_value_id');
    }
}
