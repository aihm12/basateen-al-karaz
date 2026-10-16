<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = ['id'];

    protected $hidden = ['raw_response'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'raw_response' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
