<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
    ];
}
