<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    protected $table = 'integration_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['request' => 'array', 'response' => 'array'];
    }
}
